<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberMonthAppointments, getMemberMonthStats, getMemberYearStats } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberMonthAppointment, MemberMonthStats, MemberStatsBucket, MemberYearStats } from "@/types/member";
import { appointmentStatusLabel, formatIsoDate } from "@/utils/format";

const monthLoading = ref(false);
const errorMessage = ref("");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const yearStats = ref<MemberYearStats | null>(null);
const monthStats = ref<MemberMonthStats | null>(null);
const appointments = ref<MemberMonthAppointment[]>([]);
const courseKind = ref<"group" | "private" | "all">("all");

const loading = ref(true);

const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current - 1, current, current + 1];
});

const monthOptions = Array.from({ length: 12 }, (_, index) => index + 1);

const courseKindTabs = [
  { label: "全部", value: "all" as const },
  { label: "团课", value: "group" as const },
  { label: "私教", value: "private" as const },
];

function monthLabel(month: number) {
  return `${month}月`;
}

function bucketSummary(bucket: MemberStatsBucket) {
  return `团课 ${bucket.teamTimes} 次 / 私教 ${bucket.privateTimes} 次`;
}

function appointmentTitle(item: MemberMonthAppointment) {
  return item.courseName || `课程 #${item.sessionId}`;
}

function appointmentTime(item: MemberMonthAppointment) {
  if (item.startsAt && item.endsAt) {
    return `${formatIsoDate(item.startsAt)} - ${formatIsoDate(item.endsAt)}`;
  }
  return item.bookedAt ? `预约于 ${formatIsoDate(item.bookedAt)}` : "";
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
      getMemberMonthAppointments(
        tenant.tenantId,
        selectedYear.value,
        selectedMonth.value,
        courseKind.value,
      ),
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
    await loadYearStats();
    void loadMonthDetail();
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

onShow(async () => { if (await requireMemberAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="section-title">年度统计</view>
    <view class="picker-strip">
      <u-button
        v-for="year in yearOptions"
        :key="year"
        size="small"
        type="primary"
        :plain="selectedYear !== year"
        @click="selectYear(year)"
      >
        {{ year }}年
      </u-button>
    </view>

    <view v-if="yearStats" class="summary-card">
      <view class="summary-total">全年上课 {{ yearStats.totalCount }} 次</view>
      <view class="summary-meta">{{ bucketSummary(yearStats) }}</view>
      <view class="summary-meta">
        缺席 团课 {{ yearStats.teamAbsent }} / 私教 {{ yearStats.privateAbsent }} · 取消 {{ yearStats.cancelledCount }}
      </view>
    </view>

    <view v-if="yearStats" class="month-grid">
      <view
        v-for="bucket in yearStats.months"
        :key="bucket.month"
        class="month-card"
        :class="{ active: bucket.month === selectedMonth }"
        @click="bucket.month && selectMonth(bucket.month)"
      >
        <view class="month-label">{{ monthLabel(bucket.month ?? 0) }}</view>
        <view class="month-value">{{ (bucket.teamTimes ?? 0) + (bucket.privateTimes ?? 0) }}</view>
        <view class="month-meta">次</view>
      </view>
    </view>

    <view class="section-title">月度明细</view>
    <view class="picker-strip month-strip">
      <u-button
        v-for="month in monthOptions"
        :key="month"
        size="mini"
        type="primary"
        :plain="selectedMonth !== month"
        @click="selectMonth(month)"
      >
        {{ month }}
      </u-button>
    </view>

    <view class="month-detail" :class="{ 'month-detail--refreshing': monthLoading }">
      <template v-if="monthStats">
        <view class="summary-card">
          <view class="summary-total">{{ selectedYear }}年{{ selectedMonth }}月</view>
          <view class="summary-meta">{{ bucketSummary(monthStats) }}</view>
          <view class="summary-meta">
            待上课 {{ monthStats.confirmedCount }} · 缺席 团课 {{ monthStats.teamAbsent }} / 私教 {{ monthStats.privateAbsent }}
          </view>
        </view>

        <view class="picker-strip">
          <u-button
            v-for="tab in courseKindTabs"
            :key="tab.value"
            size="small"
            type="primary"
            :plain="courseKind !== tab.value"
            @click="switchCourseKind(tab.value)"
          >
            {{ tab.label }}
          </u-button>
        </view>

        <u-empty v-if="appointments.length === 0" mode="list" text="本月暂无上课记录" />
        <view v-for="item in appointments" :key="item.id" class="appointment-card">
          <view class="appointment-header">
            <view class="appointment-title">{{ appointmentTitle(item) }}</view>
            <view class="appointment-status">{{ appointmentStatusLabel(item.status) }}</view>
          </view>
          <view class="appointment-meta">{{ appointmentTime(item) }}</view>
          <view v-if="item.coachName" class="appointment-meta">教练 {{ item.coachName }}</view>
        </view>
      </template>
    </view>
  </view>
</template>

<style scoped lang="scss">
.month-detail--refreshing {
  opacity: 0.72;
  transition: opacity 0.15s ease;
}

.picker-strip {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
  margin-bottom: $spacing-md;
}

.month-strip {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
}

.summary-card {
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.summary-total {
  font-size: 32rpx;
  font-weight: 600;
}

.summary-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.month-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: $spacing-sm;
  margin-bottom: $spacing-lg;
}

.month-card {
  padding: $spacing-sm;
  text-align: center;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;

  &.active {
    border-color: $color-primary;
    background: rgba($color-primary, 0.06);
  }
}
.month-label {
  color: $color-text-secondary;
  font-size: 22rpx;
}

.month-value {
  margin-top: 4rpx;
  font-size: 34rpx;
  font-weight: 600;
}

.month-meta {
  color: $color-text-secondary;
  font-size: 20rpx;
}

.appointment-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.appointment-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.appointment-title {
  font-size: 30rpx;
  font-weight: 600;
}

.appointment-status {
  color: $color-primary;
  font-size: 24rpx;
}

.appointment-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}
</style>
