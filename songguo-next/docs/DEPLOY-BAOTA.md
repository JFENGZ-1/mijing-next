# 宝塔一键部署

## 前置条件

在宝塔中完成以下一次性准备：

1. 安装 Nginx、MySQL 8、PHP 8.2 或更高版本。Git、curl、Node.js 22、npm、pnpm、Composer 等由部署流程检测并在缺失时安装。
2. 创建站点 `mj.zonrn.cn`，PHP 版本选择 8.2 或更高版本；Nginx 配置由管理员自行维护。
3. 创建 MySQL 数据库和独立数据库用户，字符集选择 `utf8mb4`。
4. GitHub 仓库为公开仓库，服务器拉取代码不需要登录 GitHub，也不需要配置 Token 或 Deploy Key。

不要在 Shell 命令行中直接填写数据库密码、微信 AppSecret 或 GitHub Token。首次部署脚本会在终端中隐藏读取这些值。

## 首次部署 / 后续更新

在宝塔终端以 `root` 执行一条命令：

```bash
dnf install -y curl ca-certificates && curl -fsSL https://raw.githubusercontent.com/JFENGZ-1/mijing-next/master/songguo-next/scripts/install-baota.sh | bash
```

首次运行会询问数据库和两个微信小程序的 AppID/AppSecret。后续运行保留服务器 `.env`，只更新代码、依赖、数据库迁移、Admin Web、缓存、队列与定时任务。脚本不会读取、修改或重载 Nginx。

脚本完成后，宝塔站点运行目录应手动设置为：

```text
/www/wwwroot/mj.zonrn.cn/songguo-next/apps/server/public
```

## 小程序发布

服务器部署完成并通过 HTTPS 健康检查后，在本机把会员端和员工端的 `.env.production` 都设置为：

```env
VITE_API_BASE_URL=https://mj.zonrn.cn/api/v1
```

再执行 `pnpm build:member` 和 `pnpm build:staff`，通过微信开发者工具上传。微信公众平台还需把 `https://mj.zonrn.cn` 加入 request/upload/download 合法域名。
