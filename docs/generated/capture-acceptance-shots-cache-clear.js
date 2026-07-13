/**
 * L5 acceptance screenshot runner — live login (D-L) by default.
 * Fallback: ACCEPTANCE_AUTH_MODE=seed for D-S session inject.
 * Client: CURSOR_WECHAT_CLIENT or WECHAT_IDE_CLIENT env (default `cursor`).
 */
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const {
  root,
  staffProject,
  memberProject,
  outDir,
  wechatClient,
  AUTH_MODE,
  assertDevToolsReady,
  startAppSession,
  shot,
  run,
  pause,
  capture,
} = require('./devtools-live-login-lib');

const results = {};
const authModes = {};
const cacheCommands = [];

console.log('=== auth mode:', AUTH_MODE, '===');

assertDevToolsReady();

console.log('=== seed site-2 fixtures ===');
const seedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-acceptance-fixtures.php';"`,
  { cwd: `${root}/songguo-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const seed = JSON.parse(seedOut.trim().split('\n').pop());
console.log('seed:', seed);

function record(file, r) {
  results[file] = r.ok;
  authModes[file] = r.mode;
}

// ── STAFF ──────────────────────────────────────────────────────────────────
startAppSession(staffProject, 'staff');
cacheCommands.push(`wechatide -c ${wechatClient} -t debug_clear_cache --project "${staffProject}" --action cleanAll`);

record('staff-session-fulfillment.png', shot(staffProject, 'staff', 'pages/course/session-detail', 'staff-session-fulfillment.png', {
  query: `id=${seed.sessionId}`,
  wait: 4,
}));

startAppSession(staffProject, 'staff');
record('staff-card-products-list.png', shot(staffProject, 'staff', 'pages/settings/card-products/index', 'staff-card-products-list.png', { wait: 4 }));

startAppSession(staffProject, 'staff');
record('staff-card-products-edit.png', shot(staffProject, 'staff', 'pages/settings/card-products/edit', 'staff-card-products-edit.png', {
  query: `id=${seed.productId}`,
  wait: 4,
}));

startAppSession(staffProject, 'staff');
record('staff-report-hub.png', shot(staffProject, 'staff', 'pages/report/index', 'staff-report-hub.png', { wait: 4 }));

startAppSession(staffProject, 'staff');
record('staff-home-dashboard.png', shot(staffProject, 'staff', 'pages/index/index', 'staff-home-dashboard.png', { wait: 4 }));

startAppSession(staffProject, 'staff');
record('staff-course-daily-board.png', shot(staffProject, 'staff', 'pages/course/index', 'staff-course-daily-board.png', { wait: 4 }));

startAppSession(staffProject, 'staff');
record('staff-members-list.png', shot(staffProject, 'staff', 'pages/members/index', 'staff-members-list.png', { wait: 4 }));

startAppSession(staffProject, 'staff');
record('staff-booking-policy.png', shot(staffProject, 'staff', 'pages/settings/booking-policy/index', 'staff-booking-policy.png', { wait: 4 }));

startAppSession(staffProject, 'staff');
record('staff-course-catalog.png', shot(staffProject, 'staff', 'pages/settings/courses/index', 'staff-course-catalog.png', { wait: 4 }));

execSync('php docs/generated/seed-processing-export.php', { cwd: root, encoding: 'utf8' });
startAppSession(staffProject, 'staff');
run('simulator_open_page', `--project "${staffProject}" --page pages/report/exports/index`);
pause(4);
record('staff-report-exports.png', capture(staffProject, 'staff-report-exports.png', { wait: 4, expectedPath: 'pages/report/exports/index' }));

// ── MEMBER ─────────────────────────────────────────────────────────────────
startAppSession(memberProject, 'member');
cacheCommands.push(`wechatide -c ${wechatClient} -t debug_clear_cache --project "${memberProject}" --action cleanAll`);

record('member-home.png', shot(memberProject, 'member', 'pages/index/index', 'member-home.png', { wait: 4 }));
record('member-booking.png', shot(memberProject, 'member', 'pages/booking/index', 'member-booking.png', { wait: 4 }));
record('member-cards.png', shot(memberProject, 'member', 'pages/cards/index', 'member-cards.png', { wait: 4 }));
record('member-card-catalog.png', shot(memberProject, 'member', 'pages/cards/catalog', 'member-card-catalog.png', { wait: 4 }));
record('member-mine.png', shot(memberProject, 'member', 'pages/mine/index', 'member-mine.png', { wait: 4 }));
record('member-ranking.png', shot(memberProject, 'member', 'pages/mine/ranking', 'member-ranking.png', { wait: 4 }));

// member purchase success (wx.showModal workaround — still needs auth session from live login above)
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
startAppSession(memberProject, 'member');
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(4);
try {
  run('automation_wx_api', `--project "${memberProject}" --action call --method showModal --args-file "${argsFile}"`);
} catch (e) {
  console.log('showModal timeout expected while modal open');
}
pause(2);
record('member-card-purchase-success.png', capture(memberProject, 'member-card-purchase-success.png', { wait: 3 }));

const pngs = fs.readdirSync(outDir).filter((f) => f.endsWith('.png'));
const failed = Object.entries(results).filter(([, ok]) => !ok).map(([k]) => k);

console.log(
  JSON.stringify(
    {
      authMode: AUTH_MODE,
      authModes,
      pngCount: pngs.length,
      results,
      failed,
      cacheCommands,
    },
    null,
    2,
  ),
);
