/**
 * L5 DevTools live-login helpers — wx.login via UI button, not session inject.
 * Auth modes: live (D-L, default) | seed (D-S, ACCEPTANCE_AUTH_MODE=seed).
 */
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = 'D:/Users/Zhong/Desktop/微信小程序原项目';
const staffProject = `${root}/mijing-next/apps/staff-miniapp/dist/build/mp-weixin`;
const memberProject = `${root}/mijing-next/apps/member-miniapp/dist/build/mp-weixin`;
const outDir = `${root}/docs/generated`;
const asciiTempDir = 'C:/Users/Zhong/AppData/Local/Temp/mijing-acceptance-shots';
const wechatClient = process.env.CURSOR_WECHAT_CLIENT || process.env.WECHAT_IDE_CLIENT || 'cursor';
const env = {
  ...process.env,
  PATH: 'C:\\nvm4w\\nodejs;' + process.env.PATH,
  CURSOR_WECHAT_CLIENT: wechatClient,
  WECHAT_IDE_CLIENT: wechatClient,
};

const AUTH_MODE = (process.env.ACCEPTANCE_AUTH_MODE || 'live').toLowerCase();
const LOGIN_PAGE = 'pages/login/index';
const MEMBER_ONBOARDING = 'pages/onboarding/profile';
const AUTH_GATE_PATHS = {
  staff: [LOGIN_PAGE],
  member: [LOGIN_PAGE, MEMBER_ONBOARDING],
};

fs.mkdirSync(asciiTempDir, { recursive: true });
fs.mkdirSync(outDir, { recursive: true });

function run(tool, args) {
  return execSync(`wechatide -c ${wechatClient} -t ${tool} ${args}`, {
    env,
    encoding: 'utf8',
    maxBuffer: 10 * 1024 * 1024,
  });
}

function assertDevToolsReady() {
  try {
    const out = run('check_devtools_status', '--skill-version 0.2.5');
    if (!/openid/.test(out)) {
      console.error('DevTools not ready — run: wechatide auth -c', wechatClient);
      console.error(out.slice(0, 500));
      process.exit(1);
    }
    return out;
  } catch (e) {
    console.error('DevTools MCP unreachable — enable 服务端口 and run: wechatide auth -c', wechatClient);
    console.error(String(e.message || e).slice(0, 300));
    process.exit(1);
  }
}

function readFn(name) {
  return fs.readFileSync(path.join(__dirname, name), 'utf8').trim();
}

function pause(sec) {
  execSync(`powershell -Command "Start-Sleep -Seconds ${sec}"`, { stdio: 'ignore' });
}

function currentPath(project) {
  const out = run('automation_runtime_info', `--project "${project}" --action currentPage`);
  const m = out.match(/"path":\s*"([^"]+)"/);
  return m ? m[1] : '';
}

function isAuthGatePath(app, pathNow) {
  if (!pathNow) return true;
  return AUTH_GATE_PATHS[app].some((p) => pathNow === p || pathNow.startsWith(p));
}

function openProject(project) {
  run('open_project_window', `--project "${project}"`);
}

function clearCache(project) {
  console.log('debug_clear_cache cleanAll', project);
  run('debug_clear_cache', `--project "${project}" --action cleanAll`);
  pause(4);
}

function startAppSession(project, app) {
  openProject(project);
  clearCache(project);
  if (AUTH_MODE === 'seed') {
    return injectSessionSeed(project, app);
  }
  return performLiveLogin(project, app);
}

/** D-S: session inject workaround (wx.login + manual storage). */
function injectSessionSeed(project, app) {
  const fn = app === 'staff' ? readFn('staff-report-session.js') : readFn('member-polish-session.js');
  try {
    run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  } catch (e) {
    console.log('session inject retry after settle');
    pause(3);
    run('automation_evaluate', `--project "${project}" --fn-source ${JSON.stringify(fn)}`);
  }
  pause(2);
  return { mode: 'D-S', path: currentPath(project) };
}

function tapLoginButton(project) {
  run(
    'automation_element_action',
    `--project "${project}" --selector .login-page .u-button --action tap`,
  );
}

/** D-L: cleanAll → login page → tap 微信登录 → wait API → onboarding if needed. */
function performLiveLogin(project, app) {
  run('simulator_open_page', `--project "${project}" --page ${LOGIN_PAGE}`);
  pause(3);

  let pathNow = currentPath(project);
  console.log(`live-login start app=${app} path=${pathNow}`);
  if (pathNow !== LOGIN_PAGE) {
    run('simulator_open_page', `--project "${project}" --page ${LOGIN_PAGE}`);
    pause(3);
    pathNow = currentPath(project);
  }

  tapLoginButton(project);
  pause(6);

  pathNow = currentPath(project);
  console.log(`live-login post-tap path=${pathNow}`);

  if (app === 'member' && pathNow === MEMBER_ONBOARDING) {
    completeMemberOnboarding(project);
    pathNow = currentPath(project);
  }

  if (app === 'staff' && pathNow === LOGIN_PAGE) {
    console.log('staff live-login still on login — retry tap once');
    tapLoginButton(project);
    pause(6);
    pathNow = currentPath(project);
  }

  const ok = !isAuthGatePath(app, pathNow);
  return { mode: 'D-L', ok, path: pathNow };
}

function completeMemberOnboarding(project) {
  console.log('completing member onboarding');
  pause(2);

  try {
    run(
      'automation_element_action',
      `--project "${project}" --selector .native-input --action input --value 验收用户`,
    );
  } catch (e) {
    console.log('onboarding name input warn', String(e.message || e).slice(0, 120));
  }

  for (let i = 0; i < 5; i++) {
    try {
      run(
        'automation_element_action',
        `--project "${project}" --selector .consent-row --action tap`,
      );
      pause(0.3);
    } catch (_) {
      break;
    }
  }

  pause(1);
  run(
    'automation_element_action',
    `--project "${project}" --selector .onboarding-page .u-button --action tap`,
  );
  pause(6);

  const pathNow = currentPath(project);
  console.log(`onboarding complete path=${pathNow}`);
  return pathNow;
}

function gotoPage(project, app, page, query) {
  const relaunchUrl = query ? `${page}?${query}` : page;
  const pageArg = query ? `--page ${page} --query ${query}` : `--page ${page}`;

  for (let attempt = 1; attempt <= 3; attempt++) {
    if (AUTH_MODE === 'seed') {
      injectSessionSeed(project, app);
    }

    run('simulator_open_page', `--project "${project}" ${pageArg}`);
    pause(4);

    let pathNow = currentPath(project);
    console.log(`goto attempt ${attempt} want=${page} got=${pathNow}`);
    if (pathNow === page) return { ok: true, path: pathNow };

    if (isAuthGatePath(app, pathNow)) {
      console.log('auth gate — re-run live login');
      if (AUTH_MODE === 'live') {
        performLiveLogin(project, app);
      } else {
        injectSessionSeed(project, app);
      }
      run('automation_navigate', `--project "${project}" --action reLaunch --url ${relaunchUrl}`);
      pause(5);
      pathNow = currentPath(project);
      if (pathNow === page) return { ok: true, path: pathNow };
      continue;
    }

    run('automation_navigate', `--project "${project}" --action reLaunch --url ${relaunchUrl}`);
    pause(5);
    pathNow = currentPath(project);
    console.log(`reLaunch got=${pathNow}`);
    if (pathNow === page) return { ok: true, path: pathNow };
  }

  const finalPath = currentPath(project);
  return { ok: finalPath === page, path: finalPath };
}

function capture(project, file, opts = {}) {
  const { wait = 4, minSize = 10000, expectedPath } = opts;
  if (expectedPath) {
    const pathNow = currentPath(project);
    if (pathNow !== expectedPath) {
      console.log('SKIP screenshot — path mismatch', file, 'want', expectedPath, 'got', pathNow);
      return { ok: false, size: 0, path: pathNow, mode: AUTH_MODE === 'seed' ? 'D-S' : 'D-L' };
    }
  }

  const asciiTemp = `${asciiTempDir}/${file}`;
  const finalPath = `${outDir}/${file}`;
  try {
    run(
      'automation_viewport_action',
      `--project "${project}" --action screenshot --wait-seconds ${wait} --path "${asciiTemp}"`,
    );
  } catch (e) {
    console.log('screenshot error', file, String(e.message || e).slice(0, 200));
  }
  if (fs.existsSync(asciiTemp)) {
    const tempSize = fs.statSync(asciiTemp).size;
    if (tempSize > 0) {
      fs.copyFileSync(asciiTemp, finalPath);
    } else {
      console.log('skip 0-byte temp', file);
    }
  }
  const size = fs.existsSync(finalPath) ? fs.statSync(finalPath).size : 0;
  const ok = size >= minSize;
  console.log(file, ok ? 'OK' : 'FAIL', size, `(min ${minSize})`, AUTH_MODE === 'seed' ? 'D-S' : 'D-L');
  return { ok, size, path: finalPath, mode: AUTH_MODE === 'seed' ? 'D-S' : 'D-L' };
}

function shot(project, app, page, file, opts = {}) {
  const { query, wait = 4, minSize } = opts;
  const nav = gotoPage(project, app, page, query);
  if (!nav.ok) {
    console.log('SKIP screenshot — path invalid', file, 'got', nav.path);
    return { ok: false, path: nav.path, size: 0, mode: AUTH_MODE === 'seed' ? 'D-S' : 'D-L' };
  }
  return capture(project, file, { wait, minSize, expectedPath: page });
}

module.exports = {
  root,
  staffProject,
  memberProject,
  outDir,
  asciiTempDir,
  wechatClient,
  env,
  AUTH_MODE,
  run,
  pause,
  currentPath,
  openProject,
  clearCache,
  startAppSession,
  performLiveLogin,
  completeMemberOnboarding,
  injectSessionSeed,
  gotoPage,
  capture,
  shot,
  isAuthGatePath,
  assertDevToolsReady,
};
