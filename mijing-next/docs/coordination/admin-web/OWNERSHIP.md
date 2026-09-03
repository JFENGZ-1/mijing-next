# Admin Web 并行开发所有权

## Web 后台任务独占

- `apps/admin-web/**`
- `packages/admin-*/**`
- `apps/server/app/Http/Controllers/Api/V1/Admin/**`
- `apps/server/app/Http/Requests/Admin/**`
- `apps/server/app/Services/Admin/**`
- `apps/server/tests/Feature/Admin/**`
- `docs/coordination/admin-web/**`
- 平台级微信支付配置与密钥管理（仅由服务端 `PaymentGateway` 消费）
- 图片/视频资源上传、审核、发布与生命周期管理
- 跨租户队列管理、积压监控、失败任务处置与运行指标

## 小程序与业务接口任务独占

- `apps/member-miniapp/**`
- `apps/staff-miniapp/**`
- Staff/Member 业务 Controller、Service、Request 与 Feature 测试
- `docs/openapi.yaml`
- `apps/server/routes/api.php`
- 核心模型、迁移和领域服务
- 会员购卡、待支付订单重付等 Staff/Member 业务接口
- 员工导出、薪资等业务任务的创建与状态查询

## 共享文件门禁

根 `package.json`、`pnpm-lock.yaml`、`pnpm-workspace.yaml`、`docs/openapi.yaml`、`routes/api.php`、`bootstrap/app.php` 和 `packages/domain-types/**` 属于共享集成面。Admin 依赖带来的 lock/workspace 变更只保留在隔离分支，等 checkpoint 再合并；`docs/openapi.yaml` 始终只读消费。Web 后台不得复制在建业务规则；发现缺失能力时先形成接口需求，再决定由业务任务或 Admin 专属命名空间实现。

接口任务存在未提交契约时，通过 `MIJING_OPENAPI_PATH` 读取其工作区原文件并生成本地差异报告，不复制、不回写上游文件。

小程序不得读取支付密钥或队列配置。支付流程仅调用 Member 业务接口，由服务端 `PaymentGateway` 消费超管配置；图片与视频只消费后台已发布的资源 URL。跨租户队列监控属于 Admin，单租户业务任务仍属于 Staff/Member。

上游任务：`codex://threads/01a02859-0bec-7101-8f27-faa48a0dee81`
