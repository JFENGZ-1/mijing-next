const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/mijing-next/apps/staff-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const asciiTempDir = 'C:/Users/Zhong/AppData/Local/Temp';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function currentPath() {
  const out = run('automation_runtime_info', `--project "${staffProject}" --action currentPage`);
  const m = out.match(/"path":\s*"([^"]+)"/);
  return m ? m[1] : '';
}

function openAndAuth() {
  run('project_open_window', `--project "${staffProject}"`);
  run('debug_clear_cache', `--project "${staffProject}" --action cleanAll`);
  pause(4);
  const fn = fs.readFileSync(path.join(__dirname, 'staff-report-session.js'), 'utf8').trim();
  try {
    run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(fn)}`);
  } catch (e) {
    pause(3);
    run('automation_evaluate', `--project "${staffProject}" --fn-source ${JSON.stringify(fn)}`);
  }
  pause(2);
}

function gotoPage(page) {
  for (let attempt = 1; attempt <= 3; attempt++) {
    run('simulator_open_page', `--project "${staffProject}" --page ${page}`);
    pause(6);
    const pathNow = currentPath();
    console.log(`attempt ${attempt} want=${page} got=${pathNow}`);
    if (pathNow === page) return true;
    run('automation_navigate', `--project "${staffProject}" --action reLaunch --url ${page}`);
    pause(5);
    const relaunchPath = currentPath();
    console.log(`reLaunch got=${relaunchPath}`);
    if (relaunchPath === page) return true;
  }
  return currentPath() === page;
}

function shot(page, file) {
  const ready = gotoPage(page);
  const asciiTemp = `${asciiTempDir}/${file}`;
  run(
    'automation_viewport_action',
    `--project "${staffProject}" --action screenshot --wait-seconds 4 --path "${asciiTemp}"`,
  );
  fs.copyFileSync(asciiTemp, `${outDir}/${file}`);
  const size = fs.statSync(`${outDir}/${file}`).size;
  console.log(file, ready ? 'PATH_OK' : 'PATH_MISMATCH', size);
  return { ok: ready && size > 1000, path: currentPath(), size };
}

const targets = [
  ['pages/settings/hub/index', 'staff-settings-hub.png'],
  ['pages/settings/booking-policy/index', 'staff-booking-policy.png'],
  ['pages/settings/courses/index', 'staff-courses-list.png'],
];

const results = {};
for (const [page, file] of targets) {
  openAndAuth();
  results[file] = shot(page, file);
}

console.log(
  JSON.stringify(
    {
      results,
      pngCount: fs.readdirSync(outDir).filter((f) => f.endsWith('.png')).length,
    },
    null,
    2,
  ),
);
