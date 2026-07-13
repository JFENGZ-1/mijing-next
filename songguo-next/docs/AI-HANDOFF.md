# AI 任务交接检查点

> **2026-07-13：** 首选阅读 [`HANDOFF-2026-07-13.md`](./HANDOFF-2026-07-13.md)（含最新直验数字、L5 诚实度、文件地图与对抗式审查）。本文保留 2026-07-11 基线。

更新时间：2026-07-11（Asia/Shanghai）

## 1. 最终目标

以旧微信小程序为考古参考，重建可独立上线的健身场馆预约 SaaS。旧后端和旧数据不可恢复，不追求兼容旧数据库。

- 前端：uni-app + Vue 3 + TypeScript + Pinia + uView Plus（组件约定见 `docs/UI-UVIEW-PLUS.md`）
- 后端：PHP 8.2 + Laravel 11 + MySQL 8.x
- API 基址：`http://127.0.0.1:8010/api/v1`
- 新系统目录：`songguo-next/`
- 工作原则：第一性原理、对抗式审查、多 Agent、全量追溯、不静默猜测旧业务

安全要求：不要在回复、日志或文档中输出 AppSecret、微信一次性 code、access token、完整测试手机号。

旧系统考古的独立交接文件：`../docs/ARCHAEOLOGY-HANDOFF.md`。下一模型必须同时读取，不能只继续新系统代码而跳过旧能力追溯门禁。

## 2. 项目状态（2026-07-11）

**重建主体完成**：考古 0/0；后端 Stage 05–10 + Stage 09 **BACKEND COMPLETE**；会员端与员工端主要业务流程已对接。

| 域 | 状态 |
|---|---|
| 后端 Stage 05–10 | BACKEND COMPLETE |
| 后端 Stage 09 报表/薪资/平台 | **BACKEND COMPLETE** |
| 会员端 `member-miniapp` | 主要流程已接线（预约、会员卡、资料、签到等） |
| 员工端 `staff-miniapp` | 主要流程已接线（CRM、排课、报表 tab、薪资配置/报表、连锁下钻等） |
| 全量回归（2026-07-11） | **433** tests passed；`pnpm typecheck`；`build:member` + `build:staff` 通过 |

近期收口：薪资 sales-config 阶梯校验（服务端 `PAYROLL_TIER_OVERLAP`）、导出/重算轮询、tabBar 图标、连锁报表下钻、**P4 生产脚手架**（队列/SMS/支付边界）。

**产品级 defer（非阻塞上线演示）**：

- 微信卡包 `member-card.wechat-pack.*`（Stage 07 step 9）
- 生产 SMS 供应商实现（`SMS_DRIVER` 目前仅 `log`）
- 微信支付 V3 真实下单与平台证书验签（`PAYMENT_DRIVER=wechat` 为 pending + webhook 脚手架）

Stage 01-04 已完成：身份与组织、会员基础、员工会员 CRM、潜客绑定与租户访问策略。

核心身份规则：

- 一个自然人对应一个全局 `Account`。
- 同一 `Account` 可同时拥有 Member 和 Staff 身份。
- OpenID 按 AppID 隔离。
- 手机号和 UnionID 不允许静默合并 Account。
- Member/Staff token audience 隔离。
- 手机号不是进入系统的必要条件。
- 员工录入手机号只是租户 CRM 未验证联系方式。

## 3. 后端阶段状态（2026-07-11）

| Stage | 状态 | 全量测试数（阶段收口时） | 备注 |
|---|---|---:|---|
| 05 会员卡/账本 | BACKEND COMPLETE | — | 核心完成；跨店/平台订单等 defer |
| 06 排课预约 | BACKEND COMPLETE | — | 核心完成；step 11 报表合并 defer |
| 07 会员资料/签到 | BACKEND COMPLETE | **259** | 会员端主要流程已接线；微信卡包 defer |
| 08 员工 CRM 运营 | BACKEND COMPLETE | **280** | 员工端 CRM 主要流程已接线 |
| 09 报表/薪资/平台 | **BACKEND COMPLETE** | **418**（当前全量） | 见 `stage-09-reporting-analytics.md`；员工端 report/payroll UI 已接线 |
| 10 身份/员工/租户 | BACKEND COMPLETE | **344** | 见 `stage-10-identity-staff-tenant-cleanup.md` |

当前全量测试：**433** passed（2026-07-11 P4 脚手架后）。

## 4. Stage 09 收口摘要

- Steps 1–11 全部实现：dashboard、finance/course/rankings、reminders、export jobs、platform subscription reads、chain rollups、coach reports、payroll config、**payroll reports + scoped recompute jobs**。
- Payroll recompute（`POST .../payroll/recompute-jobs`）与 ledger recount（`computeAgain` / Stage 05 reconciliation）**分离**（C3）。
- 权限：`payroll.report.read`、`payroll.recompute.execute`。
- 测试：`StaffPayrollReportTest`（7 cases）；`StaffPayrollConfigTest` 含阶梯重叠校验；全量 **418** tests。

## 5. 追溯门禁（考古完成 2026-07-11）

```text
Pages UNREVIEWED=0   (185/185)
APIs UNREVIEWED=0    (313/313)
```

考古门禁 **PASS**。禁止运行 `tools/build-traceability.ps1`。旧目录 `会员端/`、`管理端/` 只读。

Stage 05–10 考古规格均 **ARCHAEOLOGY COMPLETE**。Stage 09 后端 **COMPLETE**。

## 6. 生产部署检查清单（P4 脚手架）

| 项 | 开发态 | 生产仍需 |
|---|---|---|
| 队列 worker | `QUEUE_CONNECTION=sync` 或 database + `php artisan queue:work` | Supervisor/systemd 常驻 worker；见 `docs/production-queue.md` |
| 导出/薪资重算 | API 创建 `pending` 行并 dispatch job | 非 sync 驱动 + worker + 失败重试流程 |
| SMS | `SMS_DRIVER=log`（`LogSmsGateway`） | 实现 `SmsGateway` 供应商驱动；`verify-mobile` 仍走微信手机号授权 |
| 支付 | `PAYMENT_DRIVER=demo` 自动付清并发卡 | `PAYMENT_DRIVER=wechat` + 商户号/APIv3 密钥 + `POST /webhooks/wechat-pay` 验签 |
| 密钥 | 仅 `.env.example` 占位 | 全部敏感项放部署环境变量，不入库 |

## 7. 下一阶段建议

1. 微信支付 V3 真实 SDK 对接与回调幂等加固。
2. SMS 供应商驱动 + OTP 存储/校验端点（若产品需要非微信手机号验证）。
3. 微信卡包（`member-card.wechat-pack.*`）— 产品确认后单独迭代。
4. Stage 04 后续：rejected reopen/cancel；手机号后验重复发现。
5. 跨店/平台订单等 Stage 05 defer 项（若多店产品需要）。

## 8. 给下一模型的首条指令

```text
先读 songguo-next/docs/AI-HANDOFF.md。
重建主体完成：Stage 05–10 + 09 后端 COMPLETE；会员端/员工端主要流程已接线。
P4 脚手架：队列 dispatch、SMS/支付接口边界、微信 webhook 占位；见 docs/production-queue.md。
剩余 defer：微信卡包、真实 SMS/微信支付凭证对接。
本地演示需先 `php artisan db:seed`（含 MemberLinkRequestSeeder 等）。
不要修改 会员端/ 或 管理端/ 旧源码。
```
