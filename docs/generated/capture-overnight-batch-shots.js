/**
 * L5 overnight-batch screenshot runner — live login (D-L) by default.
 * Client: CURSOR_WECHAT_CLIENT or WECHAT_IDE_CLIENT env (default `cursor`).
 * Fallback: ACCEPTANCE_AUTH_MODE=seed for D-S session inject.
 * Output: docs/generated/staff-*.png + member-*.png
 *
 * Flow: cleanAll → login UI tap → onboarding if needed → target page → path assert → screenshot
 * Prereqs: pnpm build:staff + build:member, php artisan serve :8010, wechatide auth -c cursor
 */
const { execSync } = require('child_process');
const fs = require('fs');
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
} = require('./devtools-live-login-lib');

const MIN_SIZE = {
  default: 10000,
  'staff-session-fulfillment.png': 50000,
};

const results = {};
const runtimePaths = {};
const authModes = {};

console.log('=== auth mode:', AUTH_MODE, AUTH_MODE === 'seed' ? '(D-S inject)' : '(D-L live login) ===');

assertDevToolsReady();

console.log('=== seed acceptance fixtures (session-detail regression) ===');
const acceptSeedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-acceptance-fixtures.php';"`,
  { cwd: `${root}/songguo-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const acceptSeed = JSON.parse(acceptSeedOut.trim().split('\n').pop());
console.log('acceptSeed:', acceptSeed);

console.log('=== seed overnight-batch fixtures ===');
const seedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-overnight-batch-fixtures.php';"`,
  { cwd: `${root}/songguo-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const seed = JSON.parse(seedOut.trim().split('\n').pop());
console.log('seed:', seed);

// ── STAFF (9 overnight + 1 regression) ─────────────────────────────────────
const staffLogin = startAppSession(staffProject, 'staff');
console.log('staff auth:', staffLogin);

const staffShots = [
  ['pages/course/timetable/index', 'staff-timetable.png', {}],
  ['pages/course/timetable/display-config', 'staff-display-config.png', {}],
  ['pages/settings/defaults/card-reminder-config/index', 'staff-card-reminder-config.png', {}],
  ['pages/report/member-card-ranks/index', 'staff-member-card-ranks.png', {}],
  ['pages/report/card-sales/index', 'staff-card-sales.png', {}],
  ['pages/members/archived-cards/index', 'staff-archived-cards.png', {}],
  ['pages/settings/platform/subscription-orders/index', 'staff-platform-orders.png', {}],
  ['pages/settings/chain/cross-site-cards/index', 'staff-cross-site-cards.png', {}],
  ['pages/settings/sharing/staff-miniapp-code/index', 'staff-miniapp-code.png', {}],
  ['pages/course/session-detail', 'staff-session-fulfillment.png', { query: `id=${acceptSeed.sessionId}` }],
];

for (const [page, file, opts] of staffShots) {
  const r = shot(staffProject, 'staff', page, file, { ...opts, minSize: MIN_SIZE[file] || MIN_SIZE.default });
  results[file] = r.ok;
  runtimePaths[file] = r.path;
  authModes[file] = r.mode;
}

// ── MEMBER (6 overnight) ───────────────────────────────────────────────────
const memberLogin = startAppSession(memberProject, 'member');
console.log('member auth:', memberLogin);

const memberShots = [
  ['pages/orders/index', 'member-orders.png', {}],
  ['pages/orders/result', 'member-orders-result.png', { query: `id=${seed.orderId}` }],
  ['pages/cards/transfer', 'member-card-transfer.png', { query: `token=${seed.transferToken}` }],
  ['pages/legal/index', 'member-legal.png', {}],
  ['pages/sites/detail', 'member-site-detail.png', { query: `id=${seed.siteId}` }],
  ['pages/cards/benefits', 'member-card-benefits.png', { query: `id=${seed.memberCardId}` }],
];

for (const [page, file, opts] of memberShots) {
  const query = opts.query || '';
  const r = shot(memberProject, 'member', page, file, { ...opts, query, minSize: MIN_SIZE[file] || MIN_SIZE.default });
  results[file] = r.ok;
  runtimePaths[file] = r.path;
  authModes[file] = r.mode;
}

const pngs = fs.readdirSync(outDir).filter((f) => f.endsWith('.png'));
const failed = Object.entries(results).filter(([, ok]) => !ok).map(([k]) => k);
const valid = Object.entries(results).filter(([, ok]) => ok).map(([k]) => k);

const verifyOut = execSync(
  `powershell -Command "Get-ChildItem '${outDir.replace(/'/g, "''")}\\*.png' | Where-Object { $_.Name -in @(${Object.keys(results).map((f) => `'${f}'`).join(',')}) } | Select-Object Name, Length, FullName | ConvertTo-Json"`,
  { encoding: 'utf8' },
);

console.log(
  JSON.stringify(
    {
      client: wechatClient,
      authMode: AUTH_MODE,
      staffLogin,
      memberLogin,
      validCount: valid.length,
      invalidCount: failed.length,
      pngCount: pngs.length,
      overnightBatch: results,
      authModes,
      runtimePaths,
      failed,
      valid,
      verifyOut: JSON.parse(verifyOut || '[]'),
      cacheNote: 'cleanAll once per app; D-L live login via 微信登录 button; D-S only when ACCEPTANCE_AUTH_MODE=seed',
    },
    null,
    2,
  ),
);
