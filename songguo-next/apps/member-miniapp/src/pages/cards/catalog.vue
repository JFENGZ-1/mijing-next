<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import {
  getMemberCardProductCatalog,
  getMemberCashWallet,
  getMemberPurchaseGate,
  submitMemberCardPurchase,
  syncMemberOrderPayment,
} from "@/api/member";
import { ensureMemberContext } from "@/composables/member-context";
import type {
  MemberCardPaymentMethod,
  MemberCardProductCatalogItem,
  MemberCashWallet,
} from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { createCommandKey } from "@/utils/command-key";
import { cardTypeLabel } from "@/utils/format";

const purchasingId = ref<number | null>(null);
const errorMessage = ref("");
const products = ref<MemberCardProductCatalogItem[]>([]);
const wallet = ref<MemberCashWallet | null>(null);
const purchaseCommandKeys = new Map<string, string>();

const loading = ref(true);
const walletBalance = computed(() => wallet.value?.balance ?? null);

function paymentMethods(product: MemberCardProductCatalogItem): MemberCardPaymentMethod[] {
  const configured = product.allowedPaymentMethods?.filter(
    (method): method is MemberCardPaymentMethod => method === "online" || method === "balance",
  );
  return configured?.length ? configured : ["online"];
}

function paymentMethodLabel(method: MemberCardPaymentMethod) {
  if (method === "online") return "在线支付";
  return walletBalance.value === null
    ? "余额支付（余额以服务端为准）"
    : `余额支付（可用 ¥${walletBalance.value}）`;
}

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
    try {
      const walletResponse = await getMemberCashWallet(context.tenantId);
      wallet.value = walletResponse.data;
    } catch {
      // 钱包读取失败不阻断在线购卡；最终支付校验仍由服务端完成。
      wallet.value = null;
    }
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "卡品列表加载失败");
  } finally {
    loading.value = false;
  }
}

function confirmPurchase(product: MemberCardProductCatalogItem) {
  const methods = paymentMethods(product);
  if (methods.length > 1) {
    uni.showActionSheet({
      itemList: methods.map(paymentMethodLabel),
      success: (result) => {
        const method = methods[result.tapIndex];
        if (method) confirmPurchaseWithMethod(product, method);
      },
    });
    return;
  }

  confirmPurchaseWithMethod(product, methods[0] ?? "online");
}

function confirmPurchaseWithMethod(
  product: MemberCardProductCatalogItem,
  paymentMethod: MemberCardPaymentMethod,
) {
  uni.showModal({
    title: "确认购买",
    content: `确定购买「${product.name}」吗？\n${faceValueText(product)}\n售价 ¥${product.price}\n${paymentMethodLabel(paymentMethod)}`,
    confirmText: "确认购买",
    cancelText: "取消",
    success: async (result) => {
      if (!result.confirm) return;
      await purchaseProduct(product, paymentMethod);
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

async function purchaseProduct(
  product: MemberCardProductCatalogItem,
  paymentMethod: MemberCardPaymentMethod,
) {
  const context = await ensureMemberContext();
  if (!context) return;

  const commandMapKey = `${product.id}:${paymentMethod}`;
  let commandKey = purchaseCommandKeys.get(commandMapKey);
  if (!commandKey) {
    commandKey = createCommandKey();
    purchaseCommandKeys.set(commandMapKey, commandKey);
  }

  purchasingId.value = product.id;
  try {
    const response = await submitMemberCardPurchase(context.tenantId, context.siteId, {
      cardProductId: product.id,
      paymentMethod,
      commandKey,
    });
    purchaseCommandKeys.delete(commandMapKey);

    const orderId = response.data.order.id;
    const card = response.data.memberCard;
    if (card) {
      // 余额支付或 demo 驱动：同一事务内完成付款和发卡。
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

    <view v-if="products.length && wallet" class="wallet-banner">
      <view>
        <view class="wallet-label">会员余额</view>
        <view class="wallet-value">¥{{ wallet.balance }}</view>
      </view>
      <view class="wallet-hint">余额支付由服务端原子扣款，不会透支</view>
    </view>

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
      <view class="payment-methods">
        支持 {{ paymentMethods(product).map(paymentMethodLabel).join("、") }}
      </view>
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

.wallet-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24rpx;
  padding: 24rpx 28rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.wallet-label,
.wallet-hint {
  color: $color-text-secondary;
  font-size: 22rpx;
}

.wallet-value {
  margin-top: 4rpx;
  color: $color-text;
  font-size: 36rpx;
  font-weight: 700;
}

.wallet-hint {
  max-width: 360rpx;
  text-align: right;
  line-height: 1.5;
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

.payment-methods {
  padding: 0 28rpx 24rpx;
  color: $color-primary;
  font-size: 22rpx;
}
</style>
