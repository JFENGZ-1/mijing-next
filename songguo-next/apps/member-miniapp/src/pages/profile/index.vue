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
  // 仅首次显示全屏加载，返回本页时静默刷新
  loading.value = !onboarding.value;
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
  <view v-if="!loading" class="profile-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <template v-else-if="onboarding?.profile">
      <view class="profile-content">
        <view class="photo-wrap" @tap="editProfile">
          <view class="photo">
            <u-avatar
              :text="(onboarding.profile.displayName || '会').slice(0, 1)"
              size="70"
              bg-color="#22c788"
            />
          </view>
          <view class="modifi-photo-wrap">
            <u-icon name="edit-pen" size="14" color="#003d82" />
            <text class="modifi-photo-text">修改资料</text>
          </view>
        </view>

        <view class="form">
          <view class="form-item" @tap="editProfile">
            <view class="form-label">称呼</view>
            <view class="form-value">{{ onboarding.profile.displayName || "未设置" }}</view>
            <u-icon name="arrow-right" size="18" color="#989898" />
          </view>
          <view class="form-item" @tap="editProfile">
            <view class="form-label">手机号</view>
            <view class="form-value">{{ onboarding.profile.mobileMasked || "未验证" }}</view>
            <u-icon name="arrow-right" size="18" color="#989898" />
          </view>
          <view class="form-item" @tap="editProfile">
            <view class="form-label">性别</view>
            <view class="form-value">{{ genderLabel(onboarding.profile.gender) }}</view>
            <u-icon name="arrow-right" size="18" color="#989898" />
          </view>
          <view class="form-item" @tap="editProfile">
            <view class="form-label">生日</view>
            <view class="form-value">{{ onboarding.profile.birthDate || "未设置" }}</view>
            <u-icon name="arrow-right" size="18" color="#989898" />
          </view>
          <view class="form-item" @tap="editProfile">
            <view class="form-label">身高</view>
            <view class="form-value">{{ onboarding.profile.heightCm ? `${onboarding.profile.heightCm} cm` : "未设置" }}</view>
            <u-icon name="arrow-right" size="18" color="#989898" />
          </view>
          <view class="form-item" @tap="editProfile">
            <view class="form-label">体重</view>
            <view class="form-value">{{ onboarding.profile.weightKg ? `${onboarding.profile.weightKg} kg` : "未设置" }}</view>
            <u-icon name="arrow-right" size="18" color="#989898" />
          </view>
        </view>
      </view>

      <view class="profile-actions">
        <button class="action-btn action-edit" @tap="editProfile">编辑资料</button>
        <button class="action-btn action-logout" @tap="logout">退出登录</button>
      </view>

      <view class="bottom-logo">
        <text>觅境约课</text>
      </view>
    </template>

    <u-empty v-else mode="data" text="暂无资料" />
  </view>
</template>

<style scoped lang="scss">
.profile-page {
  min-height: 100vh;
  background: $color-page;
}

.profile-content {
  padding-bottom: 40rpx;
  background: #fff;
  overflow: hidden;
}

.photo-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin: 0 auto;
  width: 141rpx;
}

.photo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 141rpx;
  height: 141rpx;
  margin-top: 50rpx;
  margin-bottom: 17rpx;
  border-radius: 50%;
  overflow: hidden;
}

.modifi-photo-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.modifi-photo-text {
  margin-left: 8rpx;
  color: #003d82;
  font-size: 22rpx;
}

.form {
  margin: 50rpx 35rpx 0;
}

.form-item {
  display: flex;
  align-items: center;
  min-height: 96rpx;
  padding: 24rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}

.form-label {
  flex-shrink: 0;
  width: 160rpx;
  color: $color-text;
  font-size: 28rpx;
}

.form-value {
  flex: 1;
  margin-right: 12rpx;
  color: $color-text-secondary;
  font-size: 28rpx;
  text-align: right;
}

.profile-actions {
  display: flex;
  flex-direction: column;
  gap: 20rpx;
  margin: 40rpx 55rpx 0;
}

.action-btn {
  height: 90rpx;
  line-height: 90rpx;
  font-size: 32rpx;
  border: none;
  border-radius: 50rpx;
}

.action-btn::after {
  border: none;
}

.action-edit {
  color: #fff;
  background: $color-primary;
}

.action-logout {
  color: $color-text;
  background: #fff;
  border: 1rpx solid $color-border;
}

.bottom-logo {
  margin-top: 60rpx;
  padding-bottom: 40rpx;
  text-align: center;
  color: $color-text-muted;
  font-size: 22rpx;
}
</style>
