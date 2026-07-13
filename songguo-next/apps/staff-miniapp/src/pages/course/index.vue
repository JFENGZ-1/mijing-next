<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { fetchStaffBookingDailyBoard } from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffBookingDailyBoardItem } from "@/types/scheduling";
import {
  buildWeekDates,
  formatSessionTime,
  sessionStatusLabel,
  sessionStatusType,
  todayIsoDate,
} from "@/utils/format";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const selectedDate = ref(todayIsoDate());
const weekDates = computed(() => buildWeekDates(todayIsoDate(), 7));
const sessions = ref<StaffBookingDailyBoardItem[]>([]);

const canViewBoard = computed(
  () => session.can("booking.staff-daily-board.read") || session.can("schedule.session.read"),
);
const canCreate = computed(() => session.can("schedule.session.write"));
const canBatchTools = computed(
  () =>
    session.can("schedule.batch.copy")
    || session.can("schedule.batch.suspend")
    || session.can("schedule.batch.cancel"),
);
const currentSiteName = computed(
  () => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆",
);

async function loadBoard() {
  if (!session.currentSiteId || !canViewBoard.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  forbidden.value = false;
  try {
    const response = await fetchStaffBookingDailyBoard(session.currentSiteId, selectedDate.value);
    sessions.value = response.items;
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 403) {
      forbidden.value = true;
      sessions.value = [];
      return;
    }
    errorMessage.value = error instanceof Error ? error.message : "课程日程加载失败";
    sessions.value = [];
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await loadBoard();
});

onPullDownRefresh(async () => {
  await loadBoard();
  uni.stopPullDownRefresh();
});

function selectDate(value: string) {
  if (selectedDate.value === value) return;
  selectedDate.value = value;
  loadBoard();
}

function onDateChange(event: { detail: { value: string } }) {
  selectedDate.value = event.detail.value;
  loadBoard();
}

function openSitePicker() {
  if (session.sites.length <= 1) return;
  uni.showActionSheet({
    itemList: session.sites.map((site) => site.name),
    success: (result) => {
      const site = session.sites[result.tapIndex];
      if (!site || site.id === session.currentSiteId) return;
      session.selectSite(site.id);
      loadBoard();
    },
  });
}

function openSessionDetail(sessionId: number) {
  uni.navigateTo({ url: `/pages/course/session-detail?id=${sessionId}` });
}

function openCreateSession() {
  uni.navigateTo({ url: `/pages/course/session-form?date=${selectedDate.value}` });
}

function openBatchTools() {
  uni.navigateTo({ url: "/pages/course/batch-tools" });
}

function openTimetable() {
  uni.navigateTo({ url: "/pages/course/timetable/index" });
}

function sessionKindLabel(kind: string) {
  return kind === "private" ? "私教" : "团课";
}
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="page-container">
    <view class="header-row" @tap="openSitePicker">
      <view>
        <text class="title">课程日程</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
      <view class="header-actions">
        <button v-if="canBatchTools" class="batch-link" @tap.stop="openBatchTools">批量课表</button>
        <button v-if="canViewBoard" class="batch-link" @tap.stop="openTimetable">课表编辑</button>
        <u-icon v-if="session.sites.length > 1" name="arrow-right" size="18" color="#667085" />
      </view>
    </view>

    <view v-if="errorMessage" class="error-text">{{ errorMessage }}</view>

    <template v-if="canViewBoard">
      <scroll-view scroll-x class="week-strip" :show-scrollbar="false">
        <view class="week-strip-inner">
          <view
            v-for="item in weekDates"
            :key="item.value"
            class="week-chip"
            :class="{ active: selectedDate === item.value }"
            @tap="selectDate(item.value)"
          >
            <text class="week-chip-label">{{ item.label }}</text>
            <text class="week-chip-sub">{{ item.subLabel }}</text>
          </view>
        </view>
      </scroll-view>

      <picker mode="date" :value="selectedDate" @change="onDateChange">
        <view class="date-picker">{{ selectedDate }}</view>
      </picker>

      <view class="section-title">{{ selectedDate }} 共 {{ sessions.length }} 节课</view>

      <view v-if="sessions.length" class="session-list">
        <view
          v-for="item in sessions"
          :key="item.id"
          class="session-card"
          @tap="openSessionDetail(item.id)"
        >
          <view class="session-header">
            <view class="session-time">{{ formatSessionTime(item.startsAt, item.endsAt) }}</view>
            <u-tag
              :text="sessionStatusLabel(item.status)"
              size="mini"
              :type="sessionStatusType(item.status)"
            />
          </view>
          <text class="session-name">{{ item.courseName || "未命名课程" }}</text>
          <text class="session-meta">
            {{ sessionKindLabel(item.sessionKind) }}
            <template v-if="item.coachName"> · 教练 {{ item.coachName }}</template>
            <template v-if="item.roomName"> · {{ item.roomName }}</template>
          </text>
          <text class="session-meta">预约 {{ item.bookedCount }}/{{ item.capacity }}</text>
        </view>
      </view>
      <u-empty v-else mode="list" text="当天暂无排课" />
    </template>

    <u-empty v-else-if="forbidden" mode="permission" text="当前账号暂无课程日程权限" />
    <u-empty v-else mode="permission" text="需要 booking.staff-daily-board.read 或 schedule.session.read 权限" />

    <button v-if="canCreate" class="add-button" title="新建排课" @click="openCreateSession">
      <u-icon name="plus" color="#fff" size="24" />
    </button>
  </view>
</template>

<style scoped lang="scss">
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.batch-link {
  margin: 0;
  padding: 8rpx 20rpx;
  color: $color-primary;
  font-size: 24rpx;
  background: rgba($color-primary, 0.08);
  border: 1rpx solid rgba($color-primary, 0.2);
  border-radius: 999rpx;
}

.batch-link::after {
  border: 0;
}

.title,
.subtitle,
.session-name,
.session-meta,
.session-time,
.week-chip-label,
.week-chip-sub {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.error-text {
  margin-top: $spacing-sm;
  color: $color-danger;
  font-size: 24rpx;
}

.week-strip {
  margin-top: $spacing-md;
  white-space: nowrap;
}

.week-strip-inner {
  display: inline-flex;
  gap: $spacing-sm;
  padding-bottom: 4rpx;
}

.week-chip {
  min-width: 112rpx;
  padding: 16rpx 20rpx;
  text-align: center;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.week-chip.active {
  background: rgba($color-primary, 0.08);
  border-color: $color-primary;
}

.week-chip-label {
  font-size: 26rpx;
  font-weight: 600;
}

.week-chip-sub {
  margin-top: 4rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.week-chip.active .week-chip-label,
.week-chip.active .week-chip-sub {
  color: $color-primary;
}

.date-picker {
  margin: $spacing-sm 0 $spacing-md;
  padding: 20rpx;
  color: $color-primary;
  text-align: center;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.session-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
}

.session-card {
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.session-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.session-time {
  font-size: 30rpx;
  font-weight: 600;
}

.session-name {
  margin-top: $spacing-xs;
  font-size: 30rpx;
  font-weight: 600;
}

.session-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.add-button {
  position: fixed;
  right: 32rpx;
  bottom: calc(40rpx + env(safe-area-inset-bottom));
  width: 88rpx;
  height: 88rpx;
  padding: 0;
  line-height: 88rpx;
  background: $color-primary;
  border-radius: 50%;
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.16);
}

.add-button::after {
  border: 0;
}
</style>
