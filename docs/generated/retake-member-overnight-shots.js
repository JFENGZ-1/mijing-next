/** Retake failed member overnight shots — Codex client */
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const memberProject = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const asciiTempDir = 'C:/Users/Zhong/AppData/Local/Temp/songguo-acceptance-shots';
const client = 'Codex';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function run(tool, args, retries = 3) {
  for (let i = 0; i < retries; i++) {
    try {
      return execSync(`wechatide -c ${client} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
    } catch (e) {
      const msg = String(e.message || e);
      if (msg.includes('Failed to connect') || msg.includes('CONNECT_ERROR')) {
        console.log(`retry ${i + 1} after connect error for ${tool}`);
        pause(5);
        try {
          execSync(`wechatide -c ${client} -t check_devtools_status --skill-version 0.2.5`, { env, encoding: 'utf8' });
        } catch (_) {}
        continue;
      }
      throw e;
    }
  }
  throw new Error(`failed after retries: ${tool}`);
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function capture(file) {
  const asciiTemp = `${asciiTempDir}/${file}`;
  const finalPath = `${outDir}/${file}`;
  run('automation_viewport_action', `--project "${memberProject}" --action screenshot --wait-seconds 5 --path "${asciiTemp}"`);
  if (fs.existsSync(asciiTemp)) fs.copyFileSync(asciiTemp, finalPath);
  const size = fs.existsSync(finalPath) ? fs.statSync(finalPath).size : 0;
  console.log(file, size > 0 ? 'OK' : 'FAIL', size);
  return size > 0;
}

function shot(page, file, query) {
  const pageArg = query ? `--page ${page} --query "${query}"` : `--page ${page}`;
  run('simulator_open_page', `--project "${memberProject}" ${pageArg}`);
  pause(5);
  return capture(file);
}

const seedOut = execSync(
  `php artisan tinker --execute="include '${outDir.replace(/\\/g, '/')}/seed-overnight-batch-fixtures.php';"`,
  { cwd: `${root}/songguo-next/apps/server`, encoding: 'utf8' },
);
const seed = JSON.parse(seedOut.trim().split('\n').pop());
console.log('seed', seed);

run('open_project_window', `--project "${memberProject}"`);
run('debug_clear_cache', `--project "${memberProject}" --action cleanAll`);
pause(4);
const fn = readFn('member-polish-session.js');
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(fn)}`);
pause(2);

const results = {};
results['member-orders.png'] = shot('pages/orders/index', 'member-orders.png');
results['member-orders-result.png'] = shot('pages/orders/result', 'member-orders-result.png', `id=${seed.orderId}`);
results['member-card-transfer.png'] = shot('pages/cards/transfer', 'member-card-transfer.png', `token=${seed.transferToken}`);
results['member-legal.png'] = shot('pages/legal/index', 'member-legal.png');
results['member-site-detail.png'] = shot('pages/sites/detail', 'member-site-detail.png');
results['member-card-benefits.png'] = shot('pages/cards/benefits', 'member-card-benefits.png', `id=${seed.memberCardId}`);

console.log(JSON.stringify({ results, failed: Object.entries(results).filter(([, ok]) => !ok).map(([k]) => k) }, null, 2));
