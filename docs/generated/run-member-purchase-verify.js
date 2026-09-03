const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const project = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const env = { ...process.env, PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH };

function run(tool, args) {
  return execSync(`wechatide -c ${WECHAT_IDE_CLIENT} -t ${tool} ${args}`, { env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function readFn(name) {
  return fs.readFileSync(path.join(outDir, name), 'utf8').trim();
}

const report = { steps: [] };

function step(name, fn) {
  console.log(`\n=== ${name} ===`);
  try {
    const result = fn();
    report.steps.push({ name, ok: true, result: typeof result === 'string' ? result.slice(0, 2000) : result });
    return result;
  } catch (e) {
    const msg = e.stdout || e.message || String(e);
    report.steps.push({ name, ok: false, error: msg.slice(0, 2000) });
    console.log('FAIL:', msg.slice(0, 500));
    throw e;
  }
}

step('open_project_window', () =>
  run('open_project_window', `--project "${project}"`),
);

step('debug_clear_cache', () => {
  run('debug_clear_cache', `--project "${project}" --action cleanAll`);
  pause(4);
  return 'cleanAll + 4s wait';
});

step('inject_session', () => {
  const fn = readFn('member-polish-session.js');
  try {
    return run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  } catch (e) {
    console.log('first inject timeout — retrying');
    pause(3);
    return run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  }
});

pause(2);

step('navigate_catalog', () => {
  run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
  pause(4);
  return run('automation_runtime_info', `--project "${project}" --action currentPage`);
});

step('page_data', () =>
  run('automation_page_action', `--project "${project}" --action getData`),
);

step('query_products', () =>
  run('automation_page_action', `--project "${project}" --action querySelectorAll --selector .product-item`),
);

// Tap first buy button
step('tap_buy', () => {
  run('automation_element_action', `--project "${project}" --selector .product-actions .u-button --action tap`);
  pause(1);
  return 'tapped first buy button';
});

// Confirm purchase modal — try common WeChat modal confirm selectors
step('confirm_modal', () => {
  const selectors = [
    '.weui-dialog__btn_primary',
    '.weui-dialog__ft .weui-dialog__btn:nth-child(2)',
    'button',
  ];
  for (const sel of selectors) {
    try {
      run('automation_element_action', `--project "${project}" --selector ${sel} --action tap`);
      pause(0.5);
      return `confirmed via ${sel}`;
    } catch (e) {
      console.log(`selector ${sel} failed`);
    }
  }
  throw new Error('could not confirm purchase modal');
});

// Wait for success modal (300ms+ per catalog.vue)
pause(1);

step('verify_success_modal', () => {
  const data = run('automation_page_action', `--project "${project}" --action getData`);
  return data;
});

// Screenshot with ASCII temp path trick
const asciiTemp = 'C:/Users/Zhong/AppData/Local/Temp/member-card-purchase-success-live.png';
const finalPath = `${outDir}/member-card-purchase-success-live.png`;

step('screenshot', () => {
  try {
    run(
      'automation_viewport_action',
      `--project "${project}" --action screenshot --wait-seconds 2 --path "${asciiTemp}"`,
    );
  } catch (e) {
    console.log('screenshot with wait-seconds failed, retrying');
    run(
      'automation_viewport_action',
      `--project "${project}" --action screenshot --wait-seconds 3 --path "${asciiTemp}"`,
    );
  }
  fs.copyFileSync(asciiTemp, finalPath);
  const size = fs.statSync(finalPath).size;
  report.screenshot = { path: finalPath, size, ok: size > 1000 };
  return report.screenshot;
});

console.log('\n=== REPORT ===');
console.log(JSON.stringify(report, null, 2));
