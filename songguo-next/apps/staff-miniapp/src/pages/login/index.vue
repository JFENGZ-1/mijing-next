<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { useApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { StaffSiteContext } from "@/stores/session";

const loading = ref(false);
const session = useSessionStore();

onShow(() => {
  session.hydrate();
  if (session.accessToken) {
    uni.reLaunch({ url: "/pages/index/index" });
  }
});

async function login() {
  loading.value = true;
  try {
    const loginResult = await uni.login({ provider: "weixin" });
    const response = await useApiClient().request<{
      accessToken: string;
      staff: { tenantId: number; permissions: string[]; sites: StaffSiteContext[] };
    }>("/auth/wechat/login", {
      method: "POST",
      data: { appType: "staff", code: loginResult.code, deviceName: "staff-miniapp" },
    });
    session.setSession({
      accessToken: response.data.accessToken,
      tenantId: response.data.staff.tenantId,
      permissions: response.data.staff.permissions,
      sites: response.data.staff.sites,
    });
    uni.reLaunch({ url: "/pages/index/index" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "登录失败", icon: "none" });
  } finally {
    loading.value = false;
  }
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
</style>
