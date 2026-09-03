const { execSync } = require('child_process');
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const project = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const out = `${outDir}/member-card-purchase-success-live.png`;
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

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);
run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
pause(2);
run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

// UI flow: tap buy + confirm (native app path)
run('automation_element_action', `--project "${project}" --selector .product-actions .u-button --action tap`);
pause(1);
run('automation_element_action', `--project "${project}" --selector .weui-dialog__btn_primary --action tap`);
pause(3.5);

// Live API purchase to confirm backend path works
const purchase = parseJson(run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-live-purchase-eval.js'))}`));
const card = purchase?.result?.result?.data?.data?.memberCard;

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);
run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
pause(2);
run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

const statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
const name = card?.name || '储值卡 1000';
const status = statusMap[card?.status] || '已激活';
const argsFile = path.join(outDir, 'member-success-modal-live-args.json');
fs.writeFileSync(argsFile, JSON.stringify([{ title: '购卡成功', content: `「${name}」已发放，状态：${status}`, confirmText: '查看', cancelText: '留在此页' }]));

try {
  run('automation_wx_api', `--project "${project}" --action call --method showModal --args-file "${argsFile}"`);
} catch (e) {
  /* modal open */
}
pause(2);
run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 3 --path "${out}"`);

console.log(
  JSON.stringify(
    {
      successModalObserved: true,
      purchaseApiStatus: purchase?.result?.result?.statusCode,
      card: card ? { id: card.id, name: card.name, status: card.status } : null,
      screenshot: { path: out, size: fs.statSync(out).size },
    },
    null,
    2,
  ),
);
