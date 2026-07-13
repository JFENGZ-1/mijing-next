const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/songguo-next/apps/staff-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const asciiTempDir = 'C:/Users/Zhong/AppData/Local/Temp';
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
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

function shot(page, file) {
  run('simulator_open_page', `--project "${staffProject}" --page ${page}`);
  pause(5);
  const pathInfo = run('automation_runtime_info', `--project "${staffProject}" --action currentPage`);
  console.log('currentPage', pathInfo.trim());
  const asciiTemp = `${asciiTempDir}/${file}`;
  run(
    'automation_viewport_action',
    `--project "${staffProject}" --action screenshot --wait-seconds 5 --path "${asciiTemp}"`,
  );
  fs.copyFileSync(asciiTemp, `${outDir}/${file}`);
  const ok = fs.statSync(`${outDir}/${file}`).size > 1000;
  console.log(file, ok ? 'OK' : 'FAIL', fs.statSync(`${outDir}/${file}`).size);
  return ok;
}

const results = {};
openAndAuth();
results['staff-settings-hub.png'] = shot('pages/settings/hub/index', 'staff-settings-hub.png');
openAndAuth();
results['staff-booking-policy.png'] = shot('pages/settings/booking-policy/index', 'staff-booking-policy.png');
openAndAuth();
results['staff-courses-list.png'] = shot('pages/settings/courses/index', 'staff-courses-list.png');

console.log(JSON.stringify({ results, pngCount: fs.readdirSync(outDir).filter((f) => f.endsWith('.png')).length }, null, 2));
