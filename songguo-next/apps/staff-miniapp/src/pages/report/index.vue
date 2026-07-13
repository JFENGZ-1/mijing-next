<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchReportDashboardSummary } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportDashboardSummary } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const summary = ref<ReportDashboardSummary | null>(null);

const canView = computed(() => session.can("report.dashboard.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const kpiCards = computed(() => {
  const kpis = summary.value?.kpis;
  return [
    { label: "今日营业额", value: `¥${kpis?.todayRevenue ?? "0.00"}` },
    { label: "本月营业额", value: `¥${kpis?.monthRevenue ?? "0.00"}` },
    { label: "今日售卡", value: String(kpis?.todayCardSalesCount ?? 0) },
    { label: "本月售卡", value: String(kpis?.monthCardSalesCount ?? 0) },
    { label: "今日预约", value: String(kpis?.todayAppointmentCount ?? 0) },
    { label: "本月预约", value: String(kpis?.monthAppointmentCount ?? 0) },
    { label: "会员总数", value: String(kpis?.totalMemberCount ?? 0) },
    { label: "本月新增", value: String(kpis?.monthNewMemberCount ?? 0) },
  ];
});

const profitTrend = computed(() => summary.value?.profitTrend ?? []);
const maxTrendRevenue = computed(() => {
  const values = profitTrend.value.map((item) => Number.parseFloat(item.revenue)).filter((value) => !Number.isNaN(value));
  return values.length ? Math.max(...values) : 0;
});

const reportLinks = [
  { key: "finance", label: "财务利润", route: "/pages/report/finance/index", permission: "report.finance.read" },
  { key: "courses", label: "课程统计", route: "/pages/report/courses/index", permission: "report.course.read" },
  { key: "rankings", label: "排行榜", route: "/pages/report/rankings/index", permission: "report.rankings.read" },
  { key: "member-card-ranks", label: "会员卡排行", route: "/pages/report/member-card-ranks/index", permission: "report.rankings.read" },
  { key: "card-sales", label: "售卡统计", route: "/pages/report/card-sales/index", permission: "report.read" },
  { key: "coaches", label: "教练月报", route: "/pages/report/coaches/index", permission: "report.coach.read" },
  { key: "reminders", label: "会员提醒", route: "/pages/report/reminders/index", permission: "notification.reminder.read" },
  { key: "card-reminders", label: "会员卡提醒", route: "/pages/report/card-reminders/index", permission: "member-card.reminder.read" },
  { key: "chain", label: "连锁汇总", route: "/pages/report/chain/index", permission: "org.chain.read" },
  { key: "exports", label: "会员导出", route: "/pages/report/exports/index", permission: "export.member.create" },
  { key: "payroll", label: "工资报表", route: "/pages/report/payroll/index", permission: "payroll.report.read" },
] as const;

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    summary.value = await fetchReportDashboardSummary(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "报表数据加载失败";
  } finally {
    loading.value = false;
  }
}

function trendBarWidth(revenue: string) {
  const value = Number.parseFloat(revenue);
  if (!maxTrendRevenue.value || Number.isNaN(value) || value <= 0) return "8%";
  return `${Math.max(8, Math.round((value / maxTrendRevenue.value) * 100))}%`;
}

function canOpenReportLink(link: (typeof reportLinks)[number]) {
  if (link.key === "exports") {
    return session.can("export.member.create") || session.can("export.job.read");
  }
  if (link.key === "payroll") {
    return session.can("payroll.report.read") || session.can("payroll.recompute.execute");
  }
  return session.can(link.permission);
}

function openReportLink(link: (typeof reportLinks)[number]) {
  if (!canOpenReportLink(link)) {
    uni.showToast({ title: "暂无权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: link.route });
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
    <view class="header-row">
      <view>
        <text class="title">报表中心</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="!canView" mode="permission" text="暂无报表查看权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="section-title">本月 KPI</view>
      <view class="metric-grid">
        <view v-for="card in kpiCards" :key="card.label" class="metric-cell">
          <text class="metric-value">{{ card.value }}</text>
          <text class="metric-label">{{ card.label }}</text>
        </view>
      </view>

      <view class="section-title">近 12 月营业额</view>
      <view v-if="profitTrend.length" class="trend-card">
        <view v-for="item in profitTrend" :key="`${item.year}-${item.month}`" class="trend-row">
          <text class="trend-label">{{ item.label }}</text>
          <view class="trend-bar-wrap">
            <view class="trend-bar" :style="{ width: trendBarWidth(item.revenue) }" />
          </view>
          <text class="trend-value">¥{{ item.revenue }}</text>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无趋势数据" />

      <view class="section-title">更多报表</view>
      <u-cell-group>
        <u-cell
          v-for="link in reportLinks"
          :key="link.key"
          :title="link.label"
          is-link
          @click="openReportLink(link)"
        />
      </u-cell-group>
    </template>
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
.metric-value,
.metric-label,
.trend-label,
.trend-value {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.metric-label,
.trend-label {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: $spacing-sm;
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
  font-size: 32rpx;
  font-weight: 600;
}

.trend-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.trend-row {
  display: grid;
  grid-template-columns: 72rpx 1fr 120rpx;
  align-items: center;
  gap: $spacing-sm;
  margin-bottom: $spacing-sm;
}

.trend-row:last-child {
  margin-bottom: 0;
}

.trend-bar-wrap {
  height: 16rpx;
  background: #eef1f4;
  border-radius: 999rpx;
  overflow: hidden;
}

.trend-bar {
  height: 100%;
  background: #1a73e8;
  border-radius: 999rpx;
}

.trend-value {
  text-align: right;
  font-size: 22rpx;
  color: $color-text-secondary;
}
</style>
