/**
 * Phase D — DevTools evidence batch (cleanAll mandatory per VERIFICATION-LADDER.md).
 * cursor client (WECHAT_IDE_CLIENT env override). Output: docs/generated/*.png via ASCII temp copy trick.
 */
const WECHAT_IDE_CLIENT = process.env.WECHAT_IDE_CLIENT || 'cursor';
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/songguo-next/apps/staff-miniapp/dist/build/mp-weixin`;
const memberProject = `${root}/songguo-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const asciiTempDir = 'C:/Users/Zhong/AppData/Local/Temp';
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

function clearCache(project) {
  console.log('debug_clear_cache cleanAll', project);
  run('debug_clear_cache', `--project "${project}" --action cleanAll`);
  pause(4);
}

function openAndAuth(project, session) {
  run('project_open_window', `--project "${project}"`);
  clearCache(project);
  const fn = session === 'staff' ? readFn('staff-report-session.js') : readFn('member-polish-session.js');
  try {
    run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  } catch (e) {
    console.log('session inject timeout — retrying');
    pause(3);
    run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  }
  pause(2);
}

function capture(project, file, opts = {}) {
  const { wait = 4, selector } = opts;
  const asciiTemp = `${asciiTempDir}/${file}`;
  const finalPath = `${outDir}/${file}`;
  try {
    if (selector) {
      run(
        'automation_viewport_action',
        `--project "${project}" --action screenshot --wait-for-selector ${selector} --path "${asciiTemp}"`,
      );
    } else {
      run(
        'automation_viewport_action',
        `--project "${project}" --action screenshot --wait-seconds ${wait} --path "${asciiTemp}"`,
      );
    }
  } catch (e) {
    console.log('screenshot error', file, String(e.message || e).slice(0, 200));
  }
  if (fs.existsSync(asciiTemp)) {
    fs.copyFileSync(asciiTemp, finalPath);
  }
  const ok = fs.existsSync(finalPath) && fs.statSync(finalPath).size > 1000;
  console.log(file, ok ? 'OK' : 'FAIL', fs.existsSync(finalPath) ? fs.statSync(finalPath).size : 0);
  return ok;
}

function shot(project, page, file, opts = {}) {
  const { query, wait = 4, selector } = opts;
  const pageArg = query ? `--page ${page} --query ${query}` : `--page ${page}`;
  run('simulator_open_page', `--project "${project}" ${pageArg}`);
  pause(wait);
  return capture(project, file, { wait, selector });
}

const results = {};
const pngCountBefore = fs.readdirSync(outDir).filter((f) => f.endsWith('.png')).length;
console.log('pngCountBefore', pngCountBefore);

// ── STAFF ──────────────────────────────────────────────────────────────────
openAndAuth(staffProject, 'staff');

results['staff-settings-hub.png'] = shot(staffProject, 'pages/settings/hub/index', 'staff-settings-hub.png', {
  wait: 4,
  selector: '.section-card',
});

openAndAuth(staffProject, 'staff');
results['staff-booking-policy.png'] = shot(
  staffProject,
  'pages/settings/booking-policy/index',
  'staff-booking-policy.png',
  { wait: 4, selector: '.section-title' },
);

openAndAuth(staffProject, 'staff');
results['staff-courses-list.png'] = shot(staffProject, 'pages/settings/courses/index', 'staff-courses-list.png', {
  wait: 4,
  selector: '.course-card',
});

openAndAuth(staffProject, 'staff');
results['staff-schedule-batch.png'] = shot(staffProject, 'pages/course/batch-tools', 'staff-schedule-batch.png', {
  wait: 4,
  selector: '.batch-page',
});

// ── MEMBER ─────────────────────────────────────────────────────────────────
openAndAuth(memberProject, 'member');

results['member-mine.png'] = shot(memberProject, 'pages/mine/index', 'member-mine.png', { wait: 4 });
results['member-mine-profile.png'] = shot(memberProject, 'pages/mine/profile', 'member-mine-profile.png', {
  wait: 4,
  selector: '.profile-header',
});
results['member-notices.png'] = shot(memberProject, 'pages/notices/index', 'member-notices.png', {
  wait: 4,
  selector: '.title',
});

const pngs = fs.readdirSync(outDir).filter((f) => f.endsWith('.png'));
const failed = Object.entries(results).filter(([, ok]) => !ok).map(([k]) => k);

console.log(
  JSON.stringify(
    {
      pngCountBefore,
      pngCountAfter: pngs.length,
      delta: pngs.length - pngCountBefore,
      pngFiles: pngs.sort(),
      results,
      failed,
    },
    null,
    2,
  ),
);
