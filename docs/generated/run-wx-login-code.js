const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/mijing-next/apps/staff-miniapp/dist/build/mp-weixin';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(fn) {
  const cmd = `wechatide -c ${WECHAT_IDE_CLIENT} -t automation_evaluate --project "${project}" --fn-source ${JSON.stringify(fn)}`;
  return execSync(cmd, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

const out = run('function(){return new Promise(function(r,j){wx.login({success:function(res){r(res)},fail:j})})}');
console.log(out);
