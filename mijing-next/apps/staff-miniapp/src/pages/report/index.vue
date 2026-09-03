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

const canViewDashboard = computed(() => session.can("report.dashboard.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const overviewMetrics = computed(() => {
  const kpis = summary.value?.kpis;
  return [
    { label: "今日营业额", value: `¥${kpis?.todayRevenue ?? "0.00"}` },
    { label: "本月售卡", value: String(kpis?.monthCardSalesCount ?? 0) },
    { label: "本月预约", value: String(kpis?.monthAppointmentCount ?? 0) },
    { label: "本月新增会员", value: String(kpis?.monthNewMemberCount ?? 0) },
  ];
});

const profitTrend = computed(() => summary.value?.profitTrend ?? []);
const asOfLabel = computed(() => {
  const value = summary.value?.asOf;
  if (!value) return "";
  return value.replace("T", " ").slice(0, 16);
});
const maxTrendRevenue = computed(() => {
  const values = profitTrend.value.map((item) => Number.parseFloat(item.revenue)).filter((value) => !Number.isNaN(value));
  return values.length ? Math.max(...values) : 0;
});

interface ReportLink {
  key: string;
  label: string;
  desc?: string;
  route: string;
  permission: string;
}

interface ReportGroup {
  title: string;
  links: ReportLink[];
}

// 对标原版报表中心信息架构（经营数据/待办提醒/会员分析/会员卡分析/排行与分析/课程分析/老师/连锁）
const reportGroups: ReportGroup[] = [
  {
    title: "经营数据",
    links: [
      { key: "finance", label: "营收统计", desc: "日/月/年营业额、售卡与新增会员", route: "/subpackages/report/finance/index", permission: "report.finance.read" },
      { key: "card-sales", label: "售卡统计", desc: "售卡汇总与明细", route: "/subpackages/report/card-sales/index", permission: "report.read" },
      { key: "card-analyze", label: "会员卡分析 · 资产负债", desc: "卡状态分层与剩余价值", route: "/subpackages/report/card-analyze/index", permission: "report.read" },
    ],
  },
  {
    title: "待办提醒",
    links: [
      { key: "reminders", label: "会员提醒", desc: "生日/纪念日/未上课/访客", route: "/subpackages/report/reminders/index", permission: "notification.reminder.read" },
      { key: "card-reminders", label: "会员卡提醒", desc: "到期/余额/待开卡/处罚", route: "/subpackages/report/card-reminders/index", permission: "member-card.reminder.read" },
    ],
  },
  {
    title: "会员分析",
    links: [
      { key: "member-analyze", label: "会员分析", desc: "有效/无效/风险/沉寂/流失", route: "/subpackages/report/member-analyze/index", permission: "crm.member.read" },
      { key: "exports", label: "会员导出", desc: "导出会员资料", route: "/subpackages/report/exports/index", permission: "export.member.create" },
    ],
  },
  {
    title: "排行与分析",
    links: [
      { key: "rankings", label: "排行榜", desc: "消费/上课/积分/销售", route: "/subpackages/report/rankings/index", permission: "report.rankings.read" },
      { key: "member-card-ranks", label: "会员卡排行", desc: "耗卡与卡种排行", route: "/subpackages/report/member-card-ranks/index", permission: "report.rankings.read" },
      { key: "change-log", label: "变更记录", desc: "发卡/请假/停卡/删卡日志", route: "/subpackages/report/change-log/index", permission: "report.read" },
    ],
  },
  {
    title: "课程分析",
    links: [
      { key: "courses", label: "课程统计", desc: "团课/私教统计", route: "/subpackages/report/courses/index", permission: "report.course.read" },
      { key: "coaches", label: "教练月报", desc: "教练课时与预约明细", route: "/subpackages/report/coaches/index", permission: "report.coach.read" },
    ],
  },
  {
    title: "耗卡与薪酬",
    links: [
      { key: "consumption", label: "耗卡结算与提成", desc: "A履约人/B分成角色/学员/课程/卡项", route: "/subpackages/report/consumption/index", permission: "consumption.read" },
      { key: "payroll-periods", label: "月结与关账", desc: "创建自然月期间并按后端状态关账", route: "/subpackages/report/payroll-periods/index", permission: "payroll.period.close" },
      { key: "payroll", label: "课时与售卡工资试算", desc: "基础课时/售卡规则；不含 A/B 耗卡提成", route: "/subpackages/report/payroll/index", permission: "payroll.report.read" },
    ],
  },
  {
    title: "连锁",
    links: [
      { key: "chain", label: "连锁汇总", desc: "分店经营对比", route: "/subpackages/report/chain/index", permission: "org.chain.read" },
    ],
  },
];

const canView = computed(() => canViewDashboard.value
  || reportGroups.some((group) => group.links.some((link) => canOpenReportLink(link))));

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  if (!canViewDashboard.value) {
    summary.value = null;
    loading.value = false;
    return;
  }
  loading.value = true;
  summary.value = null;
  errorMessage.value = "";
  try {
    summary.value = await fetchReportDashboardSummary(session.currentSiteId);
  } catch (error) {
    summary.value = null;
    errorMessage.value = error instanceof Error ? error.message : "报表数据加载失败";
  } finally {
    loading.value = false;
  }
}

function trendBarWidth(revenue: string) {
  const value = Number.parseFloat(revenue);
  if (!maxTrendRevenue.value || Number.isNaN(value) || value <= 0) return "0%";
  return `${Math.max(8, Math.round((value / maxTrendRevenue.value) * 100))}%`;
}

function canOpenReportLink(link: ReportLink) {
  if (link.key === "exports") {
    return session.can("export.member.create") || session.can("export.job.read");
  }
  if (link.key === "payroll") {
    return session.can("payroll.report.read") || session.can("payroll.recompute.execute");
  }
  if (link.key === "payroll-periods") {
    return session.can("payroll.period.close");
  }
  return session.can(link.permission);
}

function openReportLink(link: ReportLink) {
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
    <u-empty v-if="!canView" mode="permission" text="暂无报表查看权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card">
        <view>
          <text class="error-title">经营快照暂未更新</text>
          <text class="error-detail">{{ errorMessage }}</text>
        </view>
        <button class="retry-btn" @tap="load">重新加载</button>
      </view>

      <view v-if="canViewDashboard && summary" class="revenue-card">
        <view class="snapshot-head">
          <view>
            <text class="snapshot-eyebrow">经营快照</text>
            <text class="revenue-title">本月营业额（元）</text>
          </view>
          <view class="scope-pill">{{ currentSiteName }}</view>
        </view>
        <text class="revenue-money">{{ summary?.kpis.monthRevenue ?? "0.00" }}</text>
        <text v-if="asOfLabel" class="revenue-site">更新至 {{ asOfLabel }}</text>
        <view class="revenue-metrics">
          <view v-for="item in overviewMetrics" :key="item.label" class="revenue-metric">
            <text class="metric-value">{{ item.value }}</text>
            <text class="metric-label">{{ item.label }}</text>
          </view>
        </view>
      </view>

      <view v-if="canViewDashboard && summary" class="trend-card">
        <view class="section-head">
          <text class="group-title">近 12 月营业额</text>
          <text class="section-note">按实收金额</text>
        </view>
        <view v-for="item in profitTrend" :key="item.label" class="trend-row">
          <text class="trend-label">{{ item.label }}</text>
          <view class="trend-bar-track">
            <view class="trend-bar" :style="{ width: trendBarWidth(item.revenue) }" />
          </view>
          <text class="trend-value">¥{{ item.revenue }}</text>
        </view>
      </view>

      <!-- 分组导航（对标原版报表菜单） -->
      <view v-for="group in reportGroups" :key="group.title" class="group-card">
        <text class="group-title">{{ group.title }}</text>
        <view
          v-for="link in group.links"
          :key="link.key"
          class="group-row"
          :class="{ locked: !canOpenReportLink(link) }"
          @tap="openReportLink(link)"
        >
          <view class="group-row-main">
            <text class="row-label">{{ link.label }}</text>
            <text v-if="link.desc" class="row-desc">{{ link.desc }}</text>
          </view>
          <text v-if="!canOpenReportLink(link)" class="permission-label">无权限</text>
          <u-icon v-else name="arrow-right" size="16" color="#bfbfbf" />
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.revenue-card {
  display: flex;
  flex-direction: column;
  padding: 30rpx 24rpx 32rpx;
  background: $color-surface;
  border-radius: $radius-lg;
}

.snapshot-head,
.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20rpx;
}

.snapshot-eyebrow {
  display: block;
  color: $color-primary;
  font-size: 21rpx;
  font-weight: 600;
  letter-spacing: 3rpx;
}

.scope-pill {
  max-width: 260rpx;
  padding: 8rpx 16rpx;
  overflow: hidden;
  color: $color-text-secondary;
  background: $color-page;
  border-radius: $radius-pill;
  font-size: 21rpx;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.revenue-title {
  display: block;
  margin-top: 5rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.revenue-money {
  margin-top: 16rpx;
  color: $color-primary;
  font-size: 72rpx;
  font-weight: 500;
  line-height: 72rpx;
  text-align: center;
}

.revenue-site {
  margin-top: 12rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
  text-align: center;
}

.revenue-metrics {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rpx;
  width: 100%;
  margin-top: 40rpx;
  overflow: hidden;
  background: $color-divider;
  border-radius: $radius-md;
}

.revenue-metric {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: center;
  padding: 20rpx 10rpx;
  background: $color-surface-grey;
}

.metric-value {
  max-width: 100%;
  overflow: hidden;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.metric-label {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.trend-card,
.group-card {
  margin-top: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border-radius: $radius-lg;
}

.group-title {
  display: block;
  margin-bottom: 8rpx;
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.section-head .group-title {
  margin-bottom: 0;
}

.section-note {
  color: $color-text-disabled;
  font-size: 21rpx;
}

.trend-row {
  display: flex;
  align-items: center;
  gap: 16rpx;
  margin-top: 16rpx;
}

.trend-label {
  flex-shrink: 0;
  width: 96rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.trend-bar-track {
  flex: 1;
  height: 18rpx;
  overflow: hidden;
  background: #f0f1f3;
  border-radius: 9rpx;
}

.trend-bar {
  height: 18rpx;
  border-radius: 9rpx;
  background: linear-gradient(90deg, $color-primary, $color-primary-bright);
}

.trend-value {
  flex-shrink: 0;
  min-width: 120rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
  text-align: right;
}

.group-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $color-page;

  &:last-child {
    border-bottom: none;
    padding-bottom: 4rpx;
  }
}

.group-row.locked {
  opacity: 0.52;
}

.group-row-main {
  display: flex;
  flex-direction: column;
}

.row-label {
  font-size: 28rpx;
  color: $color-text;
}

.row-desc {
  margin-top: 6rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
}

.permission-label {
  flex-shrink: 0;
  padding: 5rpx 12rpx;
  color: $color-text-tertiary;
  background: $color-page;
  border-radius: $radius-pill;
  font-size: 20rpx;
}

.error-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20rpx;
  margin-bottom: $spacing-md;
  padding: 22rpx 24rpx;
  background: #fff5f6;
  border: 1rpx solid #f2c9d1;
  border-radius: $radius-lg;
}

.error-title,
.error-detail {
  display: block;
}

.error-title {
  color: $color-danger;
  font-size: 25rpx;
  font-weight: 600;
}

.error-detail {
  margin-top: 6rpx;
  color: $color-text-secondary;
  font-size: 21rpx;
}

.retry-btn {
  flex-shrink: 0;
  height: 58rpx;
  margin: 0;
  padding: 0 20rpx;
  color: $color-danger;
  background: #fff;
  border-radius: 29rpx;
  font-size: 22rpx;
  line-height: 58rpx;
}

.retry-btn::after {
  border: 0;
}
</style>
