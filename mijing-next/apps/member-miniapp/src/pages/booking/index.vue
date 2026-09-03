<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { getMemberBookingCatalog, getMemberSiteClosureStatus } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import {
  ensureMemberContext,
  loadJoinableSites,
  loadJoinedMemberSites,
  selectMemberSite,
} from "@/composables/member-context";
import type { MemberBookingCatalogItem, MemberSiteClosureStatus, MemberSiteOption } from "@/types/member";
import { navigateToOnce } from "@/utils/navigate";
import { syncMemberTabBar } from "@/utils/tab-bar";

// 本地日期键（YYYY-MM-DD），避免 toISOString() 的 UTC 偏移导致"今天"错位。
function localDateKey(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

const loading = ref(true);
const errorMessage = ref("");
const needsSite = ref(false);
const sites = ref<MemberSiteOption[]>([]);
const siteName = ref("");
const todayIso = localDateKey(new Date());
const selectedDate = ref(todayIso);
const sessions = ref<MemberBookingCatalogItem[]>([]);
const closureStatus = ref<MemberSiteClosureStatus | null>(null);
const catalogLastDate = ref("2050-12-31");
const showCalendar = ref(false);

const closureMessage = computed(() => {
  const closure = closureStatus.value?.closure;
  if (!closureStatus.value?.isClosed || !closure) return "";
  const period = closure.beginDate && closure.endDate
    ? `${closure.beginDate} 至 ${closure.endDate}`
    : "当前日期";
  return `${period} 场馆闭店${closure.reason ? `：${closure.reason}` : ""}`;
});

const weekdayCn = ["日", "一", "二", "三", "四", "五", "六"];
const minDateIso = todayIso;
const maxDateIso = computed(() => catalogLastDate.value);

// 当前可见 7 天条的第一列日期（锚点），初始为今天 → 今天落在第一列。
const anchorDate = ref(todayIso);
// swiper 三页循环的当前页索引（0/1/2），初始居中。
const swiperCurrent = ref(1);

const palette = [
  "linear-gradient(135deg, #22c788 0%, #1dac75 100%)",
  "linear-gradient(135deg, #48afff 0%, #2b7fd4 100%)",
  "linear-gradient(135deg, #ffae00 0%, #f29100 100%)",
  "linear-gradient(135deg, #ff846d 0%, #e77a76 100%)",
  "linear-gradient(135deg, #6c8cff 0%, #4a5fd4 100%)",
];

const weekStrips = computed(() => {
  // 三页循环：当前页 = anchorDate 周；其余两页按循环偏移取 ±7 天。
  // offset(slot) = diff===2 ? -1 : diff，其中 diff = (slot - swiperCurrent + 3) % 3
  const cur = swiperCurrent.value;
  const base = new Date(`${anchorDate.value}T00:00:00`);
  return [0, 1, 2].map((slot) => {
    const diff = (slot - cur + 3) % 3;
    const offset = diff === 2 ? -1 : diff;
    const start = new Date(base);
    start.setDate(base.getDate() + offset * 7);
    return Array.from({ length: 7 }, (_, i) => {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      const iso = localDateKey(d);
      return {
        iso,
        weekdayLabel: `周${weekdayCn[d.getDay()] ?? ""}`,
        day: d.getDate(),
        isToday: iso === todayIso,
        isPast: iso < todayIso,
      };
    });
  });
});

const isGoBackToday = computed(() => {
  const start = anchorDate.value;
  const end = shiftIso(start, 6);
  return todayIso < start || todayIso > end;
});

const privateCoaches = computed(() => {
  const map = new Map<string, { name: string; sessionId: number; tagText: string; avatarUrl: string | null }>();
  for (const s of sessions.value) {
    if (!isPrivateSession(s)) continue;
    const name = s.coachName || "私教";
    if (map.has(name)) continue;
    map.set(name, {
      name,
      sessionId: s.id,
      tagText: isFull(s) && s.waitlistEnabled ? "候补" : "私教",
      avatarUrl: s.coachAvatarUrl ?? null,
    });
  }
  return Array.from(map.values());
});

const teamSessions = computed(() => sessions.value.filter((s) => !isPrivateSession(s)));

const noDataText = computed(() =>
  selectedDate.value < todayIso ? "~ 已结束 ~" : "~ 今日无排课 ~",
);

function shiftIso(iso: string, days: number) {
  const d = new Date(`${iso}T00:00:00`);
  d.setDate(d.getDate() + days);
  return localDateKey(d);
}

function formatSessionTime(iso: string) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

function isPrivateSession(session: MemberBookingCatalogItem) {
  const kind = (session.sessionKind || session.courseType || "").toLowerCase();
  return kind === "private" || kind.includes("private");
}

function courseTypeLabel(session: MemberBookingCatalogItem) {
  return isPrivateSession(session) ? "私教" : "团课";
}

function coachInitial(session: MemberBookingCatalogItem) {
  const name = session.coachName || "";
  return name ? name.slice(0, 1) : "教";
}

function isFull(session: MemberBookingCatalogItem) {
  const booked = session.bookedCount ?? 0;
  return session.capacity > 0 && booked >= session.capacity;
}

function tagText(session: MemberBookingCatalogItem) {
  if (isFull(session) && session.waitlistEnabled) return "候补";
  if (isPrivateSession(session)) return "私教";
  return "";
}

function cardBackground(session: MemberBookingCatalogItem, index: number) {
  // 后端 catalog 当前不返回封面图/纯色；displayColor 待后端暴露后接入。
  // 见 docs/BOOKING-CATALOG-BACKEND-GAP.md §5.2。
  return `background-image:${palette[index % palette.length]};background-size:100% 100%;`;
}

function isGrayed(session: MemberBookingCatalogItem) {
  return !session.bookable && !session.memberAppointmentStatus;
}

function btnLabel(session: MemberBookingCatalogItem) {
  if (session.memberAppointmentStatus === "confirmed") return "已预约";
  if (session.memberAppointmentStatus === "waitlisted") return "排队中";
  if (session.memberAppointmentStatus === "cancelled" || session.memberAppointmentStatus === "absent" || session.memberAppointmentStatus === "completed") return "已结束";
  if (session.bookable) {
    if (isFull(session) && session.waitlistEnabled) return "去排队";
    if (isFull(session)) return "已约满";
    return "约课";
  }
  return isFull(session) ? "已约满" : "已截止";
}

function btnStyle(session: MemberBookingCatalogItem) {
  const base = "border:none;height:60rpx;padding:0 28rpx;font-size:24rpx;border-radius:30rpx;line-height:60rpx;";
  const label = btnLabel(session);
  switch (label) {
    case "约课":
      return `${base}background:#22c788;color:#fff;`;
    case "去排队":
    case "排队中":
      return `${base}background:#ffae00;color:#fff;`;
    case "已预约":
      return `${base}background:#dc3c5c;color:#fff;`;
    case "已约满":
    case "已结束":
      return `${base}background:#bfbfbf;color:#fff;`;
    case "已截止":
      return `${base}background:transparent;color:#bfbfbf;border:1rpx solid #bfbfbf;`;
    default:
      return `${base}background:#bfbfbf;color:#fff;`;
  }
}

const hasLoaded = ref(false);

async function loadCatalog() {
  // 仅首次显示全屏加载；onShow 返回/切换 tab 时静默刷新
  loading.value = !hasLoaded.value;
  errorMessage.value = "";
  needsSite.value = false;

  try {
    const context = await ensureMemberContext();
    if (!context) {
      sites.value = await loadJoinableSites();
      needsSite.value = true;
      return;
    }

    siteName.value = context.siteName;
    const [response, closureResponse] = await Promise.all([
      getMemberBookingCatalog(context.tenantId, context.siteId, selectedDate.value),
      getMemberSiteClosureStatus(context.tenantId, context.siteId).catch(() => null),
    ]);
    sessions.value = response.data.items;
    closureStatus.value = closureResponse?.data ?? null;
    if (response.data.limits?.catalogLastDate) {
      catalogLastDate.value = response.data.limits.catalogLastDate;
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课程列表加载失败";
  } finally {
    loading.value = false;
    hasLoaded.value = true;
  }
}

function selectDate(value: string) {
  if (selectedDate.value === value) {
    return;
  }
  selectedDate.value = value;
  // 切换日期清空旧列表，给出即时反馈
  sessions.value = [];
  loadCatalog();
}

function goBackToday() {
  if (selectedDate.value === todayIso && anchorDate.value === todayIso) return;
  anchorDate.value = todayIso;
  swiperCurrent.value = 1;
  selectDate(todayIso);
}

function onSwiperChange(e: { detail: { current: number; source?: string } }) {
  const newIdx = e.detail.current;
  if (newIdx === swiperCurrent.value) return;
  const diff = (newIdx - swiperCurrent.value + 3) % 3;
  const direction = diff === 1 ? 1 : -1;
  anchorDate.value = shiftIso(anchorDate.value, direction * 7);
  swiperCurrent.value = newIdx;
}

function openCalendar() {
  showCalendar.value = true;
}

function onCalendarConfirm(e: string[]) {
  showCalendar.value = false;
  const picked = Array.isArray(e) ? e[0] : e;
  if (!picked) return;
  anchorDate.value = picked;
  swiperCurrent.value = 1;
  selectDate(picked);
}

function onCalendarClose() {
  showCalendar.value = false;
}

async function openSitePicker() {
  const joinedSites = await loadJoinedMemberSites();
  if (joinedSites.length === 0) {
    uni.showToast({ title: "请先加入场馆", icon: "none" });
    return;
  }
  sites.value = joinedSites;
  uni.showActionSheet({
    itemList: sites.value.map((site) => site.name),
    success: async (result) => {
      const site = sites.value[result.tapIndex];
      if (!site) return;
      selectMemberSite(site);
      await loadCatalog();
    },
  });
}

function openOnboarding() {
  uni.navigateTo({ url: "/pages/onboarding/profile" });
}

function openSessionDetail(sessionId: number) {
  if (closureStatus.value?.isClosed) {
    uni.showToast({ title: "场馆闭店期间暂不可约课", icon: "none" });
    return;
  }
  navigateToOnce(`/pages/booking/detail?id=${sessionId}`);
}

function openCoach(coach: { name: string; sessionId: number; avatarUrl: string | null }) {
  if (closureStatus.value?.isClosed) {
    uni.showToast({ title: "场馆闭店期间暂不可约课", icon: "none" });
    return;
  }
  // 对标原版：进入教练维度的私教预约页（按天选时段）
  const session = sessions.value.find((s) => s.id === coach.sessionId);
  const coachId = session?.coachStaffId;
  const params = [
    coachId != null ? `coachId=${coachId}` : "",
    `name=${encodeURIComponent(coach.name)}`,
    coach.avatarUrl ? `avatar=${encodeURIComponent(coach.avatarUrl)}` : "",
    `date=${selectedDate.value}`,
  ]
    .filter(Boolean)
    .join("&");
  uni.navigateTo({ url: `/pages/booking/coach?${params}` });
}

onShow(async () => {
  syncMemberTabBar(1);
  if (await requireMemberAuth()) await loadCatalog();
});

onPullDownRefresh(async () => {
  await loadCatalog();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="course-container">
    <view v-if="needsSite" class="page-container">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="site-prompt">
        <view class="section-title">选择场馆</view>
        <view class="prompt-text">选择场馆后查看可预约课程</view>
        <u-button type="primary" @click="openSitePicker">选择场馆</u-button>
        <u-button plain @click="openOnboarding">去加入场馆</u-button>
      </view>
    </view>

    <template v-else>
      <view class="main-content">
        <u-alert
          v-if="closureMessage"
          type="warning"
          title="场馆闭店提示"
          :description="closureMessage"
          :custom-style="{ margin: '20rpx 28rpx 0' }"
        />
        <view class="body">
          <view v-if="privateCoaches.length" class="pt">
            <view class="pt-font">
              <text>约私教</text>
            </view>
            <scroll-view class="pt-scroll-view" scroll-x :show-scrollbar="false">
              <view class="pt-scroll">
                <view
                  v-for="coach in privateCoaches"
                  :key="coach.name"
                  class="pt-scroll-item"
                  @tap="openCoach(coach)"
                >
                  <view class="image-wrap">
                    <view v-if="coach.tagText" class="tag">
                      <view class="text">{{ coach.tagText }}</view>
                    </view>
                    <image v-if="coach.avatarUrl" class="coach-photo coach-photo--img" :src="coach.avatarUrl" mode="aspectFill" />
                    <view v-else class="coach-photo">{{ coach.name.slice(0, 1) }}</view>
                  </view>
                  <view class="pt-scroll-font">
                    <text>{{ coach.name }}</text>
                  </view>
                </view>
              </view>
            </scroll-view>
            <u-line color="#DDDDDD" length="694rpx" margin="28rpx" />
          </view>

          <view class="group">
            <view class="group-top">
              <text class="group-top1">课程表</text>
              <view class="group-top-right">
                <text
                  v-if="isGoBackToday"
                  class="group-top2"
                  @tap="goBackToday"
                >返回今天</text>
                <view class="group-top3" @tap="openCalendar">
                  <u-icon name="calendar" size="24" color="#989898" />
                </view>
              </view>
            </view>

            <view class="calendar-wrap">
              <swiper
                class="week-swiper"
                :current="swiperCurrent"
                :circular="true"
                :disable-programmatic-animation="true"
                :duration="300"
                :indicator-dots="false"
                :autoplay="false"
                @change="onSwiperChange"
              >
                <swiper-item v-for="(strip, idx) in weekStrips" :key="idx">
                  <view class="calendar-weeks">
                    <view
                      v-for="d in strip"
                      :key="`w-${d.iso}`"
                      class="calendar-week"
                      :class="{ 'week-checked': d.iso === selectedDate }"
                      @tap="selectDate(d.iso)"
                    >
                      <view>{{ d.weekdayLabel }}</view>
                    </view>
                  </view>
                  <view class="calendar-days">
                    <view
                      v-for="d in strip"
                      :key="`d-${d.iso}`"
                      class="calendar-day"
                      @tap="selectDate(d.iso)"
                    >
                      <view class="date" :class="{ 'is-checked': d.iso === selectedDate }">
                        <view :class="{ 'is-today': d.isToday && d.iso !== selectedDate, 'is-past': d.isPast && d.iso !== selectedDate }">
                          {{ d.isToday ? "今" : d.day }}
                        </view>
                      </view>
                    </view>
                  </view>
                </swiper-item>
              </swiper>
            </view>
          </view>
        </view>

        <view class="courseli">
          <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

          <template v-if="!errorMessage && teamSessions.length">
            <view
              v-for="(session, index) in teamSessions"
              :key="session.id"
              class="course-warp"
              :class="{ last: index === teamSessions.length - 1 }"
            >
              <view class="course" @tap="openSessionDetail(session.id)">
                <view
                  class="course_item_wrap"
                  :class="{ gray: isGrayed(session) }"
                  :style="cardBackground(session, index)"
                >
                  <view v-if="isGrayed(session)" class="make-white mask" />
                  <view class="coursetext">
                    <view class="coursetext-row">
                      <view class="left-module">
                        <view class="top-view">
                          <text class="courseName">{{ session.courseName || "课程" }}</text>
                          <view v-if="tagText(session)" class="tag-wrap">
                            <text class="item1-text">{{ tagText(session) }}</text>
                          </view>
                        </view>
                        <view class="center-view">
                          <view class="info-text">
                            <text v-if="session.coachName">{{ session.coachName }}</text>
                            <u-line
                              v-if="session.coachName"
                              color="#ffffff"
                              direction="col"
                              length="24rpx"
                              margin="0 14rpx"
                            />
                            <text>{{ courseTypeLabel(session) }}</text>
                          </view>
                        </view>
                        <view class="bottom-view">
                          <view class="course-photo">
                            <image
                              v-if="session.coachAvatarUrl"
                              class="coach-avatar coach-avatar--img"
                              :src="session.coachAvatarUrl"
                              mode="aspectFill"
                            />
                            <view v-else class="coach-avatar">{{ coachInitial(session) }}</view>
                          </view>
                          <view class="member-info">
                            <view class="member-info-wrap">
                              <view class="name">{{ session.coachName || "教练" }}</view>
                              <view class="photo-info">
                                <view v-if="session.bookedAvatars?.length" class="booked-avatars">
                                  <view
                                    v-for="(avatar, ai) in session.bookedAvatars"
                                    :key="ai"
                                    class="booked-avatar-wrap"
                                    :class="{ first: ai === 0 }"
                                  >
                                    <image v-if="avatar" class="booked-avatar" :src="avatar" mode="aspectFill" />
                                    <view v-else class="booked-avatar booked-avatar--empty" />
                                  </view>
                                  <view v-if="(session.bookedCount ?? 0) > session.bookedAvatars.length" class="booked-more">
                                    <u-icon name="more-dot-fill" size="10" color="#ffffff" />
                                  </view>
                                </view>
                                <view class="forespeak-num">
                                  <text class="already-preengage">{{ session.bookedCount ?? 0 }}</text>/<text>{{ session.capacity }}</text>
                                </view>
                                <view v-if="isFull(session)" class="full-icon">满</view>
                                <view v-else-if="session.capacity > 0" class="min-num">
                                  剩余 {{ session.capacity - (session.bookedCount ?? 0) }}
                                </view>
                              </view>
                            </view>
                          </view>
                        </view>
                      </view>

                      <view class="right-module">
                        <view class="start-time">{{ formatSessionTime(session.startsAt) }}</view>
                        <view class="end-time">{{ formatSessionTime(session.endsAt) }}结束</view>
                        <view class="btn-wrap">
                          <u-button
                            :custom-style="btnStyle(session)"
                            :hairline="false"
                            hover-class="none"
                            @click.stop="openSessionDetail(session.id)"
                          >
                            {{ btnLabel(session) }}
                          </u-button>
                        </view>
                      </view>
                    </view>
                  </view>
                </view>
              </view>
            </view>
          </template>

          <view v-else-if="!errorMessage" class="noCourseData">
            <u-empty mode="list" :text="noDataText" />
          </view>
        </view>
      </view>

      <view class="bottom-logo">
        <text>觅境约课</text>
      </view>
    </template>
  </view>

  <u-calendar
    :show="showCalendar"
    mode="single"
    :default-date="selectedDate"
    :min-date="minDateIso"
    :max-date="maxDateIso"
    :close-on-click-overlay="true"
    color="#22c788"
    :month-switch="true"
    @confirm="onCalendarConfirm"
    @close="onCalendarClose"
  />
</template>

<style scoped lang="scss">
.course-container {
  min-height: 100vh;
  background: $color-page;
}

.site-prompt {
  display: grid;
  gap: $spacing-md;
  margin-top: $spacing-lg;
}

.prompt-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.main-content {
  background: $color-surface;
  border-radius: 26rpx 26rpx 0 0;
  overflow: hidden;
}

.body {
  position: relative;
  overflow: hidden;
}

.pt {
  position: relative;
}

.pt-font {
  padding: 45rpx 26rpx 0;

  text {
    color: $color-text;
    font-size: 34rpx;
    font-weight: 500;
  }
}

.pt-scroll-view {
  margin-left: 33rpx;
  width: 100%;
  white-space: nowrap;
}

.pt-scroll {
  display: flex;
  padding-left: 5rpx;
}

.pt-scroll-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-right: 40rpx;
  margin-top: 27rpx;
  width: 132rpx;
}

.pt-scroll-item .image-wrap {
  position: relative;
  width: 132rpx;
  height: 132rpx;
}

.pt-scroll-item .coach-photo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  border-radius: 22rpx;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
  color: #fff;
  font-size: 52rpx;
  font-weight: 500;
}

.pt-scroll-item .tag {
  position: absolute;
  bottom: 0;
  left: -5rpx;
  display: flex;
  align-items: flex-end;
  width: 120rpx;
  height: 34rpx;
}

.pt-scroll-item .tag .text {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 32rpx;
  padding: 0 5rpx;
  background: $color-badge-orange;
  color: #fff;
  font-size: 22rpx;
}

.pt-scroll-font {
  margin-top: 17rpx;
  margin-bottom: 10rpx;
  text-align: center;
  line-height: 28rpx;

  text {
    color: $color-text;
    font-size: 28rpx;
    font-weight: 400;
  }
}

.group {
  margin: 42rpx 28rpx 0;
}

.group-top {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 48rpx;
}

.group-top-right {
  display: flex;
  align-items: center;
}

.group-top1 {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 500;
}

.group-top3 {
  margin-left: 16rpx;
  padding: 0 8rpx;
  height: 48rpx;
  display: flex;
  align-items: center;
}

.group-top2 {
  padding: 12rpx 23rpx;
  background: $color-primary-light;
  border-radius: 30rpx;
  color: $color-primary;
  font-size: 22rpx;
  font-weight: 400;
}

.calendar-wrap {
  padding: 15rpx 0 8rpx;
}

.week-swiper {
  height: 116rpx;
  width: 100%;
}

.calendar-weeks {
  display: flex;
  flex-flow: row nowrap;
  justify-content: center;
  width: 100%;
  font-size: 28rpx;
}

.calendar-week {
  text-align: center;
  width: calc(100% / 7);

  view {
    width: 76rpx;
    margin: 0 auto;
    padding: 12rpx 10rpx 8rpx;
    line-height: 26rpx;
    border-top-left-radius: 50%;
    border-top-right-radius: 50%;
  }
}

.calendar-days {
  display: flex;
  flex-flow: row wrap;
  width: 100%;
  font-size: 28rpx;
}

.calendar-day {
  display: flex;
  flex-flow: column nowrap;
  justify-content: flex-start;
  text-align: center;
  width: calc(100% / 7);
  height: 70rpx;
}

.date {
  height: 50rpx;
  line-height: 32rpx;
  margin: 0 auto;

  view {
    width: 76rpx;
    margin: 0 auto;
    padding: 8rpx 10rpx 21rpx;
    line-height: 30rpx;
    border-bottom-left-radius: 50%;
    border-bottom-right-radius: 50%;
  }
}

.is-today {
  color: $color-primary !important;
}

.is-past {
  color: $color-text-muted;
}

.is-checked view,
.week-checked view {
  background: $color-primary;
  color: #fff;
}

.courseli {
  background: $color-surface-muted;
  min-height: 750rpx;
  padding: 36rpx 30rpx 58rpx;
}

.noCourseData {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 600rpx;
  padding-top: 160rpx;
}

.gray {
  filter: grayscale(100%);
}

.course-warp {
  padding-bottom: 40rpx;

  &.last {
    padding-bottom: 0;
  }
}

.course {
  position: relative;
  width: 688rpx;
  height: 278rpx;
  margin: 0 auto;
  border-radius: 21rpx;
  overflow: hidden;
}

.course_item_wrap {
  width: 100%;
  height: 100%;
  overflow: hidden;
  background-size: 100% 100%;
}

.mask {
  position: absolute;
  inset: 0;
  z-index: 2;
}

.make-white {
  background: hsla(0, 0%, 100%, 0.35);
}

.coursetext {
  margin: 28rpx 32rpx 0 33rpx;
}

.coursetext-row {
  display: flex;
  justify-content: space-between;
}

.left-module {
  overflow: hidden;
  flex: 1;
}

.top-view {
  display: flex;
  align-items: center;
  min-height: 80rpx;
}

.courseName {
  color: #fff;
  font-size: 39rpx;
  font-weight: 500;
  line-height: 48rpx;
}

.tag-wrap {
  display: flex;
  align-items: center;
  margin-left: 10rpx;

  .item1-text {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 32rpx;
    padding: 0 8rpx;
    margin-left: -1rpx;
    background-color: $color-badge-orange;
    border-radius: 0 8rpx 8rpx 0;
    color: #fff;
    font-size: 22rpx;
    white-space: nowrap;
  }
}

.center-view {
  display: flex;
  align-items: center;
  height: 24rpx;
  margin-top: 10rpx;
}

.info-text {
  display: flex;
  align-items: center;
  color: #fff;
  font-size: 24rpx;
  line-height: 24rpx;
}

.bottom-view {
  display: flex;
  height: 82rpx;
  margin-top: 28rpx;
}

.course-photo {
  width: 82rpx;
  height: 82rpx;
  border-radius: 50%;
  overflow: hidden;
}

.coach-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.85);
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.coach-avatar--img {
  display: block;
  width: 82rpx;
  height: 82rpx;
}

.member-info {
  margin: 8rpx 0 2rpx 8rpx;
}

.member-info-wrap {
  display: flex;
  flex-direction: column;
}

.member-info-wrap .name {
  color: #fff;
  font-size: 24rpx;
  line-height: 24rpx;
  margin-bottom: 10rpx;
}

.photo-info {
  display: flex;
  align-items: center;
  height: 36rpx;
}

/* 已约会员头像重叠排列（对标原版） */
.booked-avatars {
  display: flex;
  align-items: center;
  margin-right: 10rpx;
}

.booked-avatar-wrap {
  width: 36rpx;
  height: 36rpx;
  margin-left: -12rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.9);
  border-radius: 50%;
  overflow: hidden;

  &.first {
    margin-left: 0;
  }
}

.booked-avatar {
  display: block;
  width: 100%;
  height: 100%;
}

.booked-avatar--empty {
  background: rgba(255, 255, 255, 0.55);
}

.booked-more {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30rpx;
  height: 30rpx;
  margin-left: 4rpx;
}

.forespeak-num {
  color: #fff;
  font-size: 28rpx;
  margin-right: 8rpx;

  .already-preengage {
    color: #fff;
    font-size: 30rpx;
    font-weight: 500;
  }
}

.min-num {
  padding: 0 12rpx;
  height: 35rpx;
  line-height: 35rpx;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 16rpx;
  color: #fff;
  font-size: 20rpx;
}

.full-icon {
  width: 35rpx;
  height: 35rpx;
  margin-right: 11rpx;
  border-radius: 50%;
  background: #d95872;
  color: #fff;
  font-size: 22rpx;
  line-height: 35rpx;
  text-align: center;
}

.right-module {
  display: flex;
  flex-direction: column;
}

.start-time {
  padding-top: 10rpx;
  color: #fff;
  font-size: 39rpx;
  line-height: 39rpx;
  text-align: center;
}

.end-time {
  margin-top: 13rpx;
  color: #fff;
  font-size: 21rpx;
  line-height: 21rpx;
  text-align: center;
}

.btn-wrap {
  display: flex;
  align-items: flex-end;
  flex: 1;
}

.bottom-logo {
  padding: 32rpx 0 48rpx;
  text-align: center;
  color: $color-text-muted;
  font-size: 22rpx;
}
</style>
