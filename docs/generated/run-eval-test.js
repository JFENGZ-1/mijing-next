const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/mijing-next/apps/staff-miniapp/dist/build/mp-weixin';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(fn) {
  const cmd = `wechatide -c ${WECHAT_IDE_CLIENT} -t automation_evaluate --project "${project}" --fn-source ${JSON.stringify(fn)}`;
  console.log(execSync(cmd, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 }));
}

run('function(){return {ok:true,hasToken:!!wx.getStorageSync("access_token")}}');
