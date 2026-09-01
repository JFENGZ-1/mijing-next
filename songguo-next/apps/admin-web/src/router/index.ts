import { createRouter, createWebHistory } from "vue-router";

import { useSessionStore } from "@/stores/session";

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: "/login",
      name: "login",
      component: () => import("@/views/LoginView.vue"),
      meta: { public: true },
    },
    {
      path: "/",
      component: () => import("@/layouts/AdminLayout.vue"),
      children: [
        { path: "", redirect: "/dashboard" },
        { path: "dashboard", name: "dashboard", component: () => import("@/views/DashboardView.vue") },
        { path: "settings/payments", name: "payment-settings", component: () => import("@/views/PaymentSettingsView.vue") },
        { path: "media", name: "media-library", component: () => import("@/views/MediaLibraryView.vue") },
        { path: "queues", name: "queue-monitor", component: () => import("@/views/QueueMonitorView.vue") },
        {
          path: "card-consumption/roles",
          name: "compensation-roles",
          component: () => import("@/views/CompensationRolesView.vue"),
        },
        {
          path: "card-consumption/rules",
          name: "card-course-rules",
          component: () => import("@/views/CardCourseRulesView.vue"),
        },
        {
          path: "card-consumption/wallets",
          name: "member-wallets",
          component: () => import("@/views/WalletGovernanceView.vue"),
        },
        {
          path: "card-consumption/reports",
          name: "consumption-reports",
          component: () => import("@/views/ConsumptionReportsView.vue"),
        },
        {
          path: "card-consumption/settlements",
          name: "settlement-governance",
          component: () => import("@/views/SettlementGovernanceView.vue"),
        },
        {
          path: "resource/:resourceKey",
          name: "resource",
          component: () => import("@/views/ResourceView.vue"),
        },
        { path: "contracts", name: "contracts", component: () => import("@/views/ContractView.vue") },
      ],
    },
  ],
});

router.beforeEach(async (to) => {
  const session = useSessionStore();
  if (!to.meta.public && session.authenticated && !session.profile) {
    await session.hydrate();
  }
  if (!to.meta.public && !session.authenticated) {
    return { name: "login", query: { redirect: to.fullPath } };
  }
  if (to.name === "login" && session.authenticated) {
    return { name: "dashboard" };
  }
  return true;
});

export default router;
