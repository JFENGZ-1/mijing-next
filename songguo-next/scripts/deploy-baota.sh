#!/usr/bin/env bash

set -Eeuo pipefail

DOMAIN="${MIJING_DOMAIN:-mj.zonrn.cn}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${MIJING_APP_DIR:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
SERVER_DIR="${APP_DIR}/apps/server"
ADMIN_DIR="${APP_DIR}/apps/admin-web"
PUBLIC_DIR="${SERVER_DIR}/public"
ENV_FILE="${SERVER_DIR}/.env"
SERVICE_NAME="mijing-queue"

log() {
  printf '\n\033[1;32m[mijing-deploy]\033[0m %s\n' "$*"
}

fail() {
  printf '\n\033[1;31m[mijing-deploy] ERROR:\033[0m %s\n' "$*" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "缺少命令：$1"
}

install_base_tools() {
  log "安装缺失的系统基础工具"
  if command -v apt-get >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y git curl ca-certificates tar xz-utils unzip gawk coreutils grep findutils
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y git curl ca-certificates tar xz unzip gawk coreutils grep findutils
  elif command -v yum >/dev/null 2>&1; then
    yum install -y git curl ca-certificates tar xz unzip gawk coreutils grep findutils
  else
    fail "无法识别系统包管理器，请先安装 git、curl、ca-certificates、tar、xz、unzip、awk、coreutils。"
  fi
}

ensure_base_tools() {
  local command_name missing=0
  for command_name in git curl tar xz awk sort sha256sum; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
      missing=1
      break
    fi
  done
  if [ "$missing" -eq 1 ]; then
    install_base_tools
  fi
  for command_name in git curl tar xz awk sort sha256sum; do
    require_command "$command_name"
  done
}

version_at_least() {
  [ "$(printf '%s\n' "$2" "$1" | sort -V | head -n 1)" = "$2" ]
}

detect_php() {
  local candidate version
  for candidate in \
    /www/server/php/84/bin/php \
    /www/server/php/83/bin/php \
    /www/server/php/82/bin/php \
    "$(command -v php 2>/dev/null || true)"; do
    [ -n "$candidate" ] || continue
    [ -x "$candidate" ] || continue
    version="$($candidate -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
    if [ -n "$version" ] && version_at_least "$version" "8.2"; then
      printf '%s' "$candidate"
      return 0
    fi
  done
  return 1
}

check_php_extensions() {
  local extension modules missing=()
  modules="$($PHP_BIN -m | tr '[:upper:]' '[:lower:]')"
  for extension in ctype curl dom fileinfo filter mbstring openssl pdo pdo_mysql session tokenizer xml; do
    if ! grep -Fxq "$extension" <<<"$modules"; then
      missing+=("$extension")
    fi
  done
  if [ "${#missing[@]}" -gt 0 ]; then
    fail "PHP 8.2 缺少扩展：${missing[*]}。请在宝塔 PHP 8.2 扩展管理中安装或启用后重试。"
  fi
}

detect_composer() {
  local candidate
  for candidate in \
    "$(command -v composer 2>/dev/null || true)" \
    /usr/local/bin/composer \
    /usr/bin/composer; do
    [ -n "$candidate" ] || continue
    [ -x "$candidate" ] || continue
    printf '%s' "$candidate"
    return 0
  done
  return 1
}

install_composer() {
  local temporary expected_checksum actual_checksum
  temporary="$(mktemp -d)"
  expected_checksum="$(curl --fail --silent --show-error https://composer.github.io/installer.sig)"
  curl --fail --silent --show-error https://getcomposer.org/installer -o "${temporary}/composer-setup.php"
  actual_checksum="$($PHP_BIN -r "echo hash_file('sha384', '${temporary}/composer-setup.php');")"
  if [ -z "$expected_checksum" ] || [ "$expected_checksum" != "$actual_checksum" ]; then
    rm -rf "$temporary"
    fail "Composer 安装器校验失败，已停止安装。"
  fi
  "$PHP_BIN" "${temporary}/composer-setup.php" \
    --quiet \
    --install-dir=/usr/local/bin \
    --filename=composer
  chmod 755 /usr/local/bin/composer
  rm -rf "$temporary"
}

install_node22() {
  local machine_arch node_arch temporary sums_file archive_name install_name install_dir
  machine_arch="$(uname -m)"
  case "$machine_arch" in
    x86_64|amd64) node_arch="x64" ;;
    aarch64|arm64) node_arch="arm64" ;;
    *) fail "暂不支持自动安装 Node.js 的服务器架构：${machine_arch}" ;;
  esac

  temporary="$(mktemp -d)"
  sums_file="${temporary}/SHASUMS256.txt"
  curl --fail --silent --show-error \
    https://nodejs.org/dist/latest-v22.x/SHASUMS256.txt \
    -o "$sums_file"
  archive_name="$(awk -v arch="$node_arch" '$2 ~ ("linux-" arch "\\.tar\\.xz$") { print $2; exit }' "$sums_file")"
  if [ -z "$archive_name" ]; then
    rm -rf "$temporary"
    fail "无法从 Node.js 官方校验清单解析 Linux ${node_arch} 安装包。"
  fi

  curl --fail --silent --show-error \
    "https://nodejs.org/dist/latest-v22.x/${archive_name}" \
    -o "${temporary}/${archive_name}"
  if ! (cd "$temporary" && grep "  ${archive_name}$" SHASUMS256.txt | sha256sum --check --status -); then
    rm -rf "$temporary"
    fail "Node.js 安装包 SHA-256 校验失败，已停止安装。"
  fi

  install_name="${archive_name%.tar.xz}"
  install_dir="/opt/nodejs/${install_name}"
  mkdir -p "$install_dir"
  tar -xJf "${temporary}/${archive_name}" --strip-components=1 -C "$install_dir"
  for executable in node npm npx corepack; do
    if [ -x "${install_dir}/bin/${executable}" ]; then
      ln -sfn "${install_dir}/bin/${executable}" "/usr/local/bin/${executable}"
    fi
  done
  rm -rf "$temporary"
  hash -r
}

prompt_value() {
  local variable_name="$1" label="$2" default_value="${3:-}" current_value
  current_value="${!variable_name:-}"
  if [ -n "$current_value" ]; then
    return 0
  fi
  if [ -n "$default_value" ]; then
    read -r -p "${label} [${default_value}]: " current_value </dev/tty
    current_value="${current_value:-$default_value}"
  else
    read -r -p "${label}: " current_value </dev/tty
  fi
  printf -v "$variable_name" '%s' "$current_value"
}

prompt_secret() {
  local variable_name="$1" label="$2" current_value
  current_value="${!variable_name:-}"
  if [ -n "$current_value" ]; then
    return 0
  fi
  read -r -s -p "${label}: " current_value </dev/tty
  printf '\n' >/dev/tty
  printf -v "$variable_name" '%s' "$current_value"
}

env_quote() {
  local value="$1"
  case "$value" in
    true|false|null|empty)
      printf '%s' "$value"
      return 0
      ;;
  esac
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

set_env() {
  local key="$1" value="$2" temporary
  temporary="${ENV_FILE}.tmp.$$"
  awk -v key="$key" -v value="$(env_quote "$value")" '
    BEGIN { replaced = 0 }
    $0 ~ "^" key "=" {
      if (!replaced) print key "=" value
      replaced = 1
      next
    }
    { print }
    END { if (!replaced) print key "=" value }
  ' "$ENV_FILE" > "$temporary"
  mv "$temporary" "$ENV_FILE"
}

[ "$(id -u)" -eq 0 ] || fail "请在宝塔终端使用 root 用户运行。"
[ -d "${APP_DIR}/.git" ] || fail "${APP_DIR} 不是 Git 仓库，请先按文档中的一键命令克隆项目。"
[ -f "${SERVER_DIR}/artisan" ] || fail "Laravel 目录不存在：${SERVER_DIR}"
[ -f "${ADMIN_DIR}/package.json" ] || fail "Admin Web 目录不存在：${ADMIN_DIR}"

ensure_base_tools

NODE_VERSION="$(node -p 'process.versions.node' 2>/dev/null || true)"
if [ -z "$NODE_VERSION" ] || ! version_at_least "$NODE_VERSION" "20.19.0"; then
  log "安装 Node.js 22 官方 Linux 二进制包"
  install_node22
  NODE_VERSION="$(node -p 'process.versions.node' 2>/dev/null || true)"
fi
version_at_least "$NODE_VERSION" "20.19.0" || fail "Node.js 自动安装后版本仍不满足要求：${NODE_VERSION:-未安装}"
require_command npm

PHP_BIN="$(detect_php || true)"
[ -n "$PHP_BIN" ] || fail "未找到 PHP >= 8.2。请先在宝塔软件商店安装 PHP 8.2 或更高版本。"
check_php_extensions
COMPOSER_BIN="$(detect_composer || true)"
if [ -z "$COMPOSER_BIN" ]; then
  log "安装经过 SHA-384 校验的 Composer"
  install_composer
  COMPOSER_BIN="$(detect_composer || true)"
fi
[ -n "$COMPOSER_BIN" ] || fail "Composer 自动安装失败。"
if head -n 1 "$COMPOSER_BIN" | grep -qi php; then
  COMPOSER_CMD=("$PHP_BIN" "$COMPOSER_BIN")
else
  COMPOSER_CMD=("$COMPOSER_BIN")
fi

cd "$APP_DIR"

log "同步 master 分支"
git fetch origin master
git checkout master
git pull --ff-only origin master

log "安装前端依赖并构建运营后台"
if command -v corepack >/dev/null 2>&1; then
  corepack enable >/dev/null 2>&1 || true
fi
if ! command -v pnpm >/dev/null 2>&1 || [ "$(pnpm --version 2>/dev/null || true)" != "11.7.0" ]; then
  npm install --global pnpm@11.7.0
fi
pnpm install --frozen-lockfile
VITE_API_BASE_URL=/api/v1 VITE_ENABLE_DEMO_SESSION=false pnpm --filter @songguo/admin-web build

log "安装 Laravel 生产依赖"
cd "$SERVER_DIR"
COMPOSER_ALLOW_SUPERUSER=1 "${COMPOSER_CMD[@]}" install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction

FIRST_INSTALL=0
if [ ! -f "$ENV_FILE" ]; then
  FIRST_INSTALL=1
  cp .env.example "$ENV_FILE"

  printf '\n首次部署需要填写宝塔中已创建的 MySQL 数据库和微信小程序凭据。\n'
  prompt_value DB_DATABASE "数据库名" "mijing"
  prompt_value DB_USERNAME "数据库用户" "mijing"
  prompt_secret DB_PASSWORD "数据库密码"
  [ -n "$DB_PASSWORD" ] || fail "数据库密码不能为空。"
  prompt_value WECHAT_MEMBER_APPID "会员端 AppID"
  prompt_secret WECHAT_MEMBER_SECRET "会员端 AppSecret"
  prompt_value WECHAT_STAFF_APPID "员工端 AppID"
  prompt_secret WECHAT_STAFF_SECRET "员工端 AppSecret"
  [ -n "$WECHAT_MEMBER_APPID" ] || fail "会员端 AppID 不能为空。"
  [ -n "$WECHAT_MEMBER_SECRET" ] || fail "会员端 AppSecret 不能为空。"
  [ -n "$WECHAT_STAFF_APPID" ] || fail "员工端 AppID 不能为空。"
  [ -n "$WECHAT_STAFF_SECRET" ] || fail "员工端 AppSecret 不能为空。"

  set_env APP_NAME "Mijing"
  set_env APP_ENV "production"
  set_env APP_DEBUG "false"
  set_env APP_URL "https://${DOMAIN}"
  set_env LOG_LEVEL "warning"
  set_env DB_HOST "127.0.0.1"
  set_env DB_PORT "3306"
  set_env DB_DATABASE "$DB_DATABASE"
  set_env DB_USERNAME "$DB_USERNAME"
  set_env DB_PASSWORD "$DB_PASSWORD"
  set_env QUEUE_CONNECTION "database"
  set_env CACHE_STORE "file"
  set_env SESSION_DRIVER "file"
  set_env FILESYSTEM_DISK "local"
  set_env PAYMENT_DRIVER "wechat"
  set_env ADMIN_MEDIA_DISK "public"
  set_env WECHAT_MEMBER_APPID "$WECHAT_MEMBER_APPID"
  set_env WECHAT_MEMBER_SECRET "$WECHAT_MEMBER_SECRET"
  set_env WECHAT_STAFF_APPID "$WECHAT_STAFF_APPID"
  set_env WECHAT_STAFF_SECRET "$WECHAT_STAFF_SECRET"
else
  set_env APP_ENV "production"
  set_env APP_DEBUG "false"
  set_env APP_URL "https://${DOMAIN}"
fi

if ! grep -Eq '^APP_KEY=.+$' "$ENV_FILE"; then
  "$PHP_BIN" artisan key:generate --force
fi

log "迁移数据库并生成 Laravel 缓存"
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
if [ "$FIRST_INSTALL" -eq 1 ]; then
  prompt_value ADMIN_LOGIN "超级管理员登录名" "platform-admin"
  "$PHP_BIN" artisan admin:create "$ADMIN_LOGIN"
fi
"$PHP_BIN" artisan storage:link --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

log "发布 Admin Web 静态文件"
ADMIN_DIST="${ADMIN_DIR}/dist"
[ -f "${ADMIN_DIST}/index.html" ] || fail "Admin Web 构建产物不存在。"
rm -rf "${PUBLIC_DIR}/admin-assets"
cp -a "${ADMIN_DIST}/admin-assets" "${PUBLIC_DIR}/admin-assets"
cp -a "${ADMIN_DIST}/index.html" "${PUBLIC_DIR}/index.html"
find "$ADMIN_DIST" -maxdepth 1 -type f ! -name index.html -exec cp -a {} "$PUBLIC_DIR/" \;

log "设置运行目录权限"
chown -R www:www "${SERVER_DIR}/storage" "${SERVER_DIR}/bootstrap/cache" "${SERVER_DIR}/public/admin-assets" "${SERVER_DIR}/public/index.html"
find "${SERVER_DIR}/storage" "${SERVER_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;
find "${SERVER_DIR}/storage" "${SERVER_DIR}/bootstrap/cache" -type f -exec chmod 664 {} \;

log "配置队列 worker 与 Laravel 调度器"
cat > "/etc/systemd/system/${SERVICE_NAME}.service" <<EOF
[Unit]
Description=Mijing Laravel Queue Worker
After=network.target mysql.service mariadb.service

[Service]
Type=simple
User=www
Group=www
WorkingDirectory=${SERVER_DIR}
ExecStart=${PHP_BIN} artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
Restart=always
RestartSec=5
KillSignal=SIGTERM

[Install]
WantedBy=multi-user.target
EOF

if command -v systemctl >/dev/null 2>&1; then
  systemctl daemon-reload
  systemctl enable --now "$SERVICE_NAME"
  systemctl restart "$SERVICE_NAME"
else
  fail "系统不支持 systemd，队列 worker 尚未启动。"
fi

cat > /etc/cron.d/mijing-scheduler <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www cd ${SERVER_DIR} && ${PHP_BIN} artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/mijing-scheduler

"$PHP_BIN" artisan queue:restart

printf '\n\033[1;32m应用文件与后台任务部署完成。\033[0m\n'
printf '宝塔站点运行目录：%s\n' "$PUBLIC_DIR"
printf '脚本未读取、修改或重载任何 Nginx 配置。\n'
printf '配置完成后验证： https://%s/api/v1/health\n' "$DOMAIN"
if [ "$FIRST_INSTALL" -eq 1 ]; then
  printf '\n请在宝塔自行完成站点、PHP、路由规则、SSL 与强制 HTTPS 配置。\n'
fi
