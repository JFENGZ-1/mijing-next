const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const { randomUUID } = require('crypto');
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const memberProject = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

run('project_open_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(fs.readFileSync(path.join(__dirname, 'member-polish-session.js'), 'utf8').trim())}`);
const tokenOut = JSON.parse(
  run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify("function(){return{token:wx.getStorageSync('access_token')}}")}`),
);
const token = tokenOut.result.result.token;
const body = JSON.stringify({ cardProductId: 1, commandKey: randomUUID() });
const curl = `curl -s -w "\\nHTTP:%{http_code}" -X POST "http://127.0.0.1:8010/api/v1/member/card-purchases?tenantId=1&siteId=1" -H "Authorization: Bearer ${token}" -H "Content-Type: application/json" -d "${body.replace(/"/g, '\\"')}"`;
console.log(execSync(curl, { encoding: 'utf8' }));
