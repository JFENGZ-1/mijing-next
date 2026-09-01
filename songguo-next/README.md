# Mijing Next

全新建设的场馆约课系统。旧项目只作为业务考古依据，本目录不复用旧编译产物、接口或数据结构。

**新模型请先读：** [`docs/HANDOFF-2026-07-13.md`](./docs/HANDOFF-2026-07-13.md)

## 应用

- `apps/member-miniapp`：会员端微信小程序。
- `apps/staff-miniapp`：员工/管理端微信小程序。
- `apps/server`：PHP API 服务。
- `packages/api-client`：由 OpenAPI 契约生成或维护的前端 API 类型与客户端。
- `packages/domain-types`：跨前端共享的领域枚举和只读模型。

## 本地要求

- Node.js 20+
- pnpm 10+
- PHP 8.2+
- Composer 2+
- MySQL 8.x（当前本机验证版本为 8.3.0）
- Redis（开发初期可使用同步队列和文件缓存替代）

## 原则

- 资金、权益、席位和权限规则只在服务端裁决。
- 所有核心写操作具备事务、幂等和审计。
- 前后端契约以 OpenAPI 为源。
- uView Plus 仅提供基础 UI，自有业务组件不依赖其内部模型。
- 旧能力必须在需求追溯矩阵中明确重建、合并、替代或废弃。

## 当前启动方式

```powershell
# API（8000 已被本机其他服务占用）
cd apps/server
php artisan serve --host=127.0.0.1 --port=8010

# 会员端本地联调（保持运行以获得热更新）
pnpm dev:member

# 员工端本地联调（保持运行以获得热更新）
pnpm dev:staff
```

API 健康检查：`http://127.0.0.1:8010/api/v1/health`

### 小程序 dist 目录约定

| 用途 | 命令 | 微信开发者工具路径 |
| --- | --- | --- |
| 日常开发、接口联调、页面自动化 | `pnpm dev:member` / `pnpm dev:staff` | `apps/<应用>/dist/dev/mp-weixin` |
| 发布构建、发布前验收 | `pnpm build:member` / `pnpm build:staff` | `apps/<应用>/dist/dev/mp-weixin` |

- 开发阶段唯一主版本是 `dist/dev/mp-weixin`；所有模型和微信开发者工具窗口必须使用该路径，不再按时间在 `dev` 与 `build` 之间切换。
- `pnpm dev:member` 与 `pnpm dev:staff` 启动前会自动删除会员端和员工端的旧 `dist/build`，避免开发工作区同时出现两套产物。
- `pnpm build:member` 与 `pnpm build:staff` 也输出到同一 `dist/dev/mp-weixin`；`build` 只表示生产优化模式，不再创建第二个存放路径。
- 不要手工复制或合并 `dev`、`build` 的文件；需要更新主版本时修改源码并重新运行对应的 `pnpm dev:*`。

## 初始化与测试

```powershell
cd apps/server
php artisan migrate --seed
php artisan system:bootstrap
php artisan test
```

员工端微信开发者工具登录（`migrate` + `bootstrap` 之后必须做一次绑定）：

1. 运行 `pnpm dev:staff`，并在微信开发者工具中打开 `apps/staff-miniapp/dist/dev/mp-weixin`，确认已登录。
2. 读取员工端 openid（任选其一）：
   - `wechatide -c <clientName> -t check_devtools_status --skill-version 0.2.5` 返回 JSON 中的 `openid`
   - 或在员工端登录页点击「员工微信登录」前，于 DevTools 控制台执行 `uni.login({ success: ({ code }) => console.log(code) })`，再用下一步的 code 方式绑定
3. 绑定到种子管理员 `ADMIN001`（二选一）：
   - `php artisan staff:bind-openid <你的员工端openid>`
   - `php artisan staff:bind-wechat-code <wx.login 一次性 code>`
4. 可选：将 openid 写入 `.env` 的 `WECHAT_DEV_STAFF_OPENID=`，之后 `php artisan system:bootstrap` 会自动绑定。
5. 回到 DevTools 点击「员工微信登录」。

**网页绑定（可选）**：启动 API 后访问 `http://127.0.0.1:8010/dev/wechat-bindings`（详见 `apps/server/README.md` §开发工具）。

测试固定使用独立数据库 `songguo_next_test`，禁止指向开发或生产数据库。
