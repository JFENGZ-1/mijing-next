<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
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
const forbidden = ref(false);
const errorMessage = ref("");
const activeTab = ref<RankingTab>("orders");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const orderRanking = ref<ReportOrderRanking | null>(null);
const courseRanking = ref<ReportCourseAttendanceRanking | null>(null);
const pointsRanking = ref<ReportPointsRanking | null>(null);
const salesRanking = ref<ReportSalesStaffRanking | null>(null);

const canView = computed(() => session.can("report.rankings.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const tabs = [
  { key: "orders" as const, name: "消费榜" },
  { key: "course" as const, name: "上课榜" },
  { key: "points" as const, name: "积分榜" },
  { key: "sales" as const, name: "销售榜" },
];
const monthOptions = Array.from({ length: 12 }, (_, index) => index + 1);
const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current - 1, current, current + 1];
});
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));

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

async function loadActiveRanking() {
  if (!session.currentSiteId || !canView.value) return;
  const siteId = session.currentSiteId;
  const year = selectedYear.value;
  const month = selectedMonth.value;
  if (activeTab.value === "orders") {
    orderRanking.value = await fetchReportOrderRanking(siteId, year, month);
    return;
  }
  if (activeTab.value === "course") {
    courseRanking.value = await fetchReportCourseAttendanceRanking(siteId, year, month);
    return;
  }
  if (activeTab.value === "points") {
    pointsRanking.value = await fetchReportPointsRanking(siteId, year, month);
    return;
  }
  salesRanking.value = await fetchReportSalesStaffRanking(siteId, year, month);
}

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    await loadActiveRanking();
  } catch (error) {
    orderRanking.value = null;
    courseRanking.value = null;
    pointsRanking.value = null;
    salesRanking.value = null;
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

async function switchTab(index: number) {
  const tab = tabs[index];
  if (!tab || tab.key === activeTab.value) return;
  activeTab.value = tab.key;
  await load();
}

async function selectYear(year: number) {
  if (selectedYear.value === year) return;
  selectedYear.value = year;
  await load();
}

async function selectMonth(month: number) {
  if (selectedMonth.value === month) return;
  selectedMonth.value = month;
  await load();
}

function memberName(name: string | null, memberNo: string) {
  return name?.trim() || memberNo;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">排行榜</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无排行榜权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <u-tabs :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <view class="section-title">年份</view>
      <view class="chip-row">
        <view
          v-for="year in yearOptions"
          :key="year"
          class="chip"
          :class="{ active: year === selectedYear }"
          @click="selectYear(year)"
        >
          {{ year }}
        </view>
      </view>

      <view class="section-title">月份</view>
      <scroll-view scroll-x class="chip-scroll" enable-flex>
        <view class="chip-row">
          <view
            v-for="month in monthOptions"
            :key="month"
            class="chip"
            :class="{ active: month === selectedMonth }"
            @click="selectMonth(month)"
          >
            {{ month }}月
          </view>
        </view>
      </scroll-view>

      <view v-if="activeTotals" class="totals-card">{{ activeTotals }}</view>

      <view v-if="activeTab === 'orders' && orderRanking" class="list-card">
        <view v-for="item in orderRanking.items" :key="item.memberId" class="rank-row">
          <text class="rank-no">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="rank-meta">{{ item.orderCount }} 单 · ¥{{ item.totalSpend }}</text>
          </view>
        </view>
        <u-empty v-if="!orderRanking.items.length" mode="list" text="暂无排行数据" />
      </view>

      <view v-if="activeTab === 'course' && courseRanking" class="list-card">
        <view v-for="item in courseRanking.items" :key="item.memberId" class="rank-row">
          <text class="rank-no">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="rank-meta">签到 {{ item.completedAppointments }} 次</text>
          </view>
        </view>
        <u-empty v-if="!courseRanking.items.length" mode="list" text="暂无排行数据" />
      </view>

      <view v-if="activeTab === 'points' && pointsRanking" class="list-card">
        <view v-for="item in pointsRanking.items" :key="item.memberId" class="rank-row">
          <text class="rank-no">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="rank-meta">{{ item.creditPoints }} 积分</text>
          </view>
        </view>
        <u-empty v-if="!pointsRanking.items.length" mode="list" text="暂无排行数据" />
      </view>

      <view v-if="activeTab === 'sales' && salesRanking" class="list-card">
        <view v-for="item in salesRanking.items" :key="item.staffId" class="rank-row">
          <text class="rank-no">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ item.staffName }}</text>
            <text class="rank-meta">{{ item.cardSalesCount }} 张 · ¥{{ item.revenue }} · {{ item.memberCount }} 人</text>
          </view>
        </view>
        <u-empty v-if="!salesRanking.items.length" mode="list" text="暂无排行数据" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.title,
.subtitle,
.rank-name,
.rank-meta {
  display: block;
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

.chip-scroll {
  width: 100%;
  white-space: nowrap;
}

.chip-row {
  display: inline-flex;
  gap: $spacing-sm;
  padding-bottom: $spacing-xs;
}

.chip {
  padding: 12rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  background: $color-surface;
  font-size: 26rpx;
}

.chip.active {
  border-color: #1a73e8;
  color: #1a73e8;
  background: #e8f0fe;
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
  width: 48rpx;
  text-align: center;
  font-size: 30rpx;
  font-weight: 600;
  color: #1a73e8;
}

.rank-name {
  font-size: 28rpx;
  font-weight: 500;
}
</style>
