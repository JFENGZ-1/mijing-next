<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberMonthAppointments, getMemberMonthStats, getMemberYearStats } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberMonthAppointment, MemberMonthStats, MemberStatsBucket, MemberYearStats } from "@/types/member";
import type { MemberAppointmentSummary } from "@/types/member";

const monthLoading = ref(false);
const errorMessage = ref("");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const yearStats = ref<MemberYearStats | null>(null);
const monthStats = ref<MemberMonthStats | null>(null);
const appointments = ref<MemberMonthAppointment[]>([]);
const courseKind = ref<"group" | "private" | "all">("all");

const loading = ref(true);

const monthOnly = ref(false);

onLoad((query) => {
  if (query && query.scope === "month") {
    monthOnly.value = true;
    uni.setNavigationBarTitle({ title: "本月上课" });
  }
});

const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current - 1, current, current + 1];
});

const courseKindTabs = [
  { label: "全部", value: "all" as const },
  { label: "团课", value: "group" as const },
  { label: "私教", value: "private" as const },
];

const sortedMonths = computed(() => {
  if (!yearStats.value) return [];
  return [...yearStats.value.months].sort((a, b) => (a.month ?? 0) - (b.month ?? 0));
});

function bucketTotal(bucket: MemberStatsBucket) {
  return (bucket.teamTimes ?? 0) + (bucket.privateTimes ?? 0);
}

async function loadYearStats() {
  const tenant = await ensureMemberTenant();
  if (!tenant) {
    errorMessage.value = "请先选择场馆";
    return;
  }
  const response = await getMemberYearStats(tenant.tenantId, selectedYear.value);
  yearStats.value = response.data;
}

async function loadMonthDetail() {
  const tenant = await ensureMemberTenant();
  if (!tenant) return;

  monthLoading.value = true;
  try {
    const [statsResponse, appointmentsResponse] = await Promise.all([
      getMemberMonthStats(tenant.tenantId, selectedYear.value, selectedMonth.value),
      getMemberMonthAppointments(tenant.tenantId, selectedYear.value, selectedMonth.value, courseKind.value),
    ]);
    monthStats.value = statsResponse.data;
    appointments.value = appointmentsResponse.data.items;
  } finally {
    monthLoading.value = false;
  }
}

async function load() {
  loading.value = true;
  errorMessage.value = "";
  yearStats.value = null;
  monthStats.value = null;
  appointments.value = [];
  try {
    if (monthOnly.value) {
      await loadMonthDetail();
    } else {
      await loadYearStats();
      void loadMonthDetail();
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "统计数据加载失败";
  } finally {
    loading.value = false;
  }
}

async function selectYear(year: number) {
  if (selectedYear.value === year) return;
  selectedYear.value = year;
  errorMessage.value = "";
  try {
    await loadYearStats();
    await loadMonthDetail();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "统计数据加载失败";
  }
}

async function selectMonth(month: number) {
  if (selectedMonth.value === month) return;
  selectedMonth.value = month;
  errorMessage.value = "";
  try {
    await loadMonthDetail();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "月度数据加载失败";
  }
}

async function switchCourseKind(kind: "group" | "private" | "all") {
  if (courseKind.value === kind) return;
  courseKind.value = kind;
  errorMessage.value = "";
  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) return;
    const response = await getMemberMonthAppointments(
      tenant.tenantId,
      selectedYear.value,
      selectedMonth.value,
      courseKind.value,
    );
    appointments.value = response.data.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约列表加载失败";
  }
}

function openSessionDetail(item: MemberMonthAppointment) {
  uni.navigateTo({ url: `/pages/booking/detail?id=${item.sessionId}` });
}

onShow(async () => {
  if (await requireMemberAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="stats-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <view v-if="!monthOnly" class="picker-strip">
      <view
        v-for="year in yearOptions"
        :key="year"
        class="year-pill"
        :class="{ active: selectedYear === year }"
        @tap="selectYear(year)"
      >
        {{ year }}年
      </view>
    </view>

    <view v-if="yearStats && !monthOnly" class="current-year">
      <view class="tips">
        <u-icon name="info-circle" size="14" color="#dadada" />
        <text>每日凌晨更新</text>
      </view>

      <view class="total-num">
        <view class="num">{{ yearStats.totalCount }}</view>
        <view class="text">累计上课(次)</view>
      </view>

      <view class="record-wrap">
        <view v-for="bucket in sortedMonths" :key="bucket.month" class="record-item">
          <view class="month">{{ (bucket.month ?? 0) }}月</view>
          <view
            class="statistics-wrap"
            :class="{ active: bucket.month === selectedMonth }"
            @tap="bucket.month && selectMonth(bucket.month)"
          >
            <view class="group">
              <view class="num">{{ bucket.teamTimes ?? 0 }}</view>
              <view class="text">常规课(次)</view>
            </view>
            <view class="group">
              <view class="num">{{ bucket.teamAbsent ?? 0 }}</view>
              <view class="text">旷课(次)</view>
            </view>
            <view class="group">
              <view class="num">{{ bucket.privateTimes ?? 0 }}</view>
              <view class="text">私教(次)</view>
            </view>
            <view class="group">
              <view class="num">{{ bucket.privateAbsent ?? 0 }}</view>
              <view class="text">旷课(次)</view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="detail-section" :class="{ 'detail-section--refreshing': monthLoading }">
      <view class="detail-title">{{ monthOnly ? '本月上课明细' : `${selectedYear}年${selectedMonth}月明细` }}</view>

      <view v-if="monthStats" class="month-summary">
        <view class="summary-total">本月上课 {{ bucketTotal(monthStats) }} 次</view>
        <view class="summary-meta">
          待上课 {{ monthStats.confirmedCount }} · 取消 {{ monthStats.cancelledCount }}
        </view>
      </view>

      <view class="kind-tabs">
        <view
          v-for="tab in courseKindTabs"
          :key="tab.value"
          class="kind-pill"
          :class="{ active: courseKind === tab.value }"
          @tap="switchCourseKind(tab.value)"
        >
          {{ tab.label }}
        </view>
      </view>

      <u-empty v-if="appointments.length === 0" mode="list" text="本月暂无上课记录" />
      <view v-for="item in appointments" :key="item.id" class="appt-list">
        <appointment-row :item="(item as unknown as MemberAppointmentSummary)" @tap="openSessionDetail(item)" />
      </view>

      <bottom-logo />
    </view>
  </view>
</template>

<style scoped lang="scss">
.stats-page {
  min-height: 100vh;
  background: $color-page;
  padding-bottom: 40rpx;
}

.picker-strip {
  display: flex;
  gap: 16rpx;
  padding: 24rpx 28rpx 8rpx;
}

.year-pill {
  padding: 8rpx 24rpx;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: 28rpx;
  color: $color-text-secondary;
  font-size: 24rpx;

  &.active {
    background: $color-primary;
    border-color: $color-primary;
    color: #fff;
  }
}

.current-year {
  position: relative;
  margin: 16rpx 28rpx 0;
  padding: 51rpx 44rpx 54rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.tips {
  position: absolute;
  top: 18rpx;
  right: 24rpx;
  display: flex;
  align-items: center;
  gap: 5rpx;
  color: #dadada;
  font-size: 22rpx;
}

.total-num {
  text-align: center;
}

.total-num .num {
  color: #ed920f;
  font-size: 90rpx;
  font-weight: 700;
  line-height: 90rpx;
}

.total-num .text {
  margin-top: 20rpx;
  color: #7e7e7e;
  font-size: 25rpx;
  line-height: 25rpx;
}

.record-wrap {
  margin-top: 60rpx;
}

.record-item {
  margin-bottom: 40rpx;

  &:last-of-type {
    margin-bottom: 0;
  }
}

.record-item .month {
  margin-bottom: 30rpx;
  color: #dadada;
  font-size: 49rpx;
  line-height: 49rpx;
}

.statistics-wrap {
  display: flex;
  justify-content: space-between;
  padding-left: 60rpx;
  border-radius: 12rpx;

  &.active {
    background: rgba($color-primary, 0.06);
  }
}

.statistics-wrap .group {
  text-align: center;
}

.statistics-wrap .group .num {
  margin-bottom: 18rpx;
  color: $color-text;
  font-size: 33rpx;
  line-height: 33rpx;
}

.statistics-wrap .group .text {
  color: #7e7e7e;
  font-size: 22rpx;
  line-height: 22rpx;
}

.detail-section {
  margin-top: 24rpx;
  padding: 0 28rpx;
}

.detail-section--refreshing {
  opacity: 0.72;
  transition: opacity 0.15s ease;
}

.detail-title {
  margin-bottom: 20rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 600;
}

.month-summary {
  margin-bottom: 20rpx;
  padding: 24rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.summary-total {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 600;
}

.summary-meta {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.kind-tabs {
  display: flex;
  gap: 16rpx;
  margin-bottom: 20rpx;
}

.kind-pill {
  padding: 8rpx 28rpx;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: 28rpx;
  color: $color-text-secondary;
  font-size: 24rpx;

  &.active {
    background: $color-primary;
    border-color: $color-primary;
    color: #fff;
  }
}

.appt-list {
  background: $color-surface;
  padding: 0 24rpx;
  border-radius: $radius-md;
  margin-bottom: 16rpx;
}
</style>
