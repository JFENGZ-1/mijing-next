/**
 * Full DevTools acceptance capture — WECHAT_IDE_CLIENT (default Cursor).
 * Prereqs: system:bootstrap, db:seed, php artisan serve :8010, pnpm build:staff + build:member
 * Output: docs/generated/*.png via ASCII temp copy
 */
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/mijing-next/apps/staff-miniapp/dist/build/mp-weixin`;
const memberProject = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const asciiTempDir = 'C:/Users/Zhong/AppData/Local/Temp/mijing-acceptance-shots';
const client = process.env.WECHAT_IDE_CLIENT || 'Cursor';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

fs.mkdirSync(asciiTempDir, { recursive: true });
fs.mkdirSync(outDir, { recursive: true });

function run(tool, args) {
  return execSync(`wechatide -c ${client} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function clearCache(project) {
  console.log('debug_clear_cache cleanAll', project);
  run('debug_clear_cache', `--project "${project}" --action cleanAll`);
  pause(4);
}

function openAndAuth(project, session) {
  run('open_project_window', `--project "${project}"`);
  clearCache(project);
  const fn = session === 'staff' ? readFn('staff-report-session.js') : readFn('member-polish-session.js');
  try {
    run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  } catch (e) {
    console.log('session inject retry after settle');
    pause(3);
    run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  }
  pause(2);
}

function capture(project, file, opts = {}) {
  const { wait = 5 } = opts;
  const asciiTemp = `${asciiTempDir}/${file}`;
  const finalPath = `${outDir}/${file}`;
  try {
    run(
      'automation_viewport_action',
      `--project "${project}" --action screenshot --wait-seconds ${wait} --path "${asciiTemp}"`,
    );
  } catch (e) {
    console.log('screenshot error', file, String(e.message || e).slice(0, 200));
  }
  if (fs.existsSync(asciiTemp)) {
    fs.copyFileSync(asciiTemp, finalPath);
  }
  const size = fs.existsSync(finalPath) ? fs.statSync(finalPath).size : 0;
  const ok = size > 0;
  console.log(file, ok ? 'OK' : 'FAIL', size);
  return ok;
}

function shot(project, page, file, opts = {}) {
  const { query, wait = 5 } = opts;
  const pageArg = query ? `--page ${page} --query "${query}"` : `--page ${page}`;
  run('simulator_open_page', `--project "${project}" ${pageArg}`);
  pause(wait);
  return capture(project, file, { wait });
}

const results = {};
const pngBefore = fs.readdirSync(outDir).filter((f) => f.endsWith('.png'));

console.log('=== seed fixtures ===');
const seedAcceptOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-acceptance-fixtures.php';"`,
  { cwd: `${root}/mijing-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const acceptSeed = JSON.parse(seedAcceptOut.trim().split('\n').pop());
console.log('acceptSeed:', acceptSeed);

const seedOvernightOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-overnight-batch-fixtures.php';"`,
  { cwd: `${root}/mijing-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const seed = JSON.parse(seedOvernightOut.trim().split('\n').pop());
console.log('overnightSeed:', seed);

const cardSalesDetailQuery = `year=${seed.year}&month=${seed.month}&cardProductId=${seed.productId}`;

// ── STAFF: new overnight pages + core regression ───────────────────────────
openAndAuth(staffProject, 'staff');

const staffShots = [
  ['pages/course/timetable/index', 'staff-timetable.png', {}],
  ['pages/course/timetable/display-config', 'staff-timetable-display-config.png', {}],
  ['pages/settings/defaults/card-reminder-config/index', 'staff-card-reminder-config.png', {}],
  ['pages/report/member-card-ranks/index', 'staff-member-card-ranks.png', {}],
  ['pages/report/card-sales/index', 'staff-card-sales.png', {}],
  ['pages/report/card-sales/detail', 'staff-card-sales-detail.png', { query: cardSalesDetailQuery }],
  ['pages/members/archived-cards/index', 'staff-archived-cards.png', {}],
  ['pages/settings/platform/subscription-orders/index', 'staff-platform-orders.png', {}],
  ['pages/settings/chain/cross-site-cards/index', 'staff-cross-site-cards.png', {}],
  ['pages/settings/sharing/staff-miniapp-code/index', 'staff-miniapp-code.png', {}],
  ['pages/course/session-detail', 'staff-session-fulfillment.png', { query: `id=${acceptSeed.sessionId}` }],
  ['pages/settings/booking-policy/index', 'staff-booking-policy.png', {}],
  ['pages/settings/courses/index', 'staff-courses-list.png', {}],
  ['pages/course/batch-tools', 'staff-schedule-batch.png', {}],
  ['pages/settings/hub/index', 'staff-settings-hub.png', {}],
];

for (const [page, file, opts] of staffShots) {
  results[file] = shot(staffProject, page, file, opts);
}

// ── MEMBER: new pages + core regression ────────────────────────────────────
openAndAuth(memberProject, 'member');

const memberShots = [
  ['pages/orders/index', 'member-orders.png', {}],
  ['pages/orders/result', 'member-orders-result.png', { query: `id=${seed.orderId}` }],
  ['pages/cards/transfer', 'member-card-transfer.png', { query: `token=${seed.transferToken}` }],
  ['pages/legal/index', 'member-legal.png', {}],
  ['pages/sites/detail', 'member-site-detail.png', {}],
  ['pages/cards/benefits', 'member-card-benefits.png', { query: `id=${seed.memberCardId}` }],
  ['pages/index/index', 'member-home.png', {}],
  ['pages/booking/index', 'member-booking.png', {}],
  ['pages/cards/index', 'member-cards.png', {}],
  ['pages/cards/catalog', 'member-card-catalog.png', {}],
];

for (const [page, file, opts] of memberShots) {
  results[file] = shot(memberProject, page, file, opts);
}

const pngs = fs.readdirSync(outDir).filter((f) => f.endsWith('.png'));
const newPngs = pngs.filter((f) => !pngBefore.includes(f));
const failed = Object.entries(results).filter(([, ok]) => !ok).map(([k]) => k);

const verifyOut = execSync(
  `powershell -Command "Get-ChildItem '${outDir.replace(/'/g, "''")}\\*.png' | Select-Object Name, Length | ConvertTo-Json"`,
  { encoding: 'utf8' },
);

console.log(
  JSON.stringify(
    {
      client,
      pngCount: pngs.length,
      newPngCount: newPngs.length,
      newPngFiles: newPngs.sort(),
      allPngFiles: pngs.sort(),
      results,
      failed,
      verifyOut: JSON.parse(verifyOut || '[]'),
    },
    null,
    2,
  ),
);
