<script setup lang="ts">
import { computed, ref } from "vue";
import { onPageScroll, onShow } from "@dcloudio/uni-app";
import { getMemberHiddenCards, getMemberMine, hideMemberCard } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant, loadJoinableSites, loadJoinedMemberSites, selectMemberSite } from "@/composables/member-context";
import type { MemberCardWalletSummary, MemberMineDashboard, MemberSiteOption } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";

const loading = ref(true);
const errorMessage = ref("");
const needsSite = ref(false);
const sites = ref<MemberSiteOption[]>([]);
const dashboard = ref<MemberMineDashboard | null>(null);
const displayCards = ref<MemberCardWalletSummary[]>([]);
const hiddenCardCount = ref(0);
const showMoreMenu = ref(false);
const hidingCard = ref(false);
const fixedBarOpacity = ref(0);
const statusBarHeight = ref(0);
// 头部内容顶部留白：需避开微信右上角胶囊按钮，否则设置图标会被遮挡
const headerPaddingTop = ref(24);
try {
  const sys = uni.getSystemInfoSync();
  statusBarHeight.value = sys.statusBarHeight || 0;
  let top = statusBarHeight.value + 12;
  const menuRect = uni.getMenuButtonBoundingClientRect?.();
  if (menuRect?.bottom) {
    top = Math.max(top, menuRect.bottom + 8);
  }
  headerPaddingTop.value = top;
} catch {
  statusBarHeight.value = 0;
  headerPaddingTop.value = 24;
}

onPageScroll((options) => {
  const top = options.scrollTop || 0;
  fixedBarOpacity.value = Math.min(Math.max(top - 40, 0) / 100, 1);
});

const statItems = computed(() => {
  if (!dashboard.value) return [];
  const stats = dashboard.value.stats;
  const items: { label: string; value: string; route?: string }[] = [
    { label: "累计上课(次)", value: String(stats.appointCount), route: "/pages/mine/stats" },
    { label: "本月上课(次)", value: String(stats.lastMonthAppointCount), route: "/pages/mine/stats?scope=month" },
  ];
  if (dashboard.value.pointsEnabled && stats.totalPoint != null) {
    items.push({ label: "累计积分", value: String(stats.totalPoint), route: "/pages/mine/points" });
  } else if (stats.absenceCount != null) {
    items.push({ label: "本月旷课(次)", value: String(stats.absenceCount) });
  }
  if (dashboard.value.showMonthRank && stats.monthRankNum != null) {
    items.push({ label: "本月排名", value: String(stats.monthRankNum), route: "/pages/mine/ranking" });
  } else if (dashboard.value.showMonthRank) {
    items.push({ label: "本月排名", value: "-", route: "/pages/mine/ranking" });
  }
  return items;
});

const menuItems = computed(() => {
  const items = [
    { label: "约课统计", icon: "calendar", action: "stats" },
    { label: "我的订单", icon: "order", action: "orders" },
    { label: "场馆详情", icon: "home", action: "site" },
    { label: "场馆资料", icon: "edit-pen", action: "profile" },
    { label: "会员协议", icon: "file-text", action: "legal" },
  ];
  if (dashboard.value?.showMonthRank) {
    items.push({ label: "月度排行", icon: "list", action: "ranking" });
  }
  if (dashboard.value?.pointsEnabled) {
    items.push({ label: "积分明细", icon: "gift", action: "points" });
  }
  return items;
});

async function loadDashboard() {
  // 仅首次（无数据）时显示全屏加载，再次进入静默刷新
  loading.value = !dashboard.value;
  errorMessage.value = "";
  needsSite.value = false;

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      sites.value = await loadJoinableSites();
      needsSite.value = true;
      return;
    }

    const response = await getMemberMine(tenant.tenantId);
    dashboard.value = response.data;
    displayCards.value = [...(response.data.cardList ?? [])];
    void loadHiddenCount(tenant.tenantId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "我的页面加载失败";
  } finally {
    loading.value = false;
  }
}

async function loadHiddenCount(tenantId: number) {
  try {
    const response = await getMemberHiddenCards(tenantId);
    hiddenCardCount.value = response.data.length;
  } catch {
    hiddenCardCount.value = 0;
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
      await loadDashboard();
    },
  });
}

function openProfile() {
  uni.navigateTo({ url: "/pages/mine/profile" });
}

function openStats() {
  uni.navigateTo({ url: "/pages/mine/stats" });
}

function openPoints() {
  uni.navigateTo({ url: "/pages/mine/points" });
}

function openOnboarding() {
  uni.navigateTo({ url: "/pages/onboarding/profile" });
}

function openMyAppointments() {
  const cardQuery = frontCard.value ? `?cardId=${frontCard.value.id}` : "";
  uni.navigateTo({ url: `/pages/booking/my-appointments${cardQuery}` });
}

function openBalanceCheck() {
  // 对标原版：余额核对 = 使用记录页第二个 tab
  const cardQuery = frontCard.value ? `cardId=${frontCard.value.id}&` : "";
  uni.navigateTo({ url: `/pages/booking/my-appointments?${cardQuery}tab=1` });
}

function openStatItem(item: { route?: string }) {
  if (item.route) {
    uni.navigateTo({ url: item.route });
    return;
  }
  openStats();
}

function openRanking() {
  uni.navigateTo({ url: "/pages/mine/ranking" });
}

function openWallet() {
  uni.navigateTo({ url: "/pages/cards/index" });
}

function openOrders() {
  uni.navigateTo({ url: "/pages/orders/index" });
}

function openLegal() {
  uni.navigateTo({ url: "/pages/legal/index?type=member_terms" });
}

function openSiteDetail() {
  uni.navigateTo({ url: "/pages/sites/detail" });
}

function openCardDetail(cardId: number) {
  uni.navigateTo({ url: `/pages/cards/detail?id=${cardId}` });
}

const frontCard = computed(() => displayCards.value[displayCards.value.length - 1] ?? null);

function onCardTap(card: MemberCardWalletSummary, index: number) {
  // 对标原版：点击非顶层卡片将其切换到最前，点击顶层卡片进入详情
  if (index !== displayCards.value.length - 1) {
    const next = [...displayCards.value];
    next.splice(index, 1);
    next.push(card);
    displayCards.value = next;
    return;
  }
  openCardDetail(card.id);
}

function toggleMoreMenu() {
  showMoreMenu.value = !showMoreMenu.value;
}

function closeMoreMenu() {
  showMoreMenu.value = false;
}

function openRenew() {
  closeMoreMenu();
  uni.navigateTo({ url: "/pages/cards/catalog" });
}

function confirmHideFrontCard() {
  closeMoreMenu();
  const card = frontCard.value;
  if (!card) {
    uni.showToast({ title: "暂无会员卡", icon: "none" });
    return;
  }
  uni.showModal({
    title: "确认隐藏该卡吗？",
    content: "隐藏后，可点击右下角回收站图标进行恢复显示",
    success: async (result) => {
      if (!result.confirm) return;
      await hideFrontCard(card);
    },
  });
}

async function hideFrontCard(card: MemberCardWalletSummary) {
  if (hidingCard.value) return;
  const tenant = await ensureMemberTenant();
  if (!tenant) return;

  hidingCard.value = true;
  try {
    await hideMemberCard(tenant.tenantId, card.id, createCommandKey());
    uni.showToast({ title: "已隐藏", icon: "success" });
    await loadDashboard();
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "隐藏失败",
      icon: "none",
    });
  } finally {
    hidingCard.value = false;
  }
}

function openHiddenCards() {
  uni.navigateTo({ url: "/pages/cards/hidden" });
}

function openBenefits() {
  const id = frontCard.value?.id;
  if (!id) {
    uni.showToast({ title: "暂无会员卡", icon: "none" });
    return;
  }
  uni.navigateTo({ url: `/pages/cards/benefits?id=${id}` });
}

function handleMenuAction(action: string) {
  if (action === "stats") openStats();
  else if (action === "orders") openOrders();
  else if (action === "site") openSiteDetail();
  else if (action === "profile") openProfile();
  else if (action === "legal") openLegal();
  else if (action === "ranking") openRanking();
  else if (action === "points") openPoints();
}

onShow(async () => {
  if (await requireMemberAuth()) await loadDashboard();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="mine-page">
    <view v-if="needsSite" class="page-container" :style="{ paddingTop: `${headerPaddingTop}px` }">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="site-prompt">
        <view class="section-title">选择场馆</view>
        <view class="prompt-text">加入场馆后查看会员卡和上课统计</view>
        <u-button type="primary" @click="openSitePicker">选择场馆</u-button>
        <u-button plain @click="openOnboarding">去加入场馆</u-button>
      </view>
    </view>

    <template v-else-if="dashboard">
      <view
        class="fixed-bar"
        :style="{
          paddingTop: statusBarHeight ? `${statusBarHeight}px` : '0',
          opacity: fixedBarOpacity,
          pointerEvents: fixedBarOpacity > 0.1 ? 'auto' : 'none',
        }"
      >
        <view class="fixed-bar-inner" @tap="openProfile">
          <u-avatar
            size="32"
            :src="dashboard.profile.avatarUrl || undefined"
            :icon="dashboard.profile.avatarUrl ? undefined : 'account-fill'"
            bg-color="#ffffff"
            color="#181818"
          />
          <text class="fixed-bar-name">{{ dashboard.profile.displayName || "会员" }}</text>
        </view>
      </view>

      <view class="mine-header" :style="{ paddingTop: `${headerPaddingTop}px` }">
        <view class="profile-top" @tap="openProfile">
          <u-avatar
            size="52"
            :src="dashboard.profile.avatarUrl || undefined"
            :icon="dashboard.profile.avatarUrl ? undefined : 'account-fill'"
            bg-color="#ffffff"
            color="#181818"
          />
          <view class="profile-text">
            <view class="profile-name">{{ dashboard.profile.displayName || "会员" }}</view>
            <view class="profile-subtitle">{{ dashboard.helloMessage }}</view>
            <view v-if="dashboard.profile.mobileMasked" class="profile-subtitle">{{ dashboard.profile.mobileMasked }}</view>
          </view>
          <u-icon name="setting" size="18" color="#181818" />
        </view>

        <view class="stats-row">
          <view v-for="item in statItems" :key="item.label" class="stats-item" @tap="openStatItem(item)">
            <view class="stats-value">{{ item.value }}</view>
            <view class="stats-label">{{ item.label }}</view>
          </view>
        </view>
      </view>

      <view class="mine-main card-sheet">
        <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

        <view class="card-section">
          <view class="section-header" @tap="openWallet">
            <view class="section-title section-title--inline">我的会员卡（{{ dashboard.cardCount }}）</view>
            <view class="section-link">查看全部</view>
          </view>

          <view v-if="displayCards.length" class="card-list-wrap" :style="{ height: `${371 + 30 * (displayCards.length - 1)}rpx` }">
            <view
              v-for="(card, index) in displayCards"
              :key="`${card.id}-${index}`"
              class="card-item"
              :style="{ top: `${index * 30}rpx`, zIndex: index + 1 }"
              @tap="onCardTap(card, index)"
            >
              <member-card :card="card" />
            </view>
          </view>

          <view v-else class="no-card">
            <view class="hint-text">您还没有会员卡哦</view>
          </view>

          <view v-if="displayCards.length" class="handle-wrap">
            <view class="handle-item" @tap="openMyAppointments">
              <u-icon name="calendar" size="20" color="#696B99" />
              <text>预约记录</text>
            </view>
            <u-line color="#DADADA" direction="col" length="20" margin="0 25rpx" />
            <view class="handle-item" @tap="openBalanceCheck">
              <u-icon name="rmb-circle" size="20" color="#696B99" />
              <text>余额核对</text>
            </view>
            <u-line color="#DADADA" direction="col" length="20" margin="0 25rpx" />
            <view class="handle-item" @tap="openBenefits">
              <u-icon name="gift" size="20" color="#696B99" />
              <text>权益</text>
            </view>
            <u-line color="#DADADA" direction="col" length="20" margin="0 25rpx" />
            <view class="handle-item handle-item--more">
              <view class="more-trigger" @tap.stop="toggleMoreMenu">
                <u-icon name="more-dot-fill" size="22" color="#696B99" />
              </view>
              <view v-if="showMoreMenu" class="more-mask" @tap="closeMoreMenu" />
              <view v-if="showMoreMenu" class="drop-down">
                <view class="drop-down-arrow" />
                <view class="drop-down-list">
                  <view class="drop-item" @tap="openRenew">
                    <u-icon name="rmb-circle" size="18" color="#696B99" />
                    <text>我要续费</text>
                  </view>
                  <view class="drop-item" @tap="confirmHideFrontCard">
                    <u-icon name="eye-off" size="18" color="#696B99" />
                    <text>{{ hidingCard ? "隐藏中..." : "隐藏该卡" }}</text>
                  </view>
                </view>
              </view>
            </view>
          </view>
        </view>

        <view class="menu-section">
          <u-line color="#F0F0F0" />
          <view
            v-for="item in menuItems"
            :key="item.action"
            class="menu-list-item"
            @tap="handleMenuAction(item.action)"
          >
            <u-icon :name="item.icon" size="20" color="#181818" />
            <text class="menu-list-item__label">{{ item.label }}</text>
            <u-icon name="arrow-right" size="14" color="#BFBFBF" />
          </view>
        </view>
      </view>

      <view v-if="hiddenCardCount > 0" class="recycle-float" @tap="openHiddenCards">
        <u-icon name="trash" size="26" color="#696B99" />
        <text class="recycle-count">{{ hiddenCardCount }}</text>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.mine-page {
  min-height: 100vh;
  background: $color-page;
}

.fixed-bar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 99;
  background: $color-accent-yellow;
  box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.06);
}

.fixed-bar-inner {
  display: flex;
  align-items: center;
  gap: 12rpx;
  height: 80rpx;
  padding: 0 28rpx;
}

.fixed-bar-name {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 500;
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

.mine-header {
  padding: 24rpx 28rpx 50rpx;
  background: $color-accent-yellow;
}

.profile-top {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.profile-text {
  flex: 1;
}

.profile-name {
  color: $color-text;
  font-size: 35rpx;
  font-weight: 500;
  line-height: 40rpx;
}

.profile-subtitle {
  margin-top: 8rpx;
  color: $color-text;
  font-size: 19rpx;
  line-height: 24rpx;
}

.stats-row {
  display: flex;
  justify-content: space-between;
  margin-top: 26rpx;
  padding: 0 32rpx;
}

.stats-item {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
}

.stats-value {
  color: $color-text;
  font-size: 46rpx;
  line-height: 46rpx;
}

.stats-label {
  margin-top: 17rpx;
  color: $color-text;
  font-size: 19rpx;
  line-height: 24rpx;
  text-align: center;
}

.mine-main {
  margin-top: -25rpx;
  padding-bottom: 40rpx;
}

.card-section {
  padding: 40rpx 28rpx 0;
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20rpx;
}

.section-link {
  color: $color-primary;
  font-size: 24rpx;
}

.wallet-card {
  margin-bottom: 20rpx;
  padding: 24rpx;
  background: linear-gradient(135deg, #faf5f8 0%, #fff 100%);
  border: 1rpx solid #f3e8ee;
  border-radius: $radius-md;
}

.wallet-card-name {
  color: $color-accent-pink;
  font-size: 30rpx;
  font-weight: 600;
}

.wallet-card-meta {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-list-wrap {
  position: relative;
  width: 100%;
  margin-bottom: 24rpx;
}

.card-item {
  position: absolute;
  left: 0;
  right: 0;
}

.no-card {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 200rpx;
  margin-bottom: 24rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.hint-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.handle-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20rpx 0;
  margin-bottom: 24rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.handle-item {
  display: flex;
  align-items: center;
  gap: 8rpx;
  color: $color-text;
  font-size: 26rpx;
}

.handle-item--more {
  position: relative;
}

.more-trigger {
  display: flex;
  align-items: center;
  padding: 8rpx;
}

.more-mask {
  position: fixed;
  inset: 0;
  z-index: 30;
}

.drop-down {
  position: absolute;
  top: 56rpx;
  right: -16rpx;
  z-index: 31;
}

.drop-down-arrow {
  position: absolute;
  top: -10rpx;
  right: 32rpx;
  width: 0;
  height: 0;
  border-left: 12rpx solid transparent;
  border-right: 12rpx solid transparent;
  border-bottom: 12rpx solid $color-surface;
  filter: drop-shadow(0 -2rpx 2rpx rgba(0, 0, 0, 0.04));
}

.drop-down-list {
  min-width: 220rpx;
  padding: 8rpx 0;
  background: $color-surface;
  border-radius: 12rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.12);
}

.drop-item {
  display: flex;
  align-items: center;
  gap: 12rpx;
  padding: 20rpx 28rpx;
  color: $color-text;
  font-size: 26rpx;
  white-space: nowrap;
}

.recycle-float {
  position: fixed;
  right: 24rpx;
  bottom: 160rpx;
  z-index: 20;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 96rpx;
  height: 96rpx;
  background: $color-surface;
  border-radius: 50%;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.12);
}

.recycle-count {
  margin-top: 2rpx;
  color: $color-text-secondary;
  font-size: 20rpx;
  line-height: 20rpx;
}

.menu-section {
  margin-top: 20rpx;
  padding: 0 28rpx;
  background: $color-surface;
}
</style>
