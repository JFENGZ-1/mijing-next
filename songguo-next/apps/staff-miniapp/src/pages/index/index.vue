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
const checking = ref(true);
const loading = ref(false);
const errorMessage = ref("");
const summary = ref<StaffDashboardSummary | null>(null);
const salesFeed = ref<StaffDashboardSalesFeedItem[]>([]);
const appointmentFeed = ref<StaffDashboardAppointmentFeedItem[]>([]);
const hideRevenue = ref(uni.getStorageSync("hide_dashboard_revenue") === true);

const actions = ["排课", "会员", "发卡", "签到", "员工", "场馆", "报表"];
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "尚未选择场馆");
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
  return `¥${summary.value?.kpis.todayRevenue ?? "0.00"}`;
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

function openPersonalSettings() {
  uni.navigateTo({ url: "/pages/settings/personal/index" });
}

function openSettingsHub() {
  if (!session.can("tenant.settings.read")) {
    uni.showToast({ title: "暂无场馆设置权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/pages/settings/hub/index" });
}

function openSiteSettings() {
  if (!session.can("site.profile.read")) {
    uni.showToast({ title: "暂无场馆资料权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/pages/settings/site/index" });
}

function handleAction(action: string) {
  if (action === "会员") {
    uni.navigateTo({ url: "/pages/members/index" });
    return;
  }
  if (action === "场馆") {
    if (session.can("tenant.settings.read")) {
      openSettingsHub();
      return;
    }
    if (session.can("site.profile.read")) {
      openSiteSettings();
      return;
    }
    uni.navigateTo({ url: "/pages/sites/index" });
    return;
  }
  if (action === "员工") {
    if (!session.can("staff.directory.read")) {
      uni.showToast({ title: "暂无员工目录权限", icon: "none" });
      return;
    }
    uni.navigateTo({ url: "/pages/settings/staff/index" });
    return;
  }
  if (action === "报表") {
    if (!session.can("report.dashboard.read")) {
      uni.showToast({ title: "暂无报表权限", icon: "none" });
      return;
    }
    uni.navigateTo({ url: "/pages/report/index" });
    return;
  }
  if (action === "排课") {
    if (!session.can("booking.staff-daily-board.read") && !session.can("schedule.session.read")) {
      uni.showToast({ title: "暂无课程日程权限", icon: "none" });
      return;
    }
    uni.switchTab({ url: "/pages/course/index" });
    return;
  }
  if (action === "发卡") {
    if (!session.can("member-card.issue")) {
      uni.showToast({ title: "暂无发卡权限", icon: "none" });
      return;
    }
    if (!session.can("crm.member.read")) {
      uni.showToast({ title: "暂无会员查看权限", icon: "none" });
      return;
    }
    uni.navigateTo({ url: "/pages/members/index" });
    return;
  }
  if (action === "签到") {
    if (!session.can("booking.fulfillment.check-in")) {
      uni.showToast({ title: "暂无签到权限", icon: "none" });
      return;
    }
    uni.navigateTo({ url: "/pages/check-in/scan/index" });
    return;
  }
  uni.showToast({ title: `${action}暂未开放`, icon: "none" });
}
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">{{ summary?.greeting.headline || "今日工作台" }}</text>
        <text class="subtitle">{{ summary?.greeting.hint || currentSiteName }}</text>
        <text v-if="summary?.greeting.hint" class="site-name" @click="openSiteSettings">{{ currentSiteName }}</text>
      </view>
      <u-icon name="setting" size="24" color="#202124" @click="openPersonalSettings" />
    </view>

    <view v-if="errorMessage" class="error-text">{{ errorMessage }}</view>

    <view v-if="canViewSummary" class="revenue-card">
      <text class="revenue-value" @click="toggleRevenueVisibility">{{ revenueDisplay }}</text>
      <text class="revenue-label">今日营业额</text>
    </view>

    <view v-if="canViewSummary" class="metric-grid">
      <view v-for="metric in metrics" :key="metric.label" class="metric-cell">
        <text class="metric-value">{{ metric.value }}</text>
        <text class="metric-label">{{ metric.label }}</text>
      </view>
    </view>

    <view class="section-title">快捷操作</view>
    <view class="action-grid">
      <button v-for="action in actions" :key="action" class="action-button" @click="handleAction(action)">{{ action }}</button>
    </view>

    <view v-if="canViewSalesFeed" class="section-title">今日售卡</view>
    <view v-if="canViewSalesFeed && salesFeed.length" class="sales-list">
      <view v-for="item in salesFeed" :key="item.id" class="sales-item">
        <view class="sales-row">
          <view>
            <text class="sales-member">{{ item.memberName || "会员" }}</text>
            <text class="sales-meta">{{ formatSoldTime(item.soldAt) }} · {{ item.cardName || "会员卡" }}</text>
          </view>
          <view class="sales-amount-wrap">
            <text class="sales-amount">¥{{ item.amount }}</text>
            <u-tag v-if="item.isNewMember" text="新会员" size="mini" type="success" />
          </view>
        </view>
        <text v-if="item.remark" class="sales-remark">备注：{{ item.remark }}</text>
      </view>
    </view>
    <u-empty v-else-if="canViewSalesFeed" mode="list" text="今天还没有售卡记录" />

    <view v-if="canViewAppointmentFeed" class="section-title">今日预约</view>
    <view v-if="canViewAppointmentFeed && appointmentFeed.length" class="appoint-list">
      <view v-for="item in appointmentFeed" :key="item.id" class="appoint-item">
        <view class="appoint-row">
          <view>
            <text class="appoint-member">{{ item.memberName || "会员" }}</text>
            <text class="appoint-meta">
              {{ formatSessionTime(item.startsAt, item.endsAt) }}
              · {{ item.courseName || (item.sessionKind === "private" ? "私教" : "课程") }}
              <template v-if="item.coachName"> · {{ item.coachName }}</template>
            </text>
          </view>
          <u-tag :text="appointmentStatusLabel(item.status)" size="mini" type="success" />
        </view>
      </view>
    </view>
    <u-empty v-else-if="canViewAppointmentFeed" mode="list" text="今天还没有预约记录" />

    <view v-if="!canViewSummary && !canViewSalesFeed && !canViewAppointmentFeed" class="section-title">待处理</view>
    <u-empty v-if="!canViewSummary && !canViewSalesFeed && !canViewAppointmentFeed" mode="permission" text="当前账号暂无首页数据权限" />
  </view>
</template>

<style scoped lang="scss">
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title,
.subtitle,
.site-name,
.metric-value,
.metric-label,
.revenue-value,
.revenue-label,
.sales-member,
.sales-meta,
.sales-remark {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.metric-label,
.site-name,
.sales-meta,
.sales-remark {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.site-name {
  margin-top: 4rpx;
}

.error-text {
  margin-top: $spacing-sm;
  color: #d93025;
  font-size: 24rpx;
}

.revenue-card {
  margin-top: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.revenue-value {
  font-size: 44rpx;
  font-weight: 600;
}

.revenue-label {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: $spacing-md;
  overflow: hidden;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.metric-cell {
  min-height: 128rpx;
  box-sizing: border-box;
  padding: $spacing-md;
  border-right: 1rpx solid $color-border;
  border-bottom: 1rpx solid $color-border;
}

.metric-value {
  font-size: 36rpx;
  font-weight: 600;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: $spacing-sm;
}

.action-button {
  width: 100%;
  height: 88rpx;
  padding: 0;
  color: $color-text;
  font-size: 28rpx;
  line-height: 88rpx;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.action-button::after {
  border: 0;
}

.sales-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
}

.sales-item {
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.sales-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: $spacing-sm;
}

.sales-member {
  font-size: 30rpx;
  font-weight: 600;
}

.sales-amount-wrap {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8rpx;
}

.sales-amount {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.appoint-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
}

.appoint-item {
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.appoint-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: $spacing-sm;
}

.appoint-member {
  display: block;
  font-size: 30rpx;
  font-weight: 600;
}

.appoint-meta {
  display: block;
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}
</style>
