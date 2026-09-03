<script setup lang="ts">
import { CircleCheck, Refresh, Warning } from "@element-plus/icons-vue";
import { ElMessage } from "element-plus";
import { computed, ref, watch } from "vue";

import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";
import { apiOperations, contractReport } from "@/generated/api-contract";

const query = ref("");
const disposition = ref("ALL");
const currentPage = ref(1);
const pageSize = 30;

const filtered = computed(() => {
  const keyword = query.value.trim().toLowerCase();
  return apiOperations.filter((operation) => {
    const matchDisposition = disposition.value === "ALL" || operation.disposition === disposition.value;
    const matchQuery = !keyword || `${operation.operationId} ${operation.path}`.toLowerCase().includes(keyword);
    return matchDisposition && matchQuery;
  });
});

const sourceShortHash = computed(() => contractReport.sourceHash.slice(0, 12));
const hasChanges = computed(() => contractReport.changes.sourceChanged
  || contractReport.changes.added.length > 0
  || contractReport.changes.changed.length > 0
  || contractReport.changes.removed.length > 0);
const paginated = computed(() => {
  const start = (currentPage.value - 1) * pageSize;
  return filtered.value.slice(start, start + pageSize);
});

watch([query, disposition], () => {
  currentPage.value = 1;
});

function explainSync() {
  ElMessage.info("请在命令行运行 pnpm --filter @mijing/admin-web contract:sync");
}
</script>

<template>
  <div class="contract-page">
    <PageHeading eyebrow="API GOVERNANCE" title="接口契约中心" description="后台只读消费 OpenAPI，以 operationId 识别上游新增和变更，避免重复开发接口。">
      <el-button :icon="Refresh" @click="explainSync">同步契约</el-button>
    </PageHeading>

    <section class="contract-summary">
      <article><span>契约操作</span><strong>{{ contractReport.total }}</strong><small>来源哈希 {{ sourceShortHash }}</small></article>
      <article><span>可直接接入</span><strong>{{ contractReport.counts.ADOPT }}</strong><small>通用后台资源</small></article>
      <article><span>定制页面</span><strong>{{ contractReport.counts.CUSTOM }}</strong><small>复杂领域流程</small></article>
      <article class="attention"><span>待分类</span><strong>{{ contractReport.counts.UNCLASSIFIED }}</strong><small>新增接口进入审查队列</small></article>
    </section>

    <section class="change-strip" :class="{ clean: !hasChanges }">
      <el-icon><CircleCheck v-if="!hasChanges" /><Warning v-else /></el-icon>
      <div>
        <strong>{{ hasChanges ? '检测到上游契约变化' : '当前契约与基线一致' }}</strong>
        <span>新增 {{ contractReport.changes.added.length }} · 变更 {{ contractReport.changes.changed.length }} · 删除 {{ contractReport.changes.removed.length }} · 文档 {{ contractReport.changes.sourceChanged ? '有变化' : '一致' }}</span>
      </div>
      <time>{{ contractReport.generatedAt }}</time>
    </section>

    <section class="data-panel">
      <header class="data-toolbar">
        <el-input v-model="query" clearable placeholder="搜索 operationId 或路径" class="contract-search" />
        <el-select v-model="disposition" class="status-filter">
          <el-option label="全部分类" value="ALL" />
          <el-option label="直接接入" value="ADOPT" />
          <el-option label="定制页面" value="CUSTOM" />
          <el-option label="忽略" value="IGNORE" />
          <el-option label="待分类" value="UNCLASSIFIED" />
        </el-select>
        <span class="result-count">{{ filtered.length }} / {{ apiOperations.length }} 个操作</span>
      </header>

      <el-table :data="paginated" class="resource-table contract-table" row-key="operationId">
        <el-table-column prop="operationId" label="operationId" min-width="260"><template #default="scope"><code>{{ scope.row.operationId }}</code></template></el-table-column>
        <el-table-column prop="method" label="方法" width="90"><template #default="scope"><b class="method-chip" :class="scope.row.method.toLowerCase()">{{ scope.row.method }}</b></template></el-table-column>
        <el-table-column prop="path" label="接口路径" min-width="340"><template #default="scope"><code class="path-code">{{ scope.row.path }}</code></template></el-table-column>
        <el-table-column prop="group" label="领域" width="120" />
        <el-table-column prop="disposition" label="后台处置" width="130"><template #default="scope"><StatusPill :value="scope.row.disposition" /></template></el-table-column>
      </el-table>
      <footer class="table-footer contract-pagination">
        <span>每页 {{ pageSize }} 条，当前第 {{ currentPage }} 页</span>
        <el-pagination
          v-model:current-page="currentPage"
          background
          layout="prev, pager, next"
          :page-size="pageSize"
          :total="filtered.length"
        />
      </footer>
    </section>
  </div>
</template>
