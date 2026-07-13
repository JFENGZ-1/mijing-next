<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberWalletCards, hideMemberCard } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardWalletSummary } from "@/types/member";
import { cardBalanceLabel } from "@/utils/format";
import { createCommandKey } from "@/utils/command-key";

const loading = ref(true);
const hidingId = ref<number | null>(null);
const errorMessage = ref("");
const cards = ref<MemberCardWalletSummary[]>([]);
const hideCommandKeys = new Map<number, string>();

async function loadCards() {
  loading.value = true;
  errorMessage.value = "";
  cards.value = [];

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberWalletCards(tenant.tenantId);
    cards.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员卡列表加载失败";
  } finally {
    loading.value = false;
  }
}

function openDetail(card: MemberCardWalletSummary) {
  uni.navigateTo({ url: `/pages/cards/detail?id=${card.id}` });
}

function openHiddenCards() {
  uni.navigateTo({ url: "/pages/cards/hidden" });
}

function openCardCatalog() {
  uni.navigateTo({ url: "/pages/cards/catalog" });
}

function confirmHide(card: MemberCardWalletSummary) {
  uni.showModal({
    title: "隐藏会员卡",
    content: `确定隐藏「${card.name || "会员卡"}」吗？隐藏后可在「已隐藏」中恢复。`,
    success: async (result) => {
      if (!result.confirm) return;
      await hideCard(card);
    },
  });
}

async function hideCard(card: MemberCardWalletSummary) {
  const tenant = await ensureMemberTenant();
  if (!tenant) return;

  let commandKey = hideCommandKeys.get(card.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    hideCommandKeys.set(card.id, commandKey);
  }

  hidingId.value = card.id;
  try {
    await hideMemberCard(tenant.tenantId, card.id, commandKey);
    hideCommandKeys.delete(card.id);
    uni.showToast({ title: "已隐藏", icon: "success" });
    await loadCards();
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "隐藏失败",
      icon: "none",
    });
  } finally {
    hidingId.value = null;
  }
}

onShow(async () => {
  if (await requireMemberAuth()) await loadCards();
});

onPullDownRefresh(async () => {
  await loadCards();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="toolbar">
      <u-button size="small" plain @click="openHiddenCards">已隐藏</u-button>
      <u-button size="small" type="primary" plain @click="openCardCatalog">购买会员卡</u-button>
    </view>

    <u-empty v-if="cards.length === 0 && !errorMessage" mode="card" text="暂无可用会员卡" />
    <view
      v-for="card in cards"
      :key="card.id"
      class="wallet-card"
      @tap="openDetail(card)"
    >
      <view class="wallet-card-name">{{ card.name || "会员卡" }}</view>
      <view class="wallet-card-meta">{{ card.cardNoMasked }}</view>
      <view class="wallet-card-meta">{{ cardBalanceLabel(card) }}</view>
      <view class="card-actions" @tap.stop>
        <u-button
          size="mini"
          plain
          :loading="hidingId === card.id"
          @click="confirmHide(card)"
        >
          隐藏
        </u-button>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.toolbar {
  display: flex;
  justify-content: flex-end;
  gap: $spacing-sm;
  margin-bottom: $spacing-md;
}

.wallet-card {
  margin-bottom: $spacing-md;
  padding: 24rpx;
  background: linear-gradient(135deg, #faf5f8 0%, #fff 100%);
  border: 1rpx solid #f3e8ee;
  border-radius: $radius-md;
}

.wallet-card-name {
  color: $color-accent-pink;
  font-size: 30rpx;
  font-weight: 600;
}

.wallet-card-meta {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: $spacing-sm;
}
</style>
