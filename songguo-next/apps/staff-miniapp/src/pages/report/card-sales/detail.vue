<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { fetchReportCardSalesDetail } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportCardSalesDetail } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const year = ref(new Date().getFullYear());
const month = ref(new Date().getMonth() + 1);
const cardProductId = ref<number | null>(null);
const cardProductName = ref("售卡明细");
const detail = ref<ReportCardSalesDetail | null>(null);
const loadingMore = ref(false);
const page = ref(1);
const lastPage = ref(1);
const requestSeq = ref(0);
const loadedQueryKey = ref("");

const canView = computed(() => session.can("report.read"));

function currentQueryKey() {
  return JSON.stringify([
    session.currentSiteId,
    year.value,
    month.value,
    cardProductId.value,
  ]);
}

onLoad((query) => {
  if (query?.year) year.value = Number(query.year);
  if (query?.month) month.value = Number(query.month);
  if (query?.cardProductId) cardProductId.value = Number(query.cardProductId);
  if (query?.cardProductName) cardProductName.value = decodeURIComponent(String(query.cardProductName));
});

async function load(reset = true) {
  const siteId = session.currentSiteId;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    loading.value = false;
    loadingMore.value = false;
    return;
  }
  const requestedYear = year.value;
  const requestedMonth = month.value;
  const requestedCardProductId = cardProductId.value;
  const queryKey = currentQueryKey();
  if (!reset && (loading.value || loadingMore.value || page.value >= lastPage.value || loadedQueryKey.value !== queryKey)) return;
  const requestId = ++requestSeq.value;
  const requestedPage = reset ? 1 : page.value + 1;
  if (reset) {
    loading.value = true;
    errorMessage.value = "";
    page.value = 1;
    lastPage.value = 1;
    detail.value = null;
    loadedQueryKey.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const response = await fetchReportCardSalesDetail(
      siteId,
      requestedYear,
      requestedMonth,
      requestedCardProductId,
      requestedPage,
    );
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    detail.value = reset || !detail.value
      ? response
      : { ...response, items: [...detail.value.items, ...response.items] };
    page.value = requestedPage;
    lastPage.value = response.pagination.lastPage;
    loadedQueryKey.value = queryKey;
  } catch (error) {
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    if (reset) errorMessage.value = error instanceof Error ? error.message : "售卡明细加载失败";
    else uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
  } finally {
    if (requestId === requestSeq.value) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

async function loadMore() {
  if (loading.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

function formatPaidAt(value: string | null) {
  if (!value) return "支付时间待补充";
  return value.replace("T", " ").slice(0, 16);
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});

onReachBottom(loadMore);
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canView" mode="permission" text="暂无报表权限" />
    <template v-else>
      <view class="report-head">
        <text class="eyebrow">{{ year }}.{{ String(month).padStart(2, "0") }}</text>
        <text class="page-title">{{ cardProductName }}</text>
        <text class="page-subtitle">逐笔核对会员、订单与实收金额</text>
      </view>
      <view v-if="errorMessage" class="error-card">
        <u-alert type="error" :description="errorMessage" />
        <button class="retry-btn" @tap="load()">重新加载</button>
      </view>
      <view v-if="detail" class="totals-card">
        <view><text class="total-label">本月实收</text><text class="total-money">¥{{ detail.totals.revenue }}</text></view>
        <view class="total-side"><text class="total-count">{{ detail.totals.salesCount }}</text><text class="total-label">成交单数</text></view>
      </view>
      <view v-if="detail?.items.length" class="list-card">
        <view v-for="item in detail.items" :key="item.orderId" class="row">
          <view class="row-main">
            <text class="name">{{ item.memberName || "未命名会员" }}</text>
            <text class="meta">{{ item.orderNo }}</text>
            <text class="date">{{ formatPaidAt(item.paidAt) }}</text>
          </view>
          <text class="row-money">¥{{ item.amount }}</text>
        </view>
        <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" @loadmore="loadMore" />
      </view>
      <view v-else-if="detail && !errorMessage" class="empty-card">
        <u-empty mode="list" text="该卡项本月暂无成交" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.eyebrow, .page-title, .page-subtitle, .total-label, .total-money, .total-count, .name, .meta, .date { display: block; }
.eyebrow { color: $color-primary; font-size: 21rpx; font-weight: 600; letter-spacing: 2rpx; }
.page-title { margin-top: 5rpx; font-size: 36rpx; font-weight: 650; }
.page-subtitle { margin-top: 8rpx; color: $color-text-tertiary; font-size: 22rpx; }
.totals-card, .list-card, .empty-card { margin-top: 20rpx; padding: 24rpx; background: #fff; border-radius: $radius-lg; }
.totals-card { display: flex; align-items: flex-end; justify-content: space-between; }
.total-label { color: $color-text-tertiary; font-size: 21rpx; }
.total-money { margin-top: 7rpx; color: $color-primary; font-size: 44rpx; font-weight: 600; }
.total-side { text-align: right; }
.total-count { font-size: 32rpx; font-weight: 600; }
.row { display: flex; align-items: center; gap: 18rpx; min-height: 108rpx; padding: 15rpx 0; border-bottom: 1rpx solid $color-page; }
.row-main { flex: 1; min-width: 0; }
.row:last-child { border-bottom: 0; }
.name { font-size: 27rpx; font-weight: 550; }
.meta, .date { margin-top: 5rpx; color: $color-text-tertiary; font-size: 21rpx; }
.row-money { flex-shrink: 0; color: $color-primary; font-size: 27rpx; font-weight: 600; }
.empty-card { padding: 56rpx 24rpx; }
.error-card { margin-top: 20rpx; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
