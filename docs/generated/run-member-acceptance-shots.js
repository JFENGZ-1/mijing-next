const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/mijing-next/apps/member-miniapp/dist/build/mp-weixin';
const outDir = 'D:/Users/Zhong/Desktop/微信小程序原项目/docs/generated';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

console.log(run('open_project_window', `--project "${project}"`));
console.log(run('debug_clear_cache', `--project "${project}" --action cleanAll`));

const fn = fs.readFileSync(path.join(__dirname, 'member-polish-session.js'), 'utf8').trim();
console.log(run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`));

const shots = [
  ['simulator_open_page', `--project "${project}" --page pages/index/index`, `${outDir}/member-home.png`, 2],
  ['simulator_open_page', `--project "${project}" --page pages/booking/index`, `${outDir}/member-booking.png`, 2],
  ['simulator_open_page', `--project "${project}" --page pages/cards/index`, `${outDir}/member-cards.png`, 2],
];

for (const [tool, args, file, wait] of shots) {
  console.log(run(tool, args));
  console.log(run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds ${wait} --path "${file}"`));
}
