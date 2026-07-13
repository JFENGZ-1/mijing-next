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
  <view class="page-container login-page">
    <view class="brand">松果工作台</view>
    <view class="description">仅已启用的场馆员工可以登录</view>
    <u-button type="primary" :loading="loading" @click="login">员工微信登录</u-button>
  </view>
</template>

<style scoped lang="scss">
.login-page { padding-top: 180rpx; }
.brand { margin-bottom: $spacing-sm; font-size: 48rpx; font-weight: 600; }
.description { margin-bottom: 64rpx; color: $color-text-secondary; }
</style>
