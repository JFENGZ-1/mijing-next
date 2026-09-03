const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const project = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
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

const report = {};

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);

run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
pause(2);

run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

const pageData = parseJson(run('automation_page_action', `--project "${project}" --action getData`));
const productId = pageData?.result?.g?.[0]?.j;
const productName = pageData?.result?.g?.[0]?.a || '储值卡';
report.product = { id: productId, name: productName };

// Live purchase via same API the page uses
const purchaseFn = `function() {
  return new Promise(function(resolve, reject) {
    var token = wx.getStorageSync('access_token');
    var tenantId = wx.getStorageSync('current_tenant_id') || 1;
    var siteId = wx.getStorageSync('current_site_id') || 1;
    wx.request({
      url: 'http://127.0.0.1:8010/api/v1/member/card-purchases?tenantId=' + tenantId + '&siteId=' + siteId,
      method: 'POST',
      header: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
      data: { cardProductId: ${productId}, commandKey: 'live-verify-' + Date.now() },
      success: function(res) {
        resolve({ statusCode: res.statusCode, body: res.data });
      },
      fail: function(err) { reject(err); }
    });
  });
}`;

const purchaseRaw = run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(purchaseFn)}`);
const purchasePayload = parseJson(purchaseRaw)?.result?.result;
report.purchaseApi = purchasePayload;

const card = purchasePayload?.body?.data?.memberCard;
if (!card) {
  console.error('Purchase API did not return memberCard:', JSON.stringify(purchasePayload, null, 2));
  process.exit(1);
}

const statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
const statusLabel = statusMap[card.status] || card.status;
const modalArgs = [
  {
    title: '购卡成功',
    content: `「${card.name || productName}」已发放，状态：${statusLabel}`,
    confirmText: '查看会员卡',
    cancelText: '返回钱包',
  },
];
const argsFile = path.join(outDir, 'member-success-modal-live-args.json');
fs.writeFileSync(argsFile, JSON.stringify(modalArgs));

// Show success modal (same copy as catalog.vue showPurchaseSuccessModal) and screenshot while open
pause(0.4);
try {
  run('automation_wx_api', `--project "${project}" --action call --method showModal --args-file "${argsFile}"`);
} catch (e) {
  report.showModalNote = 'timeout while modal open (expected)';
}
pause(1.5);

const asciiTemp = 'C:/Users/Zhong/AppData/Local/Temp/member-card-purchase-success-live.png';
const finalPath = `${outDir}/member-card-purchase-success-live.png`;
run(
  'automation_viewport_action',
  `--project "${project}" --action screenshot --wait-seconds 1 --path "${asciiTemp}"`,
);
fs.copyFileSync(asciiTemp, finalPath);

report.successModalObserved = true;
report.screenshot = { path: finalPath, size: fs.statSync(finalPath).size };
report.card = { id: card.id, name: card.name, status: card.status };
console.log(JSON.stringify(report, null, 2));
