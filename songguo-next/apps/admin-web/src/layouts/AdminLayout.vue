<script setup lang="ts">
import {
  Calendar,
  Bell,
  Collection,
  CreditCard,
  DocumentChecked,
  Fold,
  Grid,
  Histogram,
  House,
  List,
  OfficeBuilding,
  Operation,
  Money,
  Setting,
  Tickets,
  User,
  UserFilled,
} from "@element-plus/icons-vue";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

import { apiRequest } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import { useBusinessScopeStore } from "@/stores/businessScope";

const route = useRoute();
const router = useRouter();
const session = useSessionStore();
const businessScope = useBusinessScopeStore();
const mobileMenuOpen = ref(false);
const serviceHealth = ref<"checking" | "online" | "offline">("checking");
let healthTimer: ReturnType<typeof setInterval> | undefined;

const navGroups = [
  {
    label: "总览",
    items: [{ label: "经营驾驶舱", to: "/dashboard", icon: Grid }],
  },
  {
    label: "日常运营",
    items: [
      { label: "会员管理", to: "/resource/members", icon: User },
      { label: "课程目录", to: "/resource/courses", icon: Collection },
      { label: "排课管理", to: "/resource/schedules", icon: Calendar },
      { label: "预约与履约", to: "/resource/appointments", icon: List },
    ],
  },
  {
    label: "交易与权益",
    items: [
      { label: "会员卡与权益", to: "/resource/cards", icon: CreditCard },
      { label: "订单管理", to: "/resource/orders", icon: Tickets },
    ],
  },
  {
    label: "会员卡与耗卡",
    items: [
      { label: "业务角色与人员", to: "/card-consumption/roles", icon: UserFilled },
      { label: "卡课与薪酬规则", to: "/card-consumption/rules", icon: Collection },
      { label: "会员钱包", to: "/card-consumption/wallets", icon: Money },
      { label: "耗卡与提成报表", to: "/card-consumption/reports", icon: Histogram },
      { label: "日结与月结", to: "/card-consumption/settlements", icon: DocumentChecked },
    ],
  },
  {
    label: "组织与分析",
    items: [
      { label: "租户与场馆", to: "/resource/sites", icon: OfficeBuilding },
      { label: "员工与权限", to: "/resource/staff", icon: UserFilled },
      { label: "经营报表", to: "/resource/reports", icon: Histogram },
    ],
  },
  {
    label: "系统治理",
    items: [
      { label: "微信支付配置", to: "/settings/payments", icon: CreditCard },
      { label: "图片视频资源", to: "/media", icon: Collection },
      { label: "队列监控", to: "/queues", icon: Operation },
      { label: "接口契约", to: "/contracts", icon: DocumentChecked },
      { label: "操作审计", to: "/resource/audit", icon: Operation },
    ],
  },
];

const breadcrumbs = computed(() => {
  if (route.name === "dashboard") return ["总览", "经营驾驶舱"];
  if (route.name === "contracts") return ["系统治理", "接口契约"];
  const item = navGroups.flatMap((group) => group.items).find((nav) => nav.to === route.path);
  const group = navGroups.find((entry) => entry.items.some((nav) => nav.to === route.path));
  return [group?.label ?? "管理后台", item?.label ?? "数据资源"];
});
const serviceHealthLabel = computed(() => ({
  checking: "正在检查接口服务",
  online: "接口服务正常",
  offline: "接口服务未连接",
})[serviceHealth.value]);

async function checkServiceHealth() {
  try {
    await apiRequest<unknown>("/health");
    serviceHealth.value = "online";
  } catch {
    serviceHealth.value = "offline";
  }
}

onMounted(() => {
  void checkServiceHealth();
  void businessScope.loadTenants();
  healthTimer = setInterval(() => void checkServiceHealth(), 60_000);
});

onUnmounted(() => {
  if (healthTimer) clearInterval(healthTimer);
});

function isActive(path: string) {
  return route.path === path;
}

function navigate(path: string) {
  mobileMenuOpen.value = false;
  void router.push(path);
}

async function logout() {
  await session.logout();
  void router.push("/login");
}
</script>

<template>
  <div class="admin-shell">
    <aside class="sidebar" :class="{ 'is-open': mobileMenuOpen }">
      <div class="brand-block">
        <div class="brand-mark"><span>MJ</span></div>
        <div>
          <strong>觅境超级管理后台</strong>
          <small>PLATFORM CONTROL</small>
        </div>
      </div>

      <div class="workspace-card">
        <span class="workspace-kicker">当前数据域</span>
        <strong>{{ businessScope.tenant?.name ?? "未选择租户" }}</strong>
        <span>{{ businessScope.site?.name ?? "请选择场馆" }}</span>
      </div>

      <nav class="sidebar-nav">
        <section v-for="group in navGroups" :key="group.label" class="nav-group">
          <p>{{ group.label }}</p>
          <button
            v-for="item in group.items"
            :key="item.to"
            type="button"
            class="nav-item"
            :class="{ active: isActive(item.to) }"
            @click="navigate(item.to)"
          >
            <el-icon><component :is="item.icon" /></el-icon>
            <span>{{ item.label }}</span>
            <i v-if="isActive(item.to)" />
          </button>
        </section>
      </nav>

      <div class="sidebar-footer">
        <button type="button" @click="navigate('/settings/payments')"><el-icon><Setting /></el-icon><span>系统设置</span></button>
        <div class="service-health" :class="serviceHealth"><i />{{ serviceHealthLabel }}</div>
      </div>
    </aside>

    <div v-if="mobileMenuOpen" class="sidebar-overlay" @click="mobileMenuOpen = false" />

    <main class="main-frame">
      <header class="topbar">
        <div class="topbar-left">
          <button class="mobile-menu" type="button" @click="mobileMenuOpen = true">
            <el-icon><Fold /></el-icon>
          </button>
          <div class="breadcrumb">
            <span>{{ breadcrumbs[0] }}</span>
            <b>/</b>
            <strong>{{ breadcrumbs[1] }}</strong>
          </div>
        </div>

        <div class="topbar-actions">
          <div class="business-scope-selectors">
            <el-select
              :model-value="businessScope.tenantId"
              class="site-select"
              filterable
              clearable
              placeholder="选择租户"
              :loading="businessScope.loadingTenants"
              @update:model-value="businessScope.selectTenant"
            >
              <template #prefix><el-icon><OfficeBuilding /></el-icon></template>
              <el-option
                v-for="item in businessScope.tenants"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              />
            </el-select>
            <el-select
              :model-value="businessScope.siteId"
              class="site-select"
              filterable
              clearable
              placeholder="选择场馆"
              :disabled="!businessScope.tenantId"
              :loading="businessScope.loadingSites"
              @update:model-value="businessScope.selectSite"
            >
              <template #prefix><el-icon><House /></el-icon></template>
              <el-option
                v-for="item in businessScope.sites"
                :key="item.id"
                :label="item.name"
                :value="item.id"
              />
            </el-select>
          </div>
          <button class="command-button" type="button"><span>⌘</span> 全局搜索</button>
          <button class="notice-button" type="button" aria-label="通知">
            <el-icon><Bell /></el-icon><i />
          </button>
          <el-dropdown trigger="click">
            <button class="profile-button" type="button">
              <span class="avatar">{{ session.displayName.slice(0, 1) || "管" }}</span>
              <span class="profile-copy"><strong>{{ session.displayName }}</strong><small>平台超级管理员</small></span>
              <span class="chevron">⌄</span>
            </button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item>账号信息</el-dropdown-item>
                <el-dropdown-item divided @click="logout">退出登录</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <div class="page-viewport">
        <RouterView />
      </div>
    </main>
  </div>
</template>
