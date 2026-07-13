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

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);
const sessionOut = run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
console.log('session:', sessionOut.slice(-500));
pause(2);
run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

const purchaseFn = `function() {
  var token = wx.getStorageSync('access_token');
  var tenantId = wx.getStorageSync('current_tenant_id') || 1;
  var siteId = wx.getStorageSync('current_site_id') || 1;
  return { token: token ? token.slice(0, 20) + '...' : null, tenantId: tenantId, siteId: siteId };
}`;
console.log('storage:', run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(purchaseFn)}`));

const purchaseFn2 = `function() {
  return new Promise(function(resolve) {
    var token = wx.getStorageSync('access_token');
    var tenantId = wx.getStorageSync('current_tenant_id') || 1;
    var siteId = wx.getStorageSync('current_site_id') || 1;
    wx.request({
      url: 'http://127.0.0.1:8010/api/v1/member/card-purchases?tenantId=' + tenantId + '&siteId=' + siteId,
      method: 'POST',
      header: { Authorization: 'Bearer ' + token, 'Content-Type': 'application/json' },
      data: { cardProductId: 3, commandKey: 'live-debug-' + Date.now() },
      complete: function(res) {
        resolve({ statusCode: res.statusCode, data: res.data, errMsg: res.errMsg });
      }
    });
  });
}`;
const raw = run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(purchaseFn2)}`);
console.log('purchase raw:', raw);
console.log('purchase parsed:', JSON.stringify(parseJson(raw), null, 2));
