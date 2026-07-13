const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/songguo-next/apps/staff-miniapp/dist/build/mp-weixin`;
const memberProject = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args, opts = {}) {
  const cmd = `wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`;
  const out = execSync(cmd, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024, ...opts });
  return out;
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function evalJson(fnSource, project) {
  const raw = run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fnSource)}`);
  const match = raw.match(/\{[\s\S]*\}/);
  if (!match) throw new Error(`No JSON in evaluate output: ${raw}`);
  const parsed = JSON.parse(match[0]);
  return parsed.result ?? parsed;
}

function sleep(ms) {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

console.log('=== seed site-2 fixtures ===');
const seedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-acceptance-fixtures.php';"`,
  { cwd: `${root}/songguo-next/apps/server`, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 },
);
console.log(seedOut.trim());
const seed = JSON.parse(seedOut.trim().split('\n').pop());
console.log('seed:', seed);

console.log('=== staff project ===');
run('project_open_window', `--project "${staffProject}"`);
run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(readFn('staff-report-session.js'))}`);
run('simulator_open_page', `--project "${staffProject}" --page pages/index/index`);
sleep(1500);

console.log('staff-session-fulfillment');
run('simulator_open_page', `--project "${staffProject}" --page "pages/course/session-detail?id=${seed.sessionId}"`);
run(
  'automation_viewport_action',
  `--project "${staffProject}" --action screenshot --wait-for-selector .appoint-actions --path "${outDir}/staff-session-fulfillment.png"`,
);

console.log('staff-card-products-list');
run('simulator_open_page', `--project "${staffProject}" --page pages/settings/card-products/index`);
run(
  'automation_viewport_action',
  `--project "${staffProject}" --action screenshot --wait-for-selector .product-card --path "${outDir}/staff-card-products-list.png"`,
);

console.log('staff-card-products-edit');
run(
  'automation_navigate',
  `--project "${staffProject}" --action navigateTo --url "pages/settings/card-products/edit?id=${seed.productId}"`,
);
run(
  'automation_viewport_action',
  `--project "${staffProject}" --action screenshot --wait-for-selector .course-scope-item --path "${outDir}/staff-card-products-edit.png"`,
);

console.log('staff-report-exports');
run('simulator_open_page', `--project "${staffProject}" --page pages/report/exports/index`);
sleep(2000);
try {
  run('automation_element_action', `--project "${staffProject}" --selector .action-card .u-button --action tap`);
} catch (e) {
  console.log('export tap fallback:', e.message);
}
sleep(2500);
run(
  'automation_viewport_action',
  `--project "${staffProject}" --action screenshot --wait-for-selector .job-card --path "${outDir}/staff-report-exports.png"`,
);

console.log('=== member project ===');
run('project_open_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
run('simulator_open_page', `--project "${memberProject}" --page pages/index/index`);
sleep(1500);

console.log('member-card-catalog');
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
run(
  'automation_viewport_action',
  `--project "${memberProject}" --action screenshot --wait-for-selector .product-item --path "${outDir}/member-card-catalog.png"`,
);

console.log('member-card-purchase-success');
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
sleep(2000);
const purchaseFn = `function() {
  var page = getCurrentPages().pop();
  var vm = page.$vm;
  if (!vm || !vm.setupState || !vm.setupState.products.value.length) {
    return { ok: false, reason: 'no products' };
  }
  var product = vm.setupState.products.value[0];
  return vm.setupState.purchaseProduct(product).then(function() {
    return { ok: true, productId: product.id, productName: product.name };
  }).catch(function(e) {
    return { ok: false, reason: String(e) };
  });
}`;
const purchaseResult = evalJson(purchaseFn, memberProject);
console.log('purchase result:', purchaseResult);
sleep(1500);
let purchaseShot = 'skipped';
try {
  run(
    'automation_viewport_action',
    `--project "${memberProject}" --action screenshot --wait-seconds 2 --path "${outDir}/member-card-purchase-success.png"`,
  );
  purchaseShot = 'ok';
} catch (e) {
  console.log('purchase success modal shot failed, fallback wallet');
  run('simulator_open_page', `--project "${memberProject}" --page pages/cards/index`);
  run(
    'automation_viewport_action',
    `--project "${memberProject}" --action screenshot --wait-for-selector .page-container --path "${outDir}/member-card-purchase-success.png"`,
  );
  purchaseShot = 'wallet-fallback';
}

console.log(JSON.stringify({ seed, purchaseResult, purchaseShot, done: true }, null, 2));
