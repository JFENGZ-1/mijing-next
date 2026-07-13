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
  <view v-if="!loading" class="orders-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <u-empty v-if="orders.length === 0 && !errorMessage" mode="list" text="仅显示在线购卡记录哦" />

    <view v-for="order in orders" :key="order.id" class="order-wrap" @tap="openOrder(order.id)">
      <view class="title-wrap">
        <view class="entry">{{ order.channel === "offline" ? "线下支付" : "在线购卡" }}</view>
        <view class="state">{{ orderStatusLabel(order.status) }}</view>
      </view>

      <view class="amount-wrap">
        <text class="amount-title">实付款</text>
        <text class="amount-symbol">¥</text>
        <text class="amount-num">{{ order.effectiveAmount }}</text>
      </view>

      <view v-if="order.productName" class="product-name">{{ order.productName }}</view>

      <view class="info-wrap">
        <view class="info-item">
          <view class="info-title">订单编号：</view>
          <view class="info-data">{{ order.orderNo }}</view>
        </view>
        <view v-if="order.siteName" class="info-item">
          <view class="info-title">场馆：</view>
          <view class="info-data">{{ order.siteName }}</view>
        </view>
        <view v-if="order.createdAt" class="info-item">
          <view class="info-title">下单时间：</view>
          <view class="info-data">{{ formatIsoDate(order.createdAt) }}</view>
        </view>
      </view>
    </view>

    <view v-if="page < lastPage" class="loadmore-wrap">
      <u-loadmore
        :status="loadingMore ? 'loading' : 'loadmore'"
        loadmore-text="加载更多"
        @loadmore="loadMore"
      />
    </view>

    <bottom-logo v-if="orders.length > 0" />
  </view>
</template>

<style scoped lang="scss">
.orders-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.order-wrap {
  margin-bottom: 24rpx;
  padding: 24rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.title-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.entry {
  color: $color-text;
  font-size: 28rpx;
  font-weight: 500;
}

.state {
  color: $color-primary;
  font-size: 24rpx;
}

.amount-wrap {
  display: flex;
  align-items: baseline;
  justify-content: flex-end;
  margin: 20rpx 0 4rpx;
}

.amount-title {
  color: $color-text-secondary;
  font-size: 22rpx;
  margin-right: 8rpx;
}

.amount-symbol {
  color: $color-text;
  font-size: 26rpx;
  font-weight: 600;
}

.amount-num {
  color: $color-text;
  font-size: 40rpx;
  font-weight: 700;
}

.product-name {
  text-align: right;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.info-wrap {
  margin-top: 20rpx;
  padding-top: 20rpx;
  border-top: 1rpx solid #f0f0f0;
}

.info-item {
  display: flex;
  margin-bottom: 10rpx;

  &:last-child {
    margin-bottom: 0;
  }
}

.info-title {
  flex-shrink: 0;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.info-data {
  color: $color-text;
  font-size: 24rpx;
}

.loadmore-wrap {
  padding: 12rpx 0;
}
</style>
