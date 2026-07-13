<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchReportCardSalesSummary } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportCardSalesSummary } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const selectedYear = ref(new Date().getFullYear());
const selectedMonth = ref(new Date().getMonth() + 1);
const summary = ref<ReportCardSalesSummary | null>(null);

const canView = computed(() => session.can("report.read"));

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    summary.value = await fetchReportCardSalesSummary(session.currentSiteId, selectedYear.value, selectedMonth.value);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "售卡统计加载失败";
  } finally {
    loading.value = false;
  }
}

function openDetail(cardProductId: number | null) {
  const query = `year=${selectedYear.value}&month=${selectedMonth.value}${cardProductId != null ? `&cardProductId=${cardProductId}` : ""}`;
  uni.navigateTo({ url: `/pages/report/card-sales/detail?${query}` });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canView" mode="permission" text="暂无报表权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view v-if="summary" class="totals-card">
        {{ selectedYear }}年{{ selectedMonth }}月 · {{ summary.totals.salesCount }} 单 · ¥{{ summary.totals.revenue }}
      </view>
      <view v-if="summary" class="list-card">
        <view v-for="item in summary.items" :key="`${item.cardProductId}-${item.cardProductName}`" class="row" @tap="openDetail(item.cardProductId)">
          <view>
            <text class="name">{{ item.cardProductName }}</text>
            <text class="meta">{{ item.salesCount }} 张 · ¥{{ item.revenue }}</text>
          </view>
          <u-icon name="arrow-right" size="16" color="#98a2b3" />
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.totals-card, .list-card { margin-top: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.row { display: flex; align-items: center; justify-content: space-between; padding: 12rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.name, .meta { display: block; }
.meta { margin-top: 6rpx; color: #667085; font-size: 24rpx; }
</style>
