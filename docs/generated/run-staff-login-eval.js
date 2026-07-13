const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/songguo-next/apps/staff-miniapp/dist/build/mp-weixin';
const fn = fs.readFileSync(path.join(__dirname, 'stage04-staff-inapp-login.js'), 'utf8').trim();
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };
const cmd = `wechatide -c ${WECHAT_IDE_CLIENT} -t automation_evaluate --project "${project}" --fn-source ${JSON.stringify(fn)}`;
const out = execSync(cmd, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
console.log(out);
