<script setup lang="ts">
import { Delete, Refresh, RefreshRight } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { onMounted, ref } from "vue";

import { ApiError, apiRequest } from "@/api/client";
import MetricCard from "@/components/MetricCard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";

interface QueueOverview {
  connection: string;
  driver: string;
  totals: { ready: number; reserved: number; delayed: number; failed: number; batchesPending: number };
  queues: Array<{ name: string; ready: number; reserved: number; delayed: number; failed: number; oldestWaitSeconds: number }>;
  generatedAt: string;
}
interface QueueJob { id: number; queue: string; name: string; status: string; attempts: number; createdAt: string; availableAt: string; reservedAt: string | null }
interface FailedJob { id: number; uuid: string; connection: string; queue: string; name: string; exception: string; failedAt: string }
interface PageData<T> { items: T[]; pagination: { total: number } }

const loading = ref(false);
const overview = ref<QueueOverview | null>(null);
const jobs = ref<QueueJob[]>([]);
const failedJobs = ref<FailedJob[]>([]);

async function load() {
  loading.value = true;
  try {
    const [overviewResponse, jobsResponse, failedResponse] = await Promise.all([
      apiRequest<QueueOverview>("/admin/queues/overview"),
      apiRequest<PageData<QueueJob>>("/admin/queues/jobs?perPage=100"),
      apiRequest<PageData<FailedJob>>("/admin/queues/failed?perPage=100"),
    ]);
    overview.value = overviewResponse.data;
    jobs.value = jobsResponse.data.items;
    failedJobs.value = failedResponse.data.items;
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "队列状态加载失败");
  } finally {
    loading.value = false;
  }
}

async function retry(job: FailedJob) {
  await ElMessageBox.confirm(`确认重新投递任务 ${job.name}？任务必须自行保证幂等。`, "重试失败任务", { type: "warning" });
  await apiRequest(`/admin/queues/failed/${job.uuid}/retry`, { method: "POST" });
  ElMessage.success("任务已重新投递");
  await load();
}

async function forget(job: FailedJob) {
  await ElMessageBox.confirm("删除后将无法从失败队列表恢复该记录。确认删除？", "删除失败记录", { type: "error", confirmButtonText: "确认删除" });
  await apiRequest(`/admin/queues/failed/${job.uuid}`, { method: "DELETE" });
  ElMessage.success("失败记录已删除");
  await load();
}

function waitTime(seconds: number) {
  if (seconds < 60) return `${seconds} 秒`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)} 分钟`;
  return `${Math.floor(seconds / 3600)} 小时`;
}

onMounted(load);
</script>

<template>
  <div class="governance-page" v-loading="loading">
    <PageHeading eyebrow="QUEUE OPERATIONS" title="队列管理与监控" description="跨租户查看数据库队列积压和失败任务；业务任务仍由 Staff/Member 接口创建，超管只负责全局运行治理。">
      <el-button :icon="Refresh" @click="load">刷新状态</el-button>
    </PageHeading>

    <section class="scope-notice"><span><i />{{ overview?.connection ?? '—' }} / {{ overview?.driver ?? '—' }}</span><p>失败任务列表不返回原始 payload 或完整异常堆栈，避免泄露业务数据与凭据。</p><b>{{ overview?.generatedAt ? new Date(overview.generatedAt).toLocaleString('zh-CN') : '尚未加载' }}</b></section>

    <section class="metric-grid">
      <MetricCard label="等待执行" :value="String(overview?.totals.ready ?? 0)" trend="实时" note="可立即消费" tone="green" />
      <MetricCard label="执行中" :value="String(overview?.totals.reserved ?? 0)" trend="实时" note="已被 worker 保留" tone="blue" />
      <MetricCard label="延迟任务" :value="String(overview?.totals.delayed ?? 0)" trend="实时" note="等待可用时间" tone="amber" />
      <MetricCard label="失败任务" :value="String(overview?.totals.failed ?? 0)" trend="需处置" note="可重试或删除" tone="dark" />
    </section>

    <section class="panel queue-section">
      <header class="panel-header"><div><span class="eyebrow">QUEUE PRESSURE</span><h3>各队列积压</h3></div></header>
      <el-table :data="overview?.queues ?? []" empty-text="当前没有排队或失败任务">
        <el-table-column prop="name" label="队列" min-width="180" />
        <el-table-column prop="ready" label="等待" width="100" />
        <el-table-column prop="reserved" label="执行中" width="100" />
        <el-table-column prop="delayed" label="延迟" width="100" />
        <el-table-column prop="failed" label="失败" width="100" />
        <el-table-column label="最长等待" width="140"><template #default="scope">{{ waitTime(scope.row.oldestWaitSeconds) }}</template></el-table-column>
      </el-table>
    </section>

    <section class="panel queue-section">
      <header class="panel-header"><div><span class="eyebrow">ACTIVE JOBS</span><h3>当前任务</h3></div><span class="result-count">{{ jobs.length }} 条</span></header>
      <el-table :data="jobs" empty-text="当前没有等待、执行中或延迟任务">
        <el-table-column prop="name" label="任务类" min-width="260"><template #default="scope"><code>{{ scope.row.name }}</code></template></el-table-column>
        <el-table-column prop="queue" label="队列" min-width="130" />
        <el-table-column prop="status" label="状态" width="110"><template #default="scope"><StatusPill :value="scope.row.status" /></template></el-table-column>
        <el-table-column prop="attempts" label="尝试次数" width="100" />
        <el-table-column label="创建时间" min-width="180"><template #default="scope">{{ new Date(scope.row.createdAt).toLocaleString('zh-CN') }}</template></el-table-column>
      </el-table>
    </section>

    <section class="panel queue-section failed-section">
      <header class="panel-header"><div><span class="eyebrow">FAILED JOBS</span><h3>失败任务处置</h3></div><span class="result-count">{{ failedJobs.length }} 条</span></header>
      <el-table :data="failedJobs" empty-text="没有失败任务">
        <el-table-column prop="name" label="任务类" min-width="220"><template #default="scope"><code>{{ scope.row.name }}</code></template></el-table-column>
        <el-table-column prop="queue" label="队列" width="130" />
        <el-table-column prop="exception" label="失败摘要" min-width="280" show-overflow-tooltip />
        <el-table-column label="失败时间" width="180"><template #default="scope">{{ new Date(scope.row.failedAt).toLocaleString('zh-CN') }}</template></el-table-column>
        <el-table-column label="操作" width="170" fixed="right"><template #default="scope"><el-button size="small" :icon="RefreshRight" @click="retry(scope.row)">重试</el-button><el-button size="small" type="danger" plain :icon="Delete" @click="forget(scope.row)" /></template></el-table-column>
      </el-table>
    </section>
  </div>
</template>
