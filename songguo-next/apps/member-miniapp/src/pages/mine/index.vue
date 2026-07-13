<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { getMemberMine } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant, loadJoinableSites, loadJoinedMemberSites, selectMemberSite } from "@/composables/member-context";
import type { MemberCardWalletSummary, MemberMineDashboard, MemberSiteOption } from "@/types/member";

const loading = ref(true);
const errorMessage = ref("");
const needsSite = ref(false);
const sites = ref<MemberSiteOption[]>([]);
const dashboard = ref<MemberMineDashboard | null>(null);
const displayCards = ref<MemberCardWalletSummary[]>([]);
const statusBarHeight = ref(0);
try {
  statusBarHeight.value = uni.getSystemInfoSync().statusBarHeight || 0;
} catch {
  statusBarHeight.value = 0;
}

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
    { label: "约课统计", icon: "bar-chart", action: "stats" },
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
  loading.value = true;
  errorMessage.value = "";
  needsSite.value = false;
  dashboard.value = null;

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
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "我的页面加载失败";
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
  uni.navigateTo({ url: "/pages/booking/my-appointments" });
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

function switchCardFace() {
  const list = displayCards.value;
  if (list.length <= 1) {
    uni.showToast({ title: "只有一张会员卡", icon: "none" });
    return;
  }
  const next = [...list];
  const top = next.pop();
  if (top) next.unshift(top);
  displayCards.value = next;
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
    <view v-if="needsSite" class="page-container" :style="{ paddingTop: statusBarHeight ? `${statusBarHeight}px` : '0' }">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="site-prompt">
        <view class="section-title">选择场馆</view>
        <view class="prompt-text">加入场馆后查看会员卡和上课统计</view>
        <u-button type="primary" @click="openSitePicker">选择场馆</u-button>
        <u-button plain @click="openOnboarding">去加入场馆</u-button>
      </view>
    </view>

    <template v-else-if="dashboard">
      <view class="mine-header" :style="{ paddingTop: statusBarHeight ? `${statusBarHeight + 12}px` : '24rpx' }">
        <view class="profile-top" @tap="openProfile">
          <u-avatar size="52" icon="account-fill" bg-color="#ffffff" color="#181818" />
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
              @tap="openCardDetail(card.id)"
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
            <view class="handle-item" @tap="frontCard ? openCardDetail(frontCard.id) : undefined">
              <u-icon name="rmb-circle" size="20" color="#696B99" />
              <text>余额变动</text>
            </view>
            <u-line color="#DADADA" direction="col" length="20" margin="0 25rpx" />
            <view class="handle-item" @tap="openBenefits">
              <u-icon name="gift" size="20" color="#696B99" />
              <text>权益</text>
            </view>
            <u-line color="#DADADA" direction="col" length="20" margin="0 25rpx" />
            <view class="handle-item" @tap="switchCardFace">
              <u-icon name="reload" size="20" color="#696B99" />
              <text>切换</text>
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
    </template>
  </view>
</template>

<style scoped lang="scss">
.mine-page {
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

.menu-section {
  margin-top: 20rpx;
  padding: 0 28rpx;
  background: $color-surface;
}
</style>
