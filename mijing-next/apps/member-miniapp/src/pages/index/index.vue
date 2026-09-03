<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShareAppMessage, onShow } from "@dcloudio/uni-app";
import {
  cancelMemberAppointment,
  getMemberAppointments,
  getMemberHome,
  getMemberOfficialAccountFollow,
  getMemberSiteClosureStatus,
  getMemberSitePublicDetail,
  promoteMemberAppointment,
} from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberContext, loadJoinableSites, loadJoinedMemberSites, selectMemberSite } from "@/composables/member-context";
import type {
  MemberAppointmentSummary,
  MemberHomeDashboard,
  MemberSiteClosureStatus,
  MemberSiteOption,
  MemberSitePublicDetail,
} from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import { formatIsoDate } from "@/utils/format";
import { navigateToOnce } from "@/utils/navigate";
import { syncMemberTabBar } from "@/utils/tab-bar";

const loading = ref(true);
const errorMessage = ref("");
const needsSite = ref(false);
const sites = ref<MemberSiteOption[]>([]);
const currentSite = ref<MemberSiteOption | null>(null);
const sitePublicDetail = ref<MemberSitePublicDetail | null>(null);
const closureStatus = ref<MemberSiteClosureStatus | null>(null);
const siteName = ref("");
const dashboard = ref<MemberHomeDashboard | null>(null);
const showOfficialAccountFollow = ref(false);

// 对标原版：我的约课（全部）/ 常规课 / 私教，按课程类型筛选自己的约课记录
const courseTab = ref(0);
const courseTabs = [
  { name: "我的约课" },
  { name: "常规课" },
  { name: "私教" },
];

function onCourseTabChange(item: { index: number }) {
  courseTab.value = item.index;
}

function isPrivateAppointment(item: MemberAppointmentSummary) {
  return (item.courseType || "").toLowerCase().includes("private");
}

const statusBarHeight = ref(0);
try {
  statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 0;
} catch {
  statusBarHeight.value = 0;
}
const homeTenantId = ref<number | null>(null);
const pastAppointments = ref<MemberAppointmentSummary[]>([]);
const cancellingId = ref<number | null>(null);
const cancelCommandKeys = new Map<number, string>();
const promotingId = ref<number | null>(null);
const promoteCommandKeys = new Map<number, string>();

const closureMessage = computed(() => {
  const closure = closureStatus.value?.closure;
  if (!closureStatus.value?.isClosed || !closure) return "";
  const period = closure.beginDate && closure.endDate
    ? `${closure.beginDate} 至 ${closure.endDate}`
    : "当前日期";
  return `${period} 场馆闭店${closure.reason ? `：${closure.reason}` : ""}`;
});

// 对标原版：场馆行营业时间，最多展示 2 条
const openTimeLines = computed(() => {
  const bh = sitePublicDetail.value?.businessHours;
  if (!bh) return [] as string[];
  if (typeof bh === "string") return [bh];
  const lines: string[] = [];
  for (const [key, value] of Object.entries(bh)) {
    if (value && typeof value === "object") continue;
    lines.push(`${key} ${String(value)}`);
    if (lines.length >= 2) break;
  }
  return lines;
});

const siteLogoUrl = computed(() => sitePublicDetail.value?.logoUrl || null);

const carouselItems = computed(() => {
  if (!dashboard.value) return [] as { imageUrl: string; linkUrl: string | null }[];
  const items = dashboard.value.carousel.items.map((item) => ({ imageUrl: item.imageUrl, linkUrl: item.linkUrl }));
  if (items.length > 0) return items;
  return dashboard.value.carousel.defaultImageUrl ? [{ imageUrl: dashboard.value.carousel.defaultImageUrl, linkUrl: null }] : [];
});

// 对标原版：全部约课记录按"X年X月"分组，最新在前
interface AppointmentSection {
  key: string;
  label: string;
  rows: MemberAppointmentSummary[];
}

function appointmentTime(item: MemberAppointmentSummary) {
  const iso = item.startsAt || item.bookedAt;
  const t = iso ? new Date(iso).getTime() : 0;
  return Number.isNaN(t) ? 0 : t;
}

const appointmentSections = computed<AppointmentSection[]>(() => {
  const merged = [...(dashboard.value?.upcomingAppointments ?? []), ...pastAppointments.value]
    .sort((a, b) => appointmentTime(b) - appointmentTime(a));

  const list = courseTab.value === 0
    ? merged
    : merged.filter((item) =>
        courseTab.value === 2 ? isPrivateAppointment(item) : !isPrivateAppointment(item),
      );

  const sections: AppointmentSection[] = [];
  for (const appointment of list) {
    const iso = appointment.startsAt || appointment.bookedAt;
    const d = iso ? new Date(iso) : null;
    const label = d && !Number.isNaN(d.getTime())
      ? `${d.getFullYear()}年${d.getMonth() + 1}月`
      : "其它";
    const last = sections[sections.length - 1];
    if (last && last.label === label) {
      last.rows.push(appointment);
    } else {
      sections.push({ key: label, label, rows: [appointment] });
    }
  }
  return sections;
});

const hasAppointments = computed(() => appointmentSections.value.length > 0);

function canCancel(item: MemberAppointmentSummary) {
  return item.status === "confirmed" || item.status === "waitlisted";
}

function confirmCancel(item: MemberAppointmentSummary) {
  const title = item.status === "waitlisted" ? "确认取消排队吗？" : "确认取消预约吗？";
  uni.showModal({
    title,
    content: "将退还已扣相应费用",
    success: async (result) => {
      if (!result.confirm) return;
      await cancelAppointment(item);
    },
  });
}

async function cancelAppointment(item: MemberAppointmentSummary) {
  if (homeTenantId.value == null) return;

  let commandKey = cancelCommandKeys.get(item.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    cancelCommandKeys.set(item.id, commandKey);
  }

  cancellingId.value = item.id;
  try {
    await cancelMemberAppointment(homeTenantId.value, item.id, commandKey);
    cancelCommandKeys.delete(item.id);
    uni.showToast({ title: "已取消", icon: "success" });
    await loadDashboard();
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "取消失败",
      icon: "none",
    });
  } finally {
    cancellingId.value = null;
  }
}

function confirmPromote(item: MemberAppointmentSummary) {
  uni.showModal({
    title: "确认候补名额",
    content: "确认后将尝试转为正式预约，并按课程规则扣减会员卡。",
    confirmText: "确认",
    success: async (result) => {
      if (!result.confirm) return;
      await promoteAppointment(item);
    },
  });
}

async function promoteAppointment(item: MemberAppointmentSummary) {
  if (homeTenantId.value == null || promotingId.value === item.id) return;

  let commandKey = promoteCommandKeys.get(item.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    promoteCommandKeys.set(item.id, commandKey);
  }

  promotingId.value = item.id;
  try {
    await promoteMemberAppointment(homeTenantId.value, item.id, commandKey);
    promoteCommandKeys.delete(item.id);
    uni.showToast({ title: "候补已确认", icon: "success" });
    await loadDashboard();
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "候补确认失败",
      icon: "none",
    });
  } finally {
    promotingId.value = null;
  }
}

const quickActions = [
  { label: "场馆信息", icon: "home", color: "#FF846D", action: "site" },
  { label: "购卡续费", icon: "coupon", color: "#48AFFF", action: "buy" },
  { label: "去约课", icon: "calendar", color: "#FFAE00", action: "book" },
  { label: "约课统计", icon: "list", color: "#00D197", action: "stats" },
];

async function loadDashboard() {
  // 仅首次（无数据）时显示全屏加载，再次进入静默刷新
  loading.value = !dashboard.value;
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
    homeTenantId.value = context.tenantId;
    const joinedSites = await loadJoinedMemberSites();
    currentSite.value = joinedSites.find((site) => site.id === context.siteId && site.tenantId === context.tenantId) ?? null;

    const response = await getMemberHome(context.tenantId, context.siteId);
    dashboard.value = response.data;
    // 对标原版：约课记录含历史，静默补充
    void getMemberAppointments(context.tenantId, "past")
      .then((past) => {
        pastAppointments.value = past.data.items;
      })
      .catch(() => {
        pastAppointments.value = [];
      });
    void getMemberSitePublicDetail(context.tenantId, context.siteId)
      .then((detail) => {
        sitePublicDetail.value = detail.data;
      })
      .catch(() => {
        sitePublicDetail.value = null;
      });
    void getMemberSiteClosureStatus(context.tenantId, context.siteId)
      .then((response) => {
        closureStatus.value = response.data;
      })
      .catch(() => {
        closureStatus.value = null;
      });
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
      // 切换场馆属于数据源变更，清空后重新加载（显示全屏加载）
      dashboard.value = null;
      sitePublicDetail.value = null;
      closureStatus.value = null;
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
  if (closureStatus.value?.isClosed) {
    uni.showToast({ title: "场馆闭店期间暂不可约课", icon: "none" });
    return;
  }
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

function openSessionDetail(appointment: MemberAppointmentSummary) {
  navigateToOnce(`/pages/booking/detail?id=${appointment.sessionId}`);
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
  syncMemberTabBar(0);
  if (await requireMemberAuth()) await loadDashboard();
});

onPullDownRefresh(async () => {
  await loadDashboard();
  uni.stopPullDownRefresh();
});

onShareAppMessage(() => ({
  title: siteName.value ? `${siteName.value} · 觅境约课` : "觅境约课",
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
        <u-alert
          v-if="closureMessage"
          type="warning"
          title="场馆闭店提示"
          :description="closureMessage"
          :custom-style="{ margin: '24rpx 28rpx 0' }"
        />

        <view class="shop-info" @tap="openSiteDetail">
          <view class="shop-photo">
            <u-avatar
              :src="siteLogoUrl || undefined"
              :text="siteLogoUrl ? undefined : (siteName || '馆').slice(0, 1)"
              size="55"
              bg-color="#22c788"
            />
          </view>
          <view class="shop-center">
            <text class="shop-name">{{ siteName || "当前场馆" }}</text>
            <view v-if="openTimeLines.length" class="shop-time">
              <text v-for="(line, i) in openTimeLines" :key="i" class="shop-time-line">{{ line }}</text>
            </view>
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
            :is-scroll="false"
            line-color="#22C788"
            :active-style="{ color: '#181818', fontWeight: 600 }"
            :inactive-style="{ color: '#989898' }"
            @change="onCourseTabChange"
          />
          <view class="appointment-list">
            <u-empty v-if="!hasAppointments" mode="list" text="~ 暂无约课记录 ~" />
            <view v-for="section in appointmentSections" :key="section.key" class="appt-section">
              <view class="month-date">{{ section.label }}</view>
              <view v-for="a in section.rows" :key="a.id" class="appointment-item">
                <appointment-row
                  :item="a"
                  variant="legacy"
                  :cancellable="canCancel(a)"
                  :cancelling="cancellingId === a.id"
                  :promotable="a.status === 'waitlisted'"
                  :promoting="promotingId === a.id"
                  @tap="openSessionDetail(a)"
                  @cancel="confirmCancel(a)"
                  @promote="confirmPromote(a)"
                />
              </view>
            </view>
            <view v-if="hasAppointments" class="load-wrap">
              <u-loadmore status="nomore" nomore-text="没有更多了" color="#BFBFBF" />
            </view>
          </view>
        </view>
      </view>

      <view class="bottom-logo">
        <text>觅境约课 · 让每一次约课都简单</text>
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

.shop-time {
  display: flex;
  flex-direction: column;
  max-width: 465rpx;
  overflow: hidden;
}

.shop-time-line {
  color: $color-text-secondary;
  font-size: 22rpx;
  line-height: 26rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
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
  margin-bottom: 43rpx;

  &:last-of-type {
    margin-bottom: 0;
  }
}

/* 对标原版：年月分组标题 */
.month-date {
  margin-bottom: 24rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.load-wrap {
  padding: 24rpx 0 8rpx;
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
