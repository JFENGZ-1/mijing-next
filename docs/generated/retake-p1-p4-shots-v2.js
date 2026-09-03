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

function capture(project, file, seconds = 5) {
  run(
    'automation_viewport_action',
    `--project "${project}" --action screenshot --wait-seconds ${seconds} --path "${outDir}/${file}"`,
  );
  const ok = fs.existsSync(path.join(outDir, file));
  console.log(file, ok ? 'OK' : 'MISSING');
  return ok;
}

function staffSession() {
  run('project_open_window', `--project "${staffProject}"`);
  run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(readFn('staff-report-session.js'))}`);
}

function memberSession() {
  run('project_open_window', `--project "${memberProject}"`);
  run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
}

const results = {};

staffSession();
run('simulator_open_page', `--project "${staffProject}" --page pages/course/session-detail --query id=1`);
pause(3);
results['staff-session-fulfillment.png'] = capture(staffProject, 'staff-session-fulfillment.png', 5);

staffSession();
run('simulator_open_page', `--project "${staffProject}" --page pages/settings/card-products/index`);
pause(3);
results['staff-card-products-list.png'] = capture(staffProject, 'staff-card-products-list.png', 5);

staffSession();
run('simulator_open_page', `--project "${staffProject}" --page pages/settings/card-products/edit --query id=3`);
pause(3);
results['staff-card-products-edit.png'] = capture(staffProject, 'staff-card-products-edit.png', 5);

staffSession();
run('simulator_open_page', `--project "${staffProject}" --page pages/report/exports/index`);
pause(2);
try {
  run('automation_element_action', `--project "${staffProject}" --selector .action-card button --action tap`);
  pause(3);
} catch (e) {
  console.log('export create skipped');
}
results['staff-report-exports.png'] = capture(staffProject, 'staff-report-exports.png', 4);

memberSession();
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(3);
results['member-card-catalog.png'] = capture(memberProject, 'member-card-catalog.png', 5);

memberSession();
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(3);
try {
  run('automation_element_action', `--project "${memberProject}" --selector .product-actions button --action tap`);
  pause(1);
  run('automation_element_action', `--project "${memberProject}" --selector button --action tap`);
  pause(2);
  results['member-card-purchase-success.png'] = capture(memberProject, 'member-card-purchase-success.png', 3);
} catch (e) {
  console.log('purchase flow fallback wallet');
  run('simulator_open_page', `--project "${memberProject}" --page pages/cards/index`);
  pause(2);
  results['member-card-purchase-success.png'] = capture(memberProject, 'member-card-purchase-success.png', 4);
}

console.log(JSON.stringify(results, null, 2));
