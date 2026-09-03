<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchStaffDashboardAppointmentFeed, fetchStaffDashboardSalesFeed, fetchStaffDashboardSummary } from "@/api/dashboard";
import { useSessionStore } from "@/stores/session";
import { requireStaffAuth } from "@/auth/guard";
import type {
  StaffDashboardAppointmentFeedItem,
  StaffDashboardSalesFeedItem,
  StaffDashboardSummary,
} from "@/types/dashboard";

const session = useSessionStore();
const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;
const checking = ref(true);
const loading = ref(false);
const errorMessage = ref("");
const summary = ref<StaffDashboardSummary | null>(null);
const salesFeed = ref<StaffDashboardSalesFeedItem[]>([]);
const appointmentFeed = ref<StaffDashboardAppointmentFeedItem[]>([]);
const hideRevenue = ref(uni.getStorageSync("hide_dashboard_revenue") === true);

const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "选择场馆");
const canSwitchSite = computed(() => session.sites.length > 1);
const canViewSummary = computed(() => session.can("staff.dashboard.read") || session.can("crm.member.read"));
const canViewSalesFeed = computed(() => session.can("staff.dashboard.read") || session.can("order.read"));
const canViewAppointmentFeed = computed(() => session.can("staff.dashboard.read"));

const metrics = computed(() => {
  const kpis = summary.value?.kpis;
  return [
    { label: "预约团课", value: String(kpis?.groupAppointmentCount ?? 0) },
    { label: "预约私教", value: String(kpis?.privateAppointmentCount ?? 0) },
    { label: "售卡", value: String(kpis?.saleCardCount ?? 0) },
    { label: "新增会员", value: String(kpis?.newMemberCount ?? 0) },
  ];
});

const revenueDisplay = computed(() => {
  if (!canViewSummary.value || hideRevenue.value) return "******";
  return summary.value?.kpis.todayRevenue ?? "0.00";
});

const appointmentTotal = computed(() => {
  const kpis = summary.value?.kpis;
  return (kpis?.groupAppointmentCount ?? 0) + (kpis?.privateAppointmentCount ?? 0);
});

async function loadDashboard() {
  if (!session.currentSiteId) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const tasks: Promise<void>[] = [];
    if (canViewSummary.value) {
      tasks.push(
        fetchStaffDashboardSummary(session.currentSiteId).then((response) => {
          summary.value = response;
        }),
      );
    }
    if (canViewSalesFeed.value) {
      tasks.push(
        fetchStaffDashboardSalesFeed(session.currentSiteId).then((response) => {
          salesFeed.value = response.items;
        }),
      );
    }
    if (canViewAppointmentFeed.value) {
      tasks.push(
        fetchStaffDashboardAppointmentFeed(session.currentSiteId).then((response) => {
          appointmentFeed.value = response.items;
        }),
      );
    }
    await Promise.all(tasks);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "加载首页数据失败";
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await loadDashboard();
});

onPullDownRefresh(async () => {
  await loadDashboard();
  uni.stopPullDownRefresh();
});

function toggleRevenueVisibility() {
  hideRevenue.value = !hideRevenue.value;
  uni.setStorageSync("hide_dashboard_revenue", hideRevenue.value);
}

function formatSoldTime(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

function formatSessionTime(startsAt?: string | null, endsAt?: string | null) {
  if (!startsAt) return "";
  const start = formatSoldTime(startsAt);
  const end = endsAt ? formatSoldTime(endsAt) : "";
  return end ? `${start}-${end}` : start;
}

function appointmentStatusLabel(status: string) {
  if (status === "confirmed") return "已预约";
  return status;
}

function memberInitial(name?: string | null) {
  return (name || "客").slice(0, 1);
}

function openSiteSwitcher() {
  uni.navigateTo({ url: "/subpackages/misc/sites/index" });
}
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="home-container">
    <!-- 顶部头图区（对标原版 home_top_bg，沉浸式，白字问候 + 场馆切换胶囊） -->
    <view class="top-box" :style="{ paddingTop: statusBarHeight + 8 + 'px' }">
      <view class="capsule-row">
        <view class="site-capsule" @click="openSiteSwitcher">
          <u-icon name="reload" size="15" color="#ffffff" />
          <text class="site-name">{{ currentSiteName }}</text>
          <u-icon v-if="canSwitchSite" name="arrow-down" size="12" color="#ffffff" />
        </view>
      </view>
      <view class="greet-box">
        <text class="time-greete">{{ summary?.greeting.headline || "今天" }}</text>
        <text class="greet-hint">{{ summary?.greeting.hint || "祝您工作愉快" }}</text>
      </view>
    </view>

    <!-- 白色圆角内容区上盖头图（原版 -20rpx / 20rpx 顶圆角） -->
    <view class="today-data">
      <view v-if="errorMessage" class="error-text">{{ errorMessage }}</view>

      <template v-if="canViewSummary">
        <view class="money-row" @click="toggleRevenueVisibility">
          <text class="money">{{ revenueDisplay }}</text>
        </view>
        <view class="money-label-row" @click="toggleRevenueVisibility">
          <text class="money-label">今日营业额(元)</text>
          <u-icon :name="hideRevenue ? 'eye-off' : 'eye'" size="17" color="#989898" />
        </view>

        <view class="metric-row">
          <view v-for="(metric, index) in metrics" :key="metric.label" class="metric-cell" :class="{ 'with-divider': index > 0 }">
            <text class="metric-value">{{ metric.value }}</text>
            <text class="metric-label">{{ metric.label }}</text>
          </view>
        </view>
      </template>

      <!-- 今日售卡 -->
      <template v-if="canViewSalesFeed">
        <view class="feed-header">
          <text class="feed-title">今日售卡</text>
          <text class="feed-count">共{{ summary?.kpis.saleCardCount ?? salesFeed.length }}笔</text>
        </view>
        <view v-if="salesFeed.length" class="feed-list">
          <view v-for="item in salesFeed" :key="item.id" class="sale-item">
            <view class="avatar">{{ memberInitial(item.memberName) }}</view>
            <view class="sale-main">
              <view class="sale-line1">
                <text class="sale-member">{{ item.memberName || "会员" }}</text>
                <text class="sale-amount">¥{{ item.amount }}</text>
              </view>
              <view class="sale-line2">
                <text class="sale-meta">{{ formatSoldTime(item.soldAt) }} · {{ item.cardName || "会员卡" }}</text>
                <text v-if="item.isNewMember" class="sg-tag-new">新会员</text>
              </view>
              <text v-if="item.remark" class="sale-remark">{{ item.remark }}</text>
            </view>
          </view>
        </view>
        <view v-else class="nodata-box">
          <text class="sg-empty-text">今天还没有售卡记录</text>
        </view>
      </template>

      <!-- 今日约课 -->
      <template v-if="canViewAppointmentFeed">
        <view class="feed-header">
          <text class="feed-title">今日约课</text>
          <text class="feed-count">共{{ appointmentTotal }}人次</text>
        </view>
        <view v-if="appointmentFeed.length" class="feed-list">
          <view v-for="item in appointmentFeed" :key="item.id" class="appoint-item">
            <view class="avatar appoint-avatar">{{ memberInitial(item.memberName) }}</view>
            <view class="sale-main">
              <view class="sale-line1">
                <text class="sale-member">{{ item.memberName || "会员" }}</text>
                <text class="appoint-status">{{ appointmentStatusLabel(item.status) }}</text>
              </view>
              <text class="sale-meta">
                {{ formatSessionTime(item.startsAt, item.endsAt) }}
                · {{ item.courseName || (item.sessionKind === "private" ? "私教" : "课程") }}
                <template v-if="item.coachName"> · {{ item.coachName }}</template>
              </text>
            </view>
          </view>
        </view>
        <view v-else class="nodata-box">
          <text class="sg-empty-text">今天还没有约课记录</text>
        </view>
      </template>

      <u-empty
        v-if="!canViewSummary && !canViewSalesFeed && !canViewAppointmentFeed"
        mode="permission"
        text="当前账号暂无首页数据权限"
      />
    </view>
  </view>
</template>

<style scoped lang="scss">
.home-container {
  min-height: 100vh;
  background: $color-page;
}

// —— 头图区（对标原版 310rpx 蓝色背景，沉浸式自适应状态栏） ——
.top-box {
  position: relative;
  padding: 0 28rpx 60rpx;
  box-sizing: border-box;
  background: linear-gradient(135deg, #5fa3ea 0%, #3f77c9 100%);
}

.capsule-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.site-capsule {
  display: flex;
  align-items: center;
  gap: 10rpx;
  max-width: 420rpx;
  height: 56rpx;
  padding: 0 24rpx;
  border-radius: 30rpx;
  background: rgba(0, 0, 0, 0.18);
}

.site-name {
  overflow: hidden;
  color: #fff;
  font-size: 28rpx;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.top-icons {
  display: flex;
  align-items: center;
  gap: 28rpx;
}

.greet-box {
  display: flex;
  flex-direction: column;
  margin-top: 46rpx;
}

.time-greete {
  color: #fff;
  font-size: 53rpx;
  font-weight: 700;
  line-height: 53rpx;
}

.greet-hint {
  margin-top: 20rpx;
  color: rgba(255, 255, 255, 0.9);
  font-size: 22rpx;
  line-height: 22rpx;
}

// —— 白色内容区上盖 ——
.today-data {
  position: relative;
  margin-top: -20rpx;
  padding: 56rpx 0 40rpx;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
}

.error-text {
  padding: 0 28rpx $spacing-sm;
  color: $color-danger;
  font-size: 24rpx;
}

// 营业额大字（原版 #ed920f 90rpx）
.money-row {
  display: flex;
  justify-content: center;
}

.money {
  color: $color-primary;
  font-size: 90rpx;
  font-weight: 400;
  line-height: 90rpx;
}

.money-label-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10rpx;
  margin-top: 16rpx;
}

.money-label {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

// 4 指标横排 + 竖分隔线（原版 #DDDDDD）
.metric-row {
  display: flex;
  margin-top: 56rpx;
  padding-bottom: 40rpx;
  border-bottom: 1rpx solid $color-page;
}

.metric-cell {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;

  &.with-divider {
    border-left: 1rpx solid $color-divider;
  }
}

.metric-value {
  font-size: 40rpx;
  font-weight: 600;
  color: $color-text;
}

.metric-label {
  margin-top: 12rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

// —— 今日售卡 / 今日约课 ——
.feed-header {
  display: flex;
  align-items: baseline;
  gap: 16rpx;
  margin-top: 40rpx;
  padding: 0 28rpx 8rpx;
}

.feed-title {
  font-size: 32rpx;
  font-weight: 600;
  color: $color-text;
}

.feed-count {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.feed-list {
  padding: 0 28rpx;
}

.sale-item,
.appoint-item {
  display: flex;
  gap: 20rpx;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $color-page;

  &:last-child {
    border-bottom: none;
  }
}

.avatar {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 76rpx;
  height: 76rpx;
  border-radius: 50%;
  background: $color-primary;
  color: #fff;
  font-size: 30rpx;
}

.appoint-avatar {
  background: $color-info;
}

.sale-main {
  flex: 1;
  min-width: 0;
}

.sale-line1 {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sale-member {
  font-size: 30rpx;
  font-weight: 500;
  color: $color-text;
}

.sale-amount {
  color: $color-primary;
  font-size: 32rpx;
  font-weight: 600;
}

.sale-line2 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 10rpx;
}

.sale-meta {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.sale-remark {
  display: block;
  margin-top: 8rpx;
  color: $color-text-tertiary;
  font-size: 22rpx;
}

.appoint-status {
  color: $color-success;
  font-size: 24rpx;
}

.nodata-box {
  padding: 60rpx 0;
}
</style>
