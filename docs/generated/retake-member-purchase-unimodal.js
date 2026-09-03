const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const memberProject = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

const seedOut = execSync('php docs/generated/seed-member-purchase-shot.php', { cwd: root, encoding: 'utf8' });
const payload = JSON.parse(seedOut.trim().split('\n').pop());
const statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
const modalFn = `function(){return new Promise(function(r){uni.showModal({title:'购卡成功',content:'「${payload.name}」已发放，状态：${statusMap[payload.status] || payload.status}',confirmText:'查看会员卡',cancelText:'留在此页'});r({ok:true})})}`;

run('project_open_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(4);
console.log(run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(modalFn)}`));
pause(2);
run(
  'automation_viewport_action',
  `--project "${memberProject}" --action screenshot --wait-seconds 2 --path "${outDir}/member-card-purchase-success.png"`,
);
console.log('member uni.showModal retake done');
