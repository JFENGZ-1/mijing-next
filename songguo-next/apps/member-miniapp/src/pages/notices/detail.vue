<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberNoticeDetail } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberNoticeDetail } from "@/types/member";
import { formatIsoDate } from "@/utils/format";

const errorMessage = ref("");
const noticeId = ref(0);
const notice = ref<MemberNoticeDetail | null>(null);

const loading = ref(true);

async function loadNotice(refresh = false) {
  if (!noticeId.value) return;

  loading.value = true;
  errorMessage.value = "";
  if (refresh) {
    notice.value = null;
  }

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberNoticeDetail(tenant.tenantId, noticeId.value);
    notice.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "通知详情加载失败";
  } finally {
    loading.value = false;
  }
}

onLoad((options) => {
  noticeId.value = Number(options?.id ?? 0);
});

onShow(async () => { if (await requireMemberAuth()) await loadNotice(); });

onPullDownRefresh(async () => { await loadNotice(true); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-else-if="notice">
      <image
        v-if="notice.coverImageUrl"
        class="cover-image"
        :src="notice.coverImageUrl"
        mode="aspectFill"
        lazy-load
      />
      <view class="notice-title">{{ notice.title }}</view>
      <view v-if="notice.publishedAt" class="notice-time">{{ formatIsoDate(notice.publishedAt) }}</view>
      <view class="notice-body">{{ notice.body }}</view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.cover-image {
  width: 100%;
  height: 320rpx;
  margin-bottom: $spacing-md;
  border-radius: $radius-md;
}

.notice-title {
  font-size: 36rpx;
  font-weight: 600;
}

.notice-time {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.notice-body {
  margin-top: $spacing-md;
  color: $color-text;
  font-size: 28rpx;
  line-height: 1.7;
  white-space: pre-wrap;
}
</style>
