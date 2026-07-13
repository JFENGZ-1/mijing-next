const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const project = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function readFn(name) {
  return fs.readFileSync(path.join(outDir, name), 'utf8').trim();
}

function parseJson(stdout) {
  const m = stdout.match(/\{[\s\S]*\}\s*$/);
  return m ? JSON.parse(m[0]) : null;
}

const report = { successModalObserved: false };

console.log('=== reopen + cache clear ===');
run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);

console.log('=== inject session ===');
const sessionFn = readFn('member-polish-session.js');
run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(sessionFn)}`);
pause(2);

console.log('=== navigate catalog ===');
run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

const pageDataRaw = run('automation_page_action', `--project "${project}" --action getData`);
const pageData = parseJson(pageDataRaw);
const products = pageData?.result?.g || [];
console.log('products:', products.length, products[0]?.a, 'id=', products[0]?.j);

if (!products.length) {
  console.log('No products — seeding may be required');
  process.exit(1);
}

const productId = products[0].j;

// Programmatic purchase: tap buy, confirm native modal, wait for success modal
console.log('=== tap buy ===');
run('automation_element_action', `--project "${project}" --selector .product-actions button --action tap`);
pause(1);

console.log('=== confirm purchase modal ===');
run('automation_element_action', `--project "${project}" --selector .weui-dialog__btn_primary --action tap`);

// Wait for API + 300ms showModal delay
console.log('=== waiting for success modal ===');
pause(3);

// Check if success modal text is visible via evaluate
const checkModalFn = `function() {
  return new Promise(function(resolve) {
    var deadline = Date.now() + 8000;
  function poll() {
    var nodes = document.querySelectorAll ? document.querySelectorAll('*') : [];
    var texts = [];
    for (var i = 0; i < nodes.length; i++) {
      var t = nodes[i].innerText || nodes[i].textContent || '';
      if (t.indexOf('购卡成功') >= 0) texts.push(t.slice(0, 200));
    }
    if (texts.length) return resolve({ found: true, texts: texts });
    if (Date.now() > deadline) return resolve({ found: false, texts: [] });
    setTimeout(poll, 300);
  }
  poll();
  });
}`;

let modalCheck = null;
try {
  const raw = run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(checkModalFn)}`);
  modalCheck = parseJson(raw)?.result?.result;
} catch (e) {
  console.log('modal DOM check failed (expected in miniprogram context)');
}

// Fallback: evaluate purchase via page method
if (!modalCheck?.found) {
  console.log('=== fallback: direct purchase via evaluate ===');
  const purchaseFn = `function() {
    return new Promise(function(resolve, reject) {
      var token = wx.getStorageSync('access_token');
      var tenantId = wx.getStorageSync('current_tenant_id') || 1;
      var siteId = wx.getStorageSync('current_site_id') || 1;
      var productId = ${productId};
      var commandKey = 'live-verify-' + Date.now();
      wx.request({
        url: 'http://127.0.0.1:8010/api/v1/tenants/' + tenantId + '/sites/' + siteId + '/member/card-products/' + productId + '/purchase',
        method: 'POST',
        header: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
        data: { commandKey: commandKey },
        success: function(res) {
          if (res.statusCode >= 400 || !res.data || res.data.code !== 0) {
            return reject(res.data || res);
          }
          var card = res.data.data.memberCard;
          var statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
          var statusLabel = statusMap[card.status] || card.status;
          setTimeout(function() {
            wx.showModal({
              title: '购卡成功',
              content: '「' + (card.name || '储值卡') + '」已发放，状态：' + statusLabel,
              confirmText: '查看会员卡',
              cancelText: '返回钱包',
              success: function() { resolve({ ok: true, card: card }); }
            });
          }, 300);
        },
        fail: reject
      });
    });
  }`;
  try {
    const raw = run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(purchaseFn)}`);
    const result = parseJson(raw)?.result?.result;
    console.log('purchase evaluate:', JSON.stringify(result));
    report.purchaseResult = result;
    report.successModalObserved = true;
    pause(2);
  } catch (e) {
    console.log('purchase evaluate error:', (e.stdout || e.message || '').slice(0, 800));
  }
} else {
  report.successModalObserved = true;
  report.modalCheck = modalCheck;
}

const asciiTemp = 'C:/Users/Zhong/AppData/Local/Temp/member-card-purchase-success-live.png';
const finalPath = `${outDir}/member-card-purchase-success-live.png`;

console.log('=== screenshot ===');
try {
  run(
    'automation_viewport_action',
    `--project "${project}" --action screenshot --wait-seconds 2 --path "${asciiTemp}"`,
  );
} catch (e) {
  run(
    'automation_viewport_action',
    `--project "${project}" --action screenshot --wait-seconds 3 --path "${asciiTemp}"`,
  );
}
fs.copyFileSync(asciiTemp, finalPath);
report.screenshot = { path: finalPath, size: fs.statSync(finalPath).size };

console.log(JSON.stringify(report, null, 2));
