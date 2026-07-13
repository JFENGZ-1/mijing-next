# 松果约课小程序复原资料

> **活跃开发**见 `songguo-next/`；模型交接首选 [`songguo-next/docs/HANDOFF-2026-07-13.md`](../songguo-next/docs/HANDOFF-2026-07-13.md)。本目录侧重**旧系统考古**与追溯主账。

本目录记录对遗留微信小程序编译产物的静态审计与复原依据。当前工作区包含两个应用：

- `会员端`：用户选店、约课、购卡、订单、会员资料与统计。
- `管理端`：场馆、员工、课程、排课、会员、卡项、报表与系统配置。

## 先读结论

现有代码是 uni-app 编译后的微信小程序产物，不是可维护的原始 uni-app 工程。目录中没有 `.vue`、`package.json`、源码映射或构建配置；`app.js` 仅加载 webpack/uni-app 运行时。因此它适合：

1. 在微信开发者工具中验证旧版本是否仍能启动。
2. 反推页面、接口、数据字段和业务流程。
3. 作为重建后端与新前端的行为基线。

它不适合作为长期直接开发的源码。直接编辑编译文件会造成模块 ID、分包和运行时行为难以维护。

## 文档索引

- `project-audit-summary.md`：三路人工审查与机器清单交叉验证后的总报告。
- `new-system-blueprint.md`：新系统的第一性原理产品、领域、前后端、数据库、安全与交付蓝图。
- `recovery-plan.md`：复原上线的阶段、门槛和依赖。
- `member-audit.md`：会员端逐项人工审查。
- `admin-audit.md`：管理端逐项人工审查。
- `audit-redteam.md`：自动扫描的对抗式复核与后端契约结论。
- `generated/`：机器生成的文件、页面、接口、路由、组件、事件与资源清单。

重新生成机器清单：

```powershell
pwsh -File .\tools\audit-mini-programs.ps1
```

脚本包含中文路径且当前采用 UTF-8 编码，应使用 PowerShell 7 (`pwsh`)；旧版 Windows PowerShell 5.1 可能按系统代码页误解码。

## 当前基线

| 项目 | 会员端 | 管理端 |
|---|---:|---:|
| 文件数 | 386 | 1379 |
| 注册页面 | 35 | 150 |
| 唯一接口路径 | 49 | 264 |
| API 调用位置 | 62 | 440 |
| 主包估算 | 1.700 MiB | 2.016 MiB |

生产接口基址为 `https://interface.songguoyueke.com/api`，测试接口基址为 `https://test.songguoyueke.com/api`。根路径当前可建立 HTTPS 连接但返回 404；具体接口是否可用必须使用合法账号、AppID、微信登录凭据及业务数据逐一验证。
