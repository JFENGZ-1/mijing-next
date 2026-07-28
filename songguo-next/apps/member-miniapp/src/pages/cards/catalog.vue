<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import {
  getMemberCardProductCatalog,
  getMemberPurchaseGate,
  submitMemberCardPurchase,
  syncMemberOrderPayment,
} from "@/api/member";
import { ensureMemberContext } from "@/composables/member-context";
import type { MemberCardProductCatalogItem } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { createCommandKey } from "@/utils/command-key";
import { cardTypeLabel } from "@/utils/format";

const purchasingId = ref<number | null>(null);
const errorMessage = ref("");
const products = ref<MemberCardProductCatalogItem[]>([]);
const purchaseCommandKeys = new Map<number, string>();

const loading = ref(true);

function showToast(title: string, _type: "default" | "success" | "error" = "default", duration = 2000) {
  uni.showToast({ title, icon: _type === "success" ? "success" : "none", duration });
}

function productValidityLabel(product: MemberCardProductCatalogItem) {
  if (product.validityDays != null) {
    return product.activationMode === "on_first_use"
      ? `购卡后 ${product.validityDays} 天内激活有效`
      : `有效期 ${product.validityDays} 天`;
  }
  if (product.activationMode === "on_first_use") {
    return "首次使用后激活";
  }
  return "购卡后立即生效";
}

// 卡面图案：后端图案库直发 faceGradient（总 Web 后台可控），未配置回退类型默认
function cardGradient(product: { cardType: string; faceGradient?: string | null }) {
  if (product.faceGradient) return product.faceGradient;
  switch (product.cardType) {
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

function faceValueText(product: MemberCardProductCatalogItem) {
  if (product.cardType === "count" && product.initialCount != null) return `${product.initialCount} 次`;
  if (product.cardType === "stored_value" && product.faceValue) return `面值 ¥${product.faceValue}`;
  if (product.cardType === "period" && product.validityDays != null) return `${product.validityDays} 天`;
  return "";
}

async function ensurePurchaseAllowed(tenantId: number) {
  const response = await getMemberPurchaseGate(tenantId);
  if (response.data.allowed) return true;

  const labels = response.data.missingFields.map((field) => field.label).join("、");
  uni.showModal({
    title: "资料未完善",
    content: `购卡前请先完善：${labels}`,
    confirmText: "去完善",
    cancelText: "取消",
    success: (result) => {
      if (result.confirm) uni.navigateTo({ url: "/pages/mine/profile" });
    },
  });
  return false;
}

async function loadCatalog() {
  errorMessage.value = "";

  try {
    const context = await ensureMemberContext();
    if (!context) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    if (!(await ensurePurchaseAllowed(context.tenantId))) {
      return;
    }

    const response = await getMemberCardProductCatalog(context.tenantId, context.siteId);
    products.value = response.data.items;
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "卡品列表加载失败");
  } finally {
    loading.value = false;
  }
}

function confirmPurchase(product: MemberCardProductCatalogItem) {
  uni.showModal({
    title: "确认购买",
    content: `确定购买「${product.name}」吗？\n${faceValueText(product)}\n售价 ¥${product.price}`,
    confirmText: "确认购买",
    cancelText: "取消",
    success: async (result) => {
      if (!result.confirm) return;
      await purchaseProduct(product);
    },
  });
}

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

async function purchaseProduct(product: MemberCardProductCatalogItem) {
  const context = await ensureMemberContext();
  if (!context) return;

  let commandKey = purchaseCommandKeys.get(product.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    purchaseCommandKeys.set(product.id, commandKey);
  }

  purchasingId.value = product.id;
  try {
    const response = await submitMemberCardPurchase(context.tenantId, context.siteId, {
      cardProductId: product.id,
      commandKey,
    });
    purchaseCommandKeys.delete(product.id);

    const orderId = response.data.order.id;
    const card = response.data.memberCard;
    if (card) {
      // demo 驱动：下单即发卡
      await new Promise((resolve) => setTimeout(resolve, 300));
      uni.redirectTo({ url: `/pages/orders/result?id=${orderId}` });
      return;
    }

    const payment = response.data.payment;
    if (payment?.driver === "wechat" && payment.configured !== false && payment.paymentParams) {
      const paid = await requestWechatPayment(payment.paymentParams);
      if (paid) {
        // 支付成功后主动同步一次（回调可能有延迟）
        try {
          await syncMemberOrderPayment(context.tenantId, orderId);
        } catch {
          // 同步失败不阻塞，结果页可手动刷新
        }
      } else {
        showToast("支付未完成，可在订单页继续查看", "default", 2500);
      }
      uni.redirectTo({ url: `/pages/orders/result?id=${orderId}` });
      return;
    }

    showToast("订单已创建，请完成支付", "default", 3000);
    setTimeout(() => {
      uni.navigateTo({ url: `/pages/orders/result?id=${orderId}` });
    }, 800);
  } catch (error) {
    showToast(formatApiErrorMessage(error, "购卡失败"), "error");
  } finally {
    purchasingId.value = null;
  }
}

onShow(async () => { if (await requireMemberAuth()) await loadCatalog(); });

onPullDownRefresh(async () => { await loadCatalog(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="catalog-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <u-empty v-if="products.length === 0 && !errorMessage" mode="list" text="~ 没有会员卡出售哦 ~" />

    <view v-if="products.length" class="list-title">共{{ products.length }}种</view>

    <view v-for="product in products" :key="product.id" class="product-item">
      <view class="card-face" :style="{ backgroundImage: cardGradient(product) }">
        <view class="ribbon">{{ cardTypeLabel(product.cardType) }}</view>
        <view class="card-name">{{ product.name }}</view>
        <view class="face-value">{{ faceValueText(product) }}</view>
      </view>

      <view class="price-row">
        <view class="price-left">
          <view class="price-title">售价</view>
          <view class="price-text">
            <text class="unit">¥</text>
            <text class="amount">{{ product.price }}</text>
          </view>
        </view>
        <view
          class="buy-btn"
          :class="{ 'buy-btn--disabled': purchasingId === product.id }"
          @tap="confirmPurchase(product)"
        >
          {{ purchasingId === product.id ? "购买中" : "立即购买" }}
        </view>
      </view>

      <view v-if="product.description" class="product-desc">{{ product.description }}</view>
      <view class="product-meta">{{ productValidityLabel(product) }}</view>
    </view>

    <bottom-logo v-if="products.length" />
  </view>
</template>

<style scoped lang="scss">
.catalog-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.list-title {
  margin-bottom: 20rpx;
  color: $color-text;
  font-size: 28rpx;
  font-weight: 600;
}

.product-item {
  margin-bottom: 28rpx;
  background: $color-surface;
  border-radius: $radius-md;
  overflow: hidden;
}

.card-face {
  position: relative;
  height: 280rpx;
  padding: 32rpx;
  box-sizing: border-box;
  color: #fff;
  background-size: 100% 100%;
}

.ribbon {
  display: inline-flex;
  align-items: center;
  padding: 6rpx 18rpx;
  background: rgba(255, 255, 255, 0.22);
  border-radius: 8rpx;
  font-size: 22rpx;
}

.card-name {
  margin-top: 24rpx;
  font-size: 40rpx;
  font-weight: 600;
  line-height: 52rpx;
  text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.15);
}

.face-value {
  position: absolute;
  bottom: 32rpx;
  left: 32rpx;
  font-size: 32rpx;
  font-weight: 600;
}

.price-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 28rpx;
}

.price-title {
  color: $color-text-secondary;
  font-size: 22rpx;
}

.price-text {
  display: flex;
  align-items: baseline;
  margin-top: 4rpx;
}

.unit {
  color: $color-accent-pink;
  font-size: 26rpx;
  font-weight: 600;
}

.amount {
  color: $color-accent-pink;
  font-size: 44rpx;
  font-weight: 700;
}

.buy-btn {
  padding: 12rpx 36rpx;
  background: $color-primary;
  border-radius: 36rpx;
  color: #fff;
  font-size: 26rpx;
}

.buy-btn--disabled {
  opacity: 0.5;
}

.product-desc {
  padding: 0 28rpx 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.5;
}

.product-meta {
  padding: 0 28rpx 24rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}
</style>
