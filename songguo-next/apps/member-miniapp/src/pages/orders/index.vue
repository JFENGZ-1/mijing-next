<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { getMemberOrders } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberOrderSummary } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { formatIsoDate, orderStatusLabel } from "@/utils/format";

const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const orders = ref<MemberOrderSummary[]>([]);
const page = ref(1);
const lastPage = ref(1);

async function loadOrders(reset = true) {
  if (!reset) {
    loadingMore.value = true;
  } else {
    loading.value = true;
    page.value = 1;
    orders.value = [];
    lastPage.value = 1;
  }
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberOrders(tenant.tenantId, page.value);
    orders.value = reset ? response.data.items : [...orders.value, ...response.data.items];
    lastPage.value = response.data.pagination.lastPage;
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "订单列表加载失败");
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

async function loadMore() {
  if (loadingMore.value || page.value >= lastPage.value) return;
  page.value += 1;
  await loadOrders(false);
}

function openOrder(orderId: number) {
  uni.navigateTo({ url: `/pages/orders/result?id=${orderId}` });
}

onShow(async () => {
  if (await requireMemberAuth()) await loadOrders();
});

onPullDownRefresh(async () => {
  await loadOrders();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="orders.length === 0 && !errorMessage" mode="list" text="暂无购卡订单" />

    <view
      v-for="order in orders"
      :key="order.id"
      class="order-card"
      @tap="openOrder(order.id)"
    >
      <view class="order-header">
        <view class="order-title">{{ order.productName || "会员卡订单" }}</view>
        <view class="status-tag">{{ orderStatusLabel(order.status) }}</view>
      </view>
      <view class="order-meta">订单号 {{ order.orderNo }}</view>
      <view v-if="order.siteName" class="order-meta">{{ order.siteName }}</view>
      <view class="order-footer">
        <view class="order-amount">¥{{ order.effectiveAmount }}</view>
        <view v-if="order.createdAt" class="order-time">{{ formatIsoDate(order.createdAt) }}</view>
      </view>
    </view>

    <u-button v-if="page < lastPage" plain :loading="loadingMore" @click="loadMore">加载更多</u-button>
  </view>
</template>

<style scoped lang="scss">
.order-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.order-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.order-title {
  font-size: 30rpx;
  font-weight: 600;
}

.status-tag {
  padding: 4rpx 12rpx;
  color: $color-primary;
  font-size: 22rpx;
  background: $color-primary-light;
  border-radius: $radius-sm;
}

.order-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.order-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: $spacing-sm;
}

.order-amount {
  font-size: 32rpx;
  font-weight: 600;
}

.order-time {
  color: $color-text-secondary;
  font-size: 24rpx;
}
</style>
