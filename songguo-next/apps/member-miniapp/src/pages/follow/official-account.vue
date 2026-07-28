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
  // 仅首次显示全屏加载，返回本页时静默刷新
  loading.value = !content.value;
  errorMessage.value = "";
  missing.value = false;

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
  <view v-if="!loading" class="follow-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <u-empty v-if="missing" mode="data" text="暂未配置公众号关注指引" />

    <template v-else-if="content">
      <view class="qr-layout">
        <view class="qr-card">
          <image
            class="qr-pic"
            :src="content.imageUrl"
            mode="aspectFit"
            show-menu-by-longpress
            lazy-load
          />
          <view class="qr-tip">长按图片识别</view>
        </view>
      </view>

      <view class="title-layout">
        <view class="title-big">【提醒通知】</view>
        <view class="title-desc">即可收到课程取消、上课提醒、排队成功等通知</view>
      </view>

      <view v-if="content.instructionsText" class="instructions-card">
        <view class="instructions-title">操作说明</view>
        <view class="instructions-text">{{ content.instructionsText }}</view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.follow-page {
  min-height: 100vh;
  padding-bottom: 80rpx;
  background: #696b99;
}

.qr-layout {
  display: flex;
  justify-content: center;
  padding-top: 110rpx;
}

.qr-card {
  position: relative;
  padding: 35rpx;
  background: #fff;
  border-radius: 25rpx;
}

.qr-pic {
  width: 520rpx;
  height: 520rpx;
}

.qr-tip {
  padding-bottom: 8rpx;
  color: $color-text;
  font-size: 28rpx;
  line-height: 30rpx;
  text-align: center;
}

.title-layout {
  margin-top: 90rpx;
  color: #fff;
  text-align: center;
}

.title-big {
  font-size: 90rpx;
  font-weight: 500;
  line-height: 120rpx;
}

.title-desc {
  font-size: 26rpx;
  line-height: 50rpx;
}

.instructions-card {
  margin: 60rpx 40rpx 0;
  padding: 32rpx;
  background: rgba(255, 255, 255, 0.12);
  border-radius: 20rpx;
}

.instructions-title {
  margin-bottom: 16rpx;
  color: #fff;
  font-size: 30rpx;
  font-weight: 600;
}

.instructions-text {
  color: rgba(255, 255, 255, 0.9);
  font-size: 26rpx;
  line-height: 1.7;
  white-space: pre-wrap;
}
</style>
