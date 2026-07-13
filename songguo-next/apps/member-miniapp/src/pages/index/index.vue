<script setup lang="ts">
import { computed, onUnmounted, ref } from "vue";
import { onHide, onPullDownRefresh, onShareAppMessage, onShow } from "@dcloudio/uni-app";
import { getMemberBookingCatalog, getMemberHome, getMemberOfficialAccountFollow } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberContext, loadJoinableSites, loadJoinedMemberSites, selectMemberSite } from "@/composables/member-context";
import type { MemberAppointmentSummary, MemberBookingCatalogItem, MemberHomeDashboard, MemberSiteOption } from "@/types/member";
import { formatIsoDate } from "@/utils/format";

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
const currentSite = ref<MemberSiteOption | null>(null);
const siteName = ref("");
const dashboard = ref<MemberHomeDashboard | null>(null);
const showOfficialAccountFollow = ref(false);
const courseTab = ref(0);

const courseTabs = [
  { name: "我的约课" },
  { name: "常规课" },
  { name: "私教" },
];

const todayIso = localDateKey(new Date());
const statusBarHeight = ref(0);
try {
  statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 0;
} catch {
  statusBarHeight.value = 0;
}
const catalogSessions = ref<MemberBookingCatalogItem[]>([]);
const catalogLoaded = ref(false);
const catalogLoading = ref(false);
const catalogError = ref("");
const homeTenantId = ref<number | null>(null);
const homeSiteId = ref<number | null>(null);

function onCourseTabChange(item: { index: number }) {
  courseTab.value = item.index;
  if (item.index > 0) void ensureCatalog();
}

const carouselItems = computed(() => {
  if (!dashboard.value) return [] as { imageUrl: string; linkUrl: string | null }[];
  const items = dashboard.value.carousel.items.map((item) => ({ imageUrl: item.imageUrl, linkUrl: item.linkUrl }));
  if (items.length > 0) return items;
  return dashboard.value.carousel.defaultImageUrl ? [{ imageUrl: dashboard.value.carousel.defaultImageUrl, linkUrl: null }] : [];
});

function timeOfDayLabel(d: Date): string {
  const h = d.getHours();
  if (h < 12) return "上午";
  if (h < 18) return "下午";
  return "晚上";
}

interface AppointmentSection {
  key: string;
  label: string;
  rows: MemberAppointmentSummary[];
}

const appointmentSections = computed<AppointmentSection[]>(() => {
  const list = dashboard.value?.upcomingAppointments ?? [];
  const sections: AppointmentSection[] = [];
  for (const appointment of list) {
    const d = appointment.startsAt ? new Date(appointment.startsAt) : null;
    const label = d && !Number.isNaN(d.getTime()) ? timeOfDayLabel(d) : "其它";
    const last = sections[sections.length - 1];
    if (last && last.label === label) {
      last.rows.push(appointment);
    } else {
      sections.push({ key: `s-${appointment.id}`, label, rows: [appointment] });
    }
  }
  return sections;
});

const nowMs = ref(Date.now());
let countdownTimer: ReturnType<typeof setInterval> | null = null;
function startCountdownTick() {
  stopCountdownTick();
  countdownTimer = setInterval(() => {
    nowMs.value = Date.now();
  }, 1000);
}
function stopCountdownTick() {
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
}

const nearestCountdown = computed<{ id: number; text: string } | null>(() => {
  const list = dashboard.value?.upcomingAppointments ?? [];
  const now = nowMs.value;
  for (const appointment of list) {
    if (!appointment.startsAt) continue;
    const t = new Date(appointment.startsAt).getTime();
    if (Number.isNaN(t)) continue;
    const diff = t - now;
    if (diff <= 0) continue;
    const totalMin = Math.floor(diff / 60000);
    let text: string;
    if (totalMin >= 60) {
      const h = Math.floor(totalMin / 60);
      const m = totalMin % 60;
      text = `距开始 ${h}小时${m}分`;
    } else if (totalMin >= 1) {
      text = `距开始 ${totalMin}分钟`;
    } else {
      text = "即将开始";
    }
    return { id: appointment.id, text };
  }
  return null;
});

const groupSessions = computed(() =>
  catalogSessions.value.filter((s) => (s.courseType || s.sessionKind) !== "private"),
);

const privateSessions = computed(() =>
  catalogSessions.value.filter((s) => (s.courseType || s.sessionKind) === "private"),
);

function formatSessionTime(iso: string | null | undefined) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

function catalogCoachInitial(session: MemberBookingCatalogItem) {
  const name = session.coachName || "";
  return name ? name.slice(0, 1) : "教";
}

function catalogTypeLabel(session: MemberBookingCatalogItem) {
  return (session.courseType || session.sessionKind) === "private" ? "私教" : "团课";
}

function isCatalogFull(session: MemberBookingCatalogItem) {
  const booked = session.bookedCount ?? 0;
  return session.capacity > 0 && booked >= session.capacity;
}

function catalogStatusLabel(session: MemberBookingCatalogItem) {
  if (session.memberAppointmentStatus === "confirmed") return "已预约";
  if (session.memberAppointmentStatus === "waitlisted") return "排队中";
  if (session.bookable) {
    if (isCatalogFull(session) && session.waitlistEnabled) return "候补";
    if (isCatalogFull(session)) return "已约满";
    return "可约";
  }
  return isCatalogFull(session) ? "已约满" : "已截止";
}

function catalogStatusColor(session: MemberBookingCatalogItem) {
  const label = catalogStatusLabel(session);
  switch (label) {
    case "可约":
      return "#22c788";
    case "候补":
    case "排队中":
      return "#ffae00";
    case "已预约":
      return "#dc3c5c";
    default:
      return "#989898";
  }
}

async function ensureCatalog() {
  if (catalogLoaded.value || catalogLoading.value) return;
  if (homeTenantId.value == null || homeSiteId.value == null) return;
  catalogLoading.value = true;
  catalogError.value = "";
  try {
    const res = await getMemberBookingCatalog(homeTenantId.value, homeSiteId.value, todayIso);
    catalogSessions.value = res.data.items;
    catalogLoaded.value = true;
  } catch (error) {
    catalogError.value = error instanceof Error ? error.message : "课程列表加载失败";
  } finally {
    catalogLoading.value = false;
  }
}

const quickActions = [
  { label: "场馆信息", icon: "home", color: "#FF846D", action: "site" },
  { label: "购卡续费", icon: "coupon", color: "#48AFFF", action: "buy" },
  { label: "去约课", icon: "calendar", color: "#FFAE00", action: "book" },
  { label: "约课统计", icon: "list", color: "#00D197", action: "stats" },
];

async function loadDashboard() {
  loading.value = true;
  errorMessage.value = "";
  needsSite.value = false;
  dashboard.value = null;

  try {
    const context = await ensureMemberContext();
    if (!context) {
      sites.value = await loadJoinableSites();
      needsSite.value = true;
      return;
    }

    siteName.value = context.siteName;
    homeTenantId.value = context.tenantId;
    homeSiteId.value = context.siteId;
    catalogLoaded.value = false;
    catalogSessions.value = [];
    const joinedSites = await loadJoinedMemberSites();
    currentSite.value = joinedSites.find((site) => site.id === context.siteId && site.tenantId === context.tenantId) ?? null;

    const response = await getMemberHome(context.tenantId, context.siteId);
    dashboard.value = response.data;
    showOfficialAccountFollow.value = false;
    try {
      await getMemberOfficialAccountFollow(context.tenantId, context.siteId);
      showOfficialAccountFollow.value = true;
    } catch {
      showOfficialAccountFollow.value = false;
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "首页加载失败";
  } finally {
    loading.value = false;
  }
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
      await loadDashboard();
    },
  });
}

function openOnboarding() {
  uni.navigateTo({ url: "/pages/onboarding/profile" });
}

function openSiteDetail() {
  uni.navigateTo({ url: "/pages/sites/detail" });
}

function openBuyCard() {
  uni.navigateTo({ url: "/pages/cards/catalog" });
}

function openBooking() {
  uni.switchTab({ url: "/pages/booking/index" });
}

function openStats() {
  uni.navigateTo({ url: "/pages/mine/stats" });
}

function handleQuickAction(action: string) {
  if (action === "site") openSiteDetail();
  else if (action === "buy") openBuyCard();
  else if (action === "book") openBooking();
  else if (action === "stats") openStats();
}

function openMyAppointments() {
  uni.navigateTo({ url: "/pages/booking/my-appointments" });
}

function openSessionDetail(appointment: MemberAppointmentSummary) {
  uni.navigateTo({ url: `/pages/booking/detail?id=${appointment.sessionId}` });
}

function openSessionDetailById(sessionId: number) {
  uni.navigateTo({ url: `/pages/booking/detail?id=${sessionId}` });
}

function openCarouselLink(linkUrl: string | null) {
  if (!linkUrl) return;
  if (linkUrl.startsWith("/pages/")) {
    uni.navigateTo({ url: linkUrl });
  }
}

function openNoticeDetail(noticeId: number) {
  uni.navigateTo({ url: `/pages/notices/detail?id=${noticeId}` });
}

function openNoticeList() {
  uni.navigateTo({ url: "/pages/notices/index" });
}

function openOfficialAccountFollow() {
  uni.navigateTo({ url: "/pages/follow/official-account" });
}

function callSitePhone() {
  const phone = currentSite.value?.phone;
  if (!phone) {
    uni.showToast({ title: "暂无联系电话", icon: "none" });
    return;
  }
  uni.makePhoneCall({ phoneNumber: phone });
}

onShow(async () => {
  startCountdownTick();
  if (await requireMemberAuth()) await loadDashboard();
});

onHide(() => {
  stopCountdownTick();
});

onUnmounted(() => {
  stopCountdownTick();
});

onPullDownRefresh(async () => {
  await loadDashboard();
  uni.stopPullDownRefresh();
});

onShareAppMessage(() => ({
  title: siteName.value ? `${siteName.value} · 松果约课` : "松果约课",
  path: "/pages/index/index",
}));
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="home-page">
    <view v-if="needsSite" class="page-container" :style="{ paddingTop: statusBarHeight ? `${statusBarHeight}px` : '0' }">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="site-prompt">
        <view class="section-title">选择场馆</view>
        <view class="prompt-text">加入场馆后即可查看轮播、通知和预约信息</view>
        <u-button type="primary" @click="openSitePicker">选择场馆</u-button>
        <u-button plain @click="openOnboarding">去加入场馆</u-button>
      </view>
    </view>

    <template v-else>
      <view class="hero-wrap">
        <swiper
          v-if="carouselItems.length"
          class="hero-swiper"
          circular
          autoplay
          interval="4000"
        >
          <swiper-item v-for="(item, index) in carouselItems" :key="`${item.imageUrl}-${index}`">
            <image class="hero-image" :src="item.imageUrl" mode="aspectFill" @tap="openCarouselLink(item.linkUrl)" />
          </swiper-item>
        </swiper>
        <view v-else class="hero-placeholder" />
      </view>

      <view class="main-sheet card-sheet">
        <u-alert
          v-if="errorMessage"
          type="error"
          :description="errorMessage"
          :custom-style="{ margin: '24rpx 28rpx 0' }"
        />
        <u-alert
          v-if="dashboard?.linkRequestWarning"
          type="warning"
          :description="dashboard.linkRequestWarning.message"
          :custom-style="{ margin: '24rpx 28rpx 0' }"
        />

        <view class="shop-info" @tap="openSiteDetail">
          <view class="shop-photo">
            <u-avatar :text="(siteName || '馆').slice(0, 1)" size="55" bg-color="#22c788" />
          </view>
          <view class="shop-center">
            <text class="shop-name">{{ siteName || "当前场馆" }}</text>
          </view>
          <view class="shop-switch" @tap.stop="openSitePicker">
            <u-icon name="reload" size="22" color="#181818" />
            <text class="switch-text">切换</text>
          </view>
        </view>

        <view class="info-foot">
          <view class="site-addr">{{ currentSite?.address || "点击查看场馆详情" }}</view>
          <view class="foot-actions">
            <view class="foot-action" @tap.stop="callSitePhone">
              <u-icon name="phone" size="18" color="#7e7e7e" />
              <text>电话</text>
            </view>
            <button class="foot-action foot-share" open-type="share" plain>
              <u-icon name="share" size="18" color="#7e7e7e" />
              <text>分享</text>
            </button>
          </view>
        </view>

        <view class="section-divider" />

        <view class="quick-actions">
          <view
            v-for="item in quickActions"
            :key="item.action"
            class="quick-action"
            @tap="handleQuickAction(item.action)"
          >
            <view class="quick-action-icon" :style="{ background: item.color }">
              <u-icon :name="item.icon" size="26" color="#ffffff" />
            </view>
            <text class="quick-action-label">{{ item.label }}</text>
          </view>
        </view>

        <view v-if="dashboard?.notices.length" class="notice-section">
          <view class="section-header">
            <view class="section-title section-title--inline">场馆通知</view>
            <text class="more-link" @tap="openNoticeList">更多公告</text>
          </view>
          <view
            v-for="notice in dashboard.notices"
            :key="notice.id"
            class="notice-card"
            :class="{ 'notice-card--image': notice.coverImageUrl }"
            :style="notice.coverImageUrl ? `background-image:url('${notice.coverImageUrl}');background-size:100% 100%;` : ''"
            @tap="openNoticeDetail(notice.id)"
          >
            <view v-if="notice.coverImageUrl" class="notice-overlay" />
            <view class="notice-top">
              <text class="notice-tag">通知</text>
              <text v-if="notice.publishedAt" class="notice-time">发布于：{{ formatIsoDate(notice.publishedAt) }}</text>
            </view>
            <view class="notice-title">{{ notice.title }}</view>
            <view class="notice-excerpt">{{ notice.excerpt }}</view>
          </view>
        </view>

        <view class="appointment-section">
          <u-tabs
            :list="courseTabs"
            :current="courseTab"
            line-color="#22C788"
            :active-style="{ color: '#181818', fontWeight: 600 }"
            :inactive-style="{ color: '#989898' }"
            @change="onCourseTabChange"
          />
          <view class="appointment-list">
            <template v-if="courseTab === 0">
              <u-empty v-if="!appointmentSections.length" mode="list" text="~ 暂无约课记录 ~" />
              <view v-for="section in appointmentSections" :key="section.key" class="appt-section">
                <view class="tod-header">{{ section.label }}</view>
                <view v-for="a in section.rows" :key="a.id" class="appointment-item">
                  <view
                    v-if="nearestCountdown && nearestCountdown.id === a.id"
                    class="countdown-badge"
                  >
                    <u-icon name="clock" size="22" color="#22c788" />
                    <text class="countdown-text">{{ nearestCountdown.text }}</text>
                  </view>
                  <appointment-row :item="a" variant="legacy" @tap="openSessionDetail(a)" />
                </view>
              </view>
              <view v-if="appointmentSections.length" class="appointment-more" @tap="openMyAppointments">
                查看全部预约 ›
              </view>
            </template>

            <template v-else>
              <u-empty
                v-if="!catalogLoading && !catalogSessions.length"
                mode="list"
                text="~ 今日无排课 ~"
              />
              <view
                v-for="session in (courseTab === 1 ? groupSessions : privateSessions)"
                :key="session.id"
                class="catalog-row"
                @tap="openSessionDetailById(session.id)"
              >
                <view class="catalog-avatar">{{ catalogCoachInitial(session) }}</view>
                <view class="catalog-main">
                  <view class="catalog-name-row">
                    <text class="catalog-name">{{ session.courseName || "课程" }}</text>
                    <text class="catalog-type">{{ catalogTypeLabel(session) }}</text>
                  </view>
                  <view class="catalog-sub">
                    <text v-if="session.coachName" class="catalog-coach">{{ session.coachName }}</text>
                    <text class="catalog-time">
                      {{ formatSessionTime(session.startsAt) }}-{{ formatSessionTime(session.endsAt) }}
                    </text>
                  </view>
                  <view class="catalog-cap">
                    <text>{{ session.bookedCount ?? 0 }}/{{ session.capacity }}</text>
                  </view>
                </view>
                <view class="catalog-status" :style="{ color: catalogStatusColor(session) }">
                  {{ catalogStatusLabel(session) }}
                </view>
              </view>
            </template>
          </view>
        </view>
      </view>

      <view class="bottom-logo">
        <text>松果约课 · 让每一次约课都简单</text>
      </view>

      <view v-if="showOfficialAccountFollow" class="follow-banner-wrap">
        <view class="follow-banner" @tap="openOfficialAccountFollow">
          <view class="follow-copy">
            <view class="follow-title">课程取消、上课提醒等通知收不到？</view>
            <view class="follow-subtitle">请关注公众号，即可收到消息通知</view>
          </view>
          <view class="follow-btn">去关注</view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.home-page {
  min-height: 100vh;
  padding-bottom: 140rpx;
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

.hero-wrap {
  position: relative;
  height: 458rpx;
  overflow: hidden;
}

.hero-swiper {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 458rpx;
  transform: scale(1.01);
  z-index: 0;
}

.hero-placeholder,
.hero-image {
  width: 100%;
  height: 458rpx;
}

.hero-placeholder {
  position: fixed;
  top: 0;
  left: 0;
  transform: scale(1.01);
  z-index: 0;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.main-sheet {
  position: relative;
  z-index: 1;
  margin-top: -30rpx;
  min-height: 600rpx;
  padding-bottom: 40rpx;
}

.shop-info {
  display: flex;
  align-items: flex-start;
  padding: 44rpx 28rpx 0;
}

.shop-photo {
  flex-shrink: 0;
}

.shop-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 10rpx 15rpx 0;
}

.shop-name {
  display: block;
  color: $color-text;
  font-size: 42rpx;
  font-weight: 500;
  line-height: 42rpx;
  margin: 6rpx 0 16rpx;
}

.shop-switch {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10rpx 12rpx;
}

.switch-text {
  margin-top: 6rpx;
  color: $color-text;
  font-size: 18rpx;
}

.info-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 85rpx;
  margin: 0 40rpx 0 38rpx;
}

.site-addr {
  flex: 1;
  padding-right: 20rpx;
  color: $color-text;
  font-size: 22rpx;
  line-height: 24rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.foot-actions {
  display: flex;
  align-items: center;
  white-space: nowrap;
}

.foot-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: $color-text-secondary;
  font-size: 18rpx;
  line-height: 22rpx;
}

.foot-share {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: auto;
  min-width: 0;
  margin-left: 46rpx;
  padding: 0;
  background: transparent;
  border: 0;
  color: $color-text-secondary;
  font-size: 18rpx;
  line-height: 22rpx;
}

.foot-share::after {
  border: none;
}

.section-divider {
  height: 20rpx;
  margin-top: 32rpx;
  background: $color-page;
}

.quick-actions {
  display: flex;
  justify-content: space-between;
  padding: 52rpx 58rpx 0;
}

.quick-action {
  width: 100rpx;
  text-align: center;
}

.quick-action-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 83rpx;
  height: 83rpx;
  margin: 0 auto;
  border-radius: 32rpx;
}

.quick-action-label {
  display: block;
  margin-top: 15rpx;
  color: $color-text;
  font-size: 25rpx;
}

.notice-section,
.appointment-section {
  padding: 0 28rpx;
}

.notice-section {
  margin-top: 48rpx;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.more-link {
  color: $color-primary;
  font-size: 24rpx;
}

.notice-card {
  position: relative;
  margin-bottom: 24rpx;
  padding: 25rpx 36rpx 28rpx;
  background: $color-notice-bg;
  border-radius: $radius-md;
  overflow: hidden;
}

.notice-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(134, 91, 0, 0.55) 0%, rgba(134, 91, 0, 0.7) 100%);
  z-index: 0;
}

.notice-card > view:not(.notice-overlay),
.notice-card > text {
  position: relative;
  z-index: 1;
}

.notice-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 18rpx;
}

.notice-tag {
  padding: 4rpx 12rpx;
  color: $color-notice;
  font-size: 22rpx;
  background: rgba(134, 91, 0, 0.12);
  border-radius: 8rpx;
}

.notice-time {
  color: $color-notice;
  font-size: 22rpx;
}

.notice-title {
  color: $color-notice;
  font-size: 35rpx;
  line-height: 35rpx;
}

.notice-excerpt {
  display: -webkit-box;
  margin-top: 15rpx;
  overflow: hidden;
  color: $color-notice;
  font-size: 25rpx;
  line-height: 36rpx;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.appointment-section {
  margin-top: 35rpx;
}

.appointment-list {
  min-height: 240rpx;
  margin-top: 40rpx;
}

.appointment-item {
  padding: 0 4rpx;
}

.appt-section {
  padding: 0 4rpx;
}

.tod-header {
  margin: 24rpx 0 4rpx;
  color: $color-text;
  font-size: 28rpx;
  font-weight: 500;
}

.countdown-badge {
  display: inline-flex;
  align-items: center;
  gap: 6rpx;
  margin: 10rpx 0 4rpx;
  padding: 4rpx 16rpx;
  background: rgba(34, 199, 136, 0.12);
  border-radius: 20rpx;
  color: $color-primary;
  line-height: 32rpx;
}

.countdown-text {
  color: $color-primary;
  font-size: 22rpx;
}

.appointment-more {
  padding: 20rpx 0 4rpx;
  color: $color-primary;
  font-size: 26rpx;
  text-align: center;
}

.catalog-row {
  display: flex;
  align-items: center;
  padding: 24rpx 0;
}

.catalog-avatar {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 88rpx;
  height: 88rpx;
  border-radius: 12rpx;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
  color: #fff;
  font-size: 38rpx;
  font-weight: 500;
}

.catalog-main {
  flex: 1;
  margin-left: 16rpx;
}

.catalog-name-row {
  display: flex;
  align-items: center;
}

.catalog-name {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 500;
}

.catalog-type {
  margin-left: 12rpx;
  padding: 0 12rpx;
  height: 30rpx;
  line-height: 30rpx;
  background: $color-primary-light;
  border-radius: 8rpx;
  color: $color-primary;
  font-size: 20rpx;
}

.catalog-sub {
  display: flex;
  align-items: center;
  margin-top: 10rpx;
  color: $color-text-muted;
  font-size: 22rpx;
}

.catalog-coach {
  margin-right: 16rpx;
}

.catalog-time {
  color: $color-text-muted;
}

.catalog-cap {
  margin-top: 8rpx;
  color: $color-text-muted;
  font-size: 22rpx;
}

.catalog-status {
  flex-shrink: 0;
  margin-left: 16rpx;
  font-size: 26rpx;
  font-weight: 500;
}

.bottom-logo {
  padding: 40rpx 0 24rpx;
  color: $color-text-muted;
  font-size: 22rpx;
  text-align: center;
}

.follow-banner-wrap {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 9;
  padding-bottom: constant(safe-area-inset-bottom);
  padding-bottom: env(safe-area-inset-bottom);
}

.follow-copy {
  flex: 1;
}

.follow-banner {
  display: flex;
  align-items: center;
  gap: 12rpx;
  margin: 0 17rpx;
  padding: 17rpx;
  background: $color-accent-coral;
  border-radius: 21rpx 21rpx 0 0;
  color: #fff;
}

.follow-title {
  font-size: 32rpx;
  font-weight: 500;
  line-height: 40rpx;
}

.follow-subtitle {
  font-size: 22rpx;
  line-height: 30rpx;
}

.follow-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 138rpx;
  height: 54rpx;
  color: $color-accent-pink;
  font-size: 26rpx;
  background: #fff;
  border-radius: 30rpx;
}
</style>
