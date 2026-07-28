<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import {
  fetchReportCardProductAnalytics,
  fetchReportCardProductSalesRanking,
  fetchReportMemberCardConsumptionRanking,
} from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

type RankTab = "consumption" | "sales" | "analytics";
const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const activeTab = ref<RankTab>("consumption");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const consumption = ref<Awaited<ReturnType<typeof fetchReportMemberCardConsumptionRanking>> | null>(null);
const sales = ref<Awaited<ReturnType<typeof fetchReportCardProductSalesRanking>> | null>(null);
const analytics = ref<Awaited<ReturnType<typeof fetchReportCardProductAnalytics>> | null>(null);

const canView = computed(() => session.can("report.rankings.read"));
const tabs = [
  { key: "consumption" as const, name: "会员消费" },
  { key: "sales" as const, name: "卡种销售" },
  { key: "analytics" as const, name: "连锁分析" },
];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const siteId = session.currentSiteId;
    if (activeTab.value === "consumption") {
      consumption.value = await fetchReportMemberCardConsumptionRanking(siteId, selectedYear.value, selectedMonth.value);
    } else if (activeTab.value === "sales") {
      sales.value = await fetchReportCardProductSalesRanking(siteId, selectedYear.value, selectedMonth.value);
    } else {
      analytics.value = await fetchReportCardProductAnalytics(siteId);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "排行榜加载失败";
  } finally {
    loading.value = false;
  }
}

async function switchTab(index: number) {
  const tab = tabs[index];
  if (!tab || tab.key === activeTab.value) return;
  activeTab.value = tab.key;
  await load();
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canView" mode="permission" text="暂无排行榜权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-tabs :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <view v-if="activeTab !== 'analytics'" class="chip-row">
        <text>{{ selectedYear }}年 {{ selectedMonth }}月</text>
      </view>

      <view v-if="activeTab === 'consumption' && consumption" class="list-card">
        <view v-for="item in consumption.items" :key="item.memberId" class="row">
          <text class="rank">{{ item.rank }}</text>
          <view>
            <text class="name">{{ item.memberName || item.memberNo }}</text>
            <text class="meta">扣次 {{ item.consumptionCount }} · ¥{{ item.consumptionAmount }}</text>
          </view>
        </view>
      </view>

      <view v-if="activeTab === 'sales' && sales" class="list-card">
        <view v-for="item in sales.items" :key="`${item.cardProductId}-${item.rank}`" class="row">
          <text class="rank">{{ item.rank }}</text>
          <view>
            <text class="name">{{ item.cardProductName }}</text>
            <text class="meta">{{ item.salesCount }} 张 · ¥{{ item.revenue }}</text>
          </view>
        </view>
      </view>

      <view v-if="activeTab === 'analytics' && analytics" class="list-card">
        <view v-for="item in analytics.items" :key="item.cardProductId" class="row">
          <view>
            <text class="name">{{ item.cardProductName }}</text>
            <text class="meta">{{ item.siteName }} · 发卡 {{ item.issuedCount }} · 连锁 {{ item.linkedSiteCount }}</text>
          </view>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.list-card { margin-top: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.row { display: flex; gap: 16rpx; padding: 12rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.rank { width: 40rpx; font-weight: 600; color: #ed920f; }
.name, .meta { display: block; }
.meta { margin-top: 6rpx; color: #505050; font-size: 24rpx; }
</style>
