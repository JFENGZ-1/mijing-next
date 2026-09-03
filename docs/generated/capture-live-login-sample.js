/**
 * L5 live-login sample captures — proves D-L pattern (3 staff + 2 member).
 * Run: node docs/generated/capture-live-login-sample.js
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
} = require('./devtools-live-login-lib');

const results = {};
const authModes = {};
const runtimePaths = {};

assertDevToolsReady();

console.log('=== seed fixtures ===');
const seedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-overnight-batch-fixtures.php';"`,
  { cwd: `${root}/mijing-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const seed = JSON.parse(seedOut.trim().split('\n').pop());
console.log('seed:', seed);

const acceptSeedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-acceptance-fixtures.php';"`,
  { cwd: `${root}/mijing-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
const acceptSeed = JSON.parse(acceptSeedOut.trim().split('\n').pop());

// Staff samples (3)
const staffLogin = startAppSession(staffProject, 'staff');
const staffSamples = [
  ['pages/report/index', 'staff-report-hub-live.png'],
  ['pages/course/timetable/index', 'staff-timetable-live.png'],
  ['pages/course/session-detail', 'staff-session-fulfillment-live.png', `id=${acceptSeed.sessionId}`],
];

for (const [page, file, query] of staffSamples) {
  const r = shot(staffProject, 'staff', page, file, { query, wait: 5 });
  results[file] = r.ok;
  authModes[file] = r.mode;
  runtimePaths[file] = r.path;
}

// Member samples (2)
const memberLogin = startAppSession(memberProject, 'member');
const memberSamples = [
  ['pages/orders/index', 'member-orders-live.png'],
  ['pages/legal/index', 'member-legal-live.png'],
];

for (const [page, file] of memberSamples) {
  const r = shot(memberProject, 'member', page, file, { wait: 5 });
  results[file] = r.ok;
  authModes[file] = r.mode;
  runtimePaths[file] = r.path;
}

const evidence = {
  verifiedAt: new Date().toISOString(),
  client: wechatClient,
  authMode: AUTH_MODE,
  staffLogin,
  memberLogin,
  results,
  authModes,
  runtimePaths,
  loginFlow: 'cleanAll → pages/login → tap .login-page .u-button → API → onboarding (member if needed) → target',
};

fs.writeFileSync(path.join(outDir, 'live-login-sample-evidence.json'), JSON.stringify(evidence, null, 2));
console.log(JSON.stringify(evidence, null, 2));
