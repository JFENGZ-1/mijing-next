<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { getMemberBookingCatalog } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberContext, loadJoinableSites, loadJoinedMemberSites, selectMemberSite } from "@/composables/member-context";
import type { MemberBookingCatalogItem, MemberSiteOption } from "@/types/member";
import { addDaysIsoDate, appointmentStatusLabel, todayIsoDate } from "@/utils/format";

const loading = ref(true);
const errorMessage = ref("");
const needsSite = ref(false);
const sites = ref<MemberSiteOption[]>([]);
const siteName = ref("");
const selectedDate = ref(todayIsoDate());
const sessions = ref<MemberBookingCatalogItem[]>([]);

const courseGradients = [
  "linear-gradient(135deg, #22c788 0%, #1dac75 100%)",
  "linear-gradient(135deg, #48afff 0%, #2b7fd4 100%)",
  "linear-gradient(135deg, #ffae00 0%, #f29100 100%)",
  "linear-gradient(135deg, #ff846d 0%, #e77a76 100%)",
];

const weekDates = computed(() => {
  const labels = ["日", "一", "二", "三", "四", "五", "六"];
  return Array.from({ length: 7 }, (_, index) => {
    const iso = addDaysIsoDate(index);
    const date = new Date(`${iso}T00:00:00`);
    return {
      iso,
      weekday: labels[date.getDay()] ?? "",
      day: date.getDate(),
      isToday: iso === todayIsoDate(),
    };
  });
});

function formatSessionTime(iso: string) {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${hours}:${minutes}`;
}

function courseGradient(index: number) {
  return courseGradients[index % courseGradients.length];
}

async function loadCatalog() {
  loading.value = true;
  errorMessage.value = "";
  needsSite.value = false;
  sessions.value = [];

  try {
    const context = await ensureMemberContext();
    if (!context) {
      sites.value = await loadJoinableSites();
      needsSite.value = true;
      return;
    }

    siteName.value = context.siteName;
    const response = await getMemberBookingCatalog(context.tenantId, context.siteId, selectedDate.value);
    sessions.value = response.data.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课程列表加载失败";
  } finally {
    loading.value = false;
  }
}

function selectDate(value: string) {
  selectedDate.value = value;
  loadCatalog();
}

function goBackToday() {
  selectDate(todayIsoDate());
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
  uni.navigateTo({ url: `/pages/booking/detail?id=${sessionId}` });
}

function sessionBadgeLabel(session: MemberBookingCatalogItem) {
  if (session.memberAppointmentStatus) {
    return appointmentStatusLabel(session.memberAppointmentStatus);
  }
  if (session.bookable) {
    return session.waitlistEnabled ? "可预约/候补" : "可预约";
  }
  return "暂不可约";
}

function sessionBadgeClass(session: MemberBookingCatalogItem) {
  if (session.memberAppointmentStatus) return "badge-pill--booked";
  if (!session.bookable) return "badge-pill--muted";
  return "badge-pill--bookable";
}

onShow(async () => {
  if (await requireMemberAuth()) await loadCatalog();
});

onPullDownRefresh(async () => {
  await loadCatalog();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="booking-page">
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
      <view class="booking-header">
        <view class="site-row" @tap="openSitePicker">
          <view>
            <text class="site-name">{{ siteName || "当前场馆" }}</text>
            <text class="site-hint">点击切换场馆</text>
          </view>
          <u-icon name="arrow-right" size="16" color="#989898" />
        </view>

        <view class="schedule-head">
          <text class="schedule-title">课程表</text>
          <text v-if="selectedDate !== todayIsoDate()" class="back-today" @tap="goBackToday">返回今天</text>
        </view>

        <scroll-view class="date-scroll" scroll-x :show-scrollbar="false">
          <view class="date-strip">
            <view
              v-for="item in weekDates"
              :key="item.iso"
              class="date-item"
              :class="{ active: selectedDate === item.iso, today: item.isToday }"
              @tap="selectDate(item.iso)"
            >
              <text class="date-weekday">{{ item.isToday ? "今" : item.weekday }}</text>
              <text class="date-day">{{ item.day }}</text>
              <view v-if="selectedDate === item.iso" class="date-dot" />
            </view>
          </view>
        </scroll-view>
      </view>

      <view class="course-panel">
        <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

        <u-empty v-if="!errorMessage && sessions.length === 0" mode="list" text="~ 当前日期没有可预约课程 ~" />

        <view
          v-for="(session, index) in sessions"
          :key="session.id"
          class="course-card"
          :class="{ disabled: !session.bookable && !session.memberAppointmentStatus }"
          @tap="openSessionDetail(session.id)"
        >
          <view class="course-bg" :style="{ background: courseGradient(index) }">
            <view class="course-content">
              <view class="course-main">
                <view class="course-top">
                  <text class="course-name">{{ session.courseName || "课程" }}</text>
                  <view class="badge-pill" :class="sessionBadgeClass(session)">
                    {{ sessionBadgeLabel(session) }}
                  </view>
                </view>
                <view v-if="session.coachName" class="course-coach">教练 {{ session.coachName }}</view>
                <view class="course-capacity">
                  名额 {{ session.bookedCount ?? 0 }}/{{ session.capacity }}
                  <text v-if="session.waitlistEnabled"> · 支持候补</text>
                </view>
              </view>
              <view class="course-time">
                <text class="start-time">{{ formatSessionTime(session.startsAt) }}</text>
                <text class="end-time">{{ formatSessionTime(session.endsAt) }}</text>
              </view>
            </view>
            <view v-if="!session.bookable && !session.memberAppointmentStatus" class="course-mask" />
          </view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.booking-page {
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

.booking-header {
  padding: 24rpx 28rpx 0;
  background: $color-surface;
  border-bottom-left-radius: $radius-lg;
  border-bottom-right-radius: $radius-lg;
}

.site-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 24rpx;
}

.site-name {
  display: block;
  color: $color-text;
  font-size: 34rpx;
  font-weight: 500;
}

.site-hint {
  display: block;
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.schedule-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8rpx 0 20rpx;
}

.schedule-title {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 500;
}

.back-today {
  padding: 12rpx 23rpx;
  color: $color-primary;
  font-size: 22rpx;
  background: $color-primary-light;
  border-radius: 30rpx;
}

.date-scroll {
  width: 100%;
  white-space: nowrap;
}

.date-strip {
  display: inline-flex;
  gap: 28rpx;
  padding: 0 8rpx 24rpx;
}

.date-item {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  width: 88rpx;
}

.date-weekday {
  color: $color-text-muted;
  font-size: 26rpx;
}

.date-day {
  margin-top: 20rpx;
  color: $color-text;
  font-size: 26rpx;
}

.date-item.active .date-weekday,
.date-item.active .date-day {
  color: $color-text;
  font-weight: 500;
}

.date-dot {
  width: 40rpx;
  height: 40rpx;
  margin-top: 17rpx;
  background: #5fc48d;
  border-radius: 50%;
}

.course-panel {
  min-height: 750rpx;
  padding: 36rpx 30rpx 58rpx;
  background: $color-surface-muted;
}

.course-card {
  margin-bottom: 40rpx;
}

.course-card.disabled {
  opacity: 0.72;
}

.course-bg {
  position: relative;
  overflow: hidden;
  border-radius: $radius-md;
}

.course-content {
  display: flex;
  justify-content: space-between;
  min-height: 278rpx;
  padding: 28rpx 32rpx;
  color: #fff;
}

.course-main {
  flex: 1;
  padding-right: 20rpx;
}

.course-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10rpx;
}

.course-name {
  font-size: 39rpx;
  font-weight: 500;
  line-height: 48rpx;
}

.course-coach,
.course-capacity {
  margin-top: 10rpx;
  font-size: 24rpx;
  line-height: 32rpx;
}

.course-time {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 120rpx;
}

.start-time {
  font-size: 39rpx;
  line-height: 39rpx;
}

.end-time {
  margin-top: 13rpx;
  font-size: 21rpx;
  line-height: 21rpx;
}

.course-mask {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.35);
}
</style>
