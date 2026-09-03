# 宝塔一键部署

## 前置条件

在宝塔中完成以下一次性准备：

1. 安装 Nginx、MySQL 8、PHP 8.2 或更高版本、Composer、Node.js 20.19+（建议 Node 22）。
2. 创建站点 `mj.zonrn.cn`，PHP 版本选择 8.2 或更高版本。
3. 创建 MySQL 数据库和独立数据库用户，字符集选择 `utf8mb4`。
4. 服务器完成 GitHub CLI 登录，或为私有仓库配置只读 Deploy Key。

不要在 Shell 命令行中直接填写数据库密码、微信 AppSecret 或 GitHub Token。首次部署脚本会在终端中隐藏读取这些值。

## 首次部署 / 后续更新

服务器已安装并登录 GitHub CLI 时，在宝塔终端以 `root` 执行：

```bash
REPO_DIR=/www/wwwroot/mijing-next; if [ -d "$REPO_DIR/.git" ]; then git -C "$REPO_DIR" pull --ff-only origin master; else gh repo clone JFENGZ-1/mijing-next "$REPO_DIR"; fi; bash "$REPO_DIR/songguo-next/scripts/deploy-baota.sh"
```

首次运行会询问数据库和两个微信小程序的 AppID/AppSecret。后续运行保留服务器 `.env`，只更新代码、依赖、数据库迁移、Admin Web、缓存、Nginx、队列与定时任务。

若使用 Deploy Key，可将命令中的 `gh repo clone` 替换为：

```bash
git clone git@github.com:JFENGZ-1/mijing-next.git "$APP_DIR"
```

## 小程序发布

服务器部署完成并通过 HTTPS 健康检查后，在本机把会员端和员工端的 `.env.production` 都设置为：

```env
VITE_API_BASE_URL=https://mj.zonrn.cn/api/v1
```

再执行 `pnpm build:member` 和 `pnpm build:staff`，通过微信开发者工具上传。微信公众平台还需把 `https://mj.zonrn.cn` 加入 request/upload/download 合法域名。
