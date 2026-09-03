<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import {
  fetchReportFinanceProfitCalendar,
  fetchReportFinanceProfitDaily,
  fetchReportFinanceProfitSummary,
} from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportFinanceProfitCalendar, ReportFinanceProfitDaily, ReportFinanceProfitPeriod } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const dailyLoading = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const summaryYears = ref<number[]>([]);
const selectedYear = ref(new Date().getFullYear());
const calendar = ref<ReportFinanceProfitCalendar | null>(null);
const expandedMonth = ref<number | null>(null);
const dailyByMonth = ref<Record<number, ReportFinanceProfitDaily>>({});
const querySiteId = ref<number | undefined>();
const querySiteName = ref("");
const requestSeq = ref(0);
const dailyRequestSeq = ref(0);

const reportSiteId = computed(() => querySiteId.value ?? session.currentSiteId);
const canView = computed(() => session.sites
  .find((site) => site.id === reportSiteId.value)
  ?.permissions.includes("report.finance.read") ?? false);
const currentSiteName = computed(() => {
  return session.sites.find((site) => site.id === reportSiteId.value)?.name
    || querySiteName.value
    || "当前场馆";
});
const yearOptions = computed(() => {
  if (summaryYears.value.length) return summaryYears.value;
  return [selectedYear.value];
});

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "财务数据加载失败";
}

async function load() {
  const siteId = reportSiteId.value;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    dailyRequestSeq.value += 1;
    loading.value = false;
    dailyLoading.value = false;
    return;
  }
  const requestId = ++requestSeq.value;
  dailyRequestSeq.value += 1;
  dailyLoading.value = false;
  loading.value = true;
  calendar.value = null;
  summaryYears.value = [];
  expandedMonth.value = null;
  dailyByMonth.value = {};
  forbidden.value = false;
  errorMessage.value = "";
  try {
    const summary = await fetchReportFinanceProfitSummary(siteId);
    if (requestId !== requestSeq.value || reportSiteId.value !== siteId) return;
    const years = summary.years.map((item) => item.year);
    const current = summary.years.find((item) => item.isCurrentYear);
    const year = current?.year ?? years[0] ?? selectedYear.value;
    const response = await fetchReportFinanceProfitCalendar(siteId, year);
    if (requestId !== requestSeq.value || reportSiteId.value !== siteId) return;
    summaryYears.value = years;
    selectedYear.value = year;
    calendar.value = response;
  } catch (error) {
    if (requestId !== requestSeq.value || reportSiteId.value !== siteId) return;
    calendar.value = null;
    resolveError(error);
  } finally {
    if (requestId === requestSeq.value) loading.value = false;
  }
}

async function selectYear(year: number) {
  if (selectedYear.value === year) return;
  const siteId = reportSiteId.value;
  if (!siteId || !canView.value) return;
  selectedYear.value = year;
  const requestId = ++requestSeq.value;
  dailyRequestSeq.value += 1;
  loading.value = true;
  dailyLoading.value = false;
  errorMessage.value = "";
  expandedMonth.value = null;
  dailyByMonth.value = {};
  try {
    const response = await fetchReportFinanceProfitCalendar(siteId, year);
    if (requestId !== requestSeq.value || reportSiteId.value !== siteId || selectedYear.value !== year) return;
    calendar.value = response;
  } catch (error) {
    if (requestId !== requestSeq.value || reportSiteId.value !== siteId || selectedYear.value !== year) return;
    calendar.value = null;
    resolveError(error);
  } finally {
    if (requestId === requestSeq.value) loading.value = false;
  }
}

async function toggleMonth(month: number) {
  if (expandedMonth.value === month) {
    expandedMonth.value = null;
    dailyRequestSeq.value += 1;
    dailyLoading.value = false;
    return;
  }
  expandedMonth.value = month;
  const siteId = reportSiteId.value;
  const year = selectedYear.value;
  if (!siteId || dailyByMonth.value[month]) return;
  const requestId = ++dailyRequestSeq.value;
  dailyLoading.value = true;
  try {
    const response = await fetchReportFinanceProfitDaily(siteId, year, month);
    if (
      requestId !== dailyRequestSeq.value
      || reportSiteId.value !== siteId
      || selectedYear.value !== year
      || expandedMonth.value !== month
    ) return;
    dailyByMonth.value[month] = response;
  } catch (error) {
    if (requestId !== dailyRequestSeq.value || expandedMonth.value !== month) return;
    expandedMonth.value = null;
    resolveError(error);
  } finally {
    if (requestId === dailyRequestSeq.value) dailyLoading.value = false;
  }
}

function monthLabel(month: number) {
  return `${month}月`;
}

function periodKey(item: ReportFinanceProfitPeriod) {
  return item.day ? `${item.year}-${item.month}-${item.day}` : `${item.year}-${item.month}`;
}

onLoad((query) => {
  const siteId = Number(query?.siteId);
  if (siteId) querySiteId.value = siteId;
  if (query?.siteName) querySiteName.value = decodeURIComponent(String(query.siteName));
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading || dailyLoading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="eyebrow">经营营收</text>
        <text class="title">营收统计</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无财务报表权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card">
        <u-alert type="error" :description="errorMessage" />
        <button class="retry-btn" @tap="load">重新加载</button>
      </view>

      <view class="section-title">年份</view>
      <scroll-view scroll-x class="chip-scroll" enable-flex>
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
      </scroll-view>

      <view v-if="calendar" class="summary-card">
        <view class="summary-row">
          <text class="summary-label">年度营业额</text>
          <text class="summary-value">¥{{ calendar.totals.revenue }}</text>
        </view>
        <view class="summary-row">
          <text class="summary-label">售卡数</text>
          <text class="summary-value">{{ calendar.totals.cardSalesCount }}</text>
        </view>
        <view class="summary-row">
          <text class="summary-label">新增会员</text>
          <text class="summary-value">{{ calendar.totals.newMemberCount }}</text>
        </view>
      </view>

      <view class="section-title">{{ selectedYear }} 年月历</view>
      <view v-if="calendar?.months.length" class="table-card">
        <view class="table-head">
          <text>月份</text>
          <text>营业额</text>
          <text>售卡</text>
          <text>新增</text>
        </view>
        <view v-for="item in calendar.months" :key="periodKey(item)" class="month-block">
          <view class="table-row" @click="toggleMonth(item.month)">
            <text>{{ monthLabel(item.month) }}</text>
            <text>¥{{ item.revenue }}</text>
            <text>{{ item.cardSalesCount }}</text>
            <text>{{ item.newMemberCount }}</text>
          </view>
          <view v-if="expandedMonth === item.month && dailyByMonth[item.month]" class="daily-panel">
            <view class="daily-head">
              <text>日期</text>
              <text>营业额</text>
              <text>售卡</text>
              <text>新增</text>
            </view>
            <view v-for="day in dailyByMonth[item.month].days" :key="periodKey(day)" class="daily-row">
              <text>{{ day.day }}日</text>
              <text>¥{{ day.revenue }}</text>
              <text>{{ day.cardSalesCount }}</text>
              <text>{{ day.newMemberCount }}</text>
            </view>
            <u-empty v-if="!dailyByMonth[item.month].days.length" mode="list" text="暂无日明细" />
          </view>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无月度数据" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.table-head,
.table-row,
.daily-head,
.daily-row {
  display: grid;
  grid-template-columns: 96rpx 1fr 96rpx 96rpx;
  align-items: center;
  gap: $spacing-xs;
}

.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
}

.eyebrow,
.title,
.subtitle,
.summary-label,
.summary-value {
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
.summary-label {
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
  border-color: #ed920f;
  color: #ed920f;
  background: #fdf3e3;
}

.summary-card,
.table-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.summary-card {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0;
  padding: 28rpx 12rpx;
}

.summary-row {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  padding: 0 10rpx;
  text-align: center;
}

.summary-row + .summary-row {
  border-left: 1rpx solid $color-divider;
}

.summary-value {
  order: -1;
  overflow: hidden;
  font-size: 28rpx;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-label {
  margin-top: 9rpx;
  font-size: 21rpx;
}

.table-head,
.daily-head {
  padding-bottom: $spacing-sm;
  color: $color-text-secondary;
  font-size: 24rpx;
  border-bottom: 1rpx solid $color-border;
}

.table-row {
  min-height: 80rpx;
  padding: $spacing-sm 0;
  border-bottom: 1rpx solid $color-border;
}

.month-block:last-child .table-row {
  border-bottom: none;
}

.daily-panel {
  margin: 0 0 $spacing-sm $spacing-md;
  padding: $spacing-sm;
  background: #f7f9fb;
  border-radius: $radius-sm;
}

.daily-row {
  min-height: 64rpx;
  padding: $spacing-xs 0;
  font-size: 24rpx;
}

.error-card {
  margin-top: $spacing-md;
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
