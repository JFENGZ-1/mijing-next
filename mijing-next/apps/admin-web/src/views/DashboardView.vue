<script setup lang="ts">
import { MagicStick, Refresh } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { computed, onMounted, ref } from "vue";

import { ApiError, apiRequest } from "@/api/client";
import MetricCard from "@/components/MetricCard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";

interface DashboardData {
  totals: {
    tenants: number;
    sites: number;
    accounts: number;
    members: number;
    staff: number;
    paidRevenue: string;
  };
  tenantStatus: Record<string, number>;
  recentTenants: Array<{
    id: number;
    name: string;
    code: string;
    status: string;
    siteCount: number;
    createdAt: string | null;
  }>;
  generatedAt: string;
}

interface DemoDataResult {
  tenant: { id: number; name: string; code: string };
  site: { id: number; name: string; code: string };
  counts: {
    staff: number;
    members: number;
    cardProducts: number;
    memberCards: number;
    orders: number;
    courses: number;
    scheduleSessions: number;
  };
  generatedAt: string;
}

const loading = ref(false);
const generatingDemo = ref(false);
const dashboard = ref<DashboardData | null>(null);
const generatedAt = computed(() => dashboard.value?.generatedAt
  ? new Intl.DateTimeFormat("zh-CN", { dateStyle: "medium", timeStyle: "medium" }).format(new Date(dashboard.value.generatedAt))
  : "尚未加载");
const paidRevenue = computed(() => new Intl.NumberFormat("zh-CN", {
  style: "currency",
  currency: "CNY",
  minimumFractionDigits: 2,
}).format(Number(dashboard.value?.totals.paidRevenue ?? 0)));

async function loadDashboard() {
  loading.value = true;
  try {
    dashboard.value = (await apiRequest<DashboardData>("/admin/dashboard")).data;
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "平台数据加载失败");
  } finally {
    loading.value = false;
  }
}

async function generateDemoData() {
  try {
    await ElMessageBox.confirm(
      "将补齐演示租户、场馆、全权限员工、会员、卡项、课程、排课、会员卡和订单。重复执行不会清空现有数据。",
      "一键生成演示数据",
      { type: "warning", confirmButtonText: "确认生成", cancelButtonText: "取消" },
    );
  } catch {
    return;
  }

  generatingDemo.value = true;
  try {
    const result = (await apiRequest<DemoDataResult>("/admin/demo-data/generate", { method: "POST" })).data;
    ElMessage.success(
      `已补齐 ${result.site.name}：${result.counts.members} 名会员、${result.counts.cardProducts} 个卡项、${result.counts.courses} 门课程`,
    );
    await loadDashboard();
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.payload.message : "演示数据生成失败");
  } finally {
    generatingDemo.value = false;
  }
}

onMounted(loadDashboard);
</script>

<template>
  <div class="dashboard-page" v-loading="loading">
    <PageHeading eyebrow="PLATFORM OVERVIEW" title="平台总览" description="跨租户查看平台真实数据；统计结果由超管接口在服务端聚合。">
      <el-button type="primary" plain :icon="MagicStick" :loading="generatingDemo" @click="generateDemoData">一键生成演示数据</el-button>
      <el-button :icon="Refresh" @click="loadDashboard">刷新数据</el-button>
    </PageHeading>

    <div class="scope-notice">
      <span><i />当前为真实平台数据</span>
      <p>不复用员工端场馆上下文，所有查询均经过超级管理员鉴权。</p>
      <b>更新于 {{ generatedAt }}</b>
    </div>

    <section class="metric-grid admin-metric-grid">
      <MetricCard label="平台租户" :value="`${dashboard?.totals.tenants ?? 0} 个`" trend="实时" note="全部租户" tone="green" />
      <MetricCard label="场馆总数" :value="`${dashboard?.totals.sites ?? 0} 家`" trend="实时" note="跨租户场馆" tone="blue" />
      <MetricCard label="会员档案" :value="`${dashboard?.totals.members ?? 0} 人`" trend="实时" note="平台会员关系" tone="amber" />
      <MetricCard label="累计已支付订单" :value="paidRevenue" trend="服务端" note="订单实付金额汇总" tone="dark" />
    </section>

    <section class="admin-overview-grid">
      <article class="panel admin-tenant-panel">
        <header class="panel-header">
          <div><span class="eyebrow">RECENT TENANTS</span><h3>最近创建的租户</h3></div>
          <span>真实记录 {{ dashboard?.recentTenants.length ?? 0 }} 条</span>
        </header>
        <el-table :data="dashboard?.recentTenants ?? []" empty-text="暂无租户数据">
          <el-table-column prop="name" label="租户名称" min-width="180">
            <template #default="scope"><span class="primary-cell"><b>{{ scope.row.name }}</b><small>{{ scope.row.code }}</small></span></template>
          </el-table-column>
          <el-table-column prop="siteCount" label="场馆数" width="100" />
          <el-table-column prop="createdAt" label="创建时间" min-width="180">
            <template #default="scope">{{ scope.row.createdAt ? new Date(scope.row.createdAt).toLocaleString('zh-CN') : '—' }}</template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="120"><template #default="scope"><StatusPill :value="scope.row.status" /></template></el-table-column>
        </el-table>
      </article>

      <article class="panel platform-facts-panel">
        <header class="panel-header"><div><span class="eyebrow">PLATFORM FACTS</span><h3>平台身份统计</h3></div></header>
        <div class="platform-fact"><span>账号总数</span><strong>{{ dashboard?.totals.accounts ?? 0 }}</strong><small>会员端与员工端统一身份</small></div>
        <div class="platform-fact"><span>员工档案</span><strong>{{ dashboard?.totals.staff ?? 0 }}</strong><small>跨租户任职记录</small></div>
        <div class="platform-fact"><span>活跃租户</span><strong>{{ dashboard?.tenantStatus.active ?? 0 }}</strong><small>服务端租户状态</small></div>
      </article>
    </section>
  </div>
</template>
