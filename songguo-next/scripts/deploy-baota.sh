#!/usr/bin/env bash

set -Eeuo pipefail

DOMAIN="${MIJING_DOMAIN:-mj.zonrn.cn}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${MIJING_APP_DIR:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
SERVER_DIR="${APP_DIR}/apps/server"
ADMIN_DIR="${APP_DIR}/apps/admin-web"
PUBLIC_DIR="${SERVER_DIR}/public"
ENV_FILE="${SERVER_DIR}/.env"
BT_VHOST="/www/server/panel/vhost/nginx/${DOMAIN}.conf"
BT_REWRITE="/www/server/panel/vhost/rewrite/${DOMAIN}.conf"
BT_CERT_DIR="/www/server/panel/vhost/cert/${DOMAIN}"
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

rollback_nginx() {
  local vhost_backup="$1" rewrite_backup="$2"
  if [ -n "$vhost_backup" ] && [ -f "$vhost_backup" ]; then
    cp -a "$vhost_backup" "$BT_VHOST"
  fi
  if [ -n "$rewrite_backup" ] && [ -f "$rewrite_backup" ]; then
    cp -a "$rewrite_backup" "$BT_REWRITE"
  elif [ -f "$BT_REWRITE" ]; then
    rm -f "$BT_REWRITE"
  fi
}

[ "$(id -u)" -eq 0 ] || fail "请在宝塔终端使用 root 用户运行。"
[ -d "${APP_DIR}/.git" ] || fail "${APP_DIR} 不是 Git 仓库，请先按文档中的一键命令克隆项目。"
[ -f "${SERVER_DIR}/artisan" ] || fail "Laravel 目录不存在：${SERVER_DIR}"
[ -f "${ADMIN_DIR}/package.json" ] || fail "Admin Web 目录不存在：${ADMIN_DIR}"

require_command git
require_command node
require_command npm
require_command awk
require_command sed
require_command sort

NODE_VERSION="$(node -p 'process.versions.node')"
version_at_least "$NODE_VERSION" "20.19.0" || fail "Node.js 版本必须 >= 20.19，当前为 ${NODE_VERSION}。建议在宝塔安装 Node 22。"

PHP_BIN="$(detect_php || true)"
[ -n "$PHP_BIN" ] || fail "未找到 PHP >= 8.2。请先在宝塔软件商店安装 PHP 8.2 或更高版本。"
COMPOSER_BIN="$(detect_composer || true)"
[ -n "$COMPOSER_BIN" ] || fail "未找到 Composer。请先在宝塔安装 Composer。"
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
if ! command -v pnpm >/dev/null 2>&1; then
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

log "配置宝塔 Nginx 站点"
[ -f "$BT_VHOST" ] || fail "未找到宝塔站点配置 ${BT_VHOST}。请先在宝塔网站面板创建 ${DOMAIN}，PHP 选择 8.2+。"
TIMESTAMP="$(date +%Y%m%d%H%M%S)"
VHOST_BACKUP="${BT_VHOST}.bak.${TIMESTAMP}"
REWRITE_BACKUP=""
cp -a "$BT_VHOST" "$VHOST_BACKUP"
if [ -f "$BT_REWRITE" ]; then
  REWRITE_BACKUP="${BT_REWRITE}.bak.${TIMESTAMP}"
  cp -a "$BT_REWRITE" "$REWRITE_BACKUP"
fi

sed -i -E "0,/^[[:space:]]*root[[:space:]]+[^;]+;/s|^[[:space:]]*root[[:space:]]+[^;]+;|    root ${PUBLIC_DIR};|" "$BT_VHOST"
sed -i -E "0,/^[[:space:]]*index[[:space:]]+[^;]+;/s|^[[:space:]]*index[[:space:]]+[^;]+;|    index index.html index.php index.htm;|" "$BT_VHOST"

if ! grep -Fq "$BT_REWRITE" "$BT_VHOST"; then
  rollback_nginx "$VHOST_BACKUP" "$REWRITE_BACKUP"
  fail "宝塔站点未引用 ${BT_REWRITE}，已恢复原配置。请把站点配置发给我适配。"
fi

mkdir -p "$(dirname "$BT_REWRITE")"
cat > "$BT_REWRITE" <<'NGINX'
location ^~ /api/ {
    try_files $uri $uri/ /index.php?$query_string;
}

location ^~ /webhooks/ {
    try_files $uri $uri/ /index.php?$query_string;
}

location / {
    try_files $uri $uri/ /index.html;
}
NGINX

NGINX_BIN="$(command -v nginx 2>/dev/null || true)"
if [ -z "$NGINX_BIN" ] && [ -x /www/server/nginx/sbin/nginx ]; then
  NGINX_BIN=/www/server/nginx/sbin/nginx
fi
[ -n "$NGINX_BIN" ] || {
  rollback_nginx "$VHOST_BACKUP" "$REWRITE_BACKUP"
  fail "未找到 Nginx 命令，已恢复原配置。"
}
if ! "$NGINX_BIN" -t; then
  rollback_nginx "$VHOST_BACKUP" "$REWRITE_BACKUP"
  "$NGINX_BIN" -t || true
  fail "Nginx 配置验证失败，已自动恢复原配置。"
fi
"$NGINX_BIN" -s reload

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

log "验证 API"
HEALTH_URL="http://127.0.0.1/api/v1/health"
CURL_OPTIONS=(--header "Host: ${DOMAIN}")
if [ -f "${BT_CERT_DIR}/fullchain.pem" ] && [ -f "${BT_CERT_DIR}/privkey.pem" ]; then
  HEALTH_URL="https://${DOMAIN}/api/v1/health"
  CURL_OPTIONS=(--resolve "${DOMAIN}:443:127.0.0.1")
fi
if command -v curl >/dev/null 2>&1; then
  HEALTH_STATUS="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' "${CURL_OPTIONS[@]}" "$HEALTH_URL" || true)"
  [ "$HEALTH_STATUS" = "200" ] \
    || fail "部署完成，但本机健康检查失败：HTTP ${HEALTH_STATUS:-000}，${HEALTH_URL}"
fi

printf '\n\033[1;32m部署完成。\033[0m\n'
printf '后台： https://%s/\n' "$DOMAIN"
printf 'API：  https://%s/api/v1/health\n' "$DOMAIN"
if [ "$FIRST_INSTALL" -eq 1 ]; then
  printf '\n然后在宝塔为 %s 申请 Let\x27s Encrypt 证书并开启强制 HTTPS。\n' "$DOMAIN"
fi
