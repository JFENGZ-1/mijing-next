<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { ApiError } from "@songguo/api-client";
import { getMemberOfficialAccountFollow } from "@/api/member";
import { ensureMemberContext } from "@/composables/member-context";
import type { MemberOfficialAccountFollow } from "@/types/member";

const errorMessage = ref("");
const missing = ref(false);
const content = ref<MemberOfficialAccountFollow | null>(null);

const loading = ref(true);

async function load() {
  loading.value = true;
  errorMessage.value = "";
  missing.value = false;
  content.value = null;

  try {
    const context = await ensureMemberContext();
    if (!context) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberOfficialAccountFollow(context.tenantId, context.siteId);
    content.value = response.data;
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      missing.value = true;
      return;
    }
    errorMessage.value = error instanceof Error ? error.message : "内容加载失败";
  } finally {
    loading.value = false;
  }
}

onShow(async () => { if (await requireMemberAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <u-empty v-if="missing" mode="data" text="暂未配置公众号关注指引" />

    <template v-else-if="content">
      <view class="hero-card hero-coral">
        <view class="hero-title">关注公众号</view>
        <view class="hero-subtitle">课程取消、上课提醒等通知收不到？关注后即可收到消息通知</view>
      </view>

      <view class="qr-card">
        <image class="qr-image" :src="content.imageUrl" mode="aspectFit" show-menu-by-longpress lazy-load />
      </view>

      <view class="instructions-card">
        <view class="instructions-title">操作说明</view>
        <view class="instructions-text">{{ content.instructionsText }}</view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.hero-card {
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  border-radius: $radius-md;
}

.hero-coral {
  color: #fff;
  background: $color-accent-coral;
}

.hero-title {
  font-size: 34rpx;
  font-weight: 600;
}

.hero-subtitle {
  margin-top: $spacing-xs;
  font-size: 26rpx;
  line-height: 1.6;
  opacity: 0.95;
}

.qr-card {
  display: flex;
  justify-content: center;
  margin-bottom: $spacing-md;
  padding: $spacing-lg;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.qr-image {
  width: 480rpx;
  height: 480rpx;
}

.instructions-card {
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.instructions-title {
  margin-bottom: $spacing-sm;
  font-size: 30rpx;
  font-weight: 600;
}

.instructions-text {
  color: $color-text-secondary;
  font-size: 26rpx;
  line-height: 1.7;
  white-space: pre-wrap;
}
</style>
