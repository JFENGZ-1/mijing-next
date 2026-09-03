const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/mijing-next/apps/staff-miniapp/dist/build/mp-weixin`;
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

function capture(project, file, seconds = 3) {
  run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds ${seconds} --path "${outDir}/${file}"`);
}

// export polling retry
run('project_open_window', `--project "${staffProject}"`);
run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(readFn('staff-report-session.js'))}`);
run('simulator_open_page', `--project "${staffProject}" --page pages/report/exports/index`);
pause(2);
run('automation_element_action', `--project "${staffProject}" --selector .action-card button --action tap`);
pause(1);
capture(staffProject, 'staff-report-exports.png', 2);
console.log('export retake done');

// member purchase success via evaluate
run('project_open_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(3);
const purchaseFn = readFn('member-purchase-eval.js');
const out = run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(purchaseFn)}`);
console.log(out);
pause(2);
capture(memberProject, 'member-card-purchase-success.png', 2);
console.log('purchase retake done');
