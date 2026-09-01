<script setup lang="ts">
import { computed, ref } from "vue";
import { onPageScroll, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchSettingsHub } from "@/api/settings";
import { fetchStaffProfile } from "@/api/profile";
import { fetchSiteProfile } from "@/api/site-profile";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import type { SettingsHub, SettingsHubItem, SettingsHubSection } from "@/types/settings";
import type { StaffProfile } from "@/types/profile";
import type { SiteProfile } from "@/types/site-profile";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const hub = ref<SettingsHub | null>(null);
const profile = ref<StaffProfile | null>(null);
const siteProfile = ref<SiteProfile | null>(null);
const fixedBarOpacity = ref(0);

const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;
const customBarHeight = (() => {
  try {
    const menu = uni.getMenuButtonBoundingClientRect();
    return menu.height + (menu.top - statusBarHeight) * 2;
  } catch {
    return 44;
  }
})();
const navTotalPx = statusBarHeight + customBarHeight;
const fixedBarPx = navTotalPx - 8;
const headerHeightPx = navTotalPx + 76 + 18;

const canReadSettings = computed(() => session.can("tenant.settings.read"));
const canReadSite = computed(() => session.can("site.profile.read"));
const currentSiteName = computed(
  () => siteProfile.value?.name
    || session.sites.find((site) => site.id === session.currentSiteId)?.name
    || "当前场馆",
);

function truncateText(text: string, max: number) {
  const value = String(text || "");
  return value.length > max ? `${value.slice(0, max)}...` : value;
}

async function load() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const tasks: Promise<void>[] = [
      fetchStaffProfile().then((response) => {
        profile.value = response;
      }).catch(() => undefined),
    ];
    if (session.currentSiteId && canReadSettings.value) {
      tasks.push(
        fetchSettingsHub(session.currentSiteId).then((response) => {
          hub.value = response;
        }),
      );
    }
    if (session.currentSiteId && canReadSite.value) {
      tasks.push(
        fetchSiteProfile(session.currentSiteId).then((response) => {
          siteProfile.value = response;
        }).catch(() => undefined),
      );
    }
    await Promise.all(tasks);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "场馆设置加载失败";
  } finally {
    loading.value = false;
  }
}

function openItem(item: SettingsHubItem) {
  if (!item.enabled) {
    uni.showToast({ title: "暂无权限", icon: "none" });
    return;
  }
  if (!item.implemented || !item.route) {
    uni.showToast({ title: "暂未开放", icon: "none" });
    return;
  }
  uni.navigateTo({ url: item.route });
}

function openSiteProfile() {
  if (!canReadSite.value) return;
  uni.navigateTo({ url: "/pages/settings/site/index" });
}

function openPersonal() {
  uni.navigateTo({ url: "/pages/settings/personal/index" });
}

function openCustomerService() {
  uni.navigateTo({ url: "/pages/settings/support/customer-service/index" });
}

function openVideoHelp() {
  uni.navigateTo({ url: "/pages/settings/support/video-help/index" });
}

function nameInitial(name?: string | null) {
  return (name || "员").slice(0, 1);
}

function iconFor(item: SettingsHubItem, sectionKey?: string) {
  const map: Record<string, string> = {
    "site-profile": "home-fill",
    "room-catalog": "grid-fill",
    "card-products": "integral-fill",
    "staff-directory": "account-fill",
    "course-catalog": "bookmark-fill",
    "card-course-links": "list-dot",
    "schedule-sessions": "calendar-fill",
    "booking-policy": "setting-fill",
    "notification-channels": "bell-fill",
    "card-reminder-config": "bell-fill",
    "crm-field-config": "file-text-fill",
    "membership-agreement": "file-text-fill",
    "payment-marketing": "rmb-circle-fill",
    "venue-qr": "grid-fill",
    "member-booking-help": "question-circle-fill",
    "member-home-carousel": "photo-fill",
    "member-warm-hint": "bell-fill",
    "member-miniapp-layout": "eye-fill",
    "site-closures": "calendar-fill",
    "coach-vacation": "account-fill",
    "announcements": "volume-up-fill",
    "data-export": "download",
    "chain-instructions": "question-circle-fill",
    "chain-stores": "home-fill",
    "chain-shared-cards": "integral-fill",
    "chain-store-courses": "list-dot",
    "chain-card-stats": "grid-fill",
    "chain-course-stats": "grid-fill",
    "chain-staff": "account-fill",
    "customer-service": "server-fill",
    "platform-subscription-orders": "file-text-fill",
    "video-help": "play-circle-fill",
  };
  const fallbackBySection: Record<string, string> = {
    onboarding: "star-fill",
    basics: "setting-fill",
    defaults: "setting-fill",
    "member-experience": "photo-fill",
    operations: "briefcase-fill",
    chain: "share-fill",
    support: "server-fill",
  };
  return map[item.key] || fallbackBySection[sectionKey || ""] || "setting-fill";
}

function colorFor(item: SettingsHubItem, sectionKey?: string) {
  const map: Record<string, string> = {
    onboarding: "#696b99",
    basics: "#42c598",
    defaults: "#f2a33c",
    "member-experience": "#f19469",
    operations: "#5fa3ea",
    chain: "#5fa3ea",
    support: "#22c788",
  };
  return map[sectionKey || ""] || "#42c598";
}

function sectionTitle(section: SettingsHubSection) {
  return section.label;
}

function shouldShowSection(section: SettingsHubSection) {
  return section.visible && section.items.length > 0;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});

onPageScroll(({ scrollTop }) => {
  if (scrollTop < 70) {
    fixedBarOpacity.value = 0;
  } else if (scrollTop <= 90) {
    fixedBarOpacity.value = (scrollTop - 70) / 20;
  } else {
    fixedBarOpacity.value = 1;
  }
});
</script>

<template>
  <u-loading-page :loading="loading" />

  <view v-if="!loading" class="shop-page">
    <!-- 顶部悬浮小栏（滚动后出现） -->
    <view class="fixed-bar" :style="{ height: `${fixedBarPx}px` }">
      <view class="fixed-bar-logo" :style="{ opacity: fixedBarOpacity }">
        <view class="shop-logo-box">
          <image v-if="siteProfile?.logoUrl" class="shop-logo" :src="siteProfile.logoUrl" mode="aspectFill" />
          <view v-else class="shop-logo shop-logo-fallback">{{ nameInitial(currentSiteName) }}</view>
        </view>
        <view class="shop-name">{{ truncateText(currentSiteName, 11) }}</view>
      </view>
    </view>

    <!-- 头部 -->
    <view class="header" :style="{ height: `${headerHeightPx}px` }">
      <view class="fixation" :style="{ height: `${headerHeightPx}px` }">
        <view class="status-bar" :style="{ height: `${navTotalPx}px` }" />
        <view class="venue-info-wrap">
          <view class="venue-info">
            <view class="venue-icon" @tap="openSiteProfile">
              <image v-if="siteProfile?.logoUrl" class="venue-icon-img" :src="siteProfile.logoUrl" mode="aspectFill" />
              <view v-else class="venue-icon-img venue-icon-fallback">{{ nameInitial(currentSiteName) }}</view>
            </view>
            <view class="venue-text" @tap="openSiteProfile">
              <view class="name">{{ truncateText(currentSiteName, 9) }}</view>
              <view class="address">
                <view class="address-text">{{ truncateText(siteProfile?.address || "填写场馆地址", 20) }}</view>
                <u-icon name="arrow-right" size="17rpx" color="#181818" />
              </view>
            </view>
            <view class="mumber-photo-wrap">
              <view class="mumber-photo" @tap="openPersonal">
                <image v-if="profile?.avatarUrl" class="mumber-photo-img" :src="profile.avatarUrl" mode="aspectFill" />
                <view v-else class="mumber-photo-img mumber-photo-fallback">{{ nameInitial(profile?.displayName) }}</view>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="main-content">
      <view class="content-inner">
        <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

        <template v-if="canReadSettings && hub">
          <template v-for="(section, sectionIndex) in hub.sections" :key="section.key">
            <view
              v-if="shouldShowSection(section)"
              class="module-group"
              :class="[section.key, { 'module-group--first': sectionIndex === 0, 'module-group--onboarding': section.key === 'onboarding' }]"
            >
              <view class="title">
                <text>{{ sectionTitle(section) }}</text>
              </view>
              <view class="group">
                <view
                  v-for="item in section.items"
                  :key="item.key"
                  class="group-item"
                  @tap="openItem(item)"
                >
                  <view class="logo-wrap" :style="{ background: colorFor(item, section.key) }">
                    <view v-if="item.setupIncomplete" class="card-dot-tips" />
                    <u-icon :name="iconFor(item, section.key)" :size="section.key === 'basics' ? 32 : 30" color="#ffffff" />
                  </view>
                  <view class="text-wrap">{{ item.label }}</view>
                </view>
              </view>
            </view>
          </template>
        </template>

        <view v-else class="empty-wrap">
          <u-empty mode="permission" text="暂无查看权限" />
        </view>

        <view v-if="hub?.featureFlags.shopServiceCenter" class="shop-service" @tap="openCustomerService">
          <view class="service-icon service-icon-primary">
            <u-icon name="server-fill" size="34" color="#ffffff" />
          </view>
          <view class="text">
            <view class="first-text">觅境服务中心</view>
            <view class="last-text">客服・续费・协议・我们</view>
          </view>
          <u-icon name="arrow-right" size="25rpx" color="#385161" />
        </view>

        <view v-if="hub?.featureFlags.shopServiceCenter" class="shop-service" @tap="openVideoHelp">
          <view class="service-icon service-icon-secondary">
            <u-icon name="play-circle-fill" size="34" color="#ffffff" />
          </view>
          <view class="text">
            <view class="first-text">视频讲解</view>
            <view class="last-text">如何补约・代约・发卡・复制课程等</view>
          </view>
          <u-icon name="arrow-right" size="25rpx" color="#385161" />
        </view>

        <FfBottomLogo />
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.shop-page {
  min-height: 100vh;
  background: #fbd128;
}

.fixed-bar {
  align-items: flex-end;
  background: #fbd128;
  display: flex;
  left: 0;
  padding-bottom: 12rpx;
  position: fixed;
  top: 0;
  width: 100%;
  z-index: 99;
}

.fixed-bar-logo {
  align-items: center;
  display: flex;
  height: 52rpx;
  padding: 0 28rpx;
}

.shop-logo-box {
  border-radius: 50%;
  flex-shrink: 0;
  height: 52rpx;
  overflow: hidden;
  width: 52rpx;
}

.shop-logo {
  display: block;
  height: 100%;
  width: 100%;
}

.shop-logo-fallback {
  align-items: center;
  background: #696b99;
  color: #fff;
  display: flex;
  font-size: 26rpx;
  justify-content: center;
}

.shop-name {
  flex: 1;
  font-size: 32rpx;
  font-weight: 500;
  line-height: 32rpx;
  margin-left: 12rpx;
  color: #181818;
}

.header .fixation {
  background: #fbd128;
  left: 0;
  position: fixed;
  top: 0;
  width: 100%;
}

.venue-info-wrap {
  background: #fbd128;
  width: 100%;
}

.venue-info {
  display: flex;
  height: 110rpx;
  padding: 0 26rpx;
}

.venue-icon {
  border-radius: 50%;
  height: 106rpx;
  overflow: hidden;
  width: 106rpx;
}

.venue-icon-img {
  display: block;
  height: 100%;
  width: 100%;
}

.venue-icon-fallback {
  align-items: center;
  background: #696b99;
  color: #fff;
  display: flex;
  font-size: 38rpx;
  font-weight: 600;
  justify-content: center;
}

.venue-text {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: center;
  margin-bottom: 16rpx;
  margin-left: 15rpx;
  margin-top: 12rpx;
  min-width: 0;
}

.name {
  font-size: 42rpx;
  font-weight: 500;
  line-height: 42rpx;
  margin-bottom: 14rpx;
  color: #181818;
}

.address {
  align-items: center;
  display: flex;
  font-size: 22rpx;
  line-height: 22rpx;
  color: #181818;
}

.address-text {
  margin-right: 8rpx;
}

.mumber-photo-wrap {
  align-items: flex-end;
  display: flex;
  justify-content: flex-end;
  min-width: 80rpx;
}

.mumber-photo {
  border: 1px solid #fff;
  border-radius: 50%;
  height: 54rpx;
  margin-bottom: 12rpx;
  overflow: hidden;
  width: 54rpx;
  box-sizing: border-box;
}

.mumber-photo-img {
  display: block;
  height: 100%;
  width: 100%;
  border-radius: 50%;
}

.mumber-photo-fallback {
  align-items: center;
  background: #22c788;
  color: #fff;
  display: flex;
  font-size: 22rpx;
  justify-content: center;
}

.main-content {
  background: #f5f5f5;
  background-image: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAD/CAYAAADMmJcqAAAACXBIWXMAAAsTAAALEwEAmpwYAAAF6GlUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4gPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iQWRvYmUgWE1QIENvcmUgNi4wLWMwMDIgNzkuMTY0NDYwLCAyMDIwLzA1LzEyLTE2OjA0OjE3ICAgICAgICAiPiA8cmRmOlJERiB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiPiA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIiB4bWxuczpzdFJlZj0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL3NUeXBlL1Jlc291cmNlUmVmIyIgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIgeG1sbnM6ZGM9Imh0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvIiB4bWxuczpwaG90b3Nob3A9Imh0dHA6Ly9ucy5hZG9iZS5jb20vcGhvdG9zaG9wLzEuMC8iIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIDIxLjIgKFdpbmRvd3MpIiB4bXA6Q3JlYXRlRGF0ZT0iMjAyMy0wNi0xM1QxNzozNDoyMiswODowMCIgeG1wOk1vZGlmeURhdGU9IjIwMjMtMDYtMTNUMTk6NDY6NDkrMDg6MDAiIHhtcDpNZXRhZGF0YURhdGU9IjIwMjMtMDYtMTNUMTk6NDY6NDkrMDg6MDAiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6NjRiYzJlMDYtYmM2Mi0wYTQxLWJkN2UtOGQ4NDQxMjc1NjMxIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOkZFMkUyNDNGMDM0QjExRUU5Q0FDRkRGRjUxRDExNjQ4IiB4bXBNTTpPcmlnaW5hbERvY3VtZW50SUQ9InhtcC5kaWQ6RkUyRTI0M0YwMzRCMTFFRTlDQUNGREZGNTFEMTE2NDgiIGRjOmZvcm1hdD0iaW1hZ2UvcG5nIiBwaG90b3Nob3A6Q29sb3JNb2RlPSIzIiBwaG90b3Nob3A6SUNDUHJvZmlsZT0ic1JHQiBJRUM2MTk2Ni0yLjEiPiA8eG1wTU06RGVyaXZlZEZyb20gc3RSZWY6aW5zdGFuY2VJRD0ieG1wLmlpZDpGRTJFMjQzQzAzNEIxMUVFOUNBQ0ZERkY1MUQxMTY0OCIgc3RSZWY6ZG9jdW1lbnRJRD0ieG1wLmRpZDpGRTJFMjQzRDAzNEIxMUVFOUNBQ0ZERkY1MUQxMTY0OCIvPiA8eG1wTU06SGlzdG9yeT4gPHJkZjpTZXE+IDxyZGY6bGkgc3RFdnQ6YWN0aW9uPSJzYXZlZCIgc3RFdnQ6aW5zdGFuY2VJRD0ieG1wLmlpZDo2NGJjMmUwNi1iYzYyLTBhNDEtYmQ3ZS04ZDg0NDEyNzU2MzEiIHN0RXZ0OndoZW49IjIwMjMtMDYtMTNUMTk6NDY6NDkrMDg6MDAiIHN0RXZ0OnNvZnR3YXJlQWdlbnQ9IkFkb2JlIFBob3Rvc2hvcCAyMS4yIChXaW5kb3dzKSIgc3RFdnQ6Y2hhbmdlZD0iLyIvPiA8L3JkZjpTZXE+IDwveG1wTU06SGlzdG9yeT4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz4q0pk7AAADXklEQVRo3q1ZS67cMAyTAt//sD2A2E3nwdGQlJPXAWYRxHEsiaQ+SQB/wvwSANyCFRFqASIi135BFuOKiPp3o9rTP68IciM/14s9tV+rQ6JbkWKXuLYb+83sC/rWnwew/pmX7ebnOi9nwWeH2xN98UXefdvJBetnB7hFztVfnuwLsseiexL7IdnNr3DTsywFFAZadMAyR6FZ9RPNfrjcIccAW4o42eB/QxRYXNghY0NZTOHGRN6ZFxOzchHv3aJ6RN7aqcZAm8RB6DuEkqKL4AGdvBDsjg5ays+lnmTUK6I28gxW5fbgnctgEEskcdDV3r1itqJLEE7IK2WwyDnQ8VAO1VTI2Bl2Z5Xy5HNm4U3mTcduKCnOk2B9SVDnJrrChMPExO7bDmALO/WK6UMJC6BcfdPsZd4PRxx5SEyxOHLUjaNL5KqbJ+upgIQTMYrJelsuUkT1HB6LRHJX/1TRTJUORo2yVlBMOJWj7M4TTMZpNPNRcg9lCYsFnB9CBascokqVBvsrOvS/ihw4CaqnKnfEi+MqKJSA3MqkOgFMtMLT1nJSq2lyc4k1J0ziFbttRWr7C4isF5NWQ5l5E9IS2R+9EStX28OlgxKFfzo//Cx+VMvF5MlU9uAchMJQVoz7Qzt1VIPGIF0daHc5MSRyYilCSN1X7EJOZXUhxCU+i48HK4O8dJWVYRZOeAaqmU2mxdl4wbkJVYuEg9zviKMDkVAXhdUKRYp5q7FauPCiWs5XCwBV7NKldBKhgkxzWnBfjRfYHpkrs3Ip0A8xfzcTw/6riIBOtdGM3WhWPGSdUay87Zldojd0B2CyIlo1uxHFU29OxG5wfbLeYJG/lNKCAI844uKv9FUlkgBY5YENUmAZAatQ4XczHU/fX5SJtSB+NvGgswFqYULhYIgZfE06oGYQTMbwSMVlosVoyPwVGTCPQMvpgv4Ac5c1nfoDKvDBSeNZKsz7LchOvusWx51WQy2Mr8rSNGkEbJ7F4nnlfjVnSTZv361pmeHmbNpfKPFO3KGdiOB7kXk+6g5w+01C9vJyAddgnyeNUH571/uoM6LEI8QWglpvR9hFoEPLiUp8EPv/LfeRhZpYys8J8jXLEwTUoDCbIYUzNR5DLCbTlnLUM5MDw8BX6BJCTPoRTur+NlsNloVPcbAAAAABJRU5ErkJggg==");
  background-position: top;
  background-repeat: repeat-x;
  border-top-left-radius: 20rpx;
  border-top-right-radius: 20rpx;
  margin-top: -20px;
  overflow: hidden;
  position: relative;
  width: 100%;
}

.content-inner {
  margin: 30rpx 24rpx 0;
}

.module-group {
  background: #fff;
  border-radius: 18rpx;
  margin-bottom: 20rpx;
}

.module-group--first {
  margin-top: 0;
}

.module-group--onboarding {
  border-top-left-radius: 35rpx;
  border-top-right-radius: 35rpx;
}

.module-group .title {
  color: #181818;
  font-size: 27rpx;
  font-weight: 500;
  line-height: 27rpx;
  padding: 30rpx 32rpx 34rpx;
}

.module-group .group {
  display: flex;
  flex-wrap: wrap;
  padding: 0 8rpx;
}

.group-item {
  align-items: center;
  display: flex;
  flex-direction: column;
  height: 112rpx;
  margin-bottom: 34rpx;
  width: 25%;
}

.logo-wrap {
  align-items: center;
  border-radius: 22rpx;
  display: flex;
  height: 76rpx;
  justify-content: center;
  position: relative;
  width: 76rpx;
}

.module-group.basics .logo-wrap,
.module-group.onboarding .logo-wrap {
  border-radius: 28rpx;
}

.card-dot-tips {
  background-color: #dc3c5c;
  border-radius: 50%;
  height: 18rpx;
  position: absolute;
  right: -5rpx;
  top: -5rpx;
  width: 18rpx;
}

.text-wrap {
  font-size: 22rpx;
  line-height: 22rpx;
  margin-top: 14rpx;
  text-align: center;
  color: #181818;
}

.shop-service {
  background: #fff;
  border-radius: 18rpx;
  display: flex;
  margin-bottom: 20rpx;
  padding: 22rpx 28rpx 20rpx 30rpx;
}

.service-icon {
  align-items: center;
  border-radius: 18rpx;
  display: flex;
  height: 110rpx;
  justify-content: center;
  margin-right: 16rpx;
  width: 104rpx;
}

.service-icon-primary {
  background: #5fa3ea;
}

.service-icon-secondary {
  background: #f19469;
}

.text {
  display: flex;
  flex: 1;
  flex-direction: column;
  justify-content: center;
}

.first-text {
  color: #293455;
  font-size: 27rpx;
  font-weight: 500;
  line-height: 27rpx;
  margin-bottom: 14rpx;
}

.last-text {
  color: #3d505f;
  font-size: 22rpx;
  line-height: 22rpx;
}

.empty-wrap {
  min-height: 400rpx;
  padding-top: 100rpx;
}
</style>
