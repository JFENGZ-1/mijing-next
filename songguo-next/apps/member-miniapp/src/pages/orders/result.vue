<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberOrder, resumeMemberOrderPayment, syncMemberOrderPayment } from "@/api/member";
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

const syncing = ref(false);
const paying = ref(false);

function requestWechatPayment(params: {
  timeStamp: string;
  nonceStr: string;
  package: string;
  signType: string;
  paySign: string;
}) {
  return new Promise<boolean>((resolve) => {
    uni.requestPayment({
      provider: "wxpay",
      timeStamp: params.timeStamp,
      nonceStr: params.nonceStr,
      package: params.package,
      signType: params.signType as "RSA",
      paySign: params.paySign,
      success: () => resolve(true),
      fail: () => resolve(false),
    } as UniApp.RequestPaymentOptions);
  });
}

async function continuePayment() {
  if (paying.value || order.value?.status !== "pending_payment") return;
  paying.value = true;
  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) return;
    const response = await resumeMemberOrderPayment(tenant.tenantId, orderId.value);
    order.value = response.data.order;
    const payment = response.data.payment;
    if (payment.driver !== "wechat" || payment.configured === false || !payment.paymentParams) {
      uni.showToast({ title: "微信支付暂不可用，请稍后再试", icon: "none" });
      return;
    }
    const accepted = await requestWechatPayment(payment.paymentParams);
    if (!accepted) {
      uni.showToast({ title: "支付未完成，订单仍为待支付", icon: "none" });
      return;
    }
    const synced = await syncMemberOrderPayment(tenant.tenantId, orderId.value);
    order.value = synced.data;
    uni.showToast({
      title: synced.data.status === "paid" ? "支付成功" : "支付结果确认中",
      icon: synced.data.status === "paid" ? "success" : "none",
    });
  } catch (error) {
    uni.showToast({ title: formatApiErrorMessage(error, "继续支付失败"), icon: "none" });
  } finally {
    paying.value = false;
  }
}

async function refreshPaymentStatus() {
  if (syncing.value) return;
  syncing.value = true;
  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) return;
    const response = await syncMemberOrderPayment(tenant.tenantId, orderId.value);
    order.value = response.data;
    if (response.data.status === "paid") {
      uni.showToast({ title: "支付成功", icon: "success" });
    } else {
      uni.showToast({ title: "尚未查询到支付结果", icon: "none" });
    }
  } catch (error) {
    uni.showToast({ title: formatApiErrorMessage(error, "查询失败"), icon: "none" });
  } finally {
    syncing.value = false;
  }
}

function openCardDetail() {
  if (!order.value?.memberCard) return;
  uni.navigateTo({ url: `/pages/cards/detail?id=${order.value.memberCard.id}` });
}

function openOrders() {
  uni.navigateTo({ url: "/pages/orders/index" });
}

function goHome() {
  uni.switchTab({ url: "/pages/index/index" });
}

function goMine() {
  uni.switchTab({ url: "/pages/mine/index" });
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
      <view class="hint-content">
        <view
          class="status-icon-wrap"
          :class="{
            'status-icon-wrap--pending': order.status === 'pending_payment',
            'status-icon-wrap--muted': order.status !== 'paid' && order.status !== 'pending_payment',
          }"
        >
          <view v-if="order.status === 'paid'" class="check"></view>
          <u-icon v-else-if="order.status === 'pending_payment'" name="clock" size="34" color="#fff" />
          <u-icon v-else name="close" size="30" color="#fff" />
        </view>
        <view
          class="status-text"
          :class="{
            'status-text--pending': order.status === 'pending_payment',
            'status-text--muted': order.status !== 'paid' && order.status !== 'pending_payment',
          }"
        >
          {{ order.status === "paid" ? "购卡成功" : orderStatusLabel(order.status) }}
        </view>
        <view v-if="order.effectiveAmount" class="result-amount">
          <text class="yuan">¥</text><text class="num">{{ order.effectiveAmount }}</text>
        </view>
        <view class="hint-text-sub">
          <template v-if="order.status === 'paid'">
            <view>可在“我的”中查看</view>
            <view>如拥有多张，点击后面的卡即可切换至前面</view>
          </template>
          <view v-else-if="order.status === 'pending_payment'">完成微信支付后，会员卡将自动发放</view>
          <view v-else>{{ order.productName || "会员卡订单" }}</view>
        </view>
      </view>

      <view v-if="issuedCard" class="issued-section" @tap="openCardDetail">
        <member-card :card="issuedCard" />
      </view>

      <view class="detail-card">
        <view class="detail-row">
          <text class="label">订单编号</text>
          <text class="value">{{ order.orderNo }}</text>
        </view>
        <view v-if="order.siteName" class="detail-row">
          <text class="label">场馆</text>
          <text class="value">{{ order.siteName }}</text>
        </view>
        <view v-if="order.productName" class="detail-row">
          <text class="label">卡种</text>
          <text class="value">{{ order.productName }}</text>
        </view>
        <view v-if="order.createdAt" class="detail-row">
          <text class="label">下单时间</text>
          <text class="value">{{ formatIsoDate(order.createdAt) }}</text>
        </view>
      </view>

      <view class="btn-wrap">
        <view v-if="order.memberCard" class="btn btn--primary" @tap="goMine">马上查看</view>
        <view
          v-else-if="order.status === 'pending_payment'"
          class="btn btn--primary"
          :class="{ 'btn--loading': paying }"
          @tap="continuePayment"
        >
          {{ paying ? "支付处理中..." : "继续支付" }}
        </view>
        <view
          v-if="order.status === 'pending_payment'"
          class="btn btn--plain"
          :class="{ 'btn--loading': syncing }"
          @tap="refreshPaymentStatus"
        >
          {{ syncing ? "查询中..." : "我已支付，刷新状态" }}
        </view>
        <view class="btn btn--plain" @tap="goHome">回首页</view>
        <view class="order-link" @tap="openOrders">查看全部订单 ›</view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  background: $color-surface;
  padding: 0 40rpx 60rpx;
}

/* 对标原版 buySuccess：白底居中大状态图标 */
.hint-content {
  padding-top: 90rpx;
  text-align: center;
}

.status-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 140rpx;
  height: 140rpx;
  margin: 0 auto;
  background: $color-primary;
  border-radius: 50%;
  box-shadow: 0 10rpx 30rpx rgba(34, 199, 136, 0.28);
}

.status-icon-wrap--pending {
  background: #ffae00;
  box-shadow: 0 10rpx 30rpx rgba(255, 174, 0, 0.28);
}

.status-icon-wrap--muted {
  background: #bfbfbf;
  box-shadow: none;
}

.check {
  width: 52rpx;
  height: 26rpx;
  border-left: 8rpx solid #fff;
  border-bottom: 8rpx solid #fff;
  border-radius: 2rpx;
  transform: rotate(-45deg) translate(2rpx, -6rpx);
}

.status-text {
  margin-top: 32rpx;
  color: $color-primary;
  font-size: 34rpx;
  font-weight: 600;
}

.status-text--pending {
  color: #ffae00;
}

.status-text--muted {
  color: $color-text-muted;
}

.result-amount {
  margin-top: 20rpx;
  color: $color-text;
  font-weight: 700;

  .yuan {
    margin-right: 4rpx;
    font-size: 30rpx;
  }

  .num {
    font-size: 56rpx;
  }
}

.hint-text-sub {
  margin-top: 14rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
  line-height: 36rpx;
}

.issued-section {
  margin-top: 50rpx;
}

/* 订单信息：浅灰内嵌卡 */
.detail-card {
  margin-top: 40rpx;
  padding: 8rpx 30rpx;
  background: $color-page;
  border-radius: 16rpx;
}

.detail-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
  font-size: 26rpx;
  line-height: 64rpx;

  & + .detail-row {
    border-top: 1rpx solid rgba(0, 0, 0, 0.04);
  }
}

.label {
  flex-shrink: 0;
  color: $color-text-muted;
}

.value {
  color: $color-text;
  text-align: right;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

/* 对标原版：圆角大按钮组 */
.btn-wrap {
  margin-top: 70rpx;
}

.btn {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 88rpx;
  border-radius: 44rpx;
  font-size: 30rpx;
  font-weight: 500;
}

.btn--primary {
  background: $color-primary;
  color: #fff;
  box-shadow: 0 10rpx 24rpx rgba(34, 199, 136, 0.25);
}

.btn--plain {
  margin-top: 28rpx;
  background: $color-surface;
  border: 1rpx solid $color-border-strong;
  color: $color-text;
}

.btn--loading {
  opacity: 0.6;
}

.order-link {
  margin-top: 40rpx;
  color: $color-text-muted;
  font-size: 26rpx;
  text-align: center;
}
</style>
