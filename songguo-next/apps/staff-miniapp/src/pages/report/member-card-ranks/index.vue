<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import {
  fetchReportCardProductAnalytics,
  fetchReportCardProductSalesRanking,
  fetchReportMemberCardConsumptionRanking,
} from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

type RankTab = "consumption" | "sales" | "analytics";
const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const activeTab = ref<RankTab>("consumption");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const consumption = ref<Awaited<ReturnType<typeof fetchReportMemberCardConsumptionRanking>> | null>(null);
const sales = ref<Awaited<ReturnType<typeof fetchReportCardProductSalesRanking>> | null>(null);
const analytics = ref<Awaited<ReturnType<typeof fetchReportCardProductAnalytics>> | null>(null);
const page = ref(1);
const lastPage = ref(1);
const requestSeq = ref(0);
const loadedQueryKey = ref("");

const canView = computed(() => session.can("report.rankings.read"));
const tabs = [
  { key: "consumption" as const, name: "会员消费" },
  { key: "sales" as const, name: "卡种销售" },
  { key: "analytics" as const, name: "连锁分析" },
];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));
const selectedPeriod = computed(() => `${selectedYear.value}-${String(selectedMonth.value).padStart(2, "0")}`);
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const currentQueryKey = () => JSON.stringify([
  session.currentSiteId,
  activeTab.value,
  selectedYear.value,
  selectedMonth.value,
]);
const totalsLabel = computed(() => {
  if (activeTab.value === "consumption" && consumption.value) {
    const unvalued = consumption.value.totals.hasUnvalued
      ? ` · ${consumption.value.totals.unvaluedCount} 笔价值待补录`
      : "";
    return `上榜 ${consumption.value.totals.memberCount} 人 · 耗卡 ${consumption.value.totals.consumptionCount} 笔 · 已知价值 ¥${consumption.value.totals.consumptionAmount}${unvalued}`;
  }
  if (activeTab.value === "sales" && sales.value) {
    return `卡项 ${sales.value.totals.cardProductCount} 个 · 售出 ${sales.value.totals.salesCount} 张 · ¥${sales.value.totals.revenue}`;
  }
  if (activeTab.value === "analytics" && analytics.value) {
    return `卡项 ${analytics.value.totals.cardProductCount} 个 · 累计发卡 ${analytics.value.totals.issuedCount} 张`;
  }
  return "";
});

async function load(reset = true) {
  const siteId = session.currentSiteId;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    loading.value = false;
    loadingMore.value = false;
    return;
  }
  const tab = activeTab.value;
  const year = selectedYear.value;
  const month = selectedMonth.value;
  const queryKey = currentQueryKey();
  if (!reset && (loading.value || loadingMore.value || page.value >= lastPage.value || loadedQueryKey.value !== queryKey)) return;
  const requestId = ++requestSeq.value;
  const requestedPage = reset ? 1 : page.value + 1;
  if (reset) {
    loading.value = true;
    errorMessage.value = "";
    page.value = 1;
    lastPage.value = 1;
    loadedQueryKey.value = "";
    if (tab === "consumption") consumption.value = null;
    else if (tab === "sales") sales.value = null;
    else analytics.value = null;
  } else {
    loadingMore.value = true;
  }
  try {
    if (tab === "consumption") {
      const response = await fetchReportMemberCardConsumptionRanking(siteId, year, month, requestedPage);
      if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
      consumption.value = reset || !consumption.value
        ? response
        : { ...response, items: [...consumption.value.items, ...response.items] };
      page.value = requestedPage;
      lastPage.value = response.pagination.lastPage;
    } else if (tab === "sales") {
      const response = await fetchReportCardProductSalesRanking(siteId, year, month, requestedPage);
      if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
      sales.value = reset || !sales.value
        ? response
        : { ...response, items: [...sales.value.items, ...response.items] };
      page.value = requestedPage;
      lastPage.value = response.pagination.lastPage;
    } else {
      const response = await fetchReportCardProductAnalytics(siteId);
      if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
      analytics.value = response;
      page.value = 1;
      lastPage.value = 1;
    }
    loadedQueryKey.value = queryKey;
  } catch (error) {
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    if (reset) errorMessage.value = error instanceof Error ? error.message : "排行榜加载失败";
    else uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
  } finally {
    if (requestId === requestSeq.value) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

async function switchTab(index: number) {
  const tab = tabs[index];
  if (!tab || tab.key === activeTab.value) return;
  activeTab.value = tab.key;
  await load();
}

async function changeMonth(event: { detail: { value: string } }) {
  const [year, month] = event.detail.value.split("-").map(Number);
  if (!year || !month || (year === selectedYear.value && month === selectedMonth.value)) return;
  selectedYear.value = year;
  selectedMonth.value = month;
  await load();
}

async function loadMore() {
  if (activeTab.value === "analytics" || loading.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
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
    <u-empty v-if="!canView" mode="permission" text="暂无排行榜权限" />
    <template v-else>
      <view class="report-head">
        <view><text class="eyebrow">排行与卡项</text><text class="page-title">会员卡排行</text><text class="site-name">{{ currentSiteName }}</text></view>
        <picker v-if="activeTab !== 'analytics'" mode="date" fields="month" :value="selectedPeriod" @change="changeMonth">
          <view class="period-picker"><text>{{ selectedYear }}.{{ String(selectedMonth).padStart(2, "0") }}</text><u-icon name="arrow-down" size="13" color="#989898" /></view>
        </picker>
      </view>
      <view v-if="errorMessage" class="error-card"><u-alert type="error" :description="errorMessage" /><button class="retry-btn" @tap="load()">重新加载</button></view>
      <u-tabs :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <view v-if="totalsLabel" class="totals-card">{{ totalsLabel }}</view>

      <view v-if="activeTab === 'consumption' && consumption" class="list-card">
        <view v-for="item in consumption.items" :key="item.memberId" class="row">
          <text class="rank" :class="{ podium: item.rank <= 3 }">{{ item.rank }}</text>
          <view class="row-main">
            <text class="name">{{ item.memberName || item.memberNo }}</text>
            <text class="meta">
              耗卡记录 {{ item.consumptionCount }} · 已知价值 ¥{{ item.consumptionAmount }}{{ item.hasUnvalued ? ` · ${item.unvaluedCount} 笔待补录` : "" }}
            </text>
          </view>
        </view>
        <u-empty v-if="!consumption.items.length" mode="list" text="该月份暂无会员耗卡排行" />
      </view>

      <view v-if="activeTab === 'sales' && sales" class="list-card">
        <view v-for="item in sales.items" :key="`${item.cardProductId}-${item.rank}`" class="row">
          <text class="rank" :class="{ podium: item.rank <= 3 }">{{ item.rank }}</text>
          <view class="row-main">
            <text class="name">{{ item.cardProductName }}</text>
            <text class="meta">{{ item.salesCount }} 张 · ¥{{ item.revenue }}</text>
          </view>
        </view>
        <u-empty v-if="!sales.items.length" mode="list" text="该月份暂无卡项销售排行" />
      </view>

      <view v-if="activeTab === 'analytics' && analytics" class="list-card">
        <view v-for="item in analytics.items" :key="item.cardProductId" class="row">
          <view class="row-main">
            <text class="name">{{ item.cardProductName }}</text>
            <text class="meta">{{ item.siteName }} · 发卡 {{ item.issuedCount }} · 连锁 {{ item.linkedSiteCount }}</text>
          </view>
        </view>
        <u-empty v-if="!analytics.items.length" mode="list" text="暂无连锁卡项分析" />
      </view>
      <u-loadmore v-if="activeTab !== 'analytics' && ((activeTab === 'consumption' && consumption?.items.length) || (activeTab === 'sales' && sales?.items.length))" :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" @loadmore="loadMore" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.report-head { display: flex; align-items: center; justify-content: space-between; gap: 18rpx; margin-bottom: 20rpx; }
.eyebrow, .page-title, .site-name, .name, .meta { display: block; }
.eyebrow { color: $color-primary; font-size: 21rpx; font-weight: 600; letter-spacing: 3rpx; }
.page-title { margin-top: 4rpx; font-size: 36rpx; font-weight: 650; }
.site-name { margin-top: 6rpx; color: $color-text-tertiary; font-size: 21rpx; }
.period-picker { display: flex; align-items: center; gap: 10rpx; padding: 13rpx 18rpx; background: #fff; border: 1rpx solid $color-border; border-radius: $radius-pill; font-size: 23rpx; }
.totals-card, .list-card { margin-top: 18rpx; padding: 22rpx 24rpx; background: #fff; border-radius: $radius-lg; }
.totals-card { color: $color-text-secondary; font-size: 23rpx; line-height: 34rpx; }
.row { display: flex; align-items: center; gap: 18rpx; min-height: 86rpx; padding: 13rpx 0; border-bottom: 1rpx solid $color-page; }
.row:last-of-type { border-bottom: 0; }
.rank { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 46rpx; height: 46rpx; color: $color-text-secondary; background: $color-page; border-radius: 50%; font-size: 22rpx; font-weight: 600; }
.rank.podium { color: #8b5704; background: #fff0bd; }
.row-main { flex: 1; min-width: 0; }
.name, .meta { display: block; }
.name { font-size: 27rpx; font-weight: 550; }
.meta { margin-top: 6rpx; color: $color-text-tertiary; font-size: 22rpx; }
.error-card { margin: 18rpx 0; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
