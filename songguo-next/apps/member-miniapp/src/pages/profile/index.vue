<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { redirectToLogin } from "@/auth/navigation";
import { useApiClient } from "@/api/client";
import type { MemberOnboardingData, MemberProfileData } from "@/types/member";

const errorMessage = ref("");
const onboarding = ref<MemberOnboardingData | null>(null);

const loading = ref(true);

function genderLabel(gender: MemberProfileData["gender"]) {
  if (gender === "male") return "男";
  if (gender === "female") return "女";
  if (gender === "undisclosed") return "不愿透露";
  return "未设置";
}

async function load() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await useApiClient().request<MemberOnboardingData>("/member/onboarding");
    onboarding.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "资料加载失败";
  } finally {
    loading.value = false;
  }
}

function editProfile() {
  uni.navigateTo({ url: "/pages/onboarding/profile" });
}

async function logout() {
  try {
    await useApiClient().request("/auth/logout", { method: "POST" });
  } finally {
    redirectToLogin();
  }
}

onShow(async () => { if (await requireMemberAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <template v-else-if="onboarding?.profile">
      <view class="profile-header hero-yellow">
        <u-avatar size="64" icon="account-fill" />
        <view>
          <view class="profile-name">{{ onboarding.profile.displayName }}</view>
          <view class="profile-state">资料状态：{{ onboarding.state === 'complete' ? '已完成' : '待完善' }}</view>
        </view>
      </view>

      <u-cell-group>
        <u-cell title="手机号" :value="onboarding.profile.mobileMasked || '未验证'" />
        <u-cell title="性别" :value="genderLabel(onboarding.profile.gender)" />
        <u-cell title="生日" :value="onboarding.profile.birthDate || '未设置'" />
        <u-cell title="身高" :value="onboarding.profile.heightCm ? `${onboarding.profile.heightCm} cm` : '未设置'" />
        <u-cell title="体重" :value="onboarding.profile.weightKg ? `${onboarding.profile.weightKg} kg` : '未设置'" />
      </u-cell-group>

      <view class="actions">
        <u-button type="primary" icon="edit-pen" @click="editProfile">编辑资料</u-button>
        <u-button plain icon="close-circle" @click="logout">退出登录</u-button>
      </view>
    </template>
    <u-empty v-else mode="data" text="暂无资料" />
  </view>
</template>

<style scoped lang="scss">
.profile-header {
  display: flex;
  align-items: center;
  gap: 24rpx;
  margin: -24rpx -24rpx 24rpx;
  padding: 28rpx 24rpx;
  border-radius: $radius-md;
}
.profile-name { font-size: 36rpx; font-weight: 600; }
.profile-state { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.actions { display: grid; gap: 16rpx; margin-top: 32rpx; }
</style>
