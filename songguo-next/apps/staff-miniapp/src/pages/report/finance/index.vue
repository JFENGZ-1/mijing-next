<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
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

const canView = computed(() => session.can("report.finance.read"));
const reportSiteId = computed(() => querySiteId.value ?? session.currentSiteId);
const currentSiteName = computed(() => {
  if (querySiteName.value) return querySiteName.value;
  return session.sites.find((site) => site.id === reportSiteId.value)?.name || "当前场馆";
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

async function loadCalendar() {
  if (!reportSiteId.value || !canView.value) return;
  expandedMonth.value = null;
  dailyByMonth.value = {};
  try {
    calendar.value = await fetchReportFinanceProfitCalendar(reportSiteId.value, selectedYear.value);
  } catch (error) {
    calendar.value = null;
    resolveError(error);
    throw error;
  }
}

async function load() {
  if (!reportSiteId.value || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    const summary = await fetchReportFinanceProfitSummary(reportSiteId.value);
    summaryYears.value = summary.years.map((item) => item.year);
    const current = summary.years.find((item) => item.isCurrentYear);
    if (current) selectedYear.value = current.year;
    else if (summaryYears.value.length) selectedYear.value = summaryYears.value[0];
    await loadCalendar();
  } catch (error) {
    calendar.value = null;
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

async function selectYear(year: number) {
  if (selectedYear.value === year) return;
  selectedYear.value = year;
  loading.value = true;
  errorMessage.value = "";
  try {
    await loadCalendar();
  } catch {
    // error already handled
  } finally {
    loading.value = false;
  }
}

async function toggleMonth(month: number) {
  if (expandedMonth.value === month) {
    expandedMonth.value = null;
    return;
  }
  expandedMonth.value = month;
  if (!reportSiteId.value || dailyByMonth.value[month]) return;
  dailyLoading.value = true;
  try {
    dailyByMonth.value[month] = await fetchReportFinanceProfitDaily(reportSiteId.value, selectedYear.value, month);
  } catch (error) {
    expandedMonth.value = null;
    resolveError(error);
  } finally {
    dailyLoading.value = false;
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
</script>

<template>
  <u-loading-page :loading="loading || dailyLoading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">财务利润</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无财务报表权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

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
.header-row,
.summary-row,
.table-head,
.table-row,
.daily-head,
.daily-row {
  display: grid;
  grid-template-columns: 96rpx 1fr 96rpx 96rpx;
  align-items: center;
  gap: $spacing-xs;
}

.title,
.subtitle,
.summary-label,
.summary-value {
  display: block;
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
  border-color: #1a73e8;
  color: #1a73e8;
  background: #e8f0fe;
}

.summary-card,
.table-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.summary-row + .summary-row {
  margin-top: $spacing-sm;
}

.summary-value {
  text-align: right;
  font-weight: 600;
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
</style>
