<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchScheduleDisplayConfig, fetchStaffScheduleSessions } from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ScheduleSession } from "@/types/scheduling";
import { buildWeekDates, formatSessionTime, sessionStatusLabel, sessionStatusType, todayIsoDate } from "@/utils/format";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const weekStart = ref(todayIsoDate());
const displayTitle = ref("");
const sessions = ref<ScheduleSession[]>([]);

const canView = computed(() => session.can("schedule.session.read"));
const canWrite = computed(() => session.can("schedule.session.write"));
const canBatch = computed(
  () =>
    session.can("schedule.batch.copy")
    || session.can("schedule.batch.suspend")
    || session.can("schedule.batch.cancel"),
);
const weekDates = computed(() => buildWeekDates(weekStart.value, 7));
const rangeEnd = computed(() => weekDates.value[weekDates.value.length - 1]?.value ?? weekStart.value);
const sessionsByDate = computed(() => {
  const map = new Map<string, ScheduleSession[]>();
  for (const item of sessions.value) {
    const date = item.startsAt.slice(0, 10);
    const bucket = map.get(date) ?? [];
    bucket.push(item);
    map.set(date, bucket);
  }
  return map;
});

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const siteId = session.currentSiteId;
    const [sessionList, display] = await Promise.all([
      fetchStaffScheduleSessions(siteId, weekStart.value, rangeEnd.value),
      fetchScheduleDisplayConfig(siteId).catch(() => null),
    ]);
    sessions.value = sessionList.items;
    displayTitle.value = display?.displayTitle || "课表编辑";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课表加载失败";
    sessions.value = [];
  } finally {
    loading.value = false;
  }
}

function shiftWeek(offset: number) {
  const base = new Date(`${weekStart.value}T00:00:00`);
  base.setDate(base.getDate() + offset * 7);
  weekStart.value = base.toISOString().slice(0, 10);
  load();
}

function openSession(sessionId: number) {
  uni.navigateTo({ url: `/pages/course/session-detail?id=${sessionId}` });
}

function openCreate(date: string) {
  uni.navigateTo({ url: `/pages/course/session-form?date=${date}` });
}

function openDisplayConfig() {
  uni.navigateTo({ url: "/pages/course/timetable/display-config" });
}

function openCourseCatalog() {
  uni.navigateTo({ url: "/pages/settings/courses/index" });
}

function resetWeek() {
  weekStart.value = todayIsoDate();
  load();
}

function openBatchTools() {
  uni.navigateTo({ url: "/pages/course/batch-tools" });
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
    <view class="header-row">
      <view>
        <text class="title">{{ displayTitle }}</text>
        <text class="subtitle">{{ weekStart }} ~ {{ rangeEnd }}</text>
      </view>
      <view class="header-actions">
        <button v-if="canBatch" class="link-btn" @tap="openBatchTools">批量</button>
        <button v-if="canWrite" class="link-btn" @tap="openDisplayConfig">展示</button>
      </view>
    </view>

    <u-empty v-if="!canView" mode="permission" text="暂无课表编辑权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="toolbar">
        <u-button size="small" text="上周" @click="shiftWeek(-1)" />
        <u-button size="small" text="本周" @click="resetWeek" />
        <u-button size="small" text="下周" @click="shiftWeek(1)" />
        <u-button size="small" text="课程库" @click="openCourseCatalog" />
      </view>

      <view v-for="day in weekDates" :key="day.value" class="day-card">
        <view class="day-header">
          <text class="day-label">{{ day.label }} {{ day.subLabel }}</text>
          <button v-if="canWrite" class="link-btn" @tap="openCreate(day.value)">+ 排课</button>
        </view>
        <view
          v-for="item in sessionsByDate.get(day.value) || []"
          :key="item.id"
          class="session-row"
          @tap="openSession(item.id)"
        >
          <view class="session-main">
            <text class="session-time">{{ formatSessionTime(item.startsAt, item.endsAt) }}</text>
            <text class="session-name">{{ item.courseName || "未命名课程" }}</text>
          </view>
          <u-tag :text="sessionStatusLabel(item.status)" size="mini" :type="sessionStatusType(item.status)" />
        </view>
        <u-empty v-if="!(sessionsByDate.get(day.value) || []).length" mode="list" text="暂无排课" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.header-row, .day-header, .session-row { display: flex; align-items: center; justify-content: space-between; }
.header-actions, .toolbar { display: flex; gap: 12rpx; flex-wrap: wrap; }
.title, .subtitle, .day-label, .session-time, .session-name { display: block; }
.title { font-size: 36rpx; font-weight: 600; }
.subtitle, .session-name { margin-top: 8rpx; color: #667085; font-size: 24rpx; }
.link-btn { margin: 0; padding: 8rpx 16rpx; color: #1677ff; font-size: 24rpx; background: #e8f0fe; border-radius: 999rpx; }
.link-btn::after { border: 0; }
.toolbar { margin: 16rpx 0; }
.day-card { margin-bottom: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.session-row { padding: 12rpx 0; border-top: 1rpx solid #f0f0f0; }
.session-time { font-size: 26rpx; font-weight: 600; }
</style>
