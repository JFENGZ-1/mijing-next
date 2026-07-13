<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberNotices } from "@/api/member";
import { ensureMemberContext } from "@/composables/member-context";
import type { MemberNoticeTeaser } from "@/types/member";
import { formatIsoDate } from "@/utils/format";

const errorMessage = ref("");
const siteName = ref("");
const notices = ref<MemberNoticeTeaser[]>([]);

const loading = ref(true);

async function loadNotices(refresh = false) {
  loading.value = true;
  errorMessage.value = "";
  if (refresh) {
    notices.value = [];
  }

  try {
    const context = await ensureMemberContext();
    if (!context) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    siteName.value = context.siteName;
    const response = await getMemberNotices(context.tenantId, context.siteId);
    notices.value = response.data.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "通知列表加载失败";
  } finally {
    loading.value = false;
  }
}

function openNoticeDetail(noticeId: number) {
  uni.navigateTo({ url: `/pages/notices/detail?id=${noticeId}` });
}

onShow(async () => { if (await requireMemberAuth()) await loadNotices(); });

onPullDownRefresh(async () => { await loadNotices(true); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header">
      <text class="section-title section-title--inline">场馆通知</text>
      <text v-if="siteName" class="subtitle">{{ siteName }}</text>
    </view>

    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-else-if="!notices.length" mode="news" text="暂无通知" />

    <view
      v-for="notice in notices"
      :key="notice.id"
      class="notice-card"
      @tap="openNoticeDetail(notice.id)"
    >
      <image
        v-if="notice.coverImageUrl"
        class="cover-image"
        :src="notice.coverImageUrl"
        mode="aspectFill"
        lazy-load
      />
      <view class="notice-title">{{ notice.title }}</view>
      <view v-if="notice.publishedAt" class="notice-time">发布于：{{ formatIsoDate(notice.publishedAt) }}</view>
      <view v-if="notice.excerpt" class="notice-excerpt">{{ notice.excerpt }}</view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.header {
  margin-bottom: $spacing-md;
}

.subtitle {
  display: block;
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.notice-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.cover-image {
  width: 100%;
  height: 200rpx;
  margin-bottom: $spacing-sm;
  border-radius: $radius-sm;
}

.notice-title {
  font-size: 30rpx;
  font-weight: 600;
}

.notice-time {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.notice-excerpt {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 26rpx;
  line-height: 1.5;
}
</style>
