<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberOrder } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardWalletSummary, MemberOrderSummary } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import {
  formatIsoDate,
  orderStatusLabel,
} from "@/utils/format";

const orderId = ref(0);
const errorMessage = ref("");
const order = ref<MemberOrderSummary | null>(null);

const loading = ref(true);

const issuedCard = computed<MemberCardWalletSummary | null>(() => {
  const mc = order.value?.memberCard;
  if (!mc) return null;
  return {
    id: mc.id,
    siteId: 0,
    cardType: mc.cardType,
    status: mc.status,
    cardNoMasked: "",
    name: mc.name,
    balance: mc.cachedBalance,
    remainingCount: mc.cachedRemainingCount,
    validFrom: mc.validFrom,
    validUntil: mc.validUntil,
  };
});

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
        <view class="success-icon">
          <view class="check"></view>
        </view>
        <view class="result-title">
          {{ order.status === "paid" ? "购卡成功" : orderStatusLabel(order.status) }}
        </view>
        <view v-if="order.effectiveAmount" class="result-amount">
          <text class="yaun">¥</text><text class="num">{{ order.effectiveAmount }}</text>
        </view>
        <view class="result-subtitle">{{ order.productName || "会员卡订单" }}</view>
      </view>

      <view class="detail-card">
        <view class="detail-row">
          <text class="label">订单号</text>
          <text class="value">{{ order.orderNo }}</text>
        </view>
        <view v-if="order.siteName" class="detail-row">
          <text class="label">场馆</text>
          <text class="value">{{ order.siteName }}</text>
        </view>
        <view class="detail-row">
          <text class="label">订单状态</text>
          <text class="value">{{ orderStatusLabel(order.status) }}</text>
        </view>
        <view v-if="order.createdAt" class="detail-row">
          <text class="label">下单时间</text>
          <text class="value">{{ formatIsoDate(order.createdAt) }}</text>
        </view>
      </view>

      <view v-if="issuedCard" class="issued-section">
        <view class="issued-card">
          <member-card :card="issuedCard" />
        </view>
      </view>

      <view class="actions">
        <view v-if="order.memberCard" class="btn-primary" @tap="openCardDetail">查看会员卡</view>
        <view class="btn-links">
          <text class="link" @tap="openWallet">返回钱包</text>
          <text class="link-divider">|</text>
          <text class="link" @tap="openOrders">全部订单</text>
        </view>
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  background: #ededed;
  padding: 24rpx 28rpx 0;
}

.result-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 36rpx 32rpx 32rpx;
  background: #fff;
  border-radius: 16rpx;
}

.success-icon {
  width: 84rpx;
  height: 84rpx;
  border-radius: 50%;
  background: #07c160;
  display: flex;
  align-items: center;
  justify-content: center;
}

.check {
  width: 26rpx;
  height: 13rpx;
  border-left: 5rpx solid #fff;
  border-bottom: 5rpx solid #fff;
  transform: rotate(-45deg) translate(1rpx, -3rpx);
}

.result-title {
  margin-top: 16rpx;
  color: #181818;
  font-size: 32rpx;
  font-weight: 600;
}

.result-amount {
  margin-top: 8rpx;
  color: #181818;
  font-weight: 600;

  .yaun {
    font-size: 26rpx;
  }
  .num {
    font-size: 44rpx;
  }
}

.result-subtitle {
  margin-top: 6rpx;
  color: #888;
  font-size: 24rpx;
}

.detail-card {
  margin-top: 24rpx;
  padding: 0 32rpx;
  background: #fff;
  border-radius: 16rpx;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
  padding: 28rpx 0;
  font-size: 28rpx;

  & + .detail-row {
    border-top: 1rpx solid #f0f0f0;
  }
}

.label {
  color: #888;
  flex-shrink: 0;
}

.value {
  color: #181818;
  text-align: right;
  word-break: break-all;
}

.issued-section {
  margin-top: 24rpx;
}

.actions {
  margin-top: 48rpx;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 32rpx;
}

.btn-primary {
  width: 100%;
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #07c160;
  border-radius: 88rpx;
  color: #fff;
  font-size: 32rpx;
  font-weight: 500;
}

.btn-links {
  display: flex;
  align-items: center;
  gap: 24rpx;
}

.link {
  color: #576b95;
  font-size: 28rpx;
}

.link-divider {
  color: #d0d0d0;
  font-size: 24rpx;
}
</style>
