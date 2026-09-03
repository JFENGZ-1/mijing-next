# Adversarial Execution Plan（第一性原理版）

**合成时间：** 2026-07-11（Asia/Shanghai）  
**证据来源：** 文档审计 `93934698`、实现审计 `1d6de29b`、首原理重写 `a472b7ef`、初版计划 `fd5ec098`、本工作区 grep/PHPUnit 直验。  
**规则：** 不修改 `会员端/`、`管理端/`；不运行 `build-traceability.ps1`；不 git commit。

---

## §0 第一性原理宪章（项目宪法）

以下七条公理不可协商；任何「完成」「已接线」「可上线」声明必须逐条对照。

| # | 公理 | 含义 |
|---|---|---|
| 1 | **证据先于实现** | `会员端/`、`管理端/` 只读考古；无追溯处置 + 调用点证据，不得开工 |
| 2 | **能力定义** | 每条能力 = `operationId` + 权限 +（资金/权益类）不可变流水规则 + 验收用例 ID |
| 3 | **完成定义** | 见 L0–L6 阶梯（`VERIFICATION-LADDER.md`）；`planned:*`、仅有后端、仅有 typecheck 均不构成完成 |
| 4 | **禁止虚假进度** | `planned:*` ≠ 已实现；PARTIAL ≠ 可交付；backend-only ≠ 产品完成 |
| 5 | **门禁分离** | 考古门禁（处置）≠ 实现门禁（验证交付）≠ 生产门禁（SMS/支付/队列） |
| 6 | **不可变账本** | 余额/次数/积分变更必须 ledger-backed；拒绝物理删除（见 REJECT 清单） |
| 7 | **租户/场馆隔离** | 每条 staff/member API 必须可证明 site/tenant 作用域正确 |

**SOP 一页手册：** [`VERIFICATION-LADDER.md`](./VERIFICATION-LADDER.md)

---

## §1 真相表（当前现实）

### 1.1 各门禁诚实进度

| 门禁 | 度量 | 诚实读数 | 说明 |
|---|---:|---|---|
| **考古处置** | 185 页 / 313 API，`UNREVIEWED=0` | **100%** | 分类完成 ≠ 交付完成 |
| **追溯映射** | API `planned:*` 114；页 `planned:*` 31 | **64% / 83%** 已映射到具体 op/路由 | 145 行仍阻塞 L6 |
| **验收矩阵** | 13 PASS / 7 PARTIAL / 1 DEFERRED（21 行） | **62% PASS** | 多个 PASS 缺 L5 证据 |
| **后端验证** | 433 PHPUnit 方法（`php artisan test --list-tests`） | **强**（已接线域） | 不证明 UI；不覆盖 114 未映射 API |
| **前端验证** | member 19 + staff 49 `.vue` 页；~135 typed client 导出 vs 234 `operationId` | **~58% L3** | CRM 等大量 inline `useApiClient()` |
| **运行时验证** | `docs/generated/**/*.png` | **0 文件** | **全部 L5 声明悬空** |
| **生产就绪** | 微信支付 stub、SMS=log、无 token 撤销 | **未就绪** | 与 demo 路径混淆 |

### 1.2 域完成度（混合功能，非考古 %）

| 域 | 考古 | 追溯 concrete | UI/路由 | 后端/OpenAPI | **混合诚实 %** |
|---|---:|---:|---:|---:|---:|
| **会员端** | 35/35 页, 50/50 API | 9 页 + 20 API `planned:*` | 19 Vue / 35 遗留 | 核心预约/卡/资料已接线 | **~68%** |
| **员工端** | 150/150 页, 263/263 API | 22 页 + 94 API `planned:*` | 49 Vue；**无**课表编辑器、课程目录 hub | CRM/报表/卡品/履约 largely routed | **~72%** |
| **后端** | 313/313 API, 10 REJECT | 114 行含 `planned:*` | N/A | 184 path keys, 234 `operationId` | **~78%** 路由存在 / **~64%** 追溯闭环 |

### 1.3 复合诚实进度

**~58% 朝向遗留功能对等**（加权：考古 15%、后端 30%、前端 35%、运行时验证 10%、生产硬化 10%）。

**确实扎实：** 身份/租户、会员预约核心、员工 CRM 核心、日课表 + 场次履约、会员钱包 + demo 购卡、员工卡品 CRUD、报表 hub + 财务 + 导出任务、433 后端测试。

**确实夸大：** `AI-HANDOFF`「重建主体完成」、P1–P4「Shipped」未区分 demo vs 生产路径、矩阵 PASS 行引用**不存在的 PNG**。

### 1.4 关键指标快照（2026-07-11 直验）

| 指标 | 值 | 备注 |
|---|---:|---|
| `traceability-apis.csv` `planned:*` | **114** | SESSION-PROGRESS 写 ~170 → **过时 49%** |
| `traceability-pages.csv` `planned:*` | **31** | |
| `docs/generated/**/*.png` | **0** | 矩阵/notes 引用 33+ 文件名 → **孤儿引用** |
| `docs/generated/` 其他产物 | 87 文件 | JS/PHP/JSON 脚本与证据在；PNG 缺失 |
| PHPUnit 方法 | **433** | Stage 09 写 415 → AI-HANDOFF 418 → 矩阵 433 |
| OpenAPI `operationId` | **234** | 矩阵写「25 path keys / 49 schemas」→ **严重低估** |
| 矩阵 PASS / PARTIAL / DEFERRED | 13 / 7 / 1 | |

### 1.5 已调和的矛盾状态

| 矛盾 | 裁定 |
|---|---|
| **Staff session fulfillment：矩阵 PASS vs SESSION-PROGRESS PARTIAL** | **L4 达标（代码+测试）**；`scheduling.ts` 6 函数齐全且 `session-detail.vue` 已接线；**L5 未达标（无 PNG）** → 矩阵应标 **PARTIAL（阻塞：L5）** 或 Evidence 降为 D-S/T-only |
| **测试计数 415→418→433** | 合法演进：Stage 09 快照 415；P4 会话 +18；当前 **433 为权威值**；各 doc 应统一引用 433 + 日期 |
| **OpenAPI 规模** | 权威：**184 paths, 234 operations, ~470 schema entries** |
| **DevTools「33 PNG」** | **证伪**：glob `docs/generated/**/*.png` 与 `mijing-next/docs/generated/**/*.png` 均为 **0** |

---

## §2 对抗式差距分析（违反公理之处）

### 2.1 Top 10 文档谎言 / 过度声明

| # | 声称 | 现实 |
|---|---|---|
| 1 | 考古 + 追溯门禁 **PASS** = 无遗漏 | **145** 行仍 `planned:*`；矩阵承认这不等于实现 |
| 2 | `AI-HANDOFF`：「重建主体完成」 | **6 PARTIAL** + 课表编辑器/订单/转卡等重大 UI 空洞 |
| 3 | OpenAPI 门禁：**25 paths / 49 schemas** | **184 paths, 234 ops, ~470 schemas** |
| 4 | DevTools 运行时验证 **PASS** | **0 PNG**；证据仅为 markdown/JSON |
| 5 | 会员购卡成功截图 | notes 承认 **PHP seed + `showModal` 注入**，非真实确认流 |
| 6 | Stage 06 批量课表 **COMPLETE** | unsuspend、batch course-change、display metadata 仍 `planned:schedule.*` |
| 7 | Staff session fulfillment **PASS** vs SESSION **PARTIAL** | 同会话状态不一致；**L5 缺失** |
| 8 | Stage 08「UI remains」 | CRM 页面已存在（49 staff 页）；文档过时 |
| 9 | `ARCHAEOLOGY-HANDOFF` 会员 QR **REJECT** | 追溯 **KEEP** `member.official-account-follow`；路径/package 记错 |
| 10 | P5 对齐 23 API | 仍 **114** `planned:*`；进度叙事膨胀 |

### 2.2 实现审计关键发现

| 声称 | 裁定 | 证据 |
|---|---|---|
| Session fulfillment 6 API | **L3–L4 PASS** | `scheduling.ts` + `session-detail.vue`；命名漂移 `rescheduleStaffAppointment` |
| Member purchase E2E | **L2–L4 PASS / L5 未验** | `catalog.vue` + `MemberCardPurchaseTest`；无 PNG |
| Card products recycle bin | **PASS** | `catalogStatus=archived` 参数 + UI tab |
| `ProcessExportJob` | **L2 PASS** | `AsyncQueueDispatchTest`；UI 轮询需 seed（sync queue） |
| OpenAPI ↔ routes | **无 CI 契约测试** | 20/20 抽样有路由+测试；234 ops 整体未自动化比对 |
| API client 覆盖 | **~85–100 ops 无 typed wrapper** | CRM 全 inline；member 单文件 monolith |

### 2.3 `planned:*` API 三分法（114 行）

| 类别 | 约数 | 示例 |
|---|---:|---|
| **(a) 后端有、UI 无/薄** | 35–40 | `getStaffBookingPolicy`；batch schedule；course CRUD；points adjust |
| **(b) 后端 UI 皆无** | 60–65 | `schedule.batch.unsuspend`；`card-transfer.*`；`platform.subscription.orders`；消费排名报表 |
| **(c) 误标 `planned:*`（应晋升 L1+）** | 15–20 | recycle-bin → `listStaffCardProducts?catalogStatus=archived`；sort → `patchStaffCardProduct` |

### 2.4 DevTools 证据空洞

`docs/generated/` 有 87 个 JS/PHP/JSON 脚本，但 **零 PNG**。矩阵与 `devtools-acceptance-notes.md` 引用的文件名（如 `staff-session-fulfillment.png`、`member-card-catalog.png`、`stage04-seed-*.png`）**均不在仓库**。

---

## §3 验证阶梯（核心方法论）

完整 SOP：[`VERIFICATION-LADDER.md`](./VERIFICATION-LADDER.md)

| 级别 | 名称 | 通过标准 |
|:---:|---|---|
| **L0** | 考古处置 | CSV 行有 Disposition、ReviewNote、调用点 |
| **L1** | OpenAPI 契约 | `operationId` + 路由注册 |
| **L2** | 后端验证 | PHPUnit happy path + 租户隔离 |
| **L3** | API 客户端 | `src/api/*.ts` 导出函数 ↔ OpenAPI |
| **L4** | UI 接线 | `pages.json` + 页面 import L3 |
| **L5** | 运行时证据 | `docs/generated/<flow>.png` 或登记 E2E 脚本 |
| **L6** | 矩阵 PASS | Evidence 列含 L2–L5 链接 |

### 3.1 各域能力所处阶梯（估算）

| 域 | L0 | L1 | L2 | L3 | L4 | L5 | L6 |
|---|---:|---:|---:|---:|---:|---:|---:|
| **会员** | 50/50 | ~40/50 | ~35/50 | ~25/50 | ~19/35 页 | **0/35** | ~8/12 矩阵行 |
| **员工** | 263/263 | ~200/263 | ~180/263 | ~120/263 | ~128/150 页映射 | **0/49** | ~10/15 矩阵行 |
| **后端整体** | 313/313 | ~245/313 | ~220/313 | N/A | N/A | N/A | 13/21 行 PASS* |

\*多个 L6 PASS 行实际 **L5=0** → 应降级或标 PARTIAL。

**瓶颈排序：** L5（全局 0%）> L3 client 缺口 > 追溯 `planned:*` > L2 测试引用不同步 > 生产门禁。

---

## §4 分阶段计划（按阶梯依赖排序）

每阶段出口 = 验证门禁通过；不得跳级标 PASS。

---

### Phase A — 证据卫生（**立即优先**）

**触发条件：** PNG=0 且矩阵/notes 引用过时文件名 — **已满足，立即执行**。

**目标：** 停止虚假 L5/L6 声明；统一计数；修复孤儿证据引用。

**任务：**
1. Glob 证实：**0 PNG**；在矩阵 Evidence 列批量标注 `L5-MISSING` 或降级 PASS→PARTIAL。
2. 调和 SESSION-PROGRESS vs 矩阵：session fulfillment → **PARTIAL（L5 阻塞）** 直至截图入库。
3. 统一权威计数：114 API / 31 page `planned:*`；433 tests；234 ops；0 PNG。
4. 修正 OpenAPI 规模表述（184/234/~470）。
5. 测试计数时间线脚注：415（Stage 09）→ 418（P4 中）→ **433（当前）**。
6. 为每条矩阵 PASS 行核对 L2–L5 证据链；无 PNG 的产品面向行不得保持 PASS。

**验证门禁：**
- 无文档引用不存在的 PNG 路径而不标注 MISSING。
- `SESSION-PROGRESS.md` 与 grep 计数一致。
- Evidence class 图例入矩阵：T / D-L / D-S / X（见 §5 旧版保留）。

**出口标准：** 用户读一页即可信任百分比；L5 缺口可见且可排期。

---

### Phase B — 追溯硬化 + 后端契约

**入口：** Phase A 完成。

**目标：** 削减 `planned:*`；补齐 L1–L2 缺口；启动 OpenAPI↔routes CI。

**任务：**
1. 晋升类别 **(c)** 误标 `planned:*`（~15–20 行）→ 具体 `operationId`。
2. 高流量域优先：**booking schedule metadata**、**order/payment**、**identity stubs**。
3. 禁止 boilerplate ReviewNote（9 个会员页、8 个 API 模板句）。
4. OpenAPI↔`api.php` spot-check 脚本（20 ops）→ CI skeleton。
5. 补齐测试洞：`createMemberTag`、`transferSiteOwnership`、`MeController` 隔离套件。

**验证门禁：** −30 API `planned:*`；−10 page `planned:*`；脚本在 CI 可运行。

**出口标准：** `planned:*` < 85 API；追溯行与 OpenAPI 无已知 typos。

---

### Phase C — 前端 client/UI 缺口（已映射能力）

**入口：** Phase B 对目标域 L1–L2 就绪。

**目标：** 已后端验证的能力爬到 L4。

**任务：**
1. Staff CRM：从 inline `useApiClient()` 提取 typed `crm.ts` 函数（12+ ops）。
2. Member：规划 `member.ts` 拆分（行为不变）。
3. 最大 UI 洞：**课表编辑器**（6 页）、**课程目录 hub**（3 页）、**预约策略配置**。
4. 会员：订单列表/结果、转卡领取、legal viewer。
5. `pnpm typecheck` + `build:member|staff` 门禁。

**验证门禁：** 目标域 L3 覆盖 >80%；新页面在 `pages.json`；typecheck PASS。

**出口标准：** P1–P4 已交付域 **零** `planned:*` acceptance 行。

---

### Phase D — 运行时证据批次（DevTools SOP）

**入口：** Phase C 对目标流程 L4 就绪。

**目标：** 批量产出 L5 PNG；区分 D-L vs D-S。

**任务：**
1. 复用 `docs/generated/*-session.js`；输出至 `docs/generated/<flow>.png`。
2. **最低集（15 PNG）：** 会员 home/booking/catalog/mine×3；员工 dashboard/fulfillment/CRM×3/report×3/card-products×2。
3. 会员购卡：**真实点击路径** 或明确标 D-S（seed+modal）。
4. 导出轮询：queue worker 运行下重拍，非 DB seed hack。
5. 更新 `devtools-acceptance-notes.md` 每条对应磁盘文件。

**验证门禁：** ≥15 PNG 入库；矩阵 Evidence 可点击验证。

**出口标准：** 所有产品面向矩阵行 L5 ≠ MISSING。

---

### Phase E — 矩阵 PASS 晋升（证据包）

**入口：** Phase D 对应该行流程。

**目标：** 诚实 L6；消灭无证据 PASS。

**任务：**
1. 每行 PASS = L0–L6 证据包（模板见 `VERIFICATION-LADDER.md`）。
2. PARTIAL 行写明「阻塞 L 级」与 owner。
3. 升级/降级 session fulfillment、member purchase、export polling 等争议行。

**验证门禁：** 21 矩阵行均有 L 级标注；PASS 行 100% 有 L5 或签署 D-S。

---

### Phase F — 生产门禁（正交）

**入口：** 核心功能 L6 或签署 deferral。

**任务：** 微信支付 V3 + webhook；真实 SMS；queue worker；token 撤销；设备会话（DEFERRED 延期或实现）。

**验证门禁：** `MemberCardWechatPaymentTest` 非 stub 路径；export 异步无 seed；安全行非 PARTIAL 无日期。

---

### Phase G — REJECT/DEFER 显式登记

**任务：** 维持 10 API + 3 page REJECT 理由；DEFERRED（device sessions）有日期与 owner；card-transfer 三页 — ship 或用户签署 DEFER。

---

## §5 两周冲刺（按 L 级缺口排序）

| # | 项 | 阻塞 L 级 | 完成标准 |
|---|---|---|---|
| 1 | **Phase A 证据卫生** | L5/L6 | 0 孤儿 PNG 引用；计数统一 |
| 2 | 复拍 15 张最低集 PNG | L5 | 文件入库 `docs/generated/` |
| 3 | 调和 session fulfillment 状态 | L6 | 矩阵与 SESSION 一致 |
| 4 | 晋升 15–20 误标 `planned:*` | L0→L1 | grep 验证 |
| 5 | CRM typed client 提取 | L3 | `crm.ts` 覆盖 12 ops |
| 6 | 关闭 P1–P4 遗留 `planned:*`（~20 行） | L0 | CSV 更新 |
| 7 | OpenAPI↔routes CI skeleton | L1 | 20 op 自动化 |
| 8 | 会员购卡：真实点击或标 D-S | L5 | notes + 矩阵诚实 |
| 9 | Staff CRM 三截图 | L5 | list/detail/issue-card |
| 10 | 课表编辑器范围决策文档 | L4 | MVP vs 全量 parity |
| 11 | Queue worker runbook + export 重拍 | L5/F | D-L export PNG |
| 12 | 统一测试计数文档为 433 | 文档 | 各 stage doc 脚注 |
| 13 | `weekly: php artisan test` + typecheck | L2 | CI log |
| 14 | PARTIAL 行阻塞项清单 | L6 | 无模糊 PARTIAL |

---

## §6 并行工作流

```
流 A（文档/证据）  ── Phase A → 持续矩阵卫生
流 B（追溯/契约）  ── Phase B ∥ OpenAPI CI
流 C（Staff UI）   ── Phase C 课表/目录 ∥ Phase C 报表子页
流 D（Member/卡）  ── Phase C 订单/转卡 ∥ Phase F 支付
流 E（运行时）     ── Phase D；依赖 C 的 L4 产出
流 F（生产/安全）  ── Phase F；支付稳定后启动
```

| 配对 | 理由 |
|---|---|
| **A + E（文档+运行时）** | 先止谎再拍照；否则重复过度声明 |
| **B + C** | 追溯晋升与 UI 可并行不同域 |
| **D 依赖 C L4** | 无接线勿拍空壳截图 |
| **反模式** | 跳过 Phase A 直接标 Shipped |

---

## §7 风险登记

| ID | 风险 | 概率 | 影响 | 缓解 |
|---|---|---|---|---|
| R1 | L5=0 却 L6 PASS → 信任崩塌 | **已发生** | 极高 | **Phase A 立即** |
| R2 | 微信支付/ webhook 阻塞营收 | 高 | 高 | Phase F spike；保留 demo |
| R3 | 课表编辑器范围膨胀 | 中 | 高 | MVP 先行；文档签署 |
| R4 | 手动 CSV 编辑 typo | 中 | 中 | OpenAPI CI（Phase B） |
| R5 | DevTools  flaky | 中 | 中 | notes 记录 quirk；D-S 合法标注 |
| R6 | CRM inline API 漂移 | 高 | 中 | Phase C typed client |
| R7 | sync queue 掩盖导出 UX | 高 | 中 | Phase F worker |
| R8 | token 撤销缺口 | 中 | 高 | Phase F 前多租户 |
| R9 | 链式报表聚合错误 | 低 | 高 | 测试 + drill-down 截图 |
| R10 | 误改遗留目录 | 低 | 高 | Agent 提示只读 |

---

## 附录 A — 证据类（矩阵用）

| 类 | 含义 |
|---|---|
| **T** | PHPUnit only |
| **D-L** | DevTools 真实交互 |
| **D-S** | DevTools + DB seed/fixture（数据视图合法） |
| **X** | 不足；产品面向行阻塞 PASS |

## 附录 B — 审计来源索引

| ID | 类型 | 状态 |
|---|---|---|
| `fd5ec098` | 初版计划 (~58%) | 已并入 |
| `93934698` | 文档审计 | 已并入 §1–2 |
| `1d6de29b` | 实现审计 | 已并入 §2.2–2.3 |
| `a472b7ef` | 首原理结构 | 本文 §0–§4 骨架 |
| 本任务 PNG glob | **0 PNG** | 证伪原「33 PNG」|

## 附录 C — 相关文档

- [`VERIFICATION-LADDER.md`](./VERIFICATION-LADDER.md) — Agent SOP
- [`AUDIT-SYNTHESIS.md`](./AUDIT-SYNTHESIS.md) — 用户一页摘要（中文）
- [`stage-acceptance-matrix.md`](./stage-acceptance-matrix.md)
- [`SESSION-PROGRESS.md`](./SESSION-PROGRESS.md)
- `docs/generated/devtools-acceptance-notes.md`
