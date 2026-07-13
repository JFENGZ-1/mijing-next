<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchReportCardSalesDetail } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ReportCardSalesDetail } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const year = ref(new Date().getFullYear());
const month = ref(new Date().getMonth() + 1);
const cardProductId = ref<number | null>(null);
const detail = ref<ReportCardSalesDetail | null>(null);

const canView = computed(() => session.can("report.read"));

onLoad((query) => {
  if (query?.year) year.value = Number(query.year);
  if (query?.month) month.value = Number(query.month);
  if (query?.cardProductId) cardProductId.value = Number(query.cardProductId);
});

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    detail.value = await fetchReportCardSalesDetail(
      session.currentSiteId,
      year.value,
      month.value,
      cardProductId.value,
    );
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "售卡明细加载失败";
  } finally {
    loading.value = false;
  }
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
      <view v-if="detail" class="totals-card">
        {{ year }}年{{ month }}月 · {{ detail.totals.salesCount }} 单 · ¥{{ detail.totals.revenue }}
      </view>
      <view v-if="detail" class="list-card">
        <view v-for="item in detail.items" :key="item.orderId" class="row">
          <view>
            <text class="name">{{ item.orderNo }}</text>
            <text class="meta">{{ item.memberName || "会员" }} · ¥{{ item.amount }}</text>
          </view>
          <text class="date">{{ item.paidAt?.slice(0, 10) || "" }}</text>
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
.meta, .date { color: #667085; font-size: 24rpx; }
</style>
