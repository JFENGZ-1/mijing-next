<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
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

const canView = computed(() => session.can("report.coach.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const monthOptions = Array.from({ length: 12 }, (_, index) => index + 1);
const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current - 1, current, current + 1];
});
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
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    ranking.value = await fetchCoachRankings(
      session.currentSiteId,
      selectedYear.value,
      selectedMonth.value,
      sortBy.value,
    );
  } catch (error) {
    ranking.value = null;
    resolveError(error);
  } finally {
    loading.value = false;
  }
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
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">教练月报</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无教练月报权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="section-title">排序</view>
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
          <u-icon name="arrow-right" size="16" color="#999" />
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

.rank-main {
  flex: 1;
}

.rank-name {
  font-size: 28rpx;
  font-weight: 500;
}
</style>
