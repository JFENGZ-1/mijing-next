<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import {
  fetchReportCourseAttendanceRanking,
  fetchReportOrderRanking,
  fetchReportPointsRanking,
  fetchReportSalesStaffRanking,
} from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  ReportCourseAttendanceRanking,
  ReportOrderRanking,
  ReportPointsRanking,
  ReportSalesStaffRanking,
} from "@/types/reports";

type RankingTab = "orders" | "course" | "points" | "sales";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const activeTab = ref<RankingTab>("orders");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const orderRanking = ref<ReportOrderRanking | null>(null);
const courseRanking = ref<ReportCourseAttendanceRanking | null>(null);
const pointsRanking = ref<ReportPointsRanking | null>(null);
const salesRanking = ref<ReportSalesStaffRanking | null>(null);
const page = ref(1);
const lastPage = ref(1);
const requestSeq = ref(0);
const loadedQueryKey = ref("");

const canView = computed(() => session.can("report.rankings.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const tabs = [
  { key: "orders" as const, name: "购卡实付榜" },
  { key: "course" as const, name: "上课榜" },
  { key: "points" as const, name: "积分榜" },
  { key: "sales" as const, name: "销售榜" },
];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));
const selectedPeriod = computed(() => `${selectedYear.value}-${String(selectedMonth.value).padStart(2, "0")}`);

function currentQueryKey() {
  return JSON.stringify([
    session.currentSiteId,
    activeTab.value,
    selectedYear.value,
    selectedMonth.value,
  ]);
}

const activeTotals = computed(() => {
  if (activeTab.value === "orders") {
    return orderRanking.value
      ? `会员 ${orderRanking.value.totals.memberCount} · 订单 ${orderRanking.value.totals.orderCount} · ¥${orderRanking.value.totals.totalSpend}`
      : "";
  }
  if (activeTab.value === "course") {
    return courseRanking.value
      ? `会员 ${courseRanking.value.totals.memberCount} · 签到 ${courseRanking.value.totals.completedAppointments}`
      : "";
  }
  if (activeTab.value === "points") {
    return pointsRanking.value
      ? `会员 ${pointsRanking.value.totals.memberCount} · 积分 ${pointsRanking.value.totals.creditPoints}`
      : "";
  }
  return salesRanking.value
    ? `员工 ${salesRanking.value.totals.staffCount} · 售卡 ${salesRanking.value.totals.cardSalesCount} · ¥${salesRanking.value.totals.revenue}`
    : "";
});

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "排行榜加载失败";
}

async function fetchActiveRanking(siteId: number, tab: RankingTab, year: number, month: number, requestedPage: number) {
  if (tab === "orders") return fetchReportOrderRanking(siteId, year, month, requestedPage);
  if (tab === "course") return fetchReportCourseAttendanceRanking(siteId, year, month, requestedPage);
  if (tab === "points") return fetchReportPointsRanking(siteId, year, month, requestedPage);
  return fetchReportSalesStaffRanking(siteId, year, month, requestedPage);
}

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
    forbidden.value = false;
    errorMessage.value = "";
    page.value = 1;
    lastPage.value = 1;
    loadedQueryKey.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const response = await fetchActiveRanking(siteId, tab, year, month, requestedPage);
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    if (tab === "orders") {
      const result = response as ReportOrderRanking;
      orderRanking.value = reset || !orderRanking.value
        ? result
        : { ...result, items: [...orderRanking.value.items, ...result.items] };
    } else if (tab === "course") {
      const result = response as ReportCourseAttendanceRanking;
      courseRanking.value = reset || !courseRanking.value
        ? result
        : { ...result, items: [...courseRanking.value.items, ...result.items] };
    } else if (tab === "points") {
      const result = response as ReportPointsRanking;
      pointsRanking.value = reset || !pointsRanking.value
        ? result
        : { ...result, items: [...pointsRanking.value.items, ...result.items] };
    } else {
      const result = response as ReportSalesStaffRanking;
      salesRanking.value = reset || !salesRanking.value
        ? result
        : { ...result, items: [...salesRanking.value.items, ...result.items] };
    }
    page.value = requestedPage;
    lastPage.value = response.pagination.lastPage;
    loadedQueryKey.value = queryKey;
  } catch (error) {
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    if (reset) {
      orderRanking.value = null;
      courseRanking.value = null;
      pointsRanking.value = null;
      salesRanking.value = null;
      resolveError(error);
    } else {
      uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
    }
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

function openPointsConfig() {
  uni.navigateTo({ url: "/pages/settings/points-config/index" });
}

async function changeMonth(event: { detail: { value: string } }) {
  const [year, month] = event.detail.value.split("-").map(Number);
  if (!year || !month || (year === selectedYear.value && month === selectedMonth.value)) return;
  selectedYear.value = year;
  selectedMonth.value = month;
  await load();
}

async function loadMore() {
  if (loading.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

function memberName(name: string | null, memberNo: string) {
  return name?.trim() || memberNo;
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
    <view class="header-row">
      <view>
        <text class="eyebrow">经营排行</text>
        <text class="title">排行榜</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
      <view class="head-actions">
        <picker mode="date" fields="month" :value="selectedPeriod" @change="changeMonth">
          <view class="period-picker"><text>{{ selectedYear }}.{{ String(selectedMonth).padStart(2, "0") }}</text><u-icon name="arrow-down" size="13" color="#989898" /></view>
        </picker>
        <button v-if="session.can('points.config.read')" class="config-link" @click="openPointsConfig">积分设置</button>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无排行榜权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card"><u-alert type="error" :description="errorMessage" /><button class="retry-btn" @tap="load()">重新加载</button></view>

      <u-tabs :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <view v-if="activeTotals" class="totals-card">{{ activeTotals }}</view>

      <view v-if="activeTab === 'orders' && orderRanking" class="list-card">
        <view v-for="item in orderRanking.items" :key="item.memberId" class="rank-row">
          <text class="rank-no" :class="{ podium: item.rank <= 3 }">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="rank-meta">{{ item.orderCount }} 单 · ¥{{ item.totalSpend }}</text>
          </view>
        </view>
        <u-empty v-if="!orderRanking.items.length" mode="list" text="暂无排行数据" />
      </view>

      <view v-if="activeTab === 'course' && courseRanking" class="list-card">
        <view v-for="item in courseRanking.items" :key="item.memberId" class="rank-row">
          <text class="rank-no" :class="{ podium: item.rank <= 3 }">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="rank-meta">签到 {{ item.completedAppointments }} 次</text>
          </view>
        </view>
        <u-empty v-if="!courseRanking.items.length" mode="list" text="暂无排行数据" />
      </view>

      <view v-if="activeTab === 'points' && pointsRanking" class="list-card">
        <view v-for="item in pointsRanking.items" :key="item.memberId" class="rank-row">
          <text class="rank-no" :class="{ podium: item.rank <= 3 }">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="rank-meta">{{ item.creditPoints }} 积分</text>
          </view>
        </view>
        <u-empty v-if="!pointsRanking.items.length" mode="list" text="暂无排行数据" />
      </view>

      <view v-if="activeTab === 'sales' && salesRanking" class="list-card">
        <view v-for="item in salesRanking.items" :key="item.staffId" class="rank-row">
          <text class="rank-no" :class="{ podium: item.rank <= 3 }">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ item.staffName }}</text>
            <text class="rank-meta">{{ item.cardSalesCount }} 张 · ¥{{ item.revenue }} · {{ item.memberCount }} 人</text>
          </view>
        </view>
        <u-empty v-if="!salesRanking.items.length" mode="list" text="暂无排行数据" />
      </view>
      <u-loadmore v-if="(activeTab === 'orders' && orderRanking?.items.length) || (activeTab === 'course' && courseRanking?.items.length) || (activeTab === 'points' && pointsRanking?.items.length) || (activeTab === 'sales' && salesRanking?.items.length)" :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" @loadmore="loadMore" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.eyebrow,
.title,
.subtitle,
.rank-name,
.rank-meta {
  display: block;
}

.eyebrow {
  color: $color-primary;
  font-size: 21rpx;
  font-weight: 600;
  letter-spacing: 3rpx;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.rank-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.totals-card,
.list-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.rank-row {
  display: flex;
  align-items: center;
  gap: $spacing-md;
  padding: $spacing-sm 0;
  border-bottom: 1rpx solid $color-border;
}

.rank-row:last-child {
  border-bottom: none;
}

.rank-no {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 46rpx;
  height: 46rpx;
  color: $color-text-secondary;
  background: $color-page;
  border-radius: 50%;
  font-size: 22rpx;
  font-weight: 600;
}

.rank-no.podium {
  color: #8b5704;
  background: #fff0bd;
}

.rank-name {
  font-size: 28rpx;
  font-weight: 500;
}

.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.head-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8rpx;
}

.period-picker {
  display: flex;
  align-items: center;
  gap: 9rpx;
  padding: 12rpx 17rpx;
  background: #fff;
  border: 1rpx solid $color-border;
  border-radius: $radius-pill;
  font-size: 23rpx;
}

.config-link {
  margin: 0;
  padding: 8rpx 24rpx;
  color: $color-primary;
  font-size: 24rpx;
  background: rgba(237, 146, 15, 0.08);
  border-radius: 999rpx;
}

.config-link::after {
  border: 0;
}

.error-card {
  margin: 18rpx 0;
}

.retry-btn {
  width: 220rpx;
  height: 64rpx;
  margin: 18rpx 0 0;
  color: $color-primary;
  background: #fff;
  border: 1rpx solid rgba(237, 146, 15, 0.35);
  border-radius: 32rpx;
  font-size: 23rpx;
  line-height: 62rpx;
}

.retry-btn::after {
  border: 0;
}
</style>
