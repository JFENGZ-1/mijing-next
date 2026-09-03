<script setup lang="ts">
import { Refresh, Search } from "@element-plus/icons-vue";
import { ElMessage } from "element-plus";
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";

import { ApiError, apiRequest } from "@/api/client";
import PageHeading from "@/components/PageHeading.vue";
import ResourceTable from "@/components/ResourceTable.vue";
import { getResource } from "@/resources/registry";
import { useSessionStore } from "@/stores/session";
import type { ResourceKey } from "@/types/admin";

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

interface DashboardData {
  totals: {
    tenants: number;
    sites: number;
    accounts: number;
    members: number;
    staff: number;
    paidRevenue: string;
  };
}

interface StatusOption {
  label: string;
  value: string;
}

const statusOptionsByResource: Partial<Record<ResourceKey, StatusOption[]>> = {
  members: [
    { label: "活跃", value: "active" },
    { label: "潜客", value: "lead" },
    { label: "访客", value: "visitor" },
    { label: "已关闭", value: "closed" },
  ],
  courses: [
    { label: "启用", value: "active" },
    { label: "已归档", value: "archived" },
  ],
  schedules: [
    { label: "已排课", value: "scheduled" },
    { label: "已暂停", value: "suspended" },
    { label: "已取消", value: "cancelled" },
  ],
  appointments: [
    { label: "已预约", value: "confirmed" },
    { label: "候补", value: "waitlisted" },
    { label: "已完成", value: "completed" },
    { label: "缺席", value: "absent" },
    { label: "已取消", value: "cancelled" },
  ],
  cards: [
    { label: "待激活", value: "pending_activation" },
    { label: "生效", value: "active" },
    { label: "冻结", value: "frozen" },
    { label: "过期", value: "expired" },
    { label: "已用尽", value: "exhausted" },
    { label: "归档", value: "archived" },
    { label: "作废", value: "voided" },
  ],
  orders: [
    { label: "待支付", value: "pending_payment" },
    { label: "关单中", value: "closing" },
    { label: "已支付", value: "paid" },
    { label: "已关闭", value: "closed" },
    { label: "已作废", value: "voided" },
  ],
  sites: [
    { label: "启用", value: "active" },
    { label: "停用", value: "disabled" },
  ],
  staff: [
    { label: "在职", value: "active" },
    { label: "离职", value: "departed" },
    { label: "停用", value: "disabled" },
  ],
  audit: [
    { label: "成功", value: "success" },
    { label: "失败", value: "failure" },
  ],
};

const route = useRoute();
const session = useSessionStore();
const query = ref("");
const status = ref("");
const page = ref(1);
const perPage = 20;
const loading = ref(false);
const rows = ref<Array<Record<string, string | number>>>([]);
const total = ref(0);
const lastPage = ref(1);

const resource = computed(() => getResource(String(route.params.resourceKey)) ?? getResource("members")!);
const statusOptions = computed(() => statusOptionsByResource[resource.value.key] ?? []);
const supportsFilters = computed(() => resource.value.key !== "reports");
const primaryApi = computed(() => {
  if (resource.value.key === "members") return "/admin/members";
  if (resource.value.key === "sites") return "/admin/tenants";
  if (resource.value.key === "audit") return "/admin/audit-logs";
  if (resource.value.key === "reports") return "/admin/dashboard";
  return `/admin/resources/${resource.value.key}`;
});

function displayDate(value: string | null) {
  return value ? new Intl.DateTimeFormat("zh-CN", { dateStyle: "medium" }).format(new Date(value)) : "—";
}

function queryString() {
  const params = new URLSearchParams({
    page: String(page.value),
    perPage: String(perPage),
  });
  if (query.value.trim()) params.set("query", query.value.trim());
  if (status.value) params.set("status", status.value);
  return params.toString();
}

function mapReports(data: DashboardData) {
  return [
    {
      id: "paid-revenue",
      metric: "累计已支付金额",
      today: new Intl.NumberFormat("zh-CN", { style: "currency", currency: "CNY" }).format(Number(data.totals.paidRevenue)),
      month: "平台累计",
      trend: "实时",
      source: "会员卡订单",
      status: "已更新",
    },
    {
      id: "members",
      metric: "会员档案",
      today: data.totals.members,
      month: "平台累计",
      trend: "实时",
      source: "会员关系",
      status: "已更新",
    },
    {
      id: "staff",
      metric: "员工档案",
      today: data.totals.staff,
      month: "平台累计",
      trend: "实时",
      source: "员工档案",
      status: "已更新",
    },
    {
      id: "sites",
      metric: "场馆数量",
      today: data.totals.sites,
      month: `${data.totals.tenants} 个租户`,
      trend: "实时",
      source: "租户与场馆",
      status: "已更新",
    },
  ];
}

async function loadResource() {
  loading.value = true;
  rows.value = [];
  total.value = 0;
  lastPage.value = 1;

  try {
    if (resource.value.key === "reports") {
      const data = (await apiRequest<DashboardData>("/admin/dashboard")).data;
      rows.value = mapReports(data);
      total.value = rows.value.length;
      return;
    }

    if (resource.value.key === "members") {
      const data = (await apiRequest<PaginatedData<AdminMemberRow>>(`/admin/members?${queryString()}`)).data;
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
      lastPage.value = data.pagination.lastPage;
      return;
    }

    if (resource.value.key === "sites") {
      const data = (await apiRequest<PaginatedData<AdminTenantRow>>(`/admin/tenants?${queryString()}`)).data;
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
      lastPage.value = data.pagination.lastPage;
      return;
    }

    if (resource.value.key === "audit") {
      const data = (await apiRequest<PaginatedData<AdminAuditRow>>(`/admin/audit-logs?${queryString()}`)).data;
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
      lastPage.value = data.pagination.lastPage;
      return;
    }

    const data = (await apiRequest<PaginatedData<Record<string, string | number>>>(
      `/admin/resources/${resource.value.key}?${queryString()}`,
    )).data;
    rows.value = data.items;
    total.value = data.pagination.total;
    lastPage.value = data.pagination.lastPage;
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "数据加载失败");
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  page.value = 1;
  void loadResource();
}

function resetFilters() {
  query.value = "";
  status.value = "";
  applyFilters();
}

function changePage(nextPage: number) {
  page.value = nextPage;
  void loadResource();
}

watch(
  () => route.params.resourceKey,
  () => {
    query.value = "";
    status.value = "";
    page.value = 1;
    void loadResource();
  },
  { immediate: true },
);
</script>

<template>
  <div class="resource-page" v-loading="loading">
    <PageHeading :eyebrow="resource.eyebrow" :title="resource.title" :description="resource.description">
      <el-button :icon="Refresh" @click="loadResource">刷新数据</el-button>
    </PageHeading>

    <section class="resource-context">
      <div><span>数据范围</span><strong>平台全局 · 跨租户</strong></div>
      <div><span>主接口</span><strong>{{ primaryApi }}</strong></div>
      <div><span>数据来源</span><strong>{{ resource.key === "reports" ? "真实数据库 · 服务端聚合" : "真实数据库 · 服务端分页" }}</strong></div>
    </section>

    <section class="data-panel">
      <header v-if="supportsFilters" class="data-toolbar">
        <div class="search-box">
          <el-icon><Search /></el-icon>
          <input v-model="query" placeholder="搜索编号、名称或关键字…" @keyup.enter="applyFilters" />
        </div>
        <el-select v-if="statusOptions.length" v-model="status" class="status-filter" clearable placeholder="全部状态">
          <el-option v-for="option in statusOptions" :key="option.value" :label="option.label" :value="option.value" />
        </el-select>
        <el-button type="primary" :icon="Search" @click="applyFilters">查询</el-button>
        <el-button v-if="query || status" @click="resetFilters">清除</el-button>
        <span class="result-count">共 {{ total }} 条真实记录</span>
      </header>

      <ResourceTable :columns="resource.columns" :rows="rows" :actions="[]" :can="session.can" />

      <footer class="table-footer">
        <span>当前列表只读；金额、权益、席位和权限结果均由对应业务服务计算。</span>
        <el-pagination
          v-if="lastPage > 1"
          background
          layout="prev, pager, next"
          :current-page="page"
          :total="total"
          :page-size="perPage"
          @current-change="changePage"
        />
      </footer>
    </section>
  </div>
</template>
