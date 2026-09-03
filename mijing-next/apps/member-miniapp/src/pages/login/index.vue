<script setup lang="ts">
import { ref } from "vue";
import { useApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { RegistrationState } from "@/types/member";

const loading = ref(false);
const session = useSessionStore();

async function login() {
  loading.value = true;
  try {
    const loginResult = await uni.login({ provider: "weixin" });
    const response = await useApiClient().request<{
      accessToken: string;
      registrationRequired: boolean;
      registrationState: RegistrationState;
    }>("/auth/wechat/login", {
      method: "POST",
      data: { appType: "member", code: loginResult.code, deviceName: "member-miniapp" },
    });
    session.setToken(response.data.accessToken, response.data.registrationState);
    uni.reLaunch({
      url: response.data.registrationRequired ? "/pages/onboarding/profile" : "/pages/index/index",
    });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "登录失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <view class="login-page">
    <view class="login-hero">
      <view class="login-logo">觅境约课</view>
      <view class="login-tagline">会员约课 · 场馆服务</view>
    </view>

    <view class="login-sheet card-sheet">
      <view class="welcome-text">欢迎使用</view>
      <view class="description">登录后查看课程、预约和会员权益</view>
      <button class="login-btn" :loading="loading" @tap="login">微信登录</button>
      <view class="login-foot">安全登录 · 觅境约课会员端</view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.login-page {
  min-height: 100vh;
  background: linear-gradient(180deg, #22c788 0%, #1dac75 42%, $color-page 42%);
}

.login-hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 42vh;
  padding-top: 80rpx;
  color: #fff;
}

.login-logo {
  font-size: 56rpx;
  font-weight: 600;
  letter-spacing: 4rpx;
}

.login-tagline {
  margin-top: 16rpx;
  font-size: 24rpx;
  opacity: 0.92;
}

.login-sheet {
  min-height: 58vh;
  padding: 150rpx 55rpx 80rpx;
}

.welcome-text {
  color: $color-text;
  font-size: 69rpx;
  font-weight: 500;
  line-height: 90rpx;
}

.description {
  margin-top: 47rpx;
  color: $color-text-secondary;
  font-size: 28rpx;
  line-height: 50rpx;
}

.login-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 458rpx;
  height: 90rpx;
  margin: 150rpx auto 0;
  color: $color-text;
  font-size: 34rpx;
  background: $color-accent-yellow;
  border: none;
  border-radius: 50rpx;
}

.login-btn::after {
  border: none;
}

.login-foot {
  margin-top: 80rpx;
  color: $color-text-muted;
  font-size: 19rpx;
  text-align: center;
}
</style>
