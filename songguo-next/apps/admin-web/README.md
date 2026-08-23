# Songguo Admin Web

松果场馆系统的 Web 运营后台。该应用位于独立 `codex/admin-web` worktree，现有员工端、会员端和业务 API 是持续演进的上游。

## 开发命令

```powershell
pnpm install
pnpm --filter @songguo/admin-web contract:accept
pnpm --filter @songguo/admin-web dev
```

首次启动 Laravel 后端时，先执行迁移并创建独立的超级管理员账号：

```powershell
php artisan migrate
php artisan admin:create platform-admin
```

`admin:create` 会隐藏输入密码。超级管理员存储在独立的 `super_admins` 表中，不复用员工、会员或租户账号。

构建验证：

```powershell
pnpm --filter @songguo/admin-web build
```

## 接口规则

- `../../docs/openapi.yaml` 只读，由业务接口工作流维护。
- 后台以 `operationId` 建立资源映射，不散落手写接口路径。
- `contract:sync` 生成当前 operationId 清单、后台处置分类和相对基线的变化报告。
- 独立 worktree 联调时可通过 `SONGGUO_OPENAPI_PATH` 指向接口任务正在修改的 `openapi.yaml`，无需复制或改写上游文件。
- `contract:accept` 仅在人工确认上游变化后更新基线。
- `contract:types` 对完整 OpenAPI 执行严格类型生成；若上游存在不可解析 `$ref` 会失败，不能通过修改后台副本绕过。
- Web 前端不计算金额、权益、席位、退款或权限结果；所有聚合、状态与授权均由服务端校验和裁决。

## 当前阶段

当前已接入真实账号密码登录、登录态恢复/注销、平台总览、租户、会员、员工、课程、排课、预约、会员卡、订单、平台经营汇总、微信支付配置、图片/视频资源库、跨租户队列监控和操作审计。所有资源页只展示数据库与 Admin API 返回的数据，代码中不保留演示记录。

已实现的 Admin API：

- `POST /api/v1/admin/auth/login`
- `POST /api/v1/admin/auth/logout`
- `GET /api/v1/admin/me`
- `GET /api/v1/admin/dashboard`
- `GET /api/v1/admin/tenants`
- `GET /api/v1/admin/members`
- `GET /api/v1/admin/resources/{resource}`（员工、课程、排课、预约、会员卡、订单）
- `GET|PUT /api/v1/admin/settings/payments/wechat`
- `GET|POST|PUT /api/v1/admin/media-assets...`
- `GET /api/v1/media/{uuid}`（仅已发布资源）
- `GET /api/v1/admin/queues/overview`
- `GET /api/v1/admin/queues/jobs`
- `GET /api/v1/admin/queues/failed`
- `GET /api/v1/admin/queues/batches`
- `POST|DELETE /api/v1/admin/queues/failed/{uuid}...`

微信支付密钥使用 Laravel `encrypted` cast 加密落库，读取接口只返回密钥是否存在。`ManagedPaymentGateway` 在每次业务调用时读取最新启用状态：启用数据库配置时走微信支付，未启用时保留 Demo/环境变量兼容。小程序只调用 Staff/Member 业务接口或消费已发布资源 URL，不读取支付密钥和队列配置。

资源库默认使用 `public` disk，可通过 `ADMIN_MEDIA_DISK` 切换到对象存储；队列页面不会返回任务原始 payload 或完整异常堆栈，只允许对精确 UUID 的失败任务进行重试或删除。

例如在 PowerShell 中同步另一个任务尚未提交的契约：

```powershell
$env:SONGGUO_OPENAPI_PATH = 'D:\path\to\active-worktree\songguo-next\docs\openapi.yaml'
pnpm --filter @songguo/admin-web contract:sync
```
