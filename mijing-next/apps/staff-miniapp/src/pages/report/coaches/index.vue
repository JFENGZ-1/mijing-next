<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import { fetchCoachRankings } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportCoachMonthlyRank, ReportCoachSortBy } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const forbidden = ref(false);
const errorMessage = ref("");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const sortBy = ref<ReportCoachSortBy>("total");
const ranking = ref<ReportCoachMonthlyRank | null>(null);
const requestSeq = ref(0);

const canView = computed(() => session.can("report.coach.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const selectedPeriod = computed(() => `${selectedYear.value}-${String(selectedMonth.value).padStart(2, "0")}`);
const sortOptions = [
  { value: "total" as const, label: "总课时" },
  { value: "group" as const, label: "团课" },
  { value: "private" as const, label: "私教" },
];

const totalsLabel = computed(() => {
  if (!ranking.value) return "";
  const { coachCount, groupSessionCount, privateSessionCount } = ranking.value.totals;
  return `教练 ${coachCount} · 团课 ${groupSessionCount} · 私教 ${privateSessionCount}`;
});

function staffLabel(name: string | null, staffId: number) {
  return name?.trim() || `员工 #${staffId}`;
}

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "教练月报加载失败";
}

async function load() {
  const siteId = session.currentSiteId;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    loading.value = false;
    return;
  }
  const year = selectedYear.value;
  const month = selectedMonth.value;
  const sort = sortBy.value;
  const requestId = ++requestSeq.value;
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    const response = await fetchCoachRankings(siteId, year, month, sort);
    if (
      requestId !== requestSeq.value
      || session.currentSiteId !== siteId
      || selectedYear.value !== year
      || selectedMonth.value !== month
      || sortBy.value !== sort
    ) return;
    ranking.value = response;
  } catch (error) {
    if (requestId !== requestSeq.value) return;
    ranking.value = null;
    resolveError(error);
  } finally {
    if (requestId === requestSeq.value) loading.value = false;
  }
}

async function onPeriodChange(event: { detail: { value: string } }) {
  const [year, month] = event.detail.value.split("-").map(Number);
  if (!year || !month || (selectedYear.value === year && selectedMonth.value === month)) return;
  selectedYear.value = year;
  selectedMonth.value = month;
  await load();
}

async function selectSortBy(value: ReportCoachSortBy) {
  if (sortBy.value === value) return;
  sortBy.value = value;
  await load();
}

function openDetail(item: ReportCoachMonthlyRank["items"][number]) {
  uni.navigateTo({
    url: `/pages/report/coaches/detail?staffId=${item.staffId}&staffName=${encodeURIComponent(staffLabel(item.staffName, item.staffId))}&year=${selectedYear.value}&month=${selectedMonth.value}`,
  });
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
    <view class="header-row report-head">
      <view>
        <text class="eyebrow">课程履约</text>
        <text class="title">教练月报</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
      <picker mode="date" fields="month" :value="selectedPeriod" @change="onPeriodChange">
        <view class="period-picker">{{ selectedPeriod.replace("-", " · ") }} <u-icon name="arrow-down" size="13" /></view>
      </picker>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无教练月报权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card">
        <view>
          <text class="error-title">教练月报暂未更新</text>
          <text class="error-detail">{{ errorMessage }}</text>
        </view>
        <button class="retry-btn" @tap="load">重新加载</button>
      </view>

      <view v-if="ranking" class="sort-panel">
        <text class="section-title">统计口径</text>
        <view class="chip-row">
        <view
          v-for="option in sortOptions"
          :key="option.value"
          class="chip"
          :class="{ active: option.value === sortBy }"
          @click="selectSortBy(option.value)"
        >
          {{ option.label }}
        </view>
        </view>
      </view>

      <view v-if="totalsLabel" class="totals-card">{{ totalsLabel }}</view>

      <view v-if="ranking" class="list-card">
        <view
          v-for="item in ranking.items"
          :key="item.staffId"
          class="rank-row"
          @click="openDetail(item)"
        >
          <text class="rank-no">{{ item.rank }}</text>
          <view class="rank-main">
            <text class="rank-name">{{ staffLabel(item.staffName, item.staffId) }}</text>
            <text class="rank-meta">
              团课 {{ item.groupSessionCount }} · 私教 {{ item.privateSessionCount }} · 合计 {{ item.completedSessionCount }}
            </text>
          </view>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
        <u-empty v-if="!ranking.items.length" mode="list" text="暂无教练数据" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.title,
.subtitle,
.rank-name,
.rank-meta,
.eyebrow,
.error-title,
.error-detail {
  display: block;
}

.report-head {
  align-items: flex-end;
  margin-bottom: $spacing-md;
}

.eyebrow {
  margin-bottom: 6rpx;
  color: #d98200;
  font-size: 22rpx;
  font-weight: 600;
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

.period-picker {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 14rpx 22rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  background: $color-surface;
  color: $color-text;
  font-size: 24rpx;
}

.sort-panel {
  padding: $spacing-md;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
  background: $color-surface;
}

.section-title {
  display: block;
  margin-bottom: 14rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.chip-row {
  display: flex;
  gap: $spacing-sm;
}

.chip {
  flex: 1;
  padding: 12rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  background: $color-surface;
  font-size: 26rpx;
  text-align: center;
}

.chip.active {
  border-color: #ed920f;
  color: #ed920f;
  background: #fdf3e3;
}

.totals-card,
.list-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.totals-card {
  color: $color-text;
  font-size: 26rpx;
}

.error-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-md;
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  border: 1rpx solid rgba(225, 82, 82, 0.18);
  border-radius: $radius-md;
  background: #fff6f5;
}

.error-title {
  color: $color-danger;
  font-size: 26rpx;
  font-weight: 600;
}

.error-detail {
  margin-top: 6rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.retry-btn {
  flex: none;
  margin: 0;
  padding: 0 24rpx;
  color: $color-danger;
  font-size: 24rpx;
  line-height: 56rpx;
  border: 1rpx solid currentColor;
  border-radius: 999rpx;
  background: transparent;
}

.retry-btn::after {
  border: 0;
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
  color: #ed920f;
}

.rank-main {
  flex: 1;
}

.rank-name {
  font-size: 28rpx;
  font-weight: 500;
}
</style>
