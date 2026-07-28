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
      { key: "finance", label: "财务利润", desc: "日/月/年利润报表", route: "/pages/report/finance/index", permission: "report.finance.read" },
      { key: "card-sales", label: "售卡统计", desc: "售卡汇总与明细", route: "/pages/report/card-sales/index", permission: "report.read" },
      { key: "card-analyze", label: "会员卡分析 · 资产负债", desc: "卡状态分层与剩余价值", route: "/pages/report/card-analyze/index", permission: "report.read" },
    ],
  },
  {
    title: "待办提醒",
    links: [
      { key: "reminders", label: "会员提醒", desc: "生日/纪念日/未上课/访客", route: "/pages/report/reminders/index", permission: "notification.reminder.read" },
      { key: "card-reminders", label: "会员卡提醒", desc: "到期/余额/待开卡/处罚", route: "/pages/report/card-reminders/index", permission: "member-card.reminder.read" },
    ],
  },
  {
    title: "会员分析",
    links: [
      { key: "member-analyze", label: "会员分析", desc: "有效/无效/风险/沉寂/流失", route: "/pages/report/member-analyze/index", permission: "crm.member.read" },
      { key: "exports", label: "会员导出", desc: "导出会员资料", route: "/pages/report/exports/index", permission: "export.member.create" },
    ],
  },
  {
    title: "排行与分析",
    links: [
      { key: "rankings", label: "排行榜", desc: "消费/上课/积分/销售", route: "/pages/report/rankings/index", permission: "report.rankings.read" },
      { key: "member-card-ranks", label: "会员卡排行", desc: "耗卡与卡种排行", route: "/pages/report/member-card-ranks/index", permission: "report.rankings.read" },
      { key: "change-log", label: "变更记录", desc: "发卡/请假/停卡/删卡日志", route: "/pages/report/change-log/index", permission: "report.read" },
    ],
  },
  {
    title: "课程分析",
    links: [
      { key: "courses", label: "课程统计", desc: "团课/私教统计", route: "/pages/report/courses/index", permission: "report.course.read" },
      { key: "coaches", label: "教练月报", desc: "教练课时与预约明细", route: "/pages/report/coaches/index", permission: "report.coach.read" },
    ],
  },
  {
    title: "老师",
    links: [
      { key: "payroll", label: "工资报表", desc: "教练/会籍工资", route: "/pages/report/payroll/index", permission: "payroll.report.read" },
    ],
  },
  {
    title: "连锁",
    links: [
      { key: "chain", label: "连锁汇总", desc: "分店经营对比", route: "/pages/report/chain/index", permission: "org.chain.read" },
    ],
  },
];

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

function canOpenReportLink(link: ReportLink) {
  if (link.key === "exports") {
    return session.can("export.member.create") || session.can("export.job.read");
  }
  if (link.key === "payroll") {
    return session.can("payroll.report.read") || session.can("payroll.recompute.execute");
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
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <!-- 顶部收款大卡（对标原版：橙色大字 + 指标行） -->
      <view class="revenue-card">
        <text class="revenue-title">本月营业额(元)</text>
        <text class="revenue-money">{{ summary?.kpis.monthRevenue ?? "0.00" }}</text>
        <text class="revenue-site">{{ currentSiteName }}</text>
        <view class="revenue-metrics">
          <view v-for="item in overviewMetrics" :key="item.label" class="revenue-metric">
            <text class="metric-value">{{ item.value }}</text>
            <text class="metric-label">{{ item.label }}</text>
          </view>
        </view>
      </view>

      <!-- 近 12 月趋势 -->
      <view class="trend-card">
        <text class="group-title">近 12 月营业额</text>
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
          @tap="openReportLink(link)"
        >
          <view class="group-row-main">
            <text class="row-label">{{ link.label }}</text>
            <text v-if="link.desc" class="row-desc">{{ link.desc }}</text>
          </view>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.revenue-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 48rpx 24rpx 36rpx;
  background: $color-surface;
  border-radius: $radius-lg;
}

.revenue-title {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.revenue-money {
  margin-top: 16rpx;
  color: $color-primary;
  font-size: 72rpx;
  font-weight: 500;
  line-height: 72rpx;
}

.revenue-site {
  margin-top: 12rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
}

.revenue-metrics {
  display: flex;
  width: 100%;
  margin-top: 40rpx;
}

.revenue-metric {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;

  & + & {
    border-left: 1rpx solid $color-divider;
  }
}

.metric-value {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
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
</style>
