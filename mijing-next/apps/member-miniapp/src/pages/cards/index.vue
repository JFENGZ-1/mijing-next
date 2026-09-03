<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberWalletCards, hideMemberCard } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";

const loading = ref(true);
const hidingId = ref<number | null>(null);
const errorMessage = ref("");
const cards = ref<MemberCardWalletSummary[]>([]);
const hideCommandKeys = new Map<number, string>();

const hasLoaded = ref(false);

async function loadCards() {
  loading.value = !hasLoaded.value;
  errorMessage.value = "";

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
    hasLoaded.value = true;
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
  <view v-if="!loading" class="cards-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <view class="toolbar">
      <view class="tool-pill" @click="openHiddenCards">已隐藏</view>
      <view class="tool-pill primary" @click="openCardCatalog">购买会员卡</view>
    </view>

    <view v-if="cards.length === 0 && !errorMessage" class="no-card">
      <view class="hint-text">您还没有会员卡哦</view>
    </view>

    <view v-for="card in cards" :key="card.id" class="card-block">
      <view class="card-tap" @tap="openDetail(card)">
        <member-card :card="card" />
      </view>
      <view class="card-actions">
        <view
          class="action-btn"
          :class="{ 'action-btn--disabled': hidingId === card.id }"
          @tap.stop="confirmHide(card)"
        >
          {{ hidingId === card.id ? '隐藏中...' : '隐藏此卡' }}
        </view>
      </view>
    </view>

    <bottom-logo />
  </view>
</template>

<style scoped lang="scss">
.cards-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.toolbar {
  display: flex;
  justify-content: flex-end;
  gap: 16rpx;
  margin-bottom: 24rpx;
}

.tool-pill {
  padding: 10rpx 28rpx;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: 28rpx;
  color: $color-text-secondary;
  font-size: 24rpx;

  &.primary {
    background: $color-primary;
    border-color: $color-primary;
    color: #fff;
  }
}

.no-card {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 240rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.hint-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.card-block {
  margin-bottom: 32rpx;
}

.card-tap {
  width: 100%;
}

.card-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16rpx;
}

.action-btn {
  padding: 8rpx 24rpx;
  border: 1rpx solid $color-border;
  border-radius: 28rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.action-btn--disabled {
  opacity: 0.5;
}
</style>
