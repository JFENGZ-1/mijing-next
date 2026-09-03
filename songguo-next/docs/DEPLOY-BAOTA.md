# 宝塔一键部署

## 前置条件

在宝塔中完成以下一次性准备：

1. 安装 Nginx、MySQL 8、PHP 8.2 或更高版本。Git、curl、Node.js 22、npm、pnpm、Composer 等由部署流程检测并在缺失时安装。
2. 创建站点 `mj.zonrn.cn`，PHP 版本选择 8.2 或更高版本；Nginx 配置由管理员自行维护。
3. 创建 MySQL 数据库和独立数据库用户，字符集选择 `utf8mb4`。
4. 私有仓库首次拉取时，按终端提示完成一次 GitHub 设备授权；也可以预先为仓库配置只读 Deploy Key。

不要在 Shell 命令行中直接填写数据库密码、微信 AppSecret 或 GitHub Token。首次部署脚本会在终端中隐藏读取这些值。

## 首次部署 / 后续更新

在宝塔终端以 `root` 整段执行。若系统软件源没有 GitHub CLI，命令会下载并校验 GitHub 官方二进制包：

```bash
set -e

BOOTSTRAP_MISSING=0
for command_name in git curl tar gzip sha256sum install; do
  command -v "$command_name" >/dev/null 2>&1 || BOOTSTRAP_MISSING=1
done
if [ "$BOOTSTRAP_MISSING" -eq 1 ]; then
  if command -v apt-get >/dev/null 2>&1; then
    apt-get update
    apt-get install -y git curl ca-certificates tar gzip coreutils
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y git curl ca-certificates tar gzip coreutils
  elif command -v yum >/dev/null 2>&1; then
    yum install -y git curl ca-certificates tar gzip coreutils
  else
    echo "无法识别系统包管理器" >&2
    exit 1
  fi
fi

if ! command -v gh >/dev/null 2>&1; then
  GH_VERSION=2.99.0
  case "$(uname -m)" in
    x86_64|amd64) GH_ARCH=amd64 ;;
    aarch64|arm64) GH_ARCH=arm64 ;;
    *) echo "不支持的服务器架构：$(uname -m)" >&2; exit 1 ;;
  esac
  GH_ARCHIVE="gh_${GH_VERSION}_linux_${GH_ARCH}.tar.gz"
  GH_TEMP="$(mktemp -d)"
  curl -fsSL "https://github.com/cli/cli/releases/download/v${GH_VERSION}/${GH_ARCHIVE}" -o "${GH_TEMP}/${GH_ARCHIVE}"
  curl -fsSL "https://github.com/cli/cli/releases/download/v${GH_VERSION}/gh_${GH_VERSION}_checksums.txt" -o "${GH_TEMP}/checksums.txt"
  (cd "$GH_TEMP" && grep "  ${GH_ARCHIVE}$" checksums.txt | sha256sum --check --status -)
  tar -xzf "${GH_TEMP}/${GH_ARCHIVE}" -C "$GH_TEMP"
  install -m 0755 "${GH_TEMP}/gh_${GH_VERSION}_linux_${GH_ARCH}/bin/gh" /usr/local/bin/gh
  rm -rf "$GH_TEMP"
fi

gh auth status >/dev/null 2>&1 || gh auth login --hostname github.com --git-protocol https --web
REPO_DIR=/www/wwwroot/mijing-next
if [ -d "$REPO_DIR/.git" ]; then
  git -C "$REPO_DIR" pull --ff-only origin master
else
  gh repo clone JFENGZ-1/mijing-next "$REPO_DIR"
fi
bash "$REPO_DIR/songguo-next/scripts/deploy-baota.sh"
```

首次运行会询问数据库和两个微信小程序的 AppID/AppSecret。后续运行保留服务器 `.env`，只更新代码、依赖、数据库迁移、Admin Web、缓存、队列与定时任务。脚本不会读取、修改或重载 Nginx。

脚本完成后，宝塔站点运行目录应手动设置为：

```text
/www/wwwroot/mijing-next/songguo-next/apps/server/public
```

若使用 Deploy Key，可将命令中的 `gh repo clone` 替换为：

```bash
git clone git@github.com:JFENGZ-1/mijing-next.git "$REPO_DIR"
```

## 小程序发布

服务器部署完成并通过 HTTPS 健康检查后，在本机把会员端和员工端的 `.env.production` 都设置为：

```env
VITE_API_BASE_URL=https://mj.zonrn.cn/api/v1
```

再执行 `pnpm build:member` 和 `pnpm build:staff`，通过微信开发者工具上传。微信公众平台还需把 `https://mj.zonrn.cn` 加入 request/upload/download 合法域名。
