<script setup lang="ts">
import { computed, onUnmounted, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { createMemberExport, downloadExportJob, listExportJobs } from "@/api/exports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ExportJob, ExportJobStatus } from "@/types/exports";
import { CRM_MEMBER_FILTER_STORAGE_KEY, type CrmStoredMemberFilters } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const exporting = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const jobs = ref<ExportJob[]>([]);
const page = ref(1);
const lastPage = ref(1);
const filterLabel = ref("");
const pollingJobIds = ref<Set<number>>(new Set());

const POLL_INTERVAL_MS = 2000;
const MAX_POLL_ATTEMPTS = 90;

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

function mergeJob(updated: ExportJob) {
  const index = jobs.value.findIndex((item) => item.id === updated.id);
  if (index >= 0) {
    jobs.value[index] = updated;
    return;
  }
  jobs.value = [updated, ...jobs.value];
}

async function refreshJob(jobId: number): Promise<ExportJob | null> {
  if (!session.currentSiteId || !canReadJobs.value) return null;
  const result = await listExportJobs(session.currentSiteId, 1);
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
          uni.showToast({ title: "导出已完成", icon: "success" });
        } else if (job?.status === "failed") {
          uni.showToast({ title: "导出失败", icon: "none" });
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
  for (const job of jobs.value) {
    if (isActiveStatus(job.status)) {
      void pollJobUntilSettled(job.id);
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
    filterLabel.value = parsed.label?.trim() || "已保存筛选";
    return parsed.query ?? {};
  } catch {
    filterLabel.value = "";
    return {};
  }
}

async function loadJobs(requestedPage = 1) {
  if (!session.currentSiteId || !canReadJobs.value) return;
  const result = await listExportJobs(session.currentSiteId, requestedPage);
  jobs.value = requestedPage === 1 ? result.items : [...jobs.value, ...result.items];
  page.value = result.pagination.page;
  lastPage.value = result.pagination.lastPage;
}

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  readStoredFilters();
  try {
    if (canReadJobs.value) {
      jobs.value = [];
      await loadJobs(1);
      startPollingActiveJobs();
    }
  } catch (error) {
    jobs.value = [];
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

async function triggerExport() {
  if (!session.currentSiteId || !canCreate.value || exporting.value) return;
  exporting.value = true;
  errorMessage.value = "";
  try {
    const filters = readStoredFilters();
    const job = await createMemberExport(session.currentSiteId, filters);
    uni.showToast({ title: "导出任务已创建", icon: "success" });
    if (canReadJobs.value) {
      mergeJob(job);
      if (isActiveStatus(job.status)) {
        void pollJobUntilSettled(job.id);
      }
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
  const keys = Object.keys(job.filters ?? {});
  if (!keys.length) return "全部会员";
  return keys.slice(0, 3).map((key) => `${key}`).join(" · ");
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
        <text class="title">会员导出</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无导出权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view v-if="canCreate" class="action-card">
        <text class="action-title">按当前 CRM 筛选导出</text>
        <text class="action-desc">
          {{ filterLabel ? `筛选：${filterLabel}` : "未检测到保存的筛选，将导出全部会员" }}
        </text>
        <u-button type="primary" :loading="exporting" :disabled="exporting" @click="triggerExport">
          创建导出任务
        </u-button>
      </view>

      <view v-if="canReadJobs" class="section-title">导出记录</view>
      <view v-if="canReadJobs && jobs.length">
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
</style>
