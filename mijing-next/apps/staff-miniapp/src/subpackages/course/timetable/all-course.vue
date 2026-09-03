<script setup lang="ts">
// 全部课表 —— 对标原版 pagesCourse/index/all-course（课程维度统计）
// 带 courseId 参数时为单课程排课列表（对标原版 management-schedule）
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchStaffScheduleSessions } from "@/api/scheduling";
import { fetchStaffCourseCatalog } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { ScheduleSession } from "@/types/scheduling";
import { todayIsoDate } from "@/utils/format";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(true);
const courseId = ref(0);
const courseName = ref("");
const courses = ref<CourseCatalogItem[]>([]);
const sessions = ref<ScheduleSession[]>([]);

const DAYS_FORWARD = 60;

function addDays(iso: string, days: number) {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function hhmm(iso: string) {
  const idx = iso.indexOf("T");
  return idx > 0 ? iso.slice(idx + 1, idx + 6) : iso.slice(11, 16);
}

const WEEK_NAMES = ["周日", "周一", "周二", "周三", "周四", "周五", "周六"];

function dayLabel(date: string) {
  const week = WEEK_NAMES[new Date(`${date}T00:00:00`).getDay()];
  return `${date.slice(5).replace("-", "月")}日 ${date === todayIsoDate() ? "今天" : week}`;
}

// 课程维度统计（无 courseId 模式）
const courseStats = computed(() => {
  const counts = new Map<number, number>();
  for (const item of sessions.value) {
    counts.set(item.courseId, (counts.get(item.courseId) ?? 0) + 1);
  }
  return courses.value.map((course) => ({
    course,
    count: counts.get(course.id) ?? 0,
  }));
});

// 单课程排课按天分组（courseId 模式）
const groupedByDay = computed(() => {
  const groups: { date: string; items: ScheduleSession[] }[] = [];
  const map = new Map<string, ScheduleSession[]>();
  for (const item of sessions.value) {
    if (courseId.value && item.courseId !== courseId.value) continue;
    const date = item.startsAt.slice(0, 10);
    if (!map.has(date)) {
      const bucket: ScheduleSession[] = [];
      map.set(date, bucket);
      groups.push({ date, items: bucket });
    }
    map.get(date)?.push(item);
  }
  return groups;
});

const totalCount = computed(() =>
  courseId.value ? sessions.value.filter((item) => item.courseId === courseId.value).length : sessions.value.length,
);

async function load() {
  if (!session.currentSiteId || !session.can("schedule.session.read")) {
    loading.value = false;
    return;
  }
  loading.value = true;
  try {
    const from = todayIsoDate();
    const [list, catalog] = await Promise.all([
      fetchStaffScheduleSessions(
        session.currentSiteId,
        `${from}T00:00:00`,
        `${addDays(from, DAYS_FORWARD)}T23:59:59`,
      ),
      courseId.value
        ? Promise.resolve(null)
        : fetchStaffCourseCatalog(session.currentSiteId, 1, 200, undefined, "group").catch(() => null),
    ]);
    sessions.value = list.items.filter((item) => item.status !== "cancelled");
    if (catalog) courses.value = catalog.items;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function openCourse(item: { course: CourseCatalogItem; count: number }) {
  courseId.value = item.course.id;
  courseName.value = item.course.name;
}

function backToAll() {
  courseId.value = 0;
  courseName.value = "";
}

function openSession(item: ScheduleSession) {
  uni.navigateTo({ url: `/subpackages/course/session-detail?id=${item.id}` });
}

onLoad((query) => {
  courseId.value = Number(query?.courseId || 0);
  courseName.value = decodeURIComponent(String(query?.name || ""));
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await load();
});
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="page-shell">
    <view class="body-sheet">
      <!-- 单课程模式（原版 management-schedule） -->
      <template v-if="courseId">
        <view class="head-row">
          <view>
            <text class="head-title">{{ courseName || "课程排课" }}</text>
            <text class="head-sub">未来 {{ DAYS_FORWARD }} 天共 {{ totalCount }} 节排课</text>
          </view>
          <text class="link-btn" @tap="backToAll">全部课程</text>
        </view>
        <view v-for="group in groupedByDay" :key="group.date" class="day-group">
          <text class="day-label">{{ dayLabel(group.date) }}</text>
          <view v-for="item in group.items" :key="item.id" class="session-row" @tap="openSession(item)">
            <view class="time-col">
              <text class="time-big">{{ hhmm(item.startsAt) }}</text>
              <text class="time-small">{{ hhmm(item.endsAt) }}结束</text>
            </view>
            <view class="session-main">
              <text class="session-name">{{ item.courseName }}</text>
              <text class="session-meta">
                {{ item.coachName || "" }} · 预约 {{ item.bookedCount }}/{{ item.capacity }}
                <text v-if="item.status === 'suspended'" class="stopped">（停课中）</text>
              </text>
            </view>
            <u-icon name="arrow-right" size="14" color="#bfbfbf" />
          </view>
        </view>
        <u-empty v-if="!groupedByDay.length" mode="list" text="该课程暂无排课" />
      </template>

      <!-- 全部课程统计模式（原版 all-course） -->
      <template v-else>
        <view class="head-row">
          <view>
            <text class="head-title">全部课表</text>
            <text class="head-sub">未来 {{ DAYS_FORWARD }} 天共 {{ totalCount }} 节排课</text>
          </view>
        </view>
        <view v-for="item in courseStats" :key="item.course.id" class="course-row" @tap="openCourse(item)">
          <view class="course-face" :style="{ background: item.course.faceGradient || 'linear-gradient(135deg, #3f4756, #23272f)' }">
            <text class="face-text">{{ item.course.name.slice(0, 2) }}</text>
          </view>
          <view class="session-main">
            <text class="session-name">{{ item.course.name }}</text>
            <text class="session-meta">{{ item.course.durationMinutes }}分钟 · {{ item.course.coachName || "未绑定教练" }}</text>
          </view>
          <view class="count-col">
            <text class="count-num" :class="{ zero: !item.count }">{{ item.count }}</text>
            <text class="count-label">节排课</text>
          </view>
        </view>
        <u-empty v-if="!courseStats.length" mode="list" text="暂无课程" />
      </template>

      <view class="brand-footer">觅境约课</view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 28rpx 60rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  box-sizing: border-box;
}

.head-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
}

.head-title {
  display: block;
  font-size: 34rpx;
  font-weight: 600;
  color: $color-text;
}

.head-sub {
  display: block;
  margin-top: 8rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.link-btn {
  padding: 10rpx 24rpx;
  background: rgba(251, 209, 40, 0.16);
  border-radius: 999rpx;
  color: #d9a400;
  font-size: 24rpx;
}

.day-group {
  margin-top: 28rpx;
}

.day-label {
  display: block;
  margin-bottom: 12rpx;
  color: $color-text-secondary;
  font-size: 26rpx;
  font-weight: 500;
}

.session-row,
.course-row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 22rpx 0;
  border-bottom: 1rpx solid #f5f5f5;
}

.time-col {
  flex-shrink: 0;
  width: 120rpx;
}

.time-big {
  display: block;
  font-size: 32rpx;
  font-weight: 600;
  color: $color-text;
}

.time-small {
  display: block;
  margin-top: 4rpx;
  color: $color-text-tertiary;
  font-size: 20rpx;
}

.session-main {
  flex: 1;
  min-width: 0;
}

.session-name {
  display: block;
  font-size: 28rpx;
  font-weight: 500;
  color: $color-text;
}

.session-meta {
  display: block;
  margin-top: 6rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.stopped {
  color: $color-danger;
}

.course-face {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 96rpx;
  height: 96rpx;
  border-radius: 20rpx;
}

.face-text {
  color: #fff;
  font-size: 26rpx;
  font-weight: 500;
}

.count-col {
  flex-shrink: 0;
  text-align: right;
}

.count-num {
  display: block;
  font-size: 36rpx;
  font-weight: 600;
  color: $color-primary;

  &.zero {
    color: $color-text-disabled;
  }
}

.count-label {
  display: block;
  color: $color-text-tertiary;
  font-size: 20rpx;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}
</style>
