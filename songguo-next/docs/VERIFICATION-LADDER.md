# 验证阶梯 SOP（Agent 一页手册）

更新时间：2026-07-11（审计合成后修订）

**用途**：任何 Agent 在声称「完成」「已接线」「可上线」之前，必须按本阶梯逐级爬升并留下可审计证据。`planned:*`、仅有后端、仅有 typecheck、仅有 build 均不构成完成。

**L5 状态（2026-07-11 plan close-out）：** `docs/generated/` 含 **46 PNG**；`capture-acceptance-shots-cache-clear.js` 17 条必需流程全部 OK（含 `staff-booking-policy.png`、`staff-course-catalog.png`）。截图前必须 `debug_clear_cache cleanAll`。

---

## 七条公理（不可协商）

1. **证据先于实现** — `会员端/`、`管理端/` 只读；无追溯处置 + 调用点证据，不得开工。
2. **能力定义** — 每条能力 = `operationId` + 权限 +（资金/权益类）不可变流水规则 + 验收用例 ID。
3. **完成定义** — 见下表 L0–L6；缺任一级不得标 PASS。
4. **禁止虚假进度** — `planned:*` ≠ 已实现；PARTIAL ≠ 可交付；backend-only ≠ 产品完成。
5. **门禁分离** — 考古门禁 / 实现门禁 / 生产门禁不得混用。
6. **不可变账本** — 余额/次数/积分变更必须 ledger-backed；拒绝物理删除（见 REJECT 清单）。
7. **租户/场馆隔离** — 每条 staff/member API 必须可证明 site/tenant 作用域正确。

---

## 阶梯定义

| 级别 | 名称 | 通过标准 | 证据产物 |
|:---:|---|---|---|
| **L0** | 考古处置 | `traceability-*.csv` 行有 Disposition、ReviewNote、调用点；`UNREVIEWED=0` | CSV 行 + `api-usages.csv` 引用 |
| **L1** | OpenAPI 契约 | `openapi.yaml` 含 `operationId`、权限、请求/响应 schema；路由已注册 | `operationId` + `routes/api.php` 行号 |
| **L2** | 后端验证 | PHPUnit Feature 覆盖 happy path + 租户隔离 +（写操作）幂等/ledger | 测试类名 + 用例名；`php artisan test` 全绿 |
| **L3** | API 客户端 | `member-miniapp` / `staff-miniapp` `src/api/*.ts` 导出函数，签名与 OpenAPI 一致 | 函数名 ↔ `operationId` 对照 |
| **L4** | UI 接线 | `pages.json` 注册路由；页面 import L3 函数；权限门控与后端一致 | 页面路径 + 调用的 client 函数列表 |
| **L5** | 运行时证据 | 微信 DevTools：编译产物打开、**清缓存**、**真实登录**（D-L）、seed 数据、路径断言、截图 | `docs/generated/<flow>.png` 或 `devtools-acceptance-notes.md` 条目 |

### L5 前置条件（必须）

在 `pnpm build:member|staff` 之后、打开目标页截图之前，**必须**对每个小程序 dist 执行缓存清理，否则模拟器会渲染空白页：

```bash
wechatide -c cursor -t debug_clear_cache --project <apps/*/dist/build/mp-weixin> --action cleanAll
```

**认证优先级（D-L > D-S）：**

1. **D-L（live login，默认）** — `cleanAll` 后落在 `pages/login/index` 是正确行为；点击「微信登录」→ `wx.login` → `POST /auth/wechat/login` →（会员）完成 onboarding → 导航目标页 → **路径断言** → 截图。
2. **D-S（seed/inject，仅兜底）** — `member-polish-session.js` / `staff-report-session.js` 经 `automation_evaluate` 注入；仅在 `ACCEPTANCE_AUTH_MODE=seed` 或 live 登录阻塞时使用，须在 notes 中标 **D-S**。

完整 SOP：`docs/generated/devtools-acceptance-notes.md`；共享库：`docs/generated/devtools-live-login-lib.js`；canonical runner：`docs/generated/capture-acceptance-shots-cache-clear.js`；样例：`docs/generated/capture-live-login-sample.js`。
| **L6** | 矩阵 PASS | `stage-acceptance-matrix.md` 对应行 PASS，Evidence 列含 L2–L5 链接 | 矩阵行 + 证据路径 |

**晋升规则**：只能逐级晋升；跳级标注视为虚假进度。REJECT 能力永不晋升，仅登记理由。

---

## Agent 执行清单（每条能力）

```text
[ ] L0  在 traceability-apis.csv / traceability-pages.csv 定位 LegacyId
[ ] L0  读 api-usages.csv 调用点；旧目录只读
[ ] L1  openapi.yaml 新增/核对 operationId + permission
[ ] L1  routes/api.php + Controller 注册
[ ] L2  tests/Feature/*Test.php 新增用例（含 cross-tenant 404）
[ ] L2  php artisan test — 全量或 --filter 通过
[ ] L3  apps/*/src/api/*.ts 导出函数
[ ] L4  pages.json + .vue 接线
[ ] L4  pnpm typecheck + build:member|staff 通过
[ ] L5  `debug_clear_cache cleanAll` → D-L 真实登录（或注明 D-S 兜底）→ 路径断言 → DevTools 截图 + acceptance-notes 登记
[ ] L6  更新 stage-acceptance-matrix.md Evidence 列
[ ] L6  traceability AcceptanceCase：planned:* → 具体 operationId
```

---

## 禁止事项

- 运行 `tools/build-traceability.ps1`（会清空人工分类）
- 修改 `会员端/`、`管理端/`
- 将 `Disposition=PASS` 与 `AcceptanceCase=planned:*` 同时当作完成
- 用「433 tests passed」代替单能力 L2 证据
- 在矩阵写 PASS 而无 L5 截图/E2E（产品面向行）
- 输出 AppSecret、真实手机号、微信 code/token

---

## 证据存放约定

| 类型 | 路径 |
|---|---|
| 追溯主账 | `docs/traceability-pages.csv`, `docs/traceability-apis.csv` |
| OpenAPI | `songguo-next/docs/openapi.yaml` |
| 验收矩阵 | `songguo-next/docs/stage-acceptance-matrix.md` |
| DevTools 批注 | `docs/generated/devtools-acceptance-notes.md` |
| 截图 | `docs/generated/<feature>-<screen>.png` |
| Seed / 会话脚本 | `docs/generated/seed-*.php`, `docs/generated/*-session.js` |

---

## 快速判定话术

| 声称 | 实际阶梯 | Agent 回应 |
|---|---|---|
| 「考古完成」 | L0 | 仅说明处置完成；不等于 L1+ |
| 「后端 COMPLETE」 | L1–L2 | 要求列出 operationId + 测试类 |
| 「主要流程已接线」 | L3–L4 部分 | 要求页面路径 + client 函数对照表 |
| 「DevTools 已验」 | L5 | 要求 PNG 路径或 notes 条目；须确认 `debug_clear_cache cleanAll` 已执行 |
| 「可上线」 | L6 + 生产门禁 | 矩阵 PASS + SMS/支付/队列非 scaffold |

---

## 单能力完成声明模板

```markdown
### <LegacyId> → <operationId>
- L0: <Disposition> — <ReviewNote 首句>
- L1: openapi.yaml L<line> — permission `<perm>`
- L2: `<TestClass>::<method>` — `php artisan test --filter=...`
- L3: `<api/file>.ts` → `<functionName>`
- L4: `<pages/.../index.vue>` — build:member|staff PASS
- L5: `docs/generated/<file>.png` 或 E2E: `<script>`
- L6: matrix row `<Gate name>` → PASS（或 PARTIAL + 阻塞项）
```

---

## 相关文档

- 考古交接：`docs/ARCHAEOLOGY-HANDOFF.md`
- 实现交接：`songguo-next/docs/AI-HANDOFF.md`
- 对抗式执行计划：`songguo-next/docs/ADVERSARIAL-EXECUTION-PLAN.md`
- 审计一页摘要：`songguo-next/docs/AUDIT-SYNTHESIS.md`
