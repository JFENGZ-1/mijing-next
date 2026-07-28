<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { useApiClient } from "@/api/client";
import { fetchSettingsHub } from "@/api/settings";
import { fetchStaffProfile } from "@/api/profile";
import { fetchSiteProfile } from "@/api/site-profile";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { SettingsHub, SettingsHubItem } from "@/types/settings";
import type { StaffProfile } from "@/types/profile";
import type { SiteProfile } from "@/types/site-profile";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const hub = ref<SettingsHub | null>(null);
const profile = ref<StaffProfile | null>(null);
const siteProfile = ref<SiteProfile | null>(null);

const canReadSettings = computed(() => session.can("tenant.settings.read"));
const canReadSite = computed(() => session.can("site.profile.read"));
const canReadStaff = computed(() => session.can("staff.directory.read"));
const canCheckIn = computed(() => session.can("booking.fulfillment.check-in"));
const currentSiteName = computed(
  () => siteProfile.value?.name
    || session.sites.find((site) => site.id === session.currentSiteId)?.name
    || "当前场馆",
);

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
    errorMessage.value = error instanceof Error ? error.message : "设置中心加载失败";
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

function openStaff() {
  if (!canReadStaff.value) {
    uni.showToast({ title: "暂无员工目录权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/pages/settings/staff/index" });
}

function openCheckIn() {
  if (!canCheckIn.value) {
    uni.showToast({ title: "暂无签到权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/pages/check-in/scan/index" });
}

function openSites() {
  uni.navigateTo({ url: "/pages/sites/index" });
}

async function logout() {
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "退出登录",
      content: "确定要退出当前账号吗？",
      success: (result) => resolve(!!result.confirm),
      fail: () => resolve(false),
    });
  });
  if (!confirmed) return;
  try {
    await useApiClient().request("/auth/logout", { method: "POST" });
  } catch {
    // 忽略登出接口失败，本地清理照常执行
  }
  session.clear();
  uni.reLaunch({ url: "/pages/login/index" });
}

function nameInitial(name?: string | null) {
  return (name || "员").slice(0, 1);
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
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <!-- 场馆信息卡（对标原版 shop 顶部门店卡） -->
    <view class="site-card" @tap="openSiteProfile">
      <image v-if="siteProfile?.logoUrl" class="site-logo" :src="siteProfile.logoUrl" mode="aspectFill" />
      <view v-else class="site-logo site-logo-fallback">{{ nameInitial(currentSiteName) }}</view>
      <view class="site-main">
        <text class="site-name">{{ currentSiteName }}</text>
        <text class="site-address">{{ siteProfile?.address || "点击完善场馆资料" }}</text>
      </view>
      <u-icon v-if="canReadSite" name="arrow-right" size="16" color="#bfbfbf" />
    </view>

    <!-- 个人信息卡 -->
    <view class="person-card" @tap="openPersonal">
      <image v-if="profile?.avatarUrl" class="person-avatar-img" :src="profile.avatarUrl" mode="aspectFill" />
      <view v-else class="person-avatar">{{ nameInitial(profile?.displayName) }}</view>
      <view class="person-main">
        <text class="person-name">{{ profile?.displayName || "我的资料" }}</text>
        <text class="person-sub">{{ profile?.mobileMasked || profile?.employeeNo || "查看与编辑个人资料" }}</text>
      </view>
      <u-icon name="arrow-right" size="16" color="#bfbfbf" />
    </view>

    <!-- 快捷入口（员工/签到/场馆切换） -->
    <view class="quick-row">
      <view class="quick-cell" @tap="openStaff">
        <u-icon name="account-fill" size="26" color="#5fa3ea" />
        <text class="quick-label">员工</text>
      </view>
      <view class="quick-cell" @tap="openCheckIn">
        <u-icon name="scan" size="26" color="#22c788" />
        <text class="quick-label">扫码签到</text>
      </view>
      <view class="quick-cell" @tap="openSites">
        <u-icon name="home-fill" size="26" color="#ed920f" />
        <text class="quick-label">场馆切换</text>
      </view>
    </view>

    <!-- 设置分组（后端下发） -->
    <template v-if="canReadSettings && hub">
      <view v-for="section in hub.sections" :key="section.key" class="section-card">
        <view class="section-head">{{ section.label }}</view>
        <u-cell-group>
          <u-cell
            v-for="item in section.items"
            :key="item.key"
            :title="item.label"
            :label="item.description || undefined"
            :is-link="item.enabled && item.implemented && !!item.route"
            :disabled="!item.enabled"
            @click="openItem(item)"
          >
            <template #value>
              <u-tag v-if="item.setupIncomplete" text="待完善" type="warning" size="mini" />
              <u-tag v-else-if="!item.implemented" text="待上线" type="info" size="mini" />
            </template>
          </u-cell>
        </u-cell-group>
      </view>
    </template>

    <!-- 退出登录（对标原版底部） -->
    <button class="logout-btn" @click="logout">退出登录</button>
  </view>
</template>

<style scoped lang="scss">
.site-card,
.person-card {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: $spacing-md;
  background: $color-surface;
  border-radius: $radius-lg;
}

.person-card {
  margin-top: $spacing-sm;
}

.site-logo,
.person-avatar,
.person-avatar-img {
  flex-shrink: 0;
}

.site-logo {
  width: 96rpx;
  height: 96rpx;
  border-radius: 20rpx;
}

.site-logo-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #5fa3ea, #3f77c9);
  color: #fff;
  font-size: 36rpx;
}

.site-main,
.person-main {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.site-name {
  font-size: 32rpx;
  font-weight: 600;
  color: $color-text;
}

.site-address {
  overflow: hidden;
  margin-top: 8rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.person-avatar,
.person-avatar-img {
  width: 80rpx;
  height: 80rpx;
  border-radius: 50%;
}

.person-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  background: $color-primary;
  color: #fff;
  font-size: 32rpx;
}

.person-name {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.person-sub {
  margin-top: 6rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.quick-row {
  display: flex;
  margin-top: $spacing-sm;
  padding: $spacing-md 0;
  background: $color-surface;
  border-radius: $radius-lg;
}

.quick-cell {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  gap: 12rpx;
}

.quick-label {
  color: $color-text-secondary;
  font-size: 24rpx;
}

.section-card {
  overflow: hidden;
  margin-top: $spacing-md;
  background: $color-surface;
  border-radius: $radius-lg;
}

.section-head {
  padding: 24rpx 28rpx 8rpx;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.logout-btn {
  margin: 48rpx 0 40rpx;
  height: 88rpx;
  line-height: 88rpx;
  color: $color-danger;
  font-size: 30rpx;
  background: $color-surface;
  border-radius: 44rpx;
}

.logout-btn::after {
  border: 0;
}
</style>
