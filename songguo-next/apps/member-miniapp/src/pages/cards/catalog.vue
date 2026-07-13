<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import {
  getMemberCardProductCatalog,
  getMemberPurchaseGate,
  submitMemberCardPurchase,
} from "@/api/member";
import { ensureMemberContext } from "@/composables/member-context";
import type { MemberCardProductCatalogItem, MemberCardPurchaseResult } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { createCommandKey } from "@/utils/command-key";
import { cardProductSummary, cardTypeLabel, memberCardStatusLabel } from "@/utils/format";

const MODAL_DISMISS_DELAY_MS = 300;

const purchasingId = ref<number | null>(null);
const errorMessage = ref("");
const products = ref<MemberCardProductCatalogItem[]>([]);
const purchaseCommandKeys = new Map<number, string>();

const profileModalShow = ref(false);
const profileModalLabels = ref("");
const confirmModalShow = ref(false);
const confirmProduct = ref<MemberCardProductCatalogItem | null>(null);
const successModalShow = ref(false);
const successModalData = ref<{
  card: NonNullable<MemberCardPurchaseResult["memberCard"]>;
  productName: string;
  orderId: number;
} | null>(null);

const loading = ref(true);

const confirmModalContent = computed(() => {
  const product = confirmProduct.value;
  if (!product) return "";
  return `确定购买「${product.name}」吗？\n${cardProductSummary(product)}\n\n当前为演示购卡，无需微信支付，下单后将直接发放会员卡。`;
});

const successModalContent = computed(() => {
  const data = successModalData.value;
  if (!data) return "";
  const cardName = data.card.name || data.productName;
  const statusLabel = memberCardStatusLabel(data.card.status);
  return `「${cardName}」已发放，状态：${statusLabel}`;
});

function showToast(title: string, type: "default" | "success" | "error" = "default", duration = 2000) {
  uni.$u.toast({ message: title, type, duration });
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

async function ensurePurchaseAllowed(tenantId: number) {
  const response = await getMemberPurchaseGate(tenantId);
  if (response.data.allowed) return true;

  profileModalLabels.value = response.data.missingFields.map((field) => field.label).join("、");
  profileModalShow.value = true;
  return false;
}

function onProfileModalConfirm() {
  profileModalShow.value = false;
  uni.navigateTo({ url: "/pages/mine/profile" });
}

async function loadCatalog() {
  errorMessage.value = "";
  products.value = [];

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

function waitForModalDismiss() {
  return new Promise<void>((resolve) => setTimeout(resolve, MODAL_DISMISS_DELAY_MS));
}

function confirmPurchase(product: MemberCardProductCatalogItem) {
  confirmProduct.value = product;
  confirmModalShow.value = true;
}

async function onConfirmPurchase() {
  const product = confirmProduct.value;
  confirmModalShow.value = false;
  confirmProduct.value = null;
  if (!product) return;
  await waitForModalDismiss();
  await purchaseProduct(product);
}

function navigateAfterPurchaseSuccess(orderId: number, cardId: number, viewCard: boolean) {
  successModalShow.value = false;
  successModalData.value = null;
  if (viewCard) {
    uni.redirectTo({ url: `/pages/cards/detail?id=${cardId}` });
    return;
  }
  uni.redirectTo({ url: `/pages/orders/result?id=${orderId}` });
}

function onSuccessViewCard() {
  const data = successModalData.value;
  if (!data) return;
  navigateAfterPurchaseSuccess(data.orderId, data.card.id, true);
}

function onSuccessViewOrder() {
  const data = successModalData.value;
  if (!data) return;
  navigateAfterPurchaseSuccess(data.orderId, data.card.id, false);
}

function showPurchaseSuccessModal(
  card: NonNullable<MemberCardPurchaseResult["memberCard"]>,
  productName: string,
  orderId: number,
) {
  successModalData.value = { card, productName, orderId };
  successModalShow.value = true;
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

    const card = response.data.memberCard;
    if (!card) {
      const pendingTitle =
        response.data.payment?.driver === "wechat"
          ? "订单已创建，请完成微信支付"
          : "订单已创建，请完成支付";
      showToast(pendingTitle, "default", 3000);
      setTimeout(() => {
        uni.navigateTo({ url: `/pages/orders/result?id=${response.data.order.id}` });
      }, 800);
      return;
    }

    await waitForModalDismiss();
    showPurchaseSuccessModal(card, product.name, response.data.order.id);
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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="demo-hint">演示购卡：无需微信支付，下单后直接发放会员卡。</view>

    <u-empty v-if="products.length === 0 && !errorMessage" mode="list" text="暂无可购卡品" />

    <view v-for="product in products" :key="product.id" class="product-item">
      <view class="product-header">
        <view class="product-name">{{ product.name }}</view>
        <view class="type-tag">{{ cardTypeLabel(product.cardType) }}</view>
      </view>
      <view v-if="product.description" class="product-desc">{{ product.description }}</view>
      <view class="product-meta">{{ cardProductSummary(product) }}</view>
      <view class="product-meta">{{ productValidityLabel(product) }}</view>
      <view class="product-actions">
        <u-button
          type="primary"
          size="small"
          :loading="purchasingId === product.id"
          @click="confirmPurchase(product)"
        >
          立即购买
        </u-button>
      </view>
    </view>
  </view>

  <u-modal
    :show="profileModalShow"
    title="资料未完善"
    :content="`购卡前请先完善：${profileModalLabels}`"
    confirm-text="去完善"
    cancel-text="取消"
    :show-cancel-button="true"
    @confirm="onProfileModalConfirm"
    @cancel="profileModalShow = false"
    @close="profileModalShow = false"
  />

  <u-modal
    :show="confirmModalShow"
    title="确认购买"
    :content="confirmModalContent"
    confirm-text="确认购买"
    cancel-text="取消"
    :show-cancel-button="true"
    @confirm="onConfirmPurchase"
    @cancel="confirmModalShow = false"
    @close="confirmModalShow = false"
  />

  <u-modal
    :show="successModalShow"
    title="购卡成功"
    :content="successModalContent"
    confirm-text="查看会员卡"
    cancel-text="返回钱包"
    :show-cancel-button="true"
    @confirm="onSuccessViewCard"
    @cancel="onSuccessViewOrder"
    @close="successModalShow = false"
  />
</template>

<style scoped lang="scss">
.demo-hint {
  margin-bottom: $spacing-md;
  padding: $spacing-sm $spacing-md;
  color: $color-text-secondary;
  font-size: 24rpx;
  background: #fffbeb;
  border: 1rpx solid #fde68a;
  border-radius: $radius-md;
}

.product-item {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.product-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.product-name {
  font-size: 30rpx;
  font-weight: 600;
}

.type-tag {
  padding: 4rpx 12rpx;
  color: $color-primary;
  font-size: 22rpx;
  background: $color-primary-light;
  border-radius: $radius-sm;
}

.product-desc {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.product-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.product-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: $spacing-sm;
}
</style>
