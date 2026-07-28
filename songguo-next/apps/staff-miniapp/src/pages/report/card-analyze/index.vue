<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchReportCardAnalyzeSummary } from "@/api/reports";
import type { ReportCardAnalyzeSummary } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const summary = ref<ReportCardAnalyzeSummary | null>(null);

async function load() {
  if (!session.currentSiteId) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    summary.value = await fetchReportCardAnalyzeSummary(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员卡分析加载失败";
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <!-- 资产负债表（对标原版：总收入/已耗卡金额/剩余价值） -->
    <view v-if="summary" class="sg-card">
      <text class="card-title">资产负债表</text>
      <view class="sheet-row">
        <view class="sheet-cell">
          <text class="sheet-value sg-money">¥{{ summary.balanceSheet.totalRevenue }}</text>
          <text class="sheet-label">总收入</text>
        </view>
        <view class="sheet-cell">
          <text class="sheet-value">¥{{ summary.balanceSheet.consumedValue }}</text>
          <text class="sheet-label">已耗卡金额</text>
        </view>
        <view class="sheet-cell">
          <text class="sheet-value remain">¥{{ summary.balanceSheet.remainingValue }}</text>
          <text class="sheet-label">剩余价值</text>
        </view>
      </view>
      <view class="sheet-notes">
        <text v-for="note in summary.balanceSheet.notes" :key="note" class="note-line">{{ note }}</text>
      </view>
    </view>

    <!-- 卡状态分层（对标原版会员卡分析 9 档） -->
    <view v-if="summary" class="sg-card block-card">
      <text class="card-title">会员卡分析</text>
      <view
        v-for="item in summary.cards"
        :key="item.key"
        class="analyze-row"
      >
        <view class="analyze-main">
          <text class="row-label">{{ item.label }}</text>
          <text class="row-hint">{{ item.hint }}</text>
        </view>
        <text class="row-count">{{ item.count }}</text>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.card-title {
  display: block;
  margin-bottom: 8rpx;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.block-card {
  margin-top: $spacing-md;
}

.sheet-row {
  display: flex;
  margin-top: 24rpx;
}

.sheet-cell {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;

  & + & {
    border-left: 1rpx solid $color-divider;
  }
}

.sheet-value {
  font-size: 32rpx;
  font-weight: 600;
  color: $color-text;

  &.remain {
    color: $color-success;
  }
}

.sheet-label {
  margin-top: 10rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.sheet-notes {
  margin-top: 28rpx;
  padding-top: 20rpx;
  border-top: 1rpx solid $color-page;
}

.note-line {
  display: block;
  margin-top: 8rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
  line-height: 1.6;
}

.analyze-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $color-page;

  &:last-child {
    border-bottom: none;
  }
}

.analyze-main {
  display: flex;
  flex-direction: column;
}

.row-label {
  font-size: 28rpx;
  color: $color-text;
}

.row-hint {
  margin-top: 6rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
}

.row-count {
  font-size: 36rpx;
  font-weight: 600;
  color: $color-text;
}
</style>
