<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchReportCardSalesSummary } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportCardSalesSummary } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const summary = ref<ReportCardSalesSummary | null>(null);
const requestSeq = ref(0);

const canView = computed(() => session.can("report.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const selectedPeriod = computed(() => `${selectedYear.value}-${String(selectedMonth.value).padStart(2, "0")}`);
const asOfLabel = computed(() => summary.value?.asOf?.replace("T", " ").slice(0, 16) || "");

async function load() {
  const siteId = session.currentSiteId;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    loading.value = false;
    return;
  }
  const year = selectedYear.value;
  const month = selectedMonth.value;
  const requestId = ++requestSeq.value;
  loading.value = true;
  errorMessage.value = "";
  summary.value = null;
  try {
    const response = await fetchReportCardSalesSummary(siteId, year, month);
    if (
      requestId !== requestSeq.value
      || session.currentSiteId !== siteId
      || selectedYear.value !== year
      || selectedMonth.value !== month
    ) return;
    summary.value = response;
  } catch (error) {
    if (requestId !== requestSeq.value) return;
    errorMessage.value = error instanceof Error ? error.message : "售卡统计加载失败";
  } finally {
    if (requestId === requestSeq.value) loading.value = false;
  }
}

function openDetail(item: ReportCardSalesSummary["items"][number]) {
  const cardProductQuery = item.cardProductId != null ? `&cardProductId=${item.cardProductId}` : "";
  const query = `year=${selectedYear.value}&month=${selectedMonth.value}${cardProductQuery}&cardProductName=${encodeURIComponent(item.cardProductName)}`;
  uni.navigateTo({ url: `/pages/report/card-sales/detail?${query}` });
}

async function changeMonth(event: { detail: { value: string } }) {
  const [year, month] = event.detail.value.split("-").map(Number);
  if (!year || !month || (year === selectedYear.value && month === selectedMonth.value)) return;
  selectedYear.value = year;
  selectedMonth.value = month;
  await load();
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
    <u-empty v-if="!canView" mode="permission" text="暂无报表权限" />
    <template v-else>
      <view class="report-head">
        <view>
          <text class="eyebrow">经营收入</text>
          <text class="page-title">售卡统计</text>
          <text class="site-name">{{ currentSiteName }}</text>
        </view>
        <picker mode="date" fields="month" :value="selectedPeriod" @change="changeMonth">
          <view class="period-picker">
            <text>{{ selectedYear }}.{{ String(selectedMonth).padStart(2, "0") }}</text>
            <u-icon name="arrow-down" size="13" color="#989898" />
          </view>
        </picker>
      </view>

      <view v-if="errorMessage" class="error-card">
        <u-alert type="error" :description="errorMessage" />
        <button class="retry-btn" @tap="load">重新加载</button>
      </view>

      <view v-if="summary" class="totals-card">
        <view>
          <text class="total-label">本月实收</text>
          <text class="total-money">¥{{ summary.totals.revenue }}</text>
        </view>
        <view class="total-side">
          <text class="total-count">{{ summary.totals.salesCount }}</text>
          <text class="total-label">成交单数</text>
        </view>
        <text v-if="asOfLabel" class="updated-at">数据更新至 {{ asOfLabel }}</text>
      </view>

      <view v-if="summary?.items.length" class="list-card">
        <view v-for="item in summary.items" :key="`${item.cardProductId}-${item.cardProductName}`" class="row" @tap="openDetail(item)">
          <view class="row-main">
            <text class="name">{{ item.cardProductName }}</text>
            <text class="meta">售出 {{ item.salesCount }} 张</text>
          </view>
          <text class="row-money">¥{{ item.revenue }}</text>
          <u-icon name="arrow-right" size="16" color="#989898" />
        </view>
      </view>
      <view v-else-if="summary && !errorMessage" class="empty-card">
        <u-empty mode="list" text="该月份暂无售卡记录" />
        <text class="empty-hint">可切换月份，或下拉刷新最新收款结果</text>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.report-head { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; }
.eyebrow, .page-title, .site-name, .total-label, .total-money, .total-count, .updated-at, .name, .meta { display: block; }
.eyebrow { color: $color-primary; font-size: 21rpx; font-weight: 600; letter-spacing: 3rpx; }
.page-title { margin-top: 4rpx; font-size: 38rpx; font-weight: 650; }
.site-name { margin-top: 7rpx; color: $color-text-tertiary; font-size: 22rpx; }
.period-picker { display: flex; align-items: center; gap: 10rpx; padding: 13rpx 18rpx; background: #fff; border: 1rpx solid $color-border; border-radius: $radius-pill; font-size: 24rpx; }
.totals-card, .list-card, .empty-card { margin-top: 20rpx; padding: 24rpx; background: #fff; border-radius: $radius-lg; }
.totals-card { position: relative; display: flex; align-items: flex-end; justify-content: space-between; gap: 20rpx; padding-bottom: 54rpx; }
.total-label { color: $color-text-tertiary; font-size: 21rpx; }
.total-money { margin-top: 7rpx; color: $color-primary; font-size: 48rpx; font-weight: 600; }
.total-side { text-align: right; }
.total-count { font-size: 34rpx; font-weight: 600; }
.updated-at { position: absolute; right: 24rpx; bottom: 20rpx; left: 24rpx; padding-top: 12rpx; color: $color-text-disabled; border-top: 1rpx solid $color-page; font-size: 20rpx; }
.row { display: flex; align-items: center; gap: 16rpx; min-height: 86rpx; padding: 14rpx 0; border-bottom: 1rpx solid $color-page; }
.row:last-child { border-bottom: 0; }
.row-main { flex: 1; min-width: 0; }
.name, .meta { display: block; }
.name { overflow: hidden; font-size: 27rpx; font-weight: 550; text-overflow: ellipsis; white-space: nowrap; }
.meta { margin-top: 6rpx; color: $color-text-tertiary; font-size: 22rpx; }
.row-money { flex-shrink: 0; color: $color-primary; font-size: 25rpx; font-weight: 600; }
.empty-card { padding: 54rpx 24rpx; }
.empty-hint { display: block; margin-top: 12rpx; color: $color-text-disabled; font-size: 21rpx; text-align: center; }
.error-card { margin-top: 20rpx; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
