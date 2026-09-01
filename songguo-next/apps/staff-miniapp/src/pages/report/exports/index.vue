<script setup lang="ts">
import { computed, onUnmounted, ref } from "vue";
import { onHide, onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { createMemberExport, downloadExportJob, fetchExportJob, listExportJobs } from "@/api/exports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ExportJob, ExportJobStatus } from "@/types/exports";
import { CRM_MEMBER_FILTER_STORAGE_KEY, type CrmStoredMemberFilters } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const exporting = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const jobs = ref<ExportJob[]>([]);
const page = ref(1);
const lastPage = ref(1);
const filterLabel = ref("");
const pollingJobIds = ref<Set<number>>(new Set());
const timedOutJobIds = ref<Set<number>>(new Set());
const manualRefreshingJobIds = ref<Set<number>>(new Set());
const ownedJobIds = ref<Set<number>>(new Set());
const pageActive = ref(true);
const pollGeneration = ref(0);
const listRequestSeq = ref(0);
const loadedSiteId = ref<number | null>(null);

const POLL_INTERVAL_MS = 2000;
const MAX_POLL_ATTEMPTS = 90;
const PENDING_EXPORT_STORAGE_KEY = "staff_pending_export_job_ids_v1";

type StoredPendingExportJobs = Record<string, number[]>;

const canCreate = computed(() => session.can("export.member.create"));
const canReadJobs = computed(() => session.can("export.job.read"));
const canView = computed(() => canCreate.value || canReadJobs.value);
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const statusLabels: Record<ExportJobStatus, string> = {
  pending: "排队中",
  processing: "处理中",
  completed: "已完成",
  failed: "失败",
};

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "导出任务加载失败";
}

function isActiveStatus(status: ExportJobStatus) {
  return status === "pending" || status === "processing";
}

function sleep(ms: number) {
  return new Promise<void>((resolve) => setTimeout(resolve, ms));
}

function scopeKey(tenantId: number, siteId: number) {
  return `${tenantId}:${siteId}`;
}

function readPendingExportStore(): StoredPendingExportJobs {
  try {
    const raw = uni.getStorageSync(PENDING_EXPORT_STORAGE_KEY) as StoredPendingExportJobs | string | null;
    const parsed = typeof raw === "string" ? JSON.parse(raw) as StoredPendingExportJobs : raw;
    return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
  } catch {
    return {};
  }
}

function pendingJobIds(tenantId: number, siteId: number) {
  const ids = readPendingExportStore()[scopeKey(tenantId, siteId)] ?? [];
  return [...new Set(ids.filter((id) => Number.isInteger(id) && id > 0))];
}

function writePendingJobIds(tenantId: number, siteId: number, ids: number[]) {
  const store = readPendingExportStore();
  const key = scopeKey(tenantId, siteId);
  const normalized = [...new Set(ids.filter((id) => Number.isInteger(id) && id > 0))];
  if (normalized.length) store[key] = normalized;
  else delete store[key];
  uni.setStorageSync(PENDING_EXPORT_STORAGE_KEY, store);
}

function persistPendingJob(tenantId: number, siteId: number, jobId: number) {
  writePendingJobIds(tenantId, siteId, [...pendingJobIds(tenantId, siteId), jobId]);
}

function removePendingJob(tenantId: number, siteId: number, jobId: number) {
  writePendingJobIds(tenantId, siteId, pendingJobIds(tenantId, siteId).filter((id) => id !== jobId));
}

function markPollTimedOut(jobId: number) {
  timedOutJobIds.value = new Set([...timedOutJobIds.value, jobId]);
}

function clearPollTimedOut(jobId: number) {
  const next = new Set(timedOutJobIds.value);
  next.delete(jobId);
  timedOutJobIds.value = next;
}

function isCurrentPollContext(tenantId: number, siteId: number, generation: number) {
  return pageActive.value
    && pollGeneration.value === generation
    && session.currentTenantId === tenantId
    && session.currentSiteId === siteId;
}

function mergeJob(updated: ExportJob) {
  const index = jobs.value.findIndex((item) => item.id === updated.id);
  if (index >= 0) {
    jobs.value[index] = updated;
    return;
  }
  jobs.value = [updated, ...jobs.value];
}

function syncOwnedJobPersistence(tenantId: number, siteId: number, job: ExportJob) {
  if (!ownedJobIds.value.has(job.id)) return;
  if (isActiveStatus(job.status)) {
    persistPendingJob(tenantId, siteId, job.id);
    return;
  }
  removePendingJob(tenantId, siteId, job.id);
  const next = new Set(ownedJobIds.value);
  next.delete(job.id);
  ownedJobIds.value = next;
}

async function refreshJob(
  tenantId: number,
  siteId: number,
  jobId: number,
  generation: number,
): Promise<ExportJob | null> {
  const job = await fetchExportJob(siteId, jobId);
  if (!isCurrentPollContext(tenantId, siteId, generation)) return null;
  mergeJob(job);
  syncOwnedJobPersistence(tenantId, siteId, job);
  if (!isActiveStatus(job.status)) clearPollTimedOut(job.id);
  return job;
}

async function pollJobUntilSettled(tenantId: number, siteId: number, jobId: number, generation: number) {
  if (!isCurrentPollContext(tenantId, siteId, generation) || pollingJobIds.value.has(jobId)) return;
  const isCurrentPoll = () => isCurrentPollContext(tenantId, siteId, generation);
  clearPollTimedOut(jobId);
  pollingJobIds.value = new Set([...pollingJobIds.value, jobId]);
  try {
    for (let attempt = 0; attempt < MAX_POLL_ATTEMPTS; attempt += 1) {
      if (!isCurrentPoll()) return;
      await sleep(POLL_INTERVAL_MS);
      if (!isCurrentPoll()) return;
      const job = await refreshJob(tenantId, siteId, jobId, generation);
      if (!isCurrentPoll()) return;
      if (!job || !isActiveStatus(job.status)) {
        if (job?.status === "completed") {
          uni.showToast({ title: "导出已完成", icon: "success" });
        } else if (job?.status === "failed") {
          uni.showToast({ title: "导出失败", icon: "none" });
        }
        return;
      }
    }
    if (isCurrentPoll()) {
      markPollTimedOut(jobId);
      uni.showToast({ title: "状态刷新超时，请手动刷新", icon: "none" });
    }
  } catch {
    if (isCurrentPoll()) {
      markPollTimedOut(jobId);
      uni.showToast({ title: "状态刷新中断，请手动刷新", icon: "none" });
    }
  } finally {
    if (pollGeneration.value !== generation) return;
    const next = new Set(pollingJobIds.value);
    next.delete(jobId);
    pollingJobIds.value = next;
  }
}

function startPollingActiveJobs(tenantId: number, siteId: number, generation: number) {
  for (const job of jobs.value) {
    if (isActiveStatus(job.status)) {
      void pollJobUntilSettled(tenantId, siteId, job.id, generation);
    }
  }
}

function readStoredFilters() {
  const raw = uni.getStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  if (!raw) {
    filterLabel.value = "";
    return {};
  }
  try {
    const parsed = JSON.parse(raw) as CrmStoredMemberFilters;
    if (parsed.cleared) {
      filterLabel.value = "";
      return {};
    }
    filterLabel.value = parsed.label?.trim() || "已保存筛选";
    return parsed.query ?? {};
  } catch {
    filterLabel.value = "";
    return {};
  }
}

function isCurrentListRequest(tenantId: number, siteId: number, requestId: number) {
  return pageActive.value
    && listRequestSeq.value === requestId
    && session.currentTenantId === tenantId
    && session.currentSiteId === siteId;
}

async function loadJobs(tenantId: number, siteId: number, requestedPage: number, requestId: number) {
  const result = await listExportJobs(siteId, requestedPage);
  if (!isCurrentListRequest(tenantId, siteId, requestId)) return false;
  if (requestedPage === 1) {
    jobs.value = result.items;
  } else {
    const existingIds = new Set(jobs.value.map((job) => job.id));
    jobs.value = [...jobs.value, ...result.items.filter((job) => !existingIds.has(job.id))];
  }
  for (const job of result.items) syncOwnedJobPersistence(tenantId, siteId, job);
  page.value = result.pagination.page;
  lastPage.value = result.pagination.lastPage;
  loadedSiteId.value = siteId;
  return true;
}

async function restorePendingJobs(tenantId: number, siteId: number, generation: number) {
  const storedIds = pendingJobIds(tenantId, siteId);
  ownedJobIds.value = new Set(storedIds);
  let recoveryFailed = false;
  await Promise.all(storedIds.map(async (jobId) => {
    const existing = jobs.value.find((job) => job.id === jobId);
    if (existing) {
      syncOwnedJobPersistence(tenantId, siteId, existing);
      return;
    }
    try {
      await refreshJob(tenantId, siteId, jobId, generation);
    } catch (error) {
      if (error instanceof ApiError && (error.statusCode === 403 || error.statusCode === 404)) {
        removePendingJob(tenantId, siteId, jobId);
        const next = new Set(ownedJobIds.value);
        next.delete(jobId);
        ownedJobIds.value = next;
        return;
      }
      recoveryFailed = true;
    }
  }));
  return recoveryFailed;
}

async function load() {
  const tenantId = session.currentTenantId;
  const siteId = session.currentSiteId;
  if (!tenantId || !siteId || !canView.value) {
    listRequestSeq.value += 1;
    pollGeneration.value += 1;
    loading.value = false;
    loadingMore.value = false;
    return;
  }
  const requestId = ++listRequestSeq.value;
  const generation = ++pollGeneration.value;
  pollingJobIds.value = new Set();
  timedOutJobIds.value = new Set();
  manualRefreshingJobIds.value = new Set();
  ownedJobIds.value = new Set(pendingJobIds(tenantId, siteId));
  loadedSiteId.value = null;
  loading.value = true;
  loadingMore.value = false;
  forbidden.value = false;
  errorMessage.value = "";
  readStoredFilters();
  try {
    jobs.value = [];
    if (canReadJobs.value) {
      const applied = await loadJobs(tenantId, siteId, 1, requestId);
      if (!applied) return;
    }
    if (!isCurrentListRequest(tenantId, siteId, requestId) || pollGeneration.value !== generation) return;
    const recoveryFailed = await restorePendingJobs(tenantId, siteId, generation);
    if (!isCurrentListRequest(tenantId, siteId, requestId) || pollGeneration.value !== generation) return;
    loadedSiteId.value = siteId;
    if (recoveryFailed && !canReadJobs.value) {
      errorMessage.value = "未能恢复未完成的导出任务，请下拉刷新重试";
    }
    startPollingActiveJobs(tenantId, siteId, generation);
  } catch (error) {
    if (!isCurrentListRequest(tenantId, siteId, requestId)) return;
    jobs.value = [];
    resolveError(error);
  } finally {
    if (isCurrentListRequest(tenantId, siteId, requestId)) loading.value = false;
  }
}

async function triggerExport() {
  const tenantId = session.currentTenantId;
  const siteId = session.currentSiteId;
  if (!tenantId || !siteId || !canCreate.value || exporting.value) return;
  exporting.value = true;
  errorMessage.value = "";
  try {
    const filters = readStoredFilters();
    const job = await createMemberExport(siteId, filters);
    if (isActiveStatus(job.status)) persistPendingJob(tenantId, siteId, job.id);
    else removePendingJob(tenantId, siteId, job.id);
    uni.showToast({ title: "导出任务已创建", icon: "success" });
    if (!pageActive.value || session.currentTenantId !== tenantId || session.currentSiteId !== siteId) return;
    if (isActiveStatus(job.status)) ownedJobIds.value = new Set([...ownedJobIds.value, job.id]);
    mergeJob(job);
    if (isActiveStatus(job.status)) {
      void pollJobUntilSettled(tenantId, siteId, job.id, pollGeneration.value);
    }
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 403) {
      uni.showToast({ title: "暂无导出权限", icon: "none" });
      return;
    }
    uni.showToast({ title: error instanceof Error ? error.message : "导出失败", icon: "none" });
  } finally {
    exporting.value = false;
  }
}

function isPollTimedOut(jobId: number) {
  return timedOutJobIds.value.has(jobId);
}

function isManuallyRefreshing(jobId: number) {
  return manualRefreshingJobIds.value.has(jobId);
}

async function manualRefreshJob(jobId: number) {
  const tenantId = session.currentTenantId;
  const siteId = session.currentSiteId;
  const generation = pollGeneration.value;
  if (!tenantId || !siteId || isManuallyRefreshing(jobId)) return;
  manualRefreshingJobIds.value = new Set([...manualRefreshingJobIds.value, jobId]);
  try {
    const job = await refreshJob(tenantId, siteId, jobId, generation);
    if (!job) return;
    if (isActiveStatus(job.status)) {
      markPollTimedOut(job.id);
      uni.showToast({ title: "任务仍在处理中", icon: "none" });
    } else if (job.status === "completed") {
      uni.showToast({ title: "导出已完成", icon: "success" });
    } else {
      uni.showToast({ title: "导出失败", icon: "none" });
    }
  } catch (error) {
    if (isCurrentPollContext(tenantId, siteId, generation)) {
      uni.showToast({ title: error instanceof Error ? error.message : "刷新状态失败", icon: "none" });
    }
  } finally {
    if (isCurrentPollContext(tenantId, siteId, generation)) {
      const next = new Set(manualRefreshingJobIds.value);
      next.delete(jobId);
      manualRefreshingJobIds.value = next;
    }
  }
}

async function downloadJob(job: ExportJob) {
  if (!session.currentSiteId || !job.downloadAvailable) return;
  try {
    const tempFilePath = await downloadExportJob(session.currentSiteId, job.id);
    await uni.openDocument({ filePath: tempFilePath, showMenu: true });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "下载失败", icon: "none" });
  }
}

function formatTime(value: string | null) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")} ${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
}

function filterSummary(job: ExportJob) {
  const entries = Object.entries(job.filters ?? {});
  if (!entries.length) return "全部会员";
  const labels: Record<string, string> = {
    status: "状态",
    q: "搜索",
    includeVisitors: "含访客",
    tagIds: "标签",
    flag: "分组",
    sumMode: "统计范围",
    pinyinInitial: "首字母",
    runOff: "流失预警",
    columns: "字段",
  };
  const valueText = (value: unknown) => {
    if (Array.isArray(value)) return value.join("、");
    if (typeof value === "boolean") return value ? "是" : "否";
    return String(value);
  };
  return entries.slice(0, 3).map(([key, value]) => `${labels[key] || key}：${valueText(value)}`).join(" · ");
}

onShow(async () => {
  pageActive.value = true;
  if (await requireStaffAuth()) await load();
});

onHide(() => {
  pageActive.value = false;
  pollGeneration.value += 1;
  listRequestSeq.value += 1;
  pollingJobIds.value = new Set();
  manualRefreshingJobIds.value = new Set();
  loadingMore.value = false;
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});

onReachBottom(async () => {
  const tenantId = session.currentTenantId;
  const siteId = session.currentSiteId;
  if (
    !tenantId
    || !siteId
    || !canReadJobs.value
    || loading.value
    || loadingMore.value
    || loadedSiteId.value !== siteId
    || page.value >= lastPage.value
  ) return;
  const requestId = ++listRequestSeq.value;
  loadingMore.value = true;
  try {
    const applied = await loadJobs(tenantId, siteId, page.value + 1, requestId);
    if (applied) startPollingActiveJobs(tenantId, siteId, pollGeneration.value);
  } catch (error) {
    if (isCurrentListRequest(tenantId, siteId, requestId)) {
      uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
    }
  } finally {
    if (isCurrentListRequest(tenantId, siteId, requestId)) loadingMore.value = false;
  }
});

onUnmounted(() => {
  pageActive.value = false;
  pollGeneration.value += 1;
  listRequestSeq.value += 1;
  pollingJobIds.value = new Set();
  manualRefreshingJobIds.value = new Set();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">会员导出</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无导出权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card"><u-alert type="error" :description="errorMessage" /><button class="retry-btn" @tap="load">重新加载</button></view>

      <view v-if="canCreate" class="action-card">
        <text class="action-title">按当前 CRM 筛选导出</text>
        <text class="action-desc">
          {{ filterLabel ? `筛选：${filterLabel}` : "未检测到保存的筛选，将导出全部会员" }}
        </text>
        <u-button type="primary" :loading="exporting" :disabled="exporting" @click="triggerExport">
          创建导出任务
        </u-button>
      </view>

      <view v-if="canReadJobs || jobs.length" class="section-title">导出记录</view>
      <view v-if="jobs.length">
        <view v-for="job in jobs" :key="job.id" class="job-card">
          <view class="job-row">
            <text class="job-title">任务 #{{ job.id }}</text>
            <view class="job-status">
              <u-loading-icon v-if="isActiveStatus(job.status)" mode="circle" size="14" />
              <u-tag :text="statusLabels[job.status]" size="mini" :type="job.status === 'completed' ? 'success' : job.status === 'failed' ? 'error' : 'info'" />
            </view>
          </view>
          <text class="job-meta">创建：{{ formatTime(job.createdAt) }}</text>
          <text class="job-meta">筛选：{{ filterSummary(job) }}</text>
          <text v-if="job.requestedByStaffName" class="job-meta">操作人：{{ job.requestedByStaffName }}</text>
          <view v-if="isActiveStatus(job.status) && isPollTimedOut(job.id)" class="poll-timeout">
            <text class="poll-timeout-text">自动刷新已超时，任务仍可能在后台继续处理。</text>
            <u-button
              size="small"
              plain
              :loading="isManuallyRefreshing(job.id)"
              :disabled="isManuallyRefreshing(job.id)"
              @click="manualRefreshJob(job.id)"
            >
              手动刷新状态
            </u-button>
          </view>
          <u-button
            v-if="job.status === 'completed' && job.downloadAvailable"
            size="small"
            type="primary"
            plain
            class="download-btn"
            @click="downloadJob(job)"
          >
            下载 CSV
          </u-button>
        </view>
        <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" />
      </view>
      <u-empty v-else-if="canReadJobs" mode="list" text="暂无导出记录" />
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
.action-title,
.action-desc,
.job-title,
.job-meta {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.job-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.action-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.action-title {
  font-size: 30rpx;
  font-weight: 600;
}

.action-desc {
  margin: $spacing-sm 0 $spacing-md;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.job-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.job-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.job-status {
  display: flex;
  align-items: center;
  gap: $spacing-xs;
}

.job-title {
  font-size: 28rpx;
  font-weight: 600;
}

.download-btn {
  margin-top: $spacing-sm;
}

.poll-timeout {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
  margin-top: $spacing-sm;
  padding: $spacing-sm;
  border-radius: $radius-sm;
  background: #fff8e8;
}

.poll-timeout-text {
  flex: 1;
  color: #9a6810;
  font-size: 22rpx;
  line-height: 1.5;
}

.error-card { margin-top: $spacing-sm; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
