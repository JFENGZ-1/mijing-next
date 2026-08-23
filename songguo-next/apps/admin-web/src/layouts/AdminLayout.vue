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
  Setting,
  Tickets,
  User,
  UserFilled,
} from "@element-plus/icons-vue";
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

import { apiRequest } from "@/api/client";
import { useSessionStore } from "@/stores/session";

const route = useRoute();
const router = useRouter();
const session = useSessionStore();
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
        <div class="brand-mark"><span>SG</span></div>
        <div>
          <strong>松果超级管理后台</strong>
          <small>PLATFORM CONTROL</small>
        </div>
      </div>

      <div class="workspace-card">
        <span class="workspace-kicker">当前数据域</span>
        <strong>平台全局</strong>
        <span>全部租户与场馆</span>
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
          <div class="site-select platform-scope"><el-icon><House /></el-icon><span>平台全局</span></div>
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
