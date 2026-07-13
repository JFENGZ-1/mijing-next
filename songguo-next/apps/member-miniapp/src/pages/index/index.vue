<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { getMemberHome, getMemberOfficialAccountFollow } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberContext, loadJoinableSites, loadJoinedMemberSites, selectMemberSite } from "@/composables/member-context";
import type { MemberHomeDashboard, MemberSiteOption } from "@/types/member";
import { appointmentStatusLabel, formatIsoDate } from "@/utils/format";

const loading = ref(true);
const errorMessage = ref("");
const needsSite = ref(false);
const sites = ref<MemberSiteOption[]>([]);
const currentSite = ref<MemberSiteOption | null>(null);
const siteName = ref("");
const dashboard = ref<MemberHomeDashboard | null>(null);
const showOfficialAccountFollow = ref(false);
const appointmentTab = ref(0);

function onAppointmentTabChange(item: { index: number }) {
  appointmentTab.value = item.index;
}

const carouselImages = computed(() => {
  if (!dashboard.value) return [] as string[];
  const items = dashboard.value.carousel.items.map((item) => item.imageUrl);
  if (items.length > 0) return items;
  return dashboard.value.carousel.defaultImageUrl ? [dashboard.value.carousel.defaultImageUrl] : [];
});

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
  if (await requireMemberAuth()) await loadDashboard();
});

onPullDownRefresh(async () => {
  await loadDashboard();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="home-page">
    <view v-if="needsSite" class="page-container">
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
          v-if="carouselImages.length"
          class="hero-swiper"
          circular
          indicator-dots
          indicator-color="rgba(255,255,255,0.4)"
          indicator-active-color="#ffffff"
          autoplay
          interval="4000"
        >
          <swiper-item v-for="(image, index) in carouselImages" :key="`${image}-${index}`">
            <image class="hero-image" :src="image" mode="aspectFill" />
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
            <text v-if="currentSite?.address" class="shop-meta">{{ currentSite.address }}</text>
            <text v-else class="shop-meta">点击查看场馆详情</text>
          </view>
          <view class="shop-switch" @tap.stop="openSitePicker">
            <u-icon name="reload" size="22" color="#181818" />
            <text class="switch-text">切换</text>
          </view>
        </view>

        <view class="info-foot">
          <text v-if="currentSite?.address" class="site-addr">{{ currentSite.address }}</text>
          <view class="foot-actions">
            <view class="foot-action" @tap.stop="callSitePhone">
              <u-icon name="phone" size="18" color="#7e7e7e" />
              <text>电话</text>
            </view>
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
            @tap="openNoticeDetail(notice.id)"
          >
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
            :list="[{ name: '即将上课' }, { name: '全部预约' }]"
            :current="appointmentTab"
            line-color="#22C788"
            :active-style="{ color: '#181818', fontWeight: 600 }"
            :inactive-style="{ color: '#989898' }"
            @change="onAppointmentTabChange"
          />
          <view class="appointment-list">
            <u-empty v-if="!dashboard?.upcomingAppointments.length" mode="list" text="~ 暂无约课记录 ~" />
            <view
              v-for="appointment in dashboard?.upcomingAppointments ?? []"
              :key="appointment.id"
              class="appointment-card"
              @tap="openMyAppointments"
            >
              <view class="appointment-status">{{ appointmentStatusLabel(appointment.status) }}</view>
              <view class="appointment-name">
                {{ appointment.courseName || `课程 #${appointment.sessionId}` }}
              </view>
              <view v-if="appointment.startsAt" class="appointment-time">
                {{ formatIsoDate(appointment.startsAt) }}
                <text v-if="appointment.endsAt"> - {{ formatIsoDate(appointment.endsAt) }}</text>
              </view>
            </view>
            <view v-if="appointmentTab === 1" class="appointment-more" @tap="openMyAppointments">
              查看全部预约
            </view>
          </view>
        </view>
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

.hero-swiper,
.hero-placeholder,
.hero-image {
  width: 100%;
  height: 458rpx;
}

.hero-placeholder {
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.main-sheet {
  position: relative;
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
  padding: 10rpx 15rpx 0;
}

.shop-name {
  display: block;
  color: $color-text;
  font-size: 42rpx;
  font-weight: 500;
  line-height: 42rpx;
}

.shop-meta {
  display: block;
  margin-top: 16rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
  line-height: 26rpx;
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
  margin: 32rpx 38rpx 0;
}

.site-addr {
  flex: 1;
  padding-right: 20rpx;
  color: $color-text;
  font-size: 22rpx;
  line-height: 24rpx;
}

.foot-actions {
  display: flex;
  gap: 46rpx;
}

.foot-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  color: $color-text-secondary;
  font-size: 18rpx;
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
  margin-bottom: 24rpx;
  padding: 25rpx 36rpx 28rpx;
  background: $color-notice-bg;
  border-radius: $radius-md;
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
  padding-top: 20rpx;
}

.appointment-card {
  margin-bottom: 24rpx;
  padding: 24rpx;
  background: $color-surface-muted;
  border-radius: $radius-md;
}

.appointment-status {
  color: $color-primary;
  font-size: 24rpx;
  font-weight: 500;
}

.appointment-name {
  margin-top: 8rpx;
  font-size: 32rpx;
  font-weight: 600;
}

.appointment-time {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.appointment-more {
  padding: 20rpx 0;
  color: $color-primary;
  font-size: 26rpx;
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
