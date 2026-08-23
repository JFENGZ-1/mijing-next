<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchCardProducts } from "@/api/card-products";
import { issueMemberCard } from "@/api/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useApiClient } from "@/api/client";
import { useSessionStore } from "@/stores/session";
import type { CrmMember } from "@/types/crm";
import type { StaffCardProductCatalogItem } from "@/types/member-cards";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const memberId = ref<number>();
const member = ref<CrmMember | null>(null);
const products = ref<StaffCardProductCatalogItem[]>([]);
const selectedProductId = ref<number>();
const openingBalance = ref("");
const openingCount = ref("");
const reason = ref("");
const loading = ref(true);
const submitting = ref(false);
const errorMessage = ref("");

const canIssue = computed(() => session.can("member-card.issue"));
const canLoadProducts = computed(() => session.can("card-product.catalog.read"));

const selectedProduct = computed(() =>
  products.value.find((item) => item.id === selectedProductId.value) ?? null,
);

const isStoredValue = computed(() => selectedProduct.value?.cardType === "stored_value");
const isCount = computed(() => selectedProduct.value?.cardType === "count");

function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[cardType] || cardType;
}

function productSummary(product: StaffCardProductCatalogItem) {
  const parts = [cardTypeLabel(product.cardType), `¥${product.price}`];
  if (product.faceValue) parts.push(`面值 ¥${product.faceValue}`);
  if (product.initialCount != null) parts.push(`${product.initialCount} 次`);
  if (product.validityDays != null) parts.push(`${product.validityDays} 天`);
  return parts.join(" · ");
}

function productHint(product: StaffCardProductCatalogItem | null) {
  if (!product) return "";
  if (product.cardType === "stored_value") {
    return product.faceValue ? `默认开卡余额 ¥${product.faceValue}，可填写自定义开卡金额` : "可填写开卡金额";
  }
  if (product.cardType === "count") {
    return product.initialCount != null
      ? `默认开卡次数 ${product.initialCount}，可填写自定义开卡次数`
      : "可填写开卡次数";
  }
  if (product.cardType === "period") {
    return product.validityDays != null ? `开卡后有效期 ${product.validityDays} 天` : "";
  }
  return "";
}

async function loadMember() {
  if (!memberId.value || !session.currentSiteId) return;
  const response = await useApiClient().request<CrmMember>(
    `/staff/sites/${session.currentSiteId}/members/${memberId.value}`,
  );
  member.value = response.data;
}

async function loadProducts() {
  if (!session.currentSiteId || !canLoadProducts.value) return;
  const response = await fetchCardProducts(session.currentSiteId);
  products.value = response.data.items.filter((item) => item.saleStatus === "on_sale");
}

async function loadPage() {
  if (!memberId.value || memberId.value < 1 || !session.currentSiteId) {
    errorMessage.value = "会员参数或场馆上下文无效";
    loading.value = false;
    return;
  }
  if (!canIssue.value) {
    errorMessage.value = "暂无发卡权限";
    loading.value = false;
    return;
  }
  if (!canLoadProducts.value) {
    errorMessage.value = "暂无卡产品目录查看权限";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    await Promise.all([loadMember(), loadProducts()]);
    if (products.value.length === 0) {
      errorMessage.value = "暂无可售卡产品";
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "页面加载失败";
  } finally {
    loading.value = false;
  }
}

function chooseProduct() {
  if (products.value.length === 0) return;
  uni.showActionSheet({
    itemList: products.value.map((item) => `${item.name}（${cardTypeLabel(item.cardType)}）`),
    success: ({ tapIndex }) => {
      const product = products.value[tapIndex];
      selectedProductId.value = product.id;
      openingBalance.value = "";
      openingCount.value = "";
    },
  });
}

async function submit() {
  if (!memberId.value || !session.currentSiteId || !selectedProduct.value) {
    uni.showToast({ title: "请选择卡产品", icon: "none" });
    return;
  }
  const payload: {
    cardProductId: number;
    commandKey: string;
    openingBalance?: number;
    openingCount?: number;
    reason?: string;
  } = {
    cardProductId: selectedProduct.value.id,
    commandKey: createCommandKey(),
  };
  if (reason.value.trim()) payload.reason = reason.value.trim();
  if (isStoredValue.value && openingBalance.value.trim()) {
    const amount = Number(openingBalance.value);
    if (!Number.isFinite(amount) || amount < 0) {
      uni.showToast({ title: "请输入有效开卡金额", icon: "none" });
      return;
    }
    payload.openingBalance = amount;
  }
  if (isCount.value && openingCount.value.trim()) {
    const count = Number.parseInt(openingCount.value, 10);
    if (!Number.isFinite(count) || count < 1) {
      uni.showToast({ title: "请输入有效开卡次数", icon: "none" });
      return;
    }
    payload.openingCount = count;
  }

  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认发卡",
      content: `为「${member.value?.name || "会员"}」发放「${selectedProduct.value!.name}」？`,
      success: (result) => resolve(!!result.confirm),
    });
  });
  if (!confirmed) return;

  submitting.value = true;
  try {
    const response = await issueMemberCard(session.currentSiteId, memberId.value, payload);
    uni.showToast({ title: "发卡成功", icon: "success" });
    setTimeout(() => {
      uni.redirectTo({
        url: `/pages/members/card-detail?memberId=${memberId.value}&memberCardId=${response.data.id}`,
      });
    }, 400);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "发卡失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

onLoad((options) => {
  memberId.value = Number(options?.memberId ?? options?.id);
});

onShow(async () => {
  if (await requireStaffAuth()) await loadPage();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <template v-else-if="member">
      <view class="section-band">
        <view class="section-heading">发卡会员</view>
        <view class="meta">{{ member.name }} · {{ member.memberNo }}</view>
      </view>

      <view class="section-band">
        <view class="section-heading">卡产品</view>
        <view class="picker-row" @click="chooseProduct">
          <text v-if="selectedProduct" class="picker-value">{{ selectedProduct.name }}</text>
          <text v-else class="picker-placeholder">选择要发放的卡产品</text>
          <u-icon name="arrow-right" size="16" color="#bfbfbf" />
        </view>
        <view v-if="selectedProduct" class="meta">{{ productSummary(selectedProduct) }}</view>
        <view v-if="selectedProduct" class="section-hint">{{ productHint(selectedProduct) }}</view>
      </view>

      <view v-if="selectedProduct && (isStoredValue || isCount)" class="section-band">
        <view class="section-heading">开卡额度（可选）</view>
        <u-input
          v-if="isStoredValue"
          v-model="openingBalance"
          type="digit"
          placeholder="开卡金额，留空使用产品默认值"
        />
        <u-input
          v-if="isCount"
          v-model="openingCount"
          type="number"
          placeholder="开卡次数，留空使用产品默认值"
        />
      </view>

      <view class="section-band">
        <view class="section-heading">备注（可选）</view>
        <u-input v-model="reason" placeholder="发卡原因或备注" maxlength="500" />
      </view>

      <view class="section-band">
        <u-button type="primary" :loading="submitting" :disabled="!selectedProduct" @click="submit">确认发卡</u-button>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.detail-page { padding-bottom: 48rpx; }
.section-band { margin-top: 16rpx; padding: 28rpx 24rpx; background: $color-surface; border-radius: 20rpx; }
.section-heading { font-size: 30rpx; font-weight: 600; }
.section-hint, .meta { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.picker-row { display: flex; align-items: center; justify-content: space-between; margin-top: 20rpx; padding: 24rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 12rpx; }
.picker-value { font-size: 28rpx; }
.picker-placeholder { color: $color-text-secondary; font-size: 28rpx; }
</style>
