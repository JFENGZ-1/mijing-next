// 扫描原版管理端编译 JS，提取 API 调用路径
const fs = require('fs'), path = require('path');
const root = process.argv[2];
const files = [];
(function walk(d) {
  for (const f of fs.readdirSync(d)) {
    const p = path.join(d, f);
    const st = fs.statSync(p);
    if (st.isDirectory()) {
      if (f === 'node_modules' || f === 'uview-ui' || f === 'static') continue;
      walk(p);
    } else if (f.endsWith('.js')) files.push(p);
  }
})(root);
const hits = {};
for (const f of files) {
  const s = fs.readFileSync(f, 'utf8');
  const re = /\.(get|post|put|delete|upload)\(\s*["']([^"']+)["']/g;
  let m;
  while ((m = re.exec(s))) {
    const u = m[2];
    if (u.startsWith('/page') || u.startsWith('/pages')) continue;
    if (u.startsWith('http') && !u.includes('songguoyueke')) continue;
    if (/\.(png|jpg|svg|gif)/.test(u)) continue;
    const key = m[1].toUpperCase() + ' ' + u;
    (hits[key] = hits[key] || new Set()).add(path.relative(root, f).split(path.sep).join('/'));
  }
}
const keys = Object.keys(hits).sort();
console.log('API数:', keys.length);
keys.forEach(k => console.log(k + '   <- ' + [...hits[k]].slice(0, 3).join(', ')));
