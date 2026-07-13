# 审计合成摘要（一页）

**日期：** 2026-07-11  
**范围：** 文档审计 + 实现审计 + PNG 证据直验 + 首原理计划合并  
**权威计划：** [`ADVERSARIAL-EXECUTION-PLAN.md`](./ADVERSARIAL-EXECUTION-PLAN.md)  
**Agent SOP：** [`VERIFICATION-LADDER.md`](./VERIFICATION-LADDER.md)

---

## 一句话结论

**考古分类 100% 完成，但产品交付约 58% 诚实进度；最致命缺口是运行时证据：仓库内 PNG 截图为 0，却有多处矩阵 PASS 引用不存在的截图文件。**

---

## PNG 验证结果（本任务直验）

| 路径 | PNG 数量 |
|---|---:|
| `docs/generated/**/*.png` | **0** |
| `songguo-next/docs/generated/**/*.png` | **0** |

- 文档审计声称 0 → **确认**。
- 初版计划与矩阵声称 33+ 张 → **证伪**。
- `docs/generated/` 另有 **87** 个 JS/PHP/JSON 脚本（可复用重拍），但**无一张截图入库**。

---

## 关键数字（grep / PHPUnit 直验）

| 指标 | 值 |
|---|---:|
| 遗留 API 仍 `planned:*` | **114**（非 SESSION 写的 ~170） |
| 遗留页仍 `planned:*` | **31** |
| PHPUnit 测试方法 | **433**（415→418→433 为正常演进） |
| OpenAPI `operationId` | **234**（非矩阵写的 25/49） |
| 矩阵 PASS / PARTIAL / DEFERRED | 13 / 7 / 1 |
| 域混合完成度 | 会员 **~68%** · 员工 **~72%** · 后端 **~64–78%** |

---

## 十大过度声明（精选）

1. 「重建主体完成」—— 仍有 6 个 PARTIAL 产品门禁 + 课表编辑器等大洞。  
2. 考古 PASS = 产品完成 —— **145** 行 `planned:*` 未闭环。  
3. DevTools 验证 PASS —— **0 PNG**。  
4. OpenAPI「25 paths」—— 实际 **184 paths / 234 ops**。  
5. 会员购卡成功 —— seed + `showModal` 注入，非真实 UX。  
6. Session fulfillment 矩阵 PASS vs SESSION PARTIAL —— **L4 有、L5 无**。  
7. Stage 06 批量课表 COMPLETE —— unsuspend 等仍 `planned:*`。  
8. P1–P4「Shipped」—— 多个矩阵行仍 PARTIAL。  
9. `~170 planned API` —— 实际 **114**。  
10. 433 tests = 全栈覆盖 —— 无前端 E2E；大量域无 client。

---

## 已核实为真的声称

| 项 | 结论 |
|---|---|
| Staff session fulfillment 6 API | `scheduling.ts` 齐全，`session-detail.vue` 已接线 |
| 卡品回收站 `catalogStatus=archived` | API 参数 + UI tab 存在 |
| `ProcessExportJob` 派发 | `AsyncQueueDispatchTest` 通过 |
| Member purchase 代码路径 | `catalog.vue` + `MemberCardPurchaseTest` |
| 后端 20/20 抽样 operationId | 均有路由 + Feature test |

---

## 最大风险（按优先级）

1. **L5 全局为零** —— 所有「已验」运行时声明不可审计。  
2. **L6 与 L5 脱节** —— 多个 PASS 行应降级或标 PARTIAL。  
3. **课表编辑器 + 课程目录 UI 缺失** —— 员工端最大功能洞（7+3 页）。  
4. **~85 OpenAPI ops 无 typed client** —— CRM 全靠 inline 字符串。  
5. **生产路径未就绪** —— 微信支付、SMS、token 撤销、异步队列。

---

## 立即行动 Top 5（来自合并计划 Phase A + 两周冲刺）

1. **Phase A 证据卫生** —— 修正所有引用不存在 PNG 的 PASS 行；统一 114/31/433/0 计数。  
2. **复拍最低 15 张 PNG** —— 用现有 `docs/generated/*-session.js` 脚本产出入库。  
3. **调和 session fulfillment** —— 矩阵与 SESSION-PROGRESS 一致标 PARTIAL（L5 阻塞）直至截图。  
4. **晋升 15–20 条误标 `planned:*`** —— recycle-bin、sort、ledger 等已有后端能力。  
5. **OpenAPI↔routes CI 骨架** —— 防 234 ops 与 `api.php` 静默漂移。

---

## 门禁分离（给用户的心智模型）

```
考古 100%  →  「我们知道旧系统有什么」
实现 ~58%  →  「新系统证实交付了多少」
生产 未就绪 → 「能否收真钱、发真短信、跑真队列」
```

**不要混读：** 追溯 CSV 的 `Disposition=PASS` 只代表 L0；`AcceptanceCase=planned:*` 代表**未达 L1+**。

---

## 文档索引

| 文件 | 用途 |
|---|---|
| `ADVERSARIAL-EXECUTION-PLAN.md` | 完整分阶段执行计划（§0–§7） |
| `VERIFICATION-LADDER.md` | L0–L6 Agent 操作手册 |
| 本文 `AUDIT-SYNTHESIS.md` | 管理层一页摘要 |

*未修改 `会员端/`、`管理端/`；未 git commit。*
