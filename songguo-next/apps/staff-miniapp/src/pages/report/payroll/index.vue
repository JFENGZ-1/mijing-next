<script setup lang="ts">
import { computed, onUnmounted, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import {
  createRecomputeJob,
  fetchPayrollCoachReports,
  fetchPayrollSalesReports,
  listRecomputeJobs,
} from "@/api/payroll";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  PayrollCoachReportList,
  PayrollRecomputeJob,
  PayrollRecomputeJobStatus,
  PayrollRecomputeScope,
  PayrollSalesReportList,
} from "@/types/payroll";
import { createCommandKey } from "@/utils/command-key";

type PayrollTab = "coach" | "sales";

const session = useSessionStore();
const loading = ref(true);
const recomputing = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const activeTab = ref<PayrollTab>("coach");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const coachReports = ref<PayrollCoachReportList | null>(null);
const salesReports = ref<PayrollSalesReportList | null>(null);
const recomputeJobs = ref<PayrollRecomputeJob[]>([]);
const recomputeCommandKey = ref("");
const pollingJobIds = ref<Set<number>>(new Set());

const POLL_INTERVAL_MS = 2000;
const MAX_POLL_ATTEMPTS = 90;

const canViewReports = computed(() => session.can("payroll.report.read"));
const canRecompute = computed(() => session.can("payroll.recompute.execute"));
const canViewConfig = computed(() => session.can("payroll.config.read"));
const canView = computed(() => canViewReports.value || canRecompute.value);
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const monthOptions = Array.from({ length: 12 }, (_, index) => index + 1);
const yearOptions = computed(() => {
  const current = new Date().getFullYear();
  return [current - 1, current, current + 1];
});

const tabs = [
  { key: "coach" as const, label: "教练工资" },
  { key: "sales" as const, label: "销售提成" },
];

const statusLabels: Record<PayrollRecomputeJobStatus, string> = {
  pending: "排队中",
  processing: "处理中",
  completed: "已完成",
  failed: "失败",
};

const scopeLabels: Record<PayrollRecomputeScope, string> = {
  site: "全馆",
  coach: "教练",
  sales: "销售",
};

const activeItems = computed(() =>
  activeTab.value === "coach" ? coachReports.value?.items ?? [] : salesReports.value?.items ?? [],
);

const totalsLabel = computed(() => {
  if (activeTab.value === "coach" && coachReports.value) {
    return `教练 ${coachReports.value.totals.staffCount} · 合计 ¥${formatCents(coachReports.value.totals.totalPayCents)}`;
  }
  if (activeTab.value === "sales" && salesReports.value) {
    return `销售 ${salesReports.value.totals.staffCount} · 合计 ¥${formatCents(salesReports.value.totals.totalPayCents)}`;
  }
  return "";
});

function formatCents(cents: number) {
  return (cents / 100).toFixed(2);
}

function staffLabel(name: string | null, staffId: number) {
  return name?.trim() || `员工 #${staffId}`;
}

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "工资报表加载失败";
}

function isActiveStatus(status: PayrollRecomputeJobStatus) {
  return status === "pending" || status === "processing";
}

function sleep(ms: number) {
  return new Promise<void>((resolve) => setTimeout(resolve, ms));
}

function mergeJob(updated: PayrollRecomputeJob) {
  const index = recomputeJobs.value.findIndex((item) => item.id === updated.id);
  if (index >= 0) {
    recomputeJobs.value[index] = updated;
    return;
  }
  recomputeJobs.value = [updated, ...recomputeJobs.value];
}

async function refreshJob(jobId: number): Promise<PayrollRecomputeJob | null> {
  if (!session.currentSiteId || !canRecompute.value) return null;
  const result = await listRecomputeJobs(session.currentSiteId, 1, 10);
  const found = result.items.find((item) => item.id === jobId) ?? null;
  if (found) mergeJob(found);
  return found;
}

async function pollJobUntilSettled(jobId: number) {
  if (pollingJobIds.value.has(jobId)) return;
  pollingJobIds.value = new Set([...pollingJobIds.value, jobId]);
  try {
    for (let attempt = 0; attempt < MAX_POLL_ATTEMPTS; attempt += 1) {
      await sleep(POLL_INTERVAL_MS);
      const job = await refreshJob(jobId);
      if (!job || !isActiveStatus(job.status)) {
        if (job?.status === "completed") {
          uni.showToast({ title: "重算已完成", icon: "success" });
          if (canViewReports.value) await loadReports();
        } else if (job?.status === "failed") {
          uni.showToast({ title: "重算失败", icon: "none" });
        }
        return;
      }
    }
  } catch {
    // Polling errors are non-fatal; pull-to-refresh can recover.
  } finally {
    const next = new Set(pollingJobIds.value);
    next.delete(jobId);
    pollingJobIds.value = next;
  }
}

function startPollingActiveJobs() {
  for (const job of recomputeJobs.value) {
    if (isActiveStatus(job.status)) {
      void pollJobUntilSettled(job.id);
    }
  }
}

async function loadReports() {
  if (!session.currentSiteId || !canViewReports.value) return;
  const [coach, sales] = await Promise.all([
    fetchPayrollCoachReports(session.currentSiteId, selectedYear.value, selectedMonth.value),
    fetchPayrollSalesReports(session.currentSiteId, selectedYear.value, selectedMonth.value),
  ]);
  coachReports.value = coach;
  salesReports.value = sales;
}

async function loadJobs() {
  if (!session.currentSiteId || !canRecompute.value) return;
  const result = await listRecomputeJobs(session.currentSiteId, 1, 10);
  recomputeJobs.value = result.items;
}

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    await Promise.all([loadReports(), loadJobs()]);
    startPollingActiveJobs();
  } catch (error) {
    coachReports.value = null;
    salesReports.value = null;
    recomputeJobs.value = [];
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

async function selectYear(year: number) {
  if (selectedYear.value === year) return;
  selectedYear.value = year;
  await load();
}

async function selectMonth(month: number) {
  if (selectedMonth.value === month) return;
  selectedMonth.value = month;
  await load();
}

async function selectTab(tab: PayrollTab) {
  if (activeTab.value === tab) return;
  activeTab.value = tab;
}

async function triggerRecompute() {
  if (!session.currentSiteId || !canRecompute.value || recomputing.value) return;

  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "重算工资",
      content: `确认重算 ${selectedYear.value} 年 ${selectedMonth.value} 月全馆工资？`,
      success: (result) => resolve(!!result.confirm),
    });
  });
  if (!confirmed) return;

  recomputing.value = true;
  recomputeCommandKey.value = createCommandKey();
  try {
    const job = await createRecomputeJob(session.currentSiteId, {
      year: selectedYear.value,
      month: selectedMonth.value,
      scope: "site",
      commandKey: recomputeCommandKey.value,
    });
    uni.showToast({ title: "重算任务已提交", icon: "success" });
    mergeJob(job);
    if (isActiveStatus(job.status)) {
      void pollJobUntilSettled(job.id);
    }
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 403) {
      uni.showToast({ title: "暂无重算权限", icon: "none" });
      return;
    }
    uni.showToast({ title: error instanceof Error ? error.message : "重算失败", icon: "none" });
  } finally {
    recomputing.value = false;
  }
}

function openConfig() {
  if (!canViewConfig.value) {
    uni.showToast({ title: "暂无工资配置权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/pages/report/payroll/config/index" });
}

function formatTime(value: string | null) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")} ${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});

onUnmounted(() => {
  pollingJobIds.value = new Set();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">工资报表</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
      <text v-if="canViewConfig" class="link-action" @click="openConfig">工资配置</text>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无工资报表权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="section-title">月份</view>
      <scroll-view scroll-x class="chip-scroll">
        <view class="chip-row">
          <view
            v-for="year in yearOptions"
            :key="year"
            class="chip"
            :class="{ active: selectedYear === year }"
            @click="selectYear(year)"
          >
            {{ year }} 年
          </view>
        </view>
      </scroll-view>
      <scroll-view scroll-x class="chip-scroll">
        <view class="chip-row">
          <view
            v-for="month in monthOptions"
            :key="month"
            class="chip"
            :class="{ active: selectedMonth === month }"
            @click="selectMonth(month)"
          >
            {{ month }} 月
          </view>
        </view>
      </scroll-view>

      <view v-if="canRecompute" class="recompute-row">
        <u-button type="warning" size="small" :loading="recomputing" :disabled="recomputing" @click="triggerRecompute">
          重算本月工资
        </u-button>
      </view>

      <view v-if="canViewReports">
        <view class="section-title">报表类型</view>
        <view class="chip-row">
          <view
            v-for="tab in tabs"
            :key="tab.key"
            class="chip"
            :class="{ active: activeTab === tab.key }"
            @click="selectTab(tab.key)"
          >
            {{ tab.label }}
          </view>
        </view>

        <text v-if="totalsLabel" class="totals-label">{{ totalsLabel }}</text>

        <view v-if="activeItems.length">
          <view v-for="item in activeItems" :key="item.staffId" class="report-card">
            <view class="report-row">
              <text class="report-name">{{ staffLabel(item.staffName, item.staffId) }}</text>
              <text class="report-amount">
                ¥{{
                  formatCents(
                    activeTab === "coach"
                      ? (item as PayrollCoachReportList['items'][number]).totalPayCents
                      : (item as PayrollSalesReportList['items'][number]).commissionCents,
                  )
                }}
              </text>
            </view>
            <text v-if="activeTab === 'coach'" class="report-meta">
              团课 {{ (item as PayrollCoachReportList['items'][number]).groupSessionCount }}
              · 私教 {{ (item as PayrollCoachReportList['items'][number]).privateSessionCount }}
            </text>
            <text v-else class="report-meta">
              售卡 {{ (item as PayrollSalesReportList['items'][number]).cardSalesCount }}
              · 营业额 ¥{{ formatCents((item as PayrollSalesReportList['items'][number]).revenueCents) }}
            </text>
          </view>
        </view>
        <u-empty v-else mode="list" text="暂无工资数据" />
      </view>

      <view v-if="canRecompute && recomputeJobs.length" class="section-title">重算记录</view>
      <view v-if="canRecompute && recomputeJobs.length">
        <view v-for="job in recomputeJobs" :key="job.id" class="job-card">
          <view class="report-row">
            <text class="report-name">#{{ job.id }} · {{ scopeLabels[job.scope] }}</text>
            <view class="job-status">
              <u-loading-icon v-if="isActiveStatus(job.status)" mode="circle" size="14" />
              <u-tag :text="statusLabels[job.status]" size="mini" :type="job.status === 'completed' ? 'success' : job.status === 'failed' ? 'error' : 'info'" />
            </view>
          </view>
          <text class="report-meta">{{ job.year }} 年 {{ job.month }} 月 · {{ formatTime(job.createdAt) }}</text>
          <text v-if="job.errorMessage" class="report-meta error-text">{{ job.errorMessage }}</text>
        </view>
      </view>
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
.totals-label,
.report-name,
.report-amount,
.report-meta,
.link-action {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.report-meta,
.totals-label {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.link-action {
  color: #1a73e8;
  font-size: 26rpx;
}

.chip-scroll {
  margin-top: $spacing-sm;
}

.chip-row {
  display: inline-flex;
  gap: $spacing-sm;
  padding-bottom: $spacing-xs;
}

.chip {
  padding: $spacing-xs $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  font-size: 24rpx;
  white-space: nowrap;
}

.chip.active {
  border-color: #1a73e8;
  color: #1a73e8;
  background: #eef4ff;
}

.recompute-row {
  margin-top: $spacing-md;
}

.report-card,
.job-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.report-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.job-status {
  display: flex;
  align-items: center;
  gap: $spacing-xs;
}

.report-name {
  font-size: 28rpx;
  font-weight: 600;
}

.report-amount {
  font-size: 28rpx;
  font-weight: 600;
  color: #1a73e8;
}

.error-text {
  color: #d93025;
}
</style>
