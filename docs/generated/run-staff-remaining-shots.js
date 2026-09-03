const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';

const project = 'D:/Users/Zhong/Desktop/微信小程序原项目/mijing-next/apps/staff-miniapp/dist/build/mp-weixin';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  const cmd = `wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`;
  return execSync(cmd, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

const outDir = 'D:/Users/Zhong/Desktop/微信小程序原项目/docs/generated';
const shots = [
  ['automation_navigate', `--project "${project}" --action navigateTo --url "pages/members/card-detail?memberId=2&memberCardId=3"`],
  ['automation_viewport_action', `--project "${project}" --action screenshot --wait-for-selector .page-container --path "${outDir}/staff-card-detail-lifecycle.png"`],
  ['simulator_open_page', `--project "${project}" --page pages/index/index`],
  ['automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 2 --path "${outDir}/staff-home-dashboard.png"`],
  ['simulator_open_page', `--project "${project}" --page pages/report/index`],
  ['automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 2 --path "${outDir}/staff-report-hub.png"`],
];

for (const [tool, args] of shots) {
  console.log(run(tool, args));
}
