<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberOrder } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberOrderSummary } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import {
  cardTypeLabel,
  formatIsoDate,
  memberCardStatusLabel,
  orderStatusLabel,
} from "@/utils/format";

const orderId = ref(0);
const errorMessage = ref("");
const order = ref<MemberOrderSummary | null>(null);

const loading = ref(true);

async function loadOrder(refresh = false) {
  errorMessage.value = "";
  if (refresh) {
    order.value = null;
  }

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberOrder(tenant.tenantId, orderId.value);
    order.value = response.data;
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "订单详情加载失败");
  } finally {
    loading.value = false;
  }
}

function openCardDetail() {
  if (!order.value?.memberCard) return;
  uni.navigateTo({ url: `/pages/cards/detail?id=${order.value.memberCard.id}` });
}

function openOrders() {
  uni.navigateTo({ url: "/pages/orders/index" });
}

function openWallet() {
  uni.redirectTo({ url: "/pages/cards/index" });
}

onLoad((query) => {
  orderId.value = Number(query?.id ?? 0);
});

onShow(async () => { if (await requireMemberAuth()) await loadOrder(); });

onPullDownRefresh(async () => { await loadOrder(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="order">
      <view class="result-card">
        <view class="result-icon">✓</view>
        <view class="result-title">
          {{ order.status === "paid" ? "购卡成功" : orderStatusLabel(order.status) }}
        </view>
        <view class="result-subtitle">{{ order.productName || "会员卡订单" }}</view>
      </view>

      <view class="detail-card">
        <view class="detail-row">
          <text class="label">订单号</text>
          <text>{{ order.orderNo }}</text>
        </view>
        <view v-if="order.siteName" class="detail-row">
          <text class="label">场馆</text>
          <text>{{ order.siteName }}</text>
        </view>
        <view class="detail-row">
          <text class="label">支付金额</text>
          <text class="amount">¥{{ order.effectiveAmount }}</text>
        </view>
        <view class="detail-row">
          <text class="label">订单状态</text>
          <text>{{ orderStatusLabel(order.status) }}</text>
        </view>
        <view v-if="order.createdAt" class="detail-row">
          <text class="label">下单时间</text>
          <text>{{ formatIsoDate(order.createdAt) }}</text>
        </view>
      </view>

      <view v-if="order.memberCard" class="detail-card">
        <view class="section-title">已发放会员卡</view>
        <view class="card-name">{{ order.memberCard.name || "会员卡" }}</view>
        <view class="card-meta">{{ cardTypeLabel(order.memberCard.cardType) }}</view>
        <view class="card-meta">状态：{{ memberCardStatusLabel(order.memberCard.status) }}</view>
        <view
          v-if="order.memberCard.cachedBalance || order.memberCard.cachedRemainingCount != null"
          class="card-balance"
        >
          <template v-if="order.memberCard.cardType === 'count'">
            剩余 {{ order.memberCard.cachedRemainingCount }} 次
          </template>
          <template v-else-if="order.memberCard.cachedBalance">
            余额 ¥{{ order.memberCard.cachedBalance }}
          </template>
        </view>
      </view>

      <view class="actions">
        <u-button v-if="order.memberCard" type="primary" @click="openCardDetail">查看会员卡</u-button>
        <u-button plain @click="openWallet">返回钱包</u-button>
        <u-button plain @click="openOrders">全部订单</u-button>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.result-card {
  margin-bottom: $spacing-md;
  padding: $spacing-lg $spacing-md;
  text-align: center;
  background: $color-primary-light;
  border: 1rpx solid rgba($color-primary, 0.25);
  border-radius: $radius-md;
}

.result-icon {
  color: $color-primary-dark;
  font-size: 56rpx;
  font-weight: 700;
}

.result-title {
  margin-top: $spacing-sm;
  font-size: 36rpx;
  font-weight: 600;
}

.result-subtitle {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 26rpx;
}

.detail-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
  padding: $spacing-xs 0;
  font-size: 26rpx;
}

.label {
  color: $color-text-secondary;
}

.amount {
  font-weight: 600;
}

.card-name {
  font-size: 30rpx;
  font-weight: 600;
}

.card-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-balance {
  margin-top: $spacing-sm;
  font-size: 32rpx;
  font-weight: 600;
}

.actions {
  display: grid;
  gap: $spacing-sm;
  margin-top: $spacing-md;
}
</style>
