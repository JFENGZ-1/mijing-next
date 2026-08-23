<script setup lang="ts">
import { Download, Filter, Plus, Search } from "@element-plus/icons-vue";
import { ElMessage } from "element-plus";
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";

import { ApiError, apiRequest } from "@/api/client";
import PageHeading from "@/components/PageHeading.vue";
import ResourceTable from "@/components/ResourceTable.vue";
import { getResource } from "@/resources/registry";
import { useSessionStore } from "@/stores/session";

interface PaginatedData<T> {
  items: T[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

interface AdminMemberRow {
  id: number;
  memberNo: string;
  name: string;
  mobile: string | null;
  tenant: { id: number; name: string; code: string } | null;
  homeSite: { id: number; name: string } | null;
  status: string;
  joinedAt: string | null;
}

interface AdminTenantRow {
  id: number;
  name: string;
  code: string;
  status: string;
  siteCount: number;
  memberCount: number;
  staffCount: number;
  createdAt: string | null;
}

interface AdminAuditRow {
  id: number;
  actor: { username: string; name: string } | null;
  action: string;
  method: string;
  path: string;
  subject: string | null;
  requestId: string | null;
  statusCode: number;
  occurredAt: string | null;
}

const route = useRoute();
const session = useSessionStore();
const query = ref("");
const status = ref("全部状态");
const loading = ref(false);
const rows = ref<Array<Record<string, string | number>>>([]);
const total = ref(0);
const integrated = ref(false);

const resource = computed(() => getResource(String(route.params.resourceKey)) ?? getResource("members")!);
const filteredRows = computed(() => {
  const keyword = query.value.trim().toLowerCase();
  if (!keyword) return rows.value;
  return rows.value.filter((row) => Object.values(row).some((value) => String(value).toLowerCase().includes(keyword)));
});

function displayDate(value: string | null) {
  return value ? new Intl.DateTimeFormat("zh-CN", { dateStyle: "medium" }).format(new Date(value)) : "—";
}

async function loadResource() {
  loading.value = true;
  rows.value = [];
  total.value = 0;
  integrated.value = false;
  try {
    if (resource.value.key === "members") {
      const data = (await apiRequest<PaginatedData<AdminMemberRow>>("/admin/members?perPage=100")).data;
      rows.value = data.items.map((item) => ({
        id: item.memberNo,
        name: item.name,
        mobile: item.mobile ?? "—",
        owner: item.tenant?.name ?? "—",
        cards: item.homeSite?.name ?? "—",
        lastVisit: displayDate(item.joinedAt),
        status: item.status,
      }));
      total.value = data.pagination.total;
      integrated.value = true;
    } else if (resource.value.key === "sites") {
      const data = (await apiRequest<PaginatedData<AdminTenantRow>>("/admin/tenants?perPage=100")).data;
      rows.value = data.items.map((item) => ({
        id: item.code,
        name: item.name,
        manager: item.code,
        members: item.memberCount,
        todayClasses: item.siteCount,
        address: `${item.staffCount} 名员工`,
        status: item.status,
      }));
      total.value = data.pagination.total;
      integrated.value = true;
    } else if (resource.value.key === "audit") {
      const data = (await apiRequest<PaginatedData<AdminAuditRow>>("/admin/audit-logs?perPage=100")).data;
      rows.value = data.items.map((item) => ({
        id: item.id,
        time: item.occurredAt ? new Date(item.occurredAt).toLocaleString("zh-CN") : "—",
        actor: item.actor?.name ?? item.actor?.username ?? "系统",
        action: `${item.method} ${item.path}`,
        subject: item.subject ?? item.action.split("\\").pop() ?? "—",
        requestId: item.requestId ?? "—",
        status: item.statusCode < 400 ? "成功" : "失败",
      }));
      total.value = data.pagination.total;
      integrated.value = true;
    }
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "数据加载失败");
  } finally {
    loading.value = false;
  }
}

function unavailable(action: string) {
  ElMessage.info(`${action}尚未开放；当前只展示已接入的真实只读数据`);
}

watch(() => route.params.resourceKey, loadResource, { immediate: true });
</script>

<template>
  <div class="resource-page" v-loading="loading">
    <PageHeading :eyebrow="resource.eyebrow" :title="resource.title" :description="resource.description">
      <el-button v-if="resource.key !== 'audit'" :icon="Download" @click="unavailable('导出')">导出</el-button>
      <el-button v-if="resource.key !== 'audit'" type="primary" :icon="Plus" @click="unavailable('新建')">新建记录</el-button>
    </PageHeading>

    <section class="resource-context">
      <div><span>数据范围</span><strong>平台全局</strong></div>
      <div><span>主接口</span><strong>{{ integrated ? `/admin/${resource.key === 'sites' ? 'tenants' : resource.key === 'audit' ? 'audit-logs' : resource.key}` : '尚未接入' }}</strong></div>
      <div><span>数据来源</span><strong>{{ integrated ? '真实数据库' : '不展示示例数据' }}</strong></div>
    </section>

    <section class="data-panel">
      <header class="data-toolbar">
        <div class="search-box"><el-icon><Search /></el-icon><input v-model="query" placeholder="搜索当前数据…" /></div>
        <el-select v-model="status" class="status-filter" disabled>
          <el-option label="全部状态" value="全部状态" />
        </el-select>
        <el-button :icon="Filter" disabled>更多筛选</el-button>
        <span class="result-count">{{ integrated ? `共 ${total} 条真实记录` : '接口尚未接入' }}</span>
      </header>

      <ResourceTable :columns="resource.columns" :rows="filteredRows" :actions="[]" :can="session.can" />

      <footer class="table-footer">
        <span>{{ integrated ? '当前列表来自超管只读接口' : '此模块不会显示示例数据，等待对应超管接口接入' }}</span>
        <el-pagination v-if="integrated && total > 100" background layout="prev, pager, next" :total="total" :page-size="100" />
      </footer>
    </section>
  </div>
</template>
