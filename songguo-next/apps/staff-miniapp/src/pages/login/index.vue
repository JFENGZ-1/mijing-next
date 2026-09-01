<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { usePublicApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { StaffSiteContext } from "@/stores/session";

const loading = ref(false);
const statusMessage = ref("");
const errorMessage = ref("");
const session = useSessionStore();

onShow(() => {
  session.hydrate();
  if (session.accessToken) {
    uni.reLaunch({ url: "/pages/index/index" });
  }
});

async function login() {
  if (loading.value) return;

  loading.value = true;
  errorMessage.value = "";
  statusMessage.value = "正在获取微信登录凭证…";
  try {
    const loginResult = await uni.login({ provider: "weixin" });
    if (!loginResult.code) throw new Error("未获取到微信登录凭证，请重试");

    statusMessage.value = "正在验证员工身份…";
    const response = await usePublicApiClient().request<{
      accessToken: string;
      staff: { tenantId: number; permissions: string[]; sites: StaffSiteContext[] };
    }>("/auth/wechat/login", {
      method: "POST",
      data: { appType: "staff", code: loginResult.code, deviceName: "staff-miniapp" },
      timeout: 15_000,
    });
    if (!response.data.accessToken || !response.data.staff?.sites?.length) {
      throw new Error("当前员工账号没有可用场馆，请联系管理员");
    }

    session.setSession({
      accessToken: response.data.accessToken,
      tenantId: response.data.staff.tenantId,
      permissions: response.data.staff.permissions,
      sites: response.data.staff.sites,
    });
    statusMessage.value = "登录成功，正在进入工作台…";
    await uni.reLaunch({ url: "/pages/index/index" });
  } catch (error) {
    const message = loginErrorMessage(error);
    errorMessage.value = message;
    uni.showToast({ title: message, icon: "none", duration: 3000 });
  } finally {
    loading.value = false;
    statusMessage.value = "";
  }
}

function loginErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const messages: Record<string, string> = {
      STAFF_ACCESS_DENIED: "当前微信未绑定员工账号，请联系管理员",
      STAFF_CONTEXT_REQUIRED: "当前微信关联了多个员工身份，请联系管理员确认登录范围",
      ACCOUNT_DISABLED: "当前账号已停用，请联系管理员",
      WECHAT_CODE_INVALID: "微信登录凭证已过期，请重新登录",
      WECHAT_NOT_CONFIGURED: "员工微信登录尚未配置，请联系管理员",
      WECHAT_UNAVAILABLE: "微信服务暂时不可用，请稍后重试",
      LOGIN_BUSY: "登录请求正在处理中，请稍后重试",
    };
    return messages[error.payload.code] || error.message || "登录失败，请重试";
  }

  const message = error instanceof Error
    ? error.message
    : typeof error === "object" && error !== null && "errMsg" in error
      ? String((error as { errMsg: unknown }).errMsg)
      : "登录失败，请重试";
  return /timeout/i.test(message) ? "登录请求超时，请检查网络后重试" : message;
}
</script>

<template>
  <view class="login-page">
    <view class="brand-area">
      <image class="logo" src="/static/imgs/startlogo.png" mode="aspectFit" />
      <view class="brand">觅境约课 · 工作台</view>
      <view class="description">仅已启用的场馆员工可以登录</view>
    </view>
    <button class="login-btn" :disabled="loading" @click="login">
      {{ loading ? "登录中..." : "员工微信登录" }}
    </button>
    <view v-if="statusMessage" class="status-message">{{ statusMessage }}</view>
    <view v-else-if="errorMessage" class="error-message">{{ errorMessage }}</view>
    <view class="footer-hint">登录即代表同意平台服务协议</view>
  </view>
</template>

<style scoped lang="scss">
.login-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
  padding: 0 64rpx;
  box-sizing: border-box;
  background: $color-surface;
}

.brand-area {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 220rpx;
}

.logo {
  width: 176rpx;
  height: 176rpx;
  border-radius: 40rpx;
}

.brand {
  margin-top: 36rpx;
  font-size: 44rpx;
  font-weight: 700;
  color: $color-text;
}

.description {
  margin-top: 20rpx;
  color: $color-text-tertiary;
  font-size: 26rpx;
}

.login-btn {
  width: 100%;
  height: 96rpx;
  margin-top: 140rpx;
  line-height: 96rpx;
  color: #fff;
  font-size: 32rpx;
  font-weight: 500;
  background: $color-primary;
  border-radius: 48rpx;

  &[disabled] {
    opacity: 0.6;
    color: #fff;
    background: $color-primary;
  }
}

.login-btn::after {
  border: 0;
}

.footer-hint {
  margin-top: 32rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
}

.status-message,
.error-message {
  margin-top: 24rpx;
  font-size: 24rpx;
  line-height: 36rpx;
  text-align: center;
}

.status-message {
  color: $color-text-tertiary;
}

.error-message {
  color: $color-danger;
}

.status-message + .footer-hint,
.error-message + .footer-hint {
  margin-top: 20rpx;
}
</style>
