<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { getMemberOrders } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberOrderSummary } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { cardTypeLabel, formatIsoDate, orderStatusLabel } from "@/utils/format";

function cardGradient(cardType: string | undefined) {
  switch (cardType) {
    case "stored_value":
      return "linear-gradient(135deg, #c96a32 0%, #a8521f 100%)";
    case "count":
      return "linear-gradient(135deg, #2a5fb6 0%, #003d82 100%)";
    case "period":
      return "linear-gradient(135deg, #349f91 0%, #2a877c 100%)";
    default:
      return "linear-gradient(135deg, #696b99 0%, #4a4d7a 100%)";
  }
}

// 对标原版：已支付显示"交易完成"
function orderStateText(status: string) {
  return status === "paid" ? "交易完成" : orderStatusLabel(status);
}

function orderStateClass(status: string) {
  if (status === "paid") return "state--paid";
  if (status === "pending_payment") return "state--pending";
  return "state--muted";
}

function cardFaceText(order: MemberOrderSummary) {
  const card = order.memberCard;
  if (!card) return "";
  if (card.cardType === "count" && card.cachedRemainingCount != null) return `${card.cachedRemainingCount} 次`;
  if (card.cardType === "stored_value" && card.cachedBalance) return `¥${card.cachedBalance}`;
  if (card.cardType === "period" && card.validUntil) return `${card.validUntil} 到期`;
  return "";
}

const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const orders = ref<MemberOrderSummary[]>([]);
const page = ref(1);
const lastPage = ref(1);

const hasLoaded = ref(false);

async function loadOrders(reset = true) {
  if (!reset) {
    loadingMore.value = true;
  } else {
    loading.value = !hasLoaded.value;
    page.value = 1;
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
    hasLoaded.value = true;
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
        <view class="state" :class="orderStateClass(order.status)">{{ orderStateText(order.status) }}</view>
      </view>

      <view class="card-info-wrap">
        <view class="mini-card" :style="{ backgroundImage: cardGradient(order.memberCard?.cardType) }">
          <view class="mini-card-shine" />
          <view class="mini-card-type">{{ cardTypeLabel(order.memberCard?.cardType ?? "") || "会员卡" }}</view>
          <view class="mini-card-name">{{ order.productName || order.memberCard?.name || "会员卡" }}</view>
          <view v-if="cardFaceText(order)" class="mini-card-face">{{ cardFaceText(order) }}</view>
        </view>
        <view class="amount-wrap">
          <text class="amount-title">实付款</text>
          <view class="amount-line">
            <text class="amount-symbol">¥</text>
            <text class="amount-num">{{ order.effectiveAmount }}</text>
          </view>
        </view>
      </view>

      <view class="info-wrap">
        <view class="info-item">
          <view class="info-title">订单编号</view>
          <view class="info-data">{{ order.orderNo }}</view>
        </view>
        <view v-if="order.siteName" class="info-item">
          <view class="info-title">场馆</view>
          <view class="info-data">{{ order.siteName }}</view>
        </view>
        <view v-if="order.createdAt" class="info-item">
          <view class="info-title">下单时间</view>
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
  padding: 8rpx 32rpx 30rpx;
  background: $color-surface;
  border-radius: $radius-md;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.03);
}

/* 对标原版：标题行 80rpx 行高 + 底部分隔线 */
.title-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  line-height: 80rpx;
  border-bottom: 1rpx solid $color-border;
}

.entry {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 500;
}

.state {
  font-size: 26rpx;
}

.state--paid {
  color: $color-primary;
}

.state--pending {
  color: #fc8c00;
}

.state--muted {
  color: $color-text-muted;
}

/* 对标原版：左侧卡面缩略（330rpx）+ 右侧实付款 */
.card-info-wrap {
  display: flex;
  align-items: center;
  gap: 24rpx;
  padding: 32rpx 0 8rpx;
}

.mini-card {
  position: relative;
  flex-shrink: 0;
  width: 330rpx;
  height: 190rpx;
  padding: 20rpx 24rpx;
  box-sizing: border-box;
  background-size: 100% 100%;
  border-radius: 14rpx;
  overflow: hidden;
  box-shadow: 0 6rpx 16rpx rgba(0, 0, 0, 0.12);
}

/* 卡面高光，提升质感 */
.mini-card-shine {
  position: absolute;
  top: -60rpx;
  right: -40rpx;
  width: 220rpx;
  height: 220rpx;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
}

.mini-card-type {
  display: inline-flex;
  padding: 2rpx 14rpx;
  background: rgba(255, 255, 255, 0.24);
  border-radius: 6rpx;
  color: #fff;
  font-size: 19rpx;
}

.mini-card-name {
  margin-top: 16rpx;
  color: #fff;
  font-size: 30rpx;
  font-weight: 600;
  line-height: 38rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  text-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.15);
}

.mini-card-face {
  position: absolute;
  bottom: 18rpx;
  left: 24rpx;
  color: rgba(255, 255, 255, 0.85);
  font-size: 22rpx;
}

.amount-wrap {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: flex-end;
  padding-top: 34rpx;
}

.amount-title {
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 30rpx;
}

.amount-line {
  display: flex;
  align-items: baseline;
  margin-top: 8rpx;
}

.amount-symbol {
  margin-right: 4rpx;
  color: $color-text;
  font-size: 26rpx;
  font-weight: 600;
}

.amount-num {
  color: $color-text;
  font-size: 44rpx;
  font-weight: 700;
}

/* 对标原版：左灰标签 + 右对齐值，行高 50rpx */
.info-wrap {
  margin-top: 18rpx;
  padding-top: 12rpx;
  border-top: 1rpx solid $color-border;
}

.info-item {
  display: flex;
  align-items: center;
  line-height: 50rpx;
}

.info-title {
  flex-shrink: 0;
  width: 200rpx;
  color: $color-text-muted;
  font-size: 26rpx;
}

.info-data {
  flex: 1;
  color: $color-text;
  font-size: 26rpx;
  text-align: right;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.loadmore-wrap {
  padding: 12rpx 0;
}
</style>
