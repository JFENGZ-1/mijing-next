const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/songguo-next/apps/member-miniapp/dist/build/mp-weixin';
const outDir = 'D:/Users/Zhong/Desktop/微信小程序原项目/docs/generated';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

const shots = [
  ['simulator_open_page', `--project "${project}" --page pages/index/index`, `${outDir}/member-home.png`],
  ['simulator_open_page', `--project "${project}" --page pages/booking/index`, `${outDir}/member-booking.png`],
  ['simulator_open_page', `--project "${project}" --page pages/cards/index`, `${outDir}/member-cards.png`],
];

for (const [tool, args, file] of shots) {
  console.log(run(tool, args));
  console.log(run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 3 --path "${file}"`));
}
