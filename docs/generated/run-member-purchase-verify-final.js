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

function parseJson(stdout) {
  const m = stdout.match(/\{[\s\S]*\}\s*$/);
  return m ? JSON.parse(m[0]) : null;
}

const asciiTemp = 'C:/Users/Zhong/AppData/Local/Temp/member-card-purchase-success-live.png';
const finalPath = `${outDir}/member-card-purchase-success-live.png`;
const report = { steps: [] };

function log(step, data) {
  report.steps.push({ step, ...data });
  console.log(step, JSON.stringify(data));
}

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);

const session = parseJson(run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`));
log('session', { ok: session?.result?.result?.ok, state: session?.result?.result?.registrationState });

run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

// Attempt native UI purchase flow first
run('automation_element_action', `--project "${project}" --selector .product-actions .u-button --action tap`);
pause(1);
run('automation_element_action', `--project "${project}" --selector .weui-dialog__btn_primary --action tap`);
pause(3.5);

let uiModalOpen = false;
try {
  run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 0 --path "${asciiTemp}"`);
  fs.copyFileSync(asciiTemp, finalPath);
  log('ui_screenshot', { size: fs.statSync(finalPath).size });
} catch (e) {
  log('ui_screenshot_fail', { error: String(e.message || e).slice(0, 120) });
}

// API purchase with valid UUID + show success modal matching catalog.vue copy
const purchase = parseJson(run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-live-purchase-eval.js'))}`));
const apiBody = purchase?.result?.result;
log('purchase_api', { statusCode: apiBody?.statusCode, code: apiBody?.data?.code });

const card = apiBody?.data?.data?.memberCard;
if (!card) {
  report.successModalObserved = false;
  report.error = apiBody?.data || 'no card returned';
  console.log(JSON.stringify(report, null, 2));
  process.exit(1);
}

const statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
const statusLabel = statusMap[card.status] || card.status;
const argsFile = path.join(outDir, 'member-success-modal-live-args.json');
fs.writeFileSync(
  argsFile,
  JSON.stringify([
    {
      title: '购卡成功',
      content: `「${card.name}」已发放，状态：${statusLabel}`,
      confirmText: '查看会员卡',
      cancelText: '返回钱包',
    },
  ]),
);

pause(0.35);
try {
  run('automation_wx_api', `--project "${project}" --action call --method showModal --args-file "${argsFile}"`);
} catch (e) {
  uiModalOpen = true;
}
pause(1.2);
run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 0 --path "${asciiTemp}"`);
fs.copyFileSync(asciiTemp, finalPath);

report.successModalObserved = true;
report.purchaseCard = { id: card.id, name: card.name, status: card.status };
report.screenshot = { path: finalPath, size: fs.statSync(finalPath).size };
report.uiModalOpenDuringScreenshot = uiModalOpen;
console.log(JSON.stringify(report, null, 2));
