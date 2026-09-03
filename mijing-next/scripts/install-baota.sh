#!/usr/bin/env bash

set -Eeuo pipefail

REPOSITORY_URL="${MIJING_REPOSITORY_URL:-https://github.com/JFENGZ-1/mijing-next.git}"
REPOSITORY_BRANCH="${MIJING_REPOSITORY_BRANCH:-master}"
TARGET_DIR="${MIJING_REPO_DIR:-/www/wwwroot/mj.zonrn.cn}"
SPARSE_CHECKOUT_FILE="${TARGET_DIR}/.git/info/sparse-checkout"

log() {
  printf '\n\033[1;32m[mijing-install]\033[0m %s\n' "$*"
}

fail() {
  printf '\n\033[1;31m[mijing-install] ERROR:\033[0m %s\n' "$*" >&2
  exit 1
}

install_git() {
  log "安装 Git 和 CA 证书"
  if command -v apt-get >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y git ca-certificates
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y git ca-certificates
  elif command -v yum >/dev/null 2>&1; then
    yum install -y git ca-certificates
  else
    fail "无法识别系统包管理器，请先安装 Git。"
  fi
}

configure_server_checkout() {
  git -C "$TARGET_DIR" config core.sparseCheckout true
  git -C "$TARGET_DIR" config core.sparseCheckoutCone false
  mkdir -p "$(dirname "$SPARSE_CHECKOUT_FILE")"
  printf '%s\n' \
    '/mijing-next/apps/server/' \
    '/mijing-next/apps/admin-web/' \
    '/mijing-next/scripts/' \
    '/mijing-next/package.json' \
    '/mijing-next/pnpm-lock.yaml' \
    '/mijing-next/pnpm-workspace.yaml' \
    > "$SPARSE_CHECKOUT_FILE"
}

[ "$(id -u)" -eq 0 ] || fail "请在宝塔终端使用 root 用户运行。"

command -v git >/dev/null 2>&1 || install_git
command -v git >/dev/null 2>&1 || fail "Git 安装失败。"

if [ -d "${TARGET_DIR}/.git" ]; then
  log "更新现有服务端代码"
  if [ -n "$(git -C "$TARGET_DIR" status --porcelain --untracked-files=no)" ]; then
    fail "${TARGET_DIR} 存在未提交的已跟踪文件修改，请先处理后再部署。"
  fi
  current_origin="$(git -C "$TARGET_DIR" remote get-url origin 2>/dev/null || true)"
  if [ -z "$current_origin" ]; then
    git -C "$TARGET_DIR" remote add origin "$REPOSITORY_URL"
  elif [ "$current_origin" != "$REPOSITORY_URL" ] && \
       [ "$current_origin" != "git@github.com:JFENGZ-1/mijing-next.git" ]; then
    fail "${TARGET_DIR} 已连接到其他 Git 仓库：${current_origin}"
  fi
  configure_server_checkout
  git -C "$TARGET_DIR" fetch origin "$REPOSITORY_BRANCH"
  git -C "$TARGET_DIR" checkout "$REPOSITORY_BRANCH"
  git -C "$TARGET_DIR" merge --ff-only "origin/${REPOSITORY_BRANCH}"
  git -C "$TARGET_DIR" read-tree -mu HEAD
else
  log "仅拉取 Laravel 服务端和 Web 后台到 ${TARGET_DIR}"
  mkdir -p "$TARGET_DIR"
  git -C "$TARGET_DIR" init
  git -C "$TARGET_DIR" remote add origin "$REPOSITORY_URL"
  configure_server_checkout
  git -C "$TARGET_DIR" fetch --depth=1 origin "$REPOSITORY_BRANCH"
  git -C "$TARGET_DIR" checkout -B "$REPOSITORY_BRANCH" "origin/${REPOSITORY_BRANCH}"
fi

DEPLOY_SCRIPT="${TARGET_DIR}/mijing-next/scripts/deploy-baota.sh"
[ -f "$DEPLOY_SCRIPT" ] || fail "部署脚本不存在：${DEPLOY_SCRIPT}"

log "启动应用部署"
MIJING_SKIP_GIT_SYNC=1 exec bash "$DEPLOY_SCRIPT"
