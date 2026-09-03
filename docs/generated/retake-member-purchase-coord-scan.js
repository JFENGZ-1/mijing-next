const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const memberProject = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

run('project_open_window', `--project "${memberProject}"`);
run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
run('simulator_open_page', `--project "${memberProject}" --page pages/cards/catalog`);
pause(3);
console.log(run('automation_evaluate', `--project "${memberProject}" --fn-source ${JSON.stringify(readFn('member-catalog-api-debug.js'))}`));

run('automation_element_action', `--project "${memberProject}" --selector .product-actions button --action tap`);
pause(1);

for (const pt of [
  [300, 555],
  [320, 550],
  [280, 545],
  [200, 555],
]) {
  try {
    run(
      'automation_element_action',
      `--project "${memberProject}" --selector view --action tap --x ${pt[0]} --y ${pt[1]}`,
    );
    pause(2);
    run(
      'automation_viewport_action',
      `--project "${memberProject}" --action screenshot --wait-seconds 1 --path "${outDir}/member-card-purchase-success.png"`,
    );
    console.log('tried', pt);
  } catch (e) {
    console.log('fail', pt, e.message);
  }
}
