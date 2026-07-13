const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/songguo-next/apps/staff-miniapp/dist/build/mp-weixin`;
const memberProject = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  const cmd = `wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`;
  return execSync(cmd, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function openPage(project, page, query) {
  const q = query ? ` --query ${query}` : '';
  return run('simulator_open_page', `--project "${project}" --page ${page}${q}`);
}

function shot(project, selector, file, waitSeconds) {
  const wait = selector
    ? `--wait-for-selector ${selector}`
    : `--wait-seconds ${waitSeconds || 3}`;
  try {
    const out = run(
      'automation_viewport_action',
      `--project "${project}" --action screenshot ${wait} --path "${outDir}/${file}"`,
    );
    const exists = fs.existsSync(path.join(outDir, file));
    console.log(file, exists ? 'OK' : 'MISSING');
    if (!exists) console.log(out.slice(-300));
    return exists;
  } catch (e) {
    console.log(file, 'FAIL', String(e.message).split('\n').slice(-4).join(' | '));
    return false;
  }
}

const seed = { sessionId: 1, productId: 3 };
const results = {};

console.log('=== staff ===');
run('project_open_window', `--project "${staffProject}"`);
run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(readFn('staff-report-session.js'))}`);

openPage(staffProject, 'pages/course/session-detail', `id=${seed.sessionId}`);
results['staff-session-fulfillment.png'] = shot(staffProject, '.appoint-actions', 'staff-session-fulfillment.png');

openPage(staffProject, 'pages/settings/card-products/index');
results['staff-card-products-list.png'] = shot(staffProject, '.product-card', 'staff-card-products-list.png');

openPage(staffProject, 'pages/settings/card-products/edit', `id=${seed.productId}`);
results['staff-card-products-edit.png'] = shot(staffProject, '.course-scope-item', 'staff-card-products-edit.png');

openPage(staffProject, 'pages/report/exports/index');
try {
  run('automation_element_action', `--project "${staffProject}" --selector .action-card button --action tap`);
} catch (e) {
  console.log('export tap skipped');
}
results['staff-report-exports.png'] = shot(staffProject, '.job-card', 'staff-report-exports.png', 4)
  || shot(staffProject, '.action-card', 'staff-report-exports.png', 3);

console.log('=== member ===');
run('project_open_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);

openPage(memberProject, 'pages/cards/catalog');
results['member-card-catalog.png'] = shot(memberProject, '.product-item', 'member-card-catalog.png');

openPage(memberProject, 'pages/cards/catalog');
try {
  run('automation_element_action', `--project "${memberProject}" --selector .product-actions button --action tap`);
} catch (e) {
  console.log('buy tap failed');
}
results['member-card-purchase-success.png'] = shot(memberProject, null, 'member-card-purchase-success.png', 2);
if (!results['member-card-purchase-success.png']) {
  openPage(memberProject, 'pages/cards/index');
  results['member-card-purchase-success.png'] = shot(memberProject, '.page-container', 'member-card-purchase-success.png');
}

console.log(JSON.stringify(results, null, 2));
