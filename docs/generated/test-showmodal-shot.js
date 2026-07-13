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

const seedPurchaseOut = execSync('php docs/generated/seed-member-purchase-shot.php', { cwd: root, encoding: 'utf8' });
const payload = JSON.parse(seedPurchaseOut.trim().split('\n').pop());
const statusMap = { active: '已激活', pending_activation: '待激活', expired: '已过期', frozen: '已冻结' };
const argsFile = path.join(outDir, 'member-success-modal-test-args.json');
fs.writeFileSync(
  argsFile,
  JSON.stringify([
    {
      title: '购卡成功',
      content: `「${payload.name}」已发放，状态：${statusMap[payload.status] || payload.status}`,
      confirmText: '查看',
      cancelText: '留在此页',
    },
  ]),
);

run('open_project_window', `--project "${project}"`);
run('debug_clear_cache', `--project "${project}" --action cleanAll`);
pause(4);
run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(readFn('member-polish-session.js'))}`);
pause(2);
run('simulator_open_page', `--project "${project}" --page pages/cards/catalog`);
pause(4);

let blocked = false;
try {
  run('automation_wx_api', `--project "${project}" --action call --method showModal --args-file "${argsFile}"`);
} catch (e) {
  blocked = true;
}
pause(2);

const out = path.join(outDir, 'member-card-purchase-success-test.png');
run('automation_viewport_action', `--project "${project}" --action screenshot --wait-seconds 3 --path "${out}"`);
console.log(JSON.stringify({ blocked, size: fs.statSync(out).size, path: out }, null, 2));
