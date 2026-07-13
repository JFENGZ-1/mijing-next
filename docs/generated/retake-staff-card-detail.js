const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/songguo-next/apps/staff-miniapp/dist/build/mp-weixin';
const out = 'D:/Users/Zhong/Desktop/微信小程序原项目/docs/generated/staff-card-detail-lifecycle.png';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

console.log(execSync(
  `wechatide -c ${WECHAT_IDE_CLIENT} -t automation_viewport_action --project "${project}" --action screenshot --wait-seconds 3 --path "${out}"`,
  { env, encoding: 'utf8' },
));
