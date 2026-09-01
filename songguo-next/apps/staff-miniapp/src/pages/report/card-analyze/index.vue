<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchReportCardAnalyzeSummary } from "@/api/reports";
import type { ReportCardAnalyzeSummary } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const summary = ref<ReportCardAnalyzeSummary | null>(null);
const canView = computed(() => session.can("report.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const generatedAt = computed(() => summary.value?.generatedAt?.replace("T", " ").slice(0, 16) || "");

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  summary.value = null;
  errorMessage.value = "";
  try {
    summary.value = await fetchReportCardAnalyzeSummary(session.currentSiteId);
  } catch (error) {
    summary.value = null;
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
    <view class="report-head">
      <view><text class="eyebrow">卡项健康度</text><text class="page-title">会员卡分析</text><text class="site-name">{{ currentSiteName }}</text></view>
      <text v-if="generatedAt" class="updated-at">更新至\n{{ generatedAt }}</text>
    </view>
    <u-empty v-if="!canView" mode="permission" text="暂无会员卡分析权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card"><u-alert type="error" :description="errorMessage" /><button class="retry-btn" @tap="load">重新加载</button></view>

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
          <text class="sheet-label">已知耗卡价值</text>
        </view>
        <view class="sheet-cell">
          <text class="sheet-value remain">¥{{ summary.balanceSheet.remainingValue }}</text>
          <text class="sheet-label">估算剩余价值</text>
        </view>
      </view>
      <view v-if="summary.balanceSheet.hasUnvalued" class="valuation-alert">
        <text>另有 {{ summary.balanceSheet.unvaluedCount }} 笔耗卡价值待核定，当前剩余价值估算可能偏高</text>
      </view>
      <view v-if="summary.balanceSheet.excessConsumedValue !== '0.00'" class="valuation-alert danger">
        <text>已知耗卡价值超出总实收 ¥{{ summary.balanceSheet.excessConsumedValue }}，请核对赠卡或历史收款</text>
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
    </template>
  </view>
</template>

<style scoped lang="scss">
.report-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 20rpx; margin-bottom: 20rpx; }
.eyebrow, .page-title, .site-name { display: block; }
.eyebrow { color: $color-primary; font-size: 21rpx; font-weight: 600; letter-spacing: 3rpx; }
.page-title { margin-top: 5rpx; font-size: 38rpx; font-weight: 650; }
.site-name { margin-top: 7rpx; color: $color-text-tertiary; font-size: 22rpx; }
.updated-at { color: $color-text-disabled; font-size: 19rpx; line-height: 29rpx; text-align: right; white-space: pre-line; }
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
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14rpx;
  margin-top: 24rpx;
}

.sheet-cell {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 10rpx;
  background: $color-surface-grey;
  border-radius: $radius-md;

  &:first-child {
    grid-column: 1 / -1;
  }
}

.sheet-value {
  max-width: 100%;
  overflow: hidden;
  font-size: 32rpx;
  font-weight: 600;
  color: $color-text;
  text-overflow: ellipsis;
  white-space: nowrap;

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

.valuation-alert {
  margin-top: 22rpx;
  padding: 16rpx 20rpx;
  color: $color-warning;
  background: rgba(237, 146, 15, 0.08);
  border-radius: $radius-md;
  font-size: 22rpx;
  line-height: 1.55;

  &.danger {
    color: $color-danger;
    background: rgba(235, 87, 87, 0.08);
  }
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

.error-card { margin-bottom: 20rpx; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
