const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const project = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
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

const report = { steps: [] };
function log(step, data) {
  report.steps.push({ step, ...data });
  console.log(step, JSON.stringify(data));
}

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);

const session = parseJson(run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`));
log('session', { ok: session?.result?.result?.ok, tenantId: session?.result?.result?.tenantId, siteId: session?.result?.result?.siteId });

run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

// Native UI purchase: confirm -> wait -> purchase -> success modal
run('automation_element_action', `--project "${project}" --selector .product-actions .u-button --action tap`);
pause(1);
run('automation_element_action', `--project "${project}" --selector .weui-dialog__btn_primary --action tap`);
pause(5);

const purchase = parseJson(run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-live-purchase-eval.js'))}`));
const apiBody = purchase?.result?.result;
log('purchase_api', {
  statusCode: apiBody?.statusCode,
  productId: apiBody?.productId,
  tenantId: apiBody?.tenantId,
  siteId: apiBody?.siteId,
  hasCard: !!apiBody?.data?.data?.memberCard,
  code: apiBody?.data?.code,
  message: apiBody?.data?.message,
});

const card = apiBody?.data?.data?.memberCard;
report.uiPurchaseAttempted = true;
report.apiSuccess = apiBody?.statusCode === 201 && !!card;
report.card = card ? { id: card.id, name: card.name, status: card.status } : null;
report.error = !card ? apiBody?.data || apiBody?.error : null;

console.log(JSON.stringify(report, null, 2));
process.exit(report.apiSuccess ? 0 : 1);
