<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import { fetchPrivateCoaches, fetchStaffCourseCatalog } from "@/api/catalog";
import type { CoachPrivateProfile } from "@/api/catalog";
import { fetchStaffBookingDailyBoard } from "@/api/scheduling";
import { fetchSiteClosures } from "@/api/settings";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { StaffBookingDailyBoardItem } from "@/types/scheduling";
import type { SiteClosureItem } from "@/types/settings";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import { formatClock, todayIsoDate } from "@/utils/format";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const selectedDate = ref(todayIsoDate());
const sessions = ref<StaffBookingDailyBoardItem[]>([]);
const coaches = ref<StaffDirectoryListItem[]>([]);
const privateProfiles = ref<CoachPrivateProfile[]>([]);
const courseCatalog = ref<CourseCatalogItem[]>([]);
const closures = ref<SiteClosureItem[]>([]);

// —— 周日历：以周为单位，支持左右滑动切换周与选择任意日期（对标原版 week-calendar） ——
const weekOffset = ref(0);
const WEEK_LABELS = ["日", "一", "二", "三", "四", "五", "六"] as const;

function isoOf(date: Date) {
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function mondayOf(iso: string) {
  const date = new Date(`${iso}T00:00:00`);
  const weekday = date.getDay(); // 0=周日
  const diff = weekday === 0 ? -6 : 1 - weekday;
  date.setDate(date.getDate() + diff);
  return date;
}

function weekDatesFor(offset: number) {
  const monday = mondayOf(todayIsoDate());
  monday.setDate(monday.getDate() + offset * 7);
  const today = todayIsoDate();
  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(monday);
    date.setDate(monday.getDate() + index);
    const value = isoOf(date);
    return {
      value,
      week: `周${WEEK_LABELS[date.getDay()]}`,
      dayNum: String(date.getDate()),
      isToday: value === today,
    };
  });
}

const weekDates = computed(() => weekDatesFor(weekOffset.value));

// —— swiper 无限循环换周（对标原版 week-calendar swiper circular） ——
const swiperCurrent = ref(1);

// 三个槽位按相对位置渲染上一周/本周/下一周
function slotOffset(slot: number) {
  const rel = (((slot - swiperCurrent.value) % 3) + 3) % 3; // 0=当前 1=下一 2=上一
  return rel === 1 ? 1 : rel === 2 ? -1 : 0;
}

const swiperSlots = computed(() => [0, 1, 2].map((slot) => weekDatesFor(weekOffset.value + slotOffset(slot))));

function onSwiperChange(event: { detail: { current: number } }) {
  const current = event.detail.current;
  const delta = (((current - swiperCurrent.value) % 3) + 3) % 3;
  if (delta === 1) weekOffset.value += 1;
  else if (delta === 2) weekOffset.value -= 1;
  swiperCurrent.value = current;
}

const showBackToday = computed(() => weekOffset.value !== 0 || selectedDate.value !== todayIsoDate());

function weekOffsetOfDate(iso: string) {
  const base = mondayOf(todayIsoDate()).getTime();
  const target = mondayOf(iso).getTime();
  return Math.round((target - base) / (7 * 24 * 3600 * 1000));
}

// —— 闭馆横幅（对标原版 shopStatus==2） ——
const activeClosure = computed(() => {
  const day = selectedDate.value;
  return (
    closures.value.find(
      (item) => item.lifecycleStatus !== "completed" && item.beginDate <= day && item.endDate >= day,
    ) || null
  );
});

const canViewBoard = computed(
  () => session.can("booking.staff-daily-board.read") || session.can("schedule.session.read"),
);
const canViewCoaches = computed(() => session.can("staff.directory.read"));
const canLoadCourses = computed(() => session.can("course-catalog.read"));

// courseId → 难度/最低开课人数（课程模板信息，daily-board 不含）
const courseMetaMap = computed(() => {
  const map = new Map<number, { difficulty: number; minCapacity: number }>();
  for (const item of courseCatalog.value) {
    map.set(item.id, {
      difficulty: item.difficulty ?? 0,
      minCapacity: item.minCapacity ?? 0,
    });
  }
  return map;
});

// coachId → 头像
const coachAvatarMap = computed(() => {
  const map = new Map<number, string | null>();
  for (const staff of coaches.value) map.set(staff.id, staff.avatarUrl);
  return map;
});

// 私教横滚区：私教档案（对标原版 findAllPrivateDrainerList：drainer + tagText + userList）
const privateCoaches = computed(() => {
  const bookedByCoach = new Map<number, number>();
  const membersByCoach = new Map<number, string[]>();
  for (const item of sessions.value) {
    if (item.sessionKind !== "private") continue;
    bookedByCoach.set(item.coachStaffId, (bookedByCoach.get(item.coachStaffId) ?? 0) + item.bookedCount);
    if (item.attendees?.length) {
      const list = membersByCoach.get(item.coachStaffId) ?? [];
      for (const attendee of item.attendees) {
        if (attendee.name) list.push(attendee.name);
      }
      membersByCoach.set(item.coachStaffId, list);
    }
  }
  return privateProfiles.value.map((profile) => ({
    id: profile.coachStaffId,
    name: profile.coachName || "教练",
    tagText: profile.tagText && profile.tagText !== "不指定" ? profile.tagText : "",
    avatarUrl: coachAvatarMap.value.get(profile.coachStaffId) ?? null,
    bookedCount: bookedByCoach.get(profile.coachStaffId) ?? 0,
    memberNames: (membersByCoach.get(profile.coachStaffId) ?? []).slice(0, 4),
  }));
});

// 原版主页课表只展示团课；私教安排在教练详情（coach-board）查看
// 已取消（删除）的排课不展示（对标原版：删除后即消失）
const groupSessions = computed(() =>
  sessions.value.filter((item) => item.sessionKind !== "private" && item.status !== "cancelled"),
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
    const tasks: Promise<void>[] = [
      fetchStaffBookingDailyBoard(session.currentSiteId, selectedDate.value).then((response) => {
        sessions.value = response.items;
      }),
    ];
    if (canViewCoaches.value && !coaches.value.length) {
      tasks.push(
        fetchStaffDirectory(session.currentSiteId).then((response) => {
          coaches.value = response.items;
        }),
      );
    }
    if (canLoadCourses.value && !privateProfiles.value.length) {
      tasks.push(
        fetchPrivateCoaches(session.currentSiteId)
          .then((items) => {
            privateProfiles.value = items;
          })
          .catch(() => {
            privateProfiles.value = [];
          }),
      );
    }
    if (canLoadCourses.value && !courseCatalog.value.length) {
      tasks.push(
        fetchStaffCourseCatalog(session.currentSiteId, 1, 50)
          .then((response) => {
            courseCatalog.value = response.items;
          })
          .catch(() => {
            courseCatalog.value = [];
          }),
      );
    }
    if (!closures.value.length) {
      tasks.push(
        fetchSiteClosures(session.currentSiteId)
          .then((response) => {
            closures.value = response.items;
          })
          .catch(() => {
            closures.value = []; // 无闭馆读取权限时静默降级
          }),
      );
    }
    await Promise.all(tasks);
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

function backToToday() {
  weekOffset.value = 0;
  selectDate(todayIsoDate());
}

// 日历弹窗选任意日期：跳到对应周并加载（对标原版 u-calendar calendarChange → goBackDay）
const calendarShow = ref(false);
// u-calendar 的月份序列从 minDate 起仅生成 monthNum 个月（默认 3）。
// minDate 若写死太早（如 2024-01-01）而 monthNum 不够，今天所在月份不在序列内，
// month-switch 模式会回落到第 0 个月——表现为「打开日历不显示今天」。
// 这里动态取 去年1月 ~ 明年12月（36 个月），保证 defaultDate 总能定位。
const calendarMinDate = `${new Date().getFullYear() - 1}-01-01`;
const calendarMaxDate = `${new Date().getFullYear() + 1}-12-31`;
const calendarMonthNum = 36;

function onCalendarConfirm(value: string[] | string) {
  calendarShow.value = false;
  const picked = Array.isArray(value) ? value[0] : value;
  if (!picked) return;
  weekOffset.value = weekOffsetOfDate(picked);
  selectedDate.value = picked;
  loadBoard();
}

function openSessionDetail(sessionId: number) {
  uni.navigateTo({ url: `/subpackages/course/session-detail?id=${sessionId}` });
}

function openCoachBoard(coach: { id: number; name: string }) {
  uni.navigateTo({
    url: `/subpackages/course/coach-board?staffId=${coach.id}&name=${encodeURIComponent(coach.name)}`,
  });
}

// —— 课程卡视觉 ——
// 无课程封面数据，用深色渐变按课程稳定轮换（保证白字可读）
const CARD_GRADIENTS = [
  "linear-gradient(120deg, #2b5876 0%, #4e4376 100%)",
  "linear-gradient(120deg, #1f4037 0%, #2e7d5b 100%)",
  "linear-gradient(120deg, #29323c 0%, #485563 100%)",
  "linear-gradient(120deg, #614385 0%, #516395 100%)",
  "linear-gradient(120deg, #3a6073 0%, #16222a 100%)",
];

function cardBackground(item: StaffBookingDailyBoardItem) {
  // 优先课程库配置的背景图案（平台图案库），未配置按课程轮换
  return item.courseFaceGradient || CARD_GRADIENTS[item.courseId % CARD_GRADIENTS.length];
}

function difficultyStars(item: StaffBookingDailyBoardItem) {
  const meta = courseMetaMap.value.get(item.courseId);
  const count = Math.min(5, Math.max(0, meta?.difficulty ?? 0));
  return "★".repeat(count);
}

function minCapacityOf(item: StaffBookingDailyBoardItem) {
  return courseMetaMap.value.get(item.courseId)?.minCapacity ?? 0;
}

interface StatusButton {
  text: string;
  cls: string;
  masked: boolean;
  bookable: boolean;
}

const canAssistBook = computed(() => session.can("booking.appointment.create"));

// 对标原版 showBnt 状态按钮：代约/代排队/已约满/已停课/已取消/已结束/上课中/已下课
function statusButton(item: StaffBookingDailyBoardItem): StatusButton {
  if (item.status === "cancelled") return { text: "已取消", cls: "btn-grey", masked: true, bookable: false };
  if (item.status === "suspended") return { text: "已停课", cls: "btn-grey", masked: true, bookable: false };
  if (item.status === "completed") return { text: "已下课", cls: "btn-light", masked: true, bookable: false };
  const now = Date.now();
  const start = new Date(item.startsAt).getTime();
  const end = new Date(item.endsAt).getTime();
  if (!Number.isNaN(end) && now > end) return { text: "已结束", cls: "btn-grey", masked: true, bookable: false };
  if (!Number.isNaN(start) && !Number.isNaN(end) && now >= start && now <= end) {
    return { text: "上课中", cls: "btn-light", masked: false, bookable: false };
  }
  if (item.bookedCount >= item.capacity) {
    // 团课满员可代排队（后端满员自动入候补）；私教满员仅展示
    if (item.sessionKind !== "private") {
      return { text: "代排队", cls: "btn-light", masked: false, bookable: canAssistBook.value };
    }
    return { text: "已约满", cls: "btn-pink", masked: false, bookable: false };
  }
  return { text: "代 约", cls: "btn-green", masked: false, bookable: canAssistBook.value };
}

// 「代 约/代排队」直达详情并自动打开代预约面板（对标原版 leagueClassDetails(item, true, n)）
function tapStatusButton(item: StaffBookingDailyBoardItem) {
  const button = statusButton(item);
  if (!button.bookable) {
    openSessionDetail(item.id);
    return;
  }
  uni.navigateTo({ url: `/subpackages/course/session-detail?id=${item.id}&action=book` });
}

function coachInitial(name?: string | null) {
  return (name || "教").slice(0, 1);
}
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="page-shell">
    <view class="body-sheet">
    <view v-if="errorMessage" class="error-text">{{ errorMessage }}</view>

    <template v-if="canViewBoard">
      <!-- 私教横滚 + 课程表标题 + 周日历（对标原版 body） -->
      <view class="body-card">
        <template v-if="privateCoaches.length">
          <view class="pt-font">私教</view>
          <scroll-view scroll-x class="pt-scroll-view" :show-scrollbar="false">
            <view class="pt-scroll">
              <view
                v-for="coach in privateCoaches"
                :key="coach.id"
                class="pt-scroll-item"
                @tap="openCoachBoard(coach)"
              >
                <view class="pt-photo-wrap">
                  <!-- 标签缎带（原版 tag：金牌等，覆盖头像顶部） -->
                  <view v-if="coach.tagText" class="pt-tag">{{ coach.tagText }}</view>
                  <image v-if="coach.avatarUrl" class="coach-photo" :src="coach.avatarUrl" mode="aspectFill" />
                  <view v-else class="coach-photo coach-photo-fallback">{{ coachInitial(coach.name) }}</view>
                </view>
                <text class="pt-name">{{ coach.name }}</text>
                <view v-if="coach.memberNames.length" class="pt-member-wall">
                  <view v-for="(memberName, index) in coach.memberNames" :key="index" class="pt-member-dot">
                    {{ memberName.slice(0, 1) }}
                  </view>
                </view>
                <text class="pt-booked" :class="{ active: coach.bookedCount > 0 }">
                  {{ coach.bookedCount > 0 ? `${coach.bookedCount}人预约` : "可预约" }}
                </text>
              </view>
            </view>
          </scroll-view>
          <view class="divider" />
        </template>

        <view class="group-top">
          <text class="group-title">课程表</text>
          <view class="group-right">
            <view @tap="calendarShow = true">
              <u-icon name="calendar" size="21" color="#505050" />
            </view>
            <text v-if="showBackToday" class="back-today" @tap="backToToday">返回今天</text>
          </view>
        </view>

        <!-- 周日历（对标原版 week-calendar：swiper 循环滑动换周，上周几行、下日期数字圆行） -->
        <swiper
          class="week-swiper"
          circular
          :current="swiperCurrent"
          :disable-programmatic-animation="true"
          :duration="300"
          @change="onSwiperChange"
        >
          <swiper-item v-for="(slotDates, slot) in swiperSlots" :key="slot">
            <view class="week-calendar">
              <view class="calendar-weeks">
                <view
                  v-for="item in slotDates"
                  :key="`w-${item.value}`"
                  class="calendar-week"
                  :class="{ 'is-today': item.isToday && selectedDate !== item.value }"
                  @tap="selectDate(item.value)"
                >
                  {{ item.week }}
                </view>
              </view>
              <view class="calendar-days">
                <view
                  v-for="item in slotDates"
                  :key="`d-${item.value}`"
                  class="calendar-day"
                  @tap="selectDate(item.value)"
                >
                  <view class="date-circle" :class="{ checked: selectedDate === item.value }">
                    <text :class="{ 'is-today': item.isToday && selectedDate !== item.value }">
                      {{ item.isToday ? "今" : item.dayNum }}
                    </text>
                  </view>
                </view>
              </view>
            </view>
          </swiper-item>
        </swiper>
      </view>

      <!-- 闭馆横幅（对标原版 shopStatus==2 stop-doing-wrap） -->
      <view v-if="activeClosure" class="closure-banner">
        <u-icon name="info-circle-fill" size="20" color="#dc3c5c" />
        <view class="closure-text">
          <text class="closure-title">闭馆时间：{{ activeClosure.beginDate }} 至 {{ activeClosure.endDate }}</text>
          <text v-if="activeClosure.reason" class="closure-reason">{{ activeClosure.reason }}</text>
        </view>
      </view>

      <!-- 团课列表（深色渐变卡 + 白字 + 状态按钮） -->
      <view class="course-list">
        <view
          v-for="item in groupSessions"
          :key="item.id"
          class="course-card"
          :style="{ background: cardBackground(item) }"
          @tap="openSessionDetail(item.id)"
        >
          <view class="card-left">
            <view class="name-row">
              <text class="course-name">{{ item.courseName || "未命名课程" }}</text>
            </view>
            <view class="info-row">
              <text v-if="item.roomName">{{ item.roomName }}</text>
              <text v-if="item.roomName" class="info-sep">|</text>
              <template v-if="difficultyStars(item)">
                <text>难度</text>
                <text class="stars">{{ difficultyStars(item) }}</text>
              </template>
            </view>
            <view class="coach-row">
              <image
                v-if="coachAvatarMap.get(item.coachStaffId)"
                class="coach-ava"
                :src="coachAvatarMap.get(item.coachStaffId) || ''"
                mode="aspectFill"
              />
              <view v-else class="coach-ava coach-ava-fallback">{{ coachInitial(item.coachName) }}</view>
              <text class="coach-name">{{ item.coachName || "待定教练" }}</text>
            </view>
            <view class="booked-row">
              <view v-if="item.attendees?.length" class="attendee-wall">
                <view
                  v-for="(attendee, index) in item.attendees"
                  :key="attendee.memberId"
                  class="attendee-dot"
                  :class="{ first: index === 0 }"
                >
                  {{ (attendee.name || "客").slice(0, 1) }}
                </view>
                <view v-if="item.bookedCount > (item.attendees?.length ?? 0)" class="attendee-more">…</view>
              </view>
              <text class="booked-strong">{{ item.bookedCount }}</text>
              <text class="booked-total">/{{ item.capacity }}</text>
              <view v-if="item.bookedCount >= item.capacity" class="full-icon">满</view>
              <view v-else-if="minCapacityOf(item) > 0" class="min-num">满{{ minCapacityOf(item) }}人开课</view>
              <view v-if="item.waitlistCount" class="queue-chip">排队{{ item.waitlistCount }}</view>
            </view>
          </view>
          <view class="card-right">
            <text class="start-time">{{ formatClock(item.startsAt) }}</text>
            <text class="end-time">{{ formatClock(item.endsAt) }}结束</text>
            <view class="status-btn" :class="statusButton(item).cls" @tap.stop="tapStatusButton(item)">{{ statusButton(item).text }}</view>
          </view>
          <view v-if="statusButton(item).masked" class="card-mask" />
          <!-- 停课/取消印章（对标原版 img_wrap 图标） -->
          <view v-if="item.status === 'suspended'" class="card-stamp">已停课</view>
          <view v-else-if="item.status === 'cancelled'" class="card-stamp grey">已取消</view>
        </view>
      </view>

      <view v-if="!groupSessions.length" class="nodata-box">
        <text class="sg-empty-text">~ 今日无排课 ~</text>
      </view>
    </template>

    <u-empty v-else-if="forbidden" mode="permission" text="当前账号暂无课程日程权限" />
    <u-empty v-else mode="permission" text="需要课程日程查看权限" />

    <view class="brand-footer">觅境约课</view>
    </view>

    <!-- 日历弹窗（对标原版 u-calendar） -->
    <u-calendar
      :show="calendarShow"
      mode="single"
      :default-date="selectedDate"
      :min-date="calendarMinDate"
      :max-date="calendarMaxDate"
      :month-num="calendarMonthNum"
      :close-on-click-overlay="true"
      color="#22c788"
      :month-switch="true"
      @confirm="onCalendarConfirm"
      @close="calendarShow = false"
    />
  </view>
</template>

<style scoped lang="scss">
// —— 黄导航壳 + 白圆角主体（对标原版 course-container / main-content） ——
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 28rpx 24rpx 60rpx;
  background: #f7f7f7;
  border-radius: 20rpx 20rpx 0 0;
  box-sizing: border-box;
}

.error-text {
  margin-top: $spacing-sm;
  color: $color-danger;
  font-size: 24rpx;
}

// —— 白色 body 卡（对标原版 body：私教 + 课程表 + 周日历） ——
.body-card {
  margin-top: $spacing-md;
  padding: 28rpx;
  background: $color-surface;
  border-radius: 21rpx;
}

.pt-font {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.pt-scroll-view {
  margin-top: 20rpx;
  white-space: nowrap;
}

.pt-scroll {
  display: inline-flex;
}

// 原版 pt-scroll-item：132rpx 宽、方形圆角 12rpx 头像
.pt-scroll-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 132rpx;
  margin-right: 40rpx;
}

.pt-photo-wrap {
  position: relative;
}

// 标签缎带（原版 tag：金牌等，覆盖头像顶部居中）
.pt-tag {
  position: absolute;
  top: -12rpx;
  left: 50%;
  z-index: 2;
  padding: 2rpx 14rpx;
  background: linear-gradient(90deg, #f7c873, #e89b2c);
  border-radius: 999rpx;
  color: #7a4a00;
  font-size: 18rpx;
  white-space: nowrap;
  transform: translateX(-50%);
}

.coach-photo {
  width: 132rpx;
  height: 132rpx;
  border-radius: 12rpx;
}

.coach-photo-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #5fa3ea, #3f77c9);
  color: #fff;
  font-size: 44rpx;
}

.pt-name {
  overflow: hidden;
  max-width: 132rpx;
  margin-top: 12rpx;
  font-size: 24rpx;
  color: $color-text;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.pt-booked {
  margin-top: 6rpx;
  color: $color-text-disabled;
  font-size: 20rpx;

  &.active {
    color: $color-success;
  }
}

// 私教横滚：会员首字墙（对标原版 userList 头像，前4位重叠）
.pt-member-wall {
  display: flex;
  margin-top: 10rpx;
  height: 35rpx;
}

.pt-member-dot {
  width: 35rpx;
  height: 35rpx;
  margin-left: -10rpx;
  border: 1rpx solid #fff;
  border-radius: 50%;
  background: $color-info;
  color: #fff;
  font-size: 20rpx;
  line-height: 33rpx;
  text-align: center;

  &:first-child {
    margin-left: 0;
  }
}

.divider {
  height: 1rpx;
  margin: 28rpx 0;
  background: $color-divider;
}

.group-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.group-title {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.group-right {
  display: flex;
  align-items: center;
  gap: 24rpx;
}

.back-today {
  color: $color-success;
  font-size: 26rpx;
}

// —— 周日历（原版 week-calendar：周字行 + 日期数字圆行，选中绿圆 #22c788） ——
.week-swiper {
  height: 156rpx;
  margin-top: 18rpx;
}

.week-calendar {
  padding-bottom: 6rpx;
}

.calendar-weeks,
.calendar-days {
  display: flex;
}

.calendar-week {
  flex: 1;
  color: $color-text-secondary;
  font-size: 24rpx;
  text-align: center;

  &.is-today {
    color: $color-success;
  }
}

.calendar-days {
  margin-top: 16rpx;
}

.calendar-day {
  display: flex;
  flex: 1;
  justify-content: center;
}

.date-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  border-radius: 50%;
  color: $color-text;
  font-size: 28rpx;

  .is-today {
    color: $color-success;
  }

  &.checked {
    background: $color-success;
    color: #fff;

    .is-today {
      color: #fff;
    }
  }
}

// —— 闭馆横幅（对标原版 stop-doing-wrap） ——
.closure-banner {
  display: flex;
  align-items: flex-start;
  gap: 14rpx;
  margin-top: $spacing-sm;
  padding: 22rpx 24rpx;
  background: #fdecef;
  border-radius: 16rpx;
}

.closure-text {
  display: flex;
  flex-direction: column;
}

.closure-title {
  color: $color-danger;
  font-size: 26rpx;
  font-weight: 500;
}

.closure-reason {
  margin-top: 6rpx;
  color: #b3556a;
  font-size: 22rpx;
}

// —— 课程卡：深色渐变 + 白字（对标原版背景图卡） ——
.course-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
  margin-top: $spacing-md;
}

.course-card {
  position: relative;
  display: flex;
  overflow: hidden;
  justify-content: space-between;
  padding: 30rpx 28rpx;
  border-radius: 21rpx;
}

.card-left {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.name-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
}

.course-name {
  overflow: hidden;
  color: #fff;
  font-size: 39rpx;
  font-weight: 500;
  line-height: 48rpx;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.info-row {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-top: 14rpx;
  color: rgba(255, 255, 255, 0.9);
  font-size: 24rpx;
}

.info-sep {
  color: rgba(255, 255, 255, 0.5);
}

.stars {
  color: $color-brand-yellow;
  letter-spacing: 4rpx;
}

.coach-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
  margin-top: 22rpx;
}

.coach-ava {
  width: 56rpx;
  height: 56rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.6);
  border-radius: 50%;
}

.coach-ava-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.25);
  color: #fff;
  font-size: 24rpx;
}

.coach-name {
  color: #fff;
  font-size: 26rpx;
}

.booked-row {
  display: flex;
  align-items: center;
  gap: 12rpx;
  margin-top: 20rpx;
}

// 课程卡：预约会员首字墙（对标原版 photo-wrap，前7位重叠）
.attendee-wall {
  display: flex;
  align-items: center;
  margin-right: 6rpx;
}

.attendee-dot {
  width: 44rpx;
  height: 44rpx;
  margin-left: -14rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.8);
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.28);
  color: #fff;
  font-size: 22rpx;
  line-height: 42rpx;
  text-align: center;

  &.first {
    margin-left: 0;
  }
}

.attendee-more {
  margin-left: 6rpx;
  color: rgba(255, 255, 255, 0.8);
  font-size: 24rpx;
}

.queue-chip {
  height: 35rpx;
  padding: 0 12rpx;
  border-radius: 16rpx;
  background: rgba(0, 0, 0, 0.3);
  color: #fff;
  font-size: 20rpx;
  line-height: 35rpx;
}

.booked-strong {
  color: #fff;
  font-size: 30rpx;
  font-weight: 500;
}

.booked-total {
  color: rgba(255, 255, 255, 0.75);
  font-size: 24rpx;
}

// 原版：红圆"满"标 #d95872
.full-icon {
  width: 35rpx;
  height: 35rpx;
  border-radius: 50%;
  background: #d95872;
  color: #fff;
  font-size: 22rpx;
  line-height: 35rpx;
  text-align: center;
}

// 原版：黑30%胶囊"满X人开课"
.min-num {
  height: 35rpx;
  padding: 0 12rpx;
  border-radius: 16rpx;
  background: rgba(0, 0, 0, 0.3);
  color: #fff;
  font-size: 20rpx;
  line-height: 35rpx;
}

.card-right {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  margin-left: 20rpx;
}

.start-time {
  padding-top: 10rpx;
  color: #fff;
  font-size: 39rpx;
  line-height: 39rpx;
}

.end-time {
  margin-top: 13rpx;
  color: rgba(255, 255, 255, 0.85);
  font-size: 21rpx;
  line-height: 21rpx;
}

// —— 状态按钮（原版四色 136×62rpx 胶囊） ——
.status-btn {
  width: 136rpx;
  height: 62rpx;
  margin-top: 20rpx;
  border-radius: 31rpx;
  font-size: 26rpx;
  line-height: 62rpx;
  text-align: center;
}

.btn-green {
  background: #22c788;
  color: #fff;
}

.btn-light {
  background: #ecf8f3;
  color: #22c788;
}

.btn-pink {
  background: #faf5f8;
  color: #d95872;
}

.btn-grey {
  background: #bababa;
  color: #fff;
}

// 已停课/已取消/已结束 灰化遮罩（对标原版 make-white mask）
.card-mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  background: rgba(255, 255, 255, 0.55);
  pointer-events: none;
}

// 停课/取消印章（对标原版 img_wrap 印章图标）
.card-stamp {
  position: absolute;
  top: 50%;
  left: 50%;
  z-index: 2;
  padding: 8rpx 22rpx;
  border: 4rpx solid #dc3c5c;
  border-radius: 12rpx;
  color: #dc3c5c;
  font-size: 34rpx;
  font-weight: 600;
  letter-spacing: 4rpx;
  transform: translate(-50%, -50%) rotate(-14deg);
  pointer-events: none;

  &.grey {
    border-color: #989898;
    color: #989898;
  }
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

.nodata-box {
  padding: 120rpx 0;
}

</style>
