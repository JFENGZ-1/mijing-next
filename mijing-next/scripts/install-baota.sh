#!/usr/bin/env bash

set -Eeuo pipefail

REPOSITORY_URL="${MIJING_REPOSITORY_URL:-https://github.com/JFENGZ-1/mijing-next.git}"
REPOSITORY_BRANCH="${MIJING_REPOSITORY_BRANCH:-master}"
TARGET_DIR="${MIJING_REPO_DIR:-/www/wwwroot/mj.zonrn.cn}"

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

[ "$(id -u)" -eq 0 ] || fail "请在宝塔终端使用 root 用户运行。"

command -v git >/dev/null 2>&1 || install_git
command -v git >/dev/null 2>&1 || fail "Git 安装失败。"

if [ -d "${TARGET_DIR}/.git" ]; then
  log "更新现有代码"
  current_origin="$(git -C "$TARGET_DIR" remote get-url origin 2>/dev/null || true)"
  if [ -z "$current_origin" ]; then
    git -C "$TARGET_DIR" remote add origin "$REPOSITORY_URL"
  elif [ "$current_origin" != "$REPOSITORY_URL" ] && \
       [ "$current_origin" != "git@github.com:JFENGZ-1/mijing-next.git" ]; then
    fail "${TARGET_DIR} 已连接到其他 Git 仓库：${current_origin}"
  fi
  git -C "$TARGET_DIR" fetch origin "$REPOSITORY_BRANCH"
  git -C "$TARGET_DIR" checkout "$REPOSITORY_BRANCH"
  git -C "$TARGET_DIR" pull --ff-only origin "$REPOSITORY_BRANCH"
else
  log "拉取项目到 ${TARGET_DIR}"
  mkdir -p "$TARGET_DIR"
  git -C "$TARGET_DIR" init
  git -C "$TARGET_DIR" remote add origin "$REPOSITORY_URL"
  git -C "$TARGET_DIR" fetch origin "$REPOSITORY_BRANCH"
  git -C "$TARGET_DIR" checkout -B "$REPOSITORY_BRANCH" "origin/${REPOSITORY_BRANCH}"
fi

DEPLOY_SCRIPT="${TARGET_DIR}/mijing-next/scripts/deploy-baota.sh"
[ -f "$DEPLOY_SCRIPT" ] || fail "部署脚本不存在：${DEPLOY_SCRIPT}"

log "启动应用部署"
exec bash "$DEPLOY_SCRIPT"
