<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchStaffProfile, updateStaffProfile, uploadStaffAvatar } from "@/api/profile";
import { requireStaffAuth } from "@/auth/guard";
import { redirectToLogin } from "@/auth/navigation";
import { useApiClient } from "@/api/client";
import type { StaffProfile } from "@/types/profile";

const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const profile = ref<StaffProfile | null>(null);
const displayName = ref("");

async function load() {
  loading.value = true;
  errorMessage.value = "";
  try {
    profile.value = await fetchStaffProfile();
    displayName.value = profile.value.displayName;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function saveDisplayName() {
  if (!profile.value || displayName.value.trim() === profile.value.displayName) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    profile.value = await updateStaffProfile({
      displayName: displayName.value.trim(),
      version: profile.value.version,
    });
    displayName.value = profile.value.displayName;
    uni.showToast({ title: "保存成功", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function chooseAvatar() {
  if (!profile.value) return;
  uni.chooseImage({
    count: 1,
    sizeType: ["compressed"],
    sourceType: ["album", "camera"],
    success: async (result) => {
      const filePath = result.tempFilePaths[0];
      if (!filePath) return;
      saving.value = true;
      errorMessage.value = "";
      try {
        const uploaded = await uploadStaffAvatar(filePath);
        profile.value = {
          ...profile.value!,
          avatarUrl: uploaded.avatarUrl,
          version: uploaded.version,
        };
        uni.showToast({ title: "头像已更新", icon: "none" });
      } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : "头像上传失败";
      } finally {
        saving.value = false;
      }
    },
  });
}

async function logout() {
  try {
    await useApiClient().request("/auth/logout", { method: "POST" });
  } finally {
    redirectToLogin();
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="profile">
      <view class="profile-header" @click="chooseAvatar">
        <u-avatar :src="profile.avatarUrl || undefined" :text="profile.displayName?.slice(0, 1) || '?'" size="64" />
        <view>
          <view class="profile-name">{{ profile.displayName }}</view>
          <view class="profile-hint">点击头像更换</view>
        </view>
      </view>

      <u-cell-group>
        <u-cell title="工号" :value="profile.employeeNo" />
        <u-cell title="手机号" :value="profile.mobileMasked || profile.mobile || '未绑定'" />
      </u-cell-group>

      <view class="field-block">
        <view class="field-label">显示名称</view>
        <u-input v-model="displayName" placeholder="请输入显示名称" maxlength="80" />
      </view>

      <view class="actions">
        <u-button type="primary" :loading="saving" @click="saveDisplayName">保存名称</u-button>
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
  margin-bottom: 24rpx;
  padding: 28rpx 0;
}

.profile-name {
  font-size: 36rpx;
  font-weight: 600;
}

.profile-hint {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.field-block {
  margin-top: 24rpx;
}

.field-label {
  margin-bottom: 12rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.actions {
  display: grid;
  gap: 16rpx;
  margin-top: 32rpx;
}
</style>
