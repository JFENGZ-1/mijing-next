const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/songguo-next/apps/staff-miniapp/dist/build/mp-weixin`;
const memberProject = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = 'C:/Users/Zhong/AppData/Local/Temp/songguo-acceptance-shots';
const finalDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

fs.mkdirSync(outDir, { recursive: true });

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function shot(project, page, file, opts = {}) {
  const { query, wait = 3, selector, session } = opts;
  if (session === 'staff') {
    run('open_project_window', `--project "${staffProject}"`);
    run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(readFn('staff-report-session.js'))}`);
  } else if (session === 'member') {
    run('open_project_window', `--project "${memberProject}"`);
    run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
  }
  const pageArg = query ? `--page ${page} --query ${query}` : `--page ${page}`;
  run('simulator_open_page', `--project "${project}" ${pageArg}`);
  pause(wait);
  const outPath = `${outDir}/${file}`;
  if (selector) {
    run('automation_viewport_action', `--project "${project}" --action screenshot --wait-for-selector ${selector} --path "${outPath}"`);
  } else {
    run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds ${wait} --path "${outPath}"`);
  }
  const ok = fs.existsSync(outPath) && fs.statSync(outPath).size > 1000;
  console.log(file, ok ? 'OK' : 'MISSING');
  return ok;
}

const results = {};

console.log('=== seed site-2 fixtures ===');
const seedOut = execSync(
  `php artisan tinker --execute="include '${finalDir.replace(/\\/g, '/')}/seed-acceptance-fixtures.php';"`,
  { cwd: `${root}/songguo-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const seed = JSON.parse(seedOut.trim().split('\n').pop());
console.log('seed:', seed);

results['staff-session-fulfillment.png'] = shot(staffProject, 'pages/course/session-detail', 'staff-session-fulfillment.png', {
  query: `id=${seed.sessionId}`,
  wait: 4,
  selector: '.appoint-actions',
  session: 'staff',
});

results['staff-card-products-list.png'] = shot(staffProject, 'pages/settings/card-products/index', 'staff-card-products-list.png', {
  wait: 4,
  selector: '.product-card',
  session: 'staff',
});

results['staff-card-products-edit.png'] = shot(staffProject, 'pages/settings/card-products/edit', 'staff-card-products-edit.png', {
  query: `id=${seed.productId}`,
  wait: 4,
  selector: '.course-scope-item',
  session: 'staff',
});

execSync('php docs/generated/seed-processing-export.php', { cwd: root, encoding: 'utf8' });
run('open_project_window', `--project "${staffProject}"`);
run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(readFn('staff-report-session.js'))}`);
run('simulator_open_page', `--project "${staffProject}" --page pages/report/exports/index`);
pause(3);
try {
  run('automation_element_action', `--project "${staffProject}" --selector .action-card .u-button --action tap`);
} catch (e) {
  console.log('export tap skipped');
}
pause(2);
run(
  'automation_viewport_action',
  `--project "${staffProject}" --action screenshot --wait-for-selector .job-card --path "${outDir}/staff-report-exports.png"`,
);
results['staff-report-exports.png'] = fs.existsSync(`${outDir}/staff-report-exports.png`);

results['staff-home-dashboard.png'] = shot(staffProject, 'pages/index/index', 'staff-home-dashboard.png', { wait: 3, session: 'staff' });
results['staff-report-hub.png'] = shot(staffProject, 'pages/report/index', 'staff-report-hub.png', { wait: 3, session: 'staff' });
results['staff-course-daily-board.png'] = shot(staffProject, 'pages/course/index', 'staff-course-daily-board.png', { wait: 4, session: 'staff' });
results['staff-report-finance.png'] = shot(staffProject, 'pages/report/finance/index', 'staff-report-finance.png', { wait: 3, session: 'staff' });

results['member-home.png'] = shot(memberProject, 'pages/index/index', 'member-home.png', { wait: 3, session: 'member' });
results['member-booking.png'] = shot(memberProject, 'pages/booking/index', 'member-booking.png', { wait: 3, session: 'member' });
results['member-cards.png'] = shot(memberProject, 'pages/cards/index', 'member-cards.png', { wait: 3, session: 'member' });
results['member-card-catalog.png'] = shot(memberProject, 'pages/cards/catalog', 'member-card-catalog.png', {
  wait: 4,
  selector: '.product-item',
  session: 'member',
});

const seedPurchaseOut = execSync('php docs/generated/seed-member-purchase-shot.php', { cwd: root, encoding: 'utf8' });
const payload = JSON.parse(seedPurchaseOut.trim().split('\n').pop());
const statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
const argsFile = path.join(outDir, 'member-success-modal-args.json');
fs.writeFileSync(
  argsFile,
  JSON.stringify([
    {
      title: '购卡成功',
      content: `「${payload.name}」已发放，状态：${statusMap[payload.status] || payload.status}`,
      confirmText: '查看',
      cancelText: '留在此页',
    },
  ]),
);
run('open_project_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(4);
try {
  run('automation_wx_api', `--project "${memberProject}" --action call --method showModal --args-file "${argsFile}"`);
} catch (e) {
  console.log('showModal timeout expected');
}
pause(2);
run(
  'automation_viewport_action',
  `--project "${memberProject}" --action screenshot --wait-seconds 2 --path "${outDir}/member-card-purchase-success.png"`,
);
results['member-card-purchase-success.png'] = fs.existsSync(`${outDir}/member-card-purchase-success.png`);

for (const file of fs.readdirSync(outDir).filter((f) => f.endsWith('.png'))) {
  fs.copyFileSync(path.join(outDir, file), path.join(finalDir, file));
}

const copied = fs.readdirSync(finalDir).filter((f) => f.endsWith('.png'));
console.log(JSON.stringify({ results, copiedCount: copied.length, copied }, null, 2));
