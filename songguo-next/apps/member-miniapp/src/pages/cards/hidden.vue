<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberHiddenCards, restoreMemberCardVisibility } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";

const restoringId = ref<number | null>(null);
const errorMessage = ref("");
const cards = ref<MemberCardWalletSummary[]>([]);
const restoreCommandKeys = new Map<number, string>();

const loading = ref(true);

async function loadCards(refresh = false) {
  errorMessage.value = "";
  if (refresh) {
    cards.value = [];
  }

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberHiddenCards(tenant.tenantId);
    cards.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "已隐藏会员卡加载失败";
  } finally {
    loading.value = false;
  }
}

function confirmRestore(card: MemberCardWalletSummary) {
  uni.showModal({
    title: "恢复显示",
    content: `确定恢复「${card.name || "会员卡"}」到钱包吗？`,
    success: async (result) => {
      if (!result.confirm) return;
      await restoreCard(card);
    },
  });
}

async function restoreCard(card: MemberCardWalletSummary) {
  const tenant = await ensureMemberTenant();
  if (!tenant) return;

  let commandKey = restoreCommandKeys.get(card.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    restoreCommandKeys.set(card.id, commandKey);
  }

  restoringId.value = card.id;
  try {
    await restoreMemberCardVisibility(tenant.tenantId, card.id, commandKey);
    restoreCommandKeys.delete(card.id);
    uni.showToast({ title: "已恢复", icon: "success" }); await loadCards();
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "恢复失败",
      icon: "none",
    });
  } finally {
    restoringId.value = null;
  }
}

onShow(async () => { if (await requireMemberAuth()) await loadCards(); });

onPullDownRefresh(async () => { await loadCards(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="hidden-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <view class="hint-text">以下会员卡已从钱包中隐藏，恢复后将重新显示在「我的会员卡」列表。</view>

    <u-empty v-if="cards.length === 0 && !errorMessage" mode="card" text="暂无已隐藏会员卡" />

    <view v-if="cards.length" class="total-card">共{{ cards.length }}张</view>

    <view v-for="card in cards" :key="card.id" class="card-block">
      <member-card :card="card" />
      <view
        class="restore-btn"
        :class="{ 'restore-btn--disabled': restoringId === card.id }"
        @tap="confirmRestore(card)"
      >
        {{ restoringId === card.id ? "恢复中..." : "恢复" }}
      </view>
    </view>

    <bottom-logo v-if="cards.length" />
  </view>
</template>

<style scoped lang="scss">
.hidden-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.hint-text {
  margin-bottom: 24rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.total-card {
  margin-bottom: 24rpx;
  color: $color-text;
  font-size: 28rpx;
  font-weight: 600;
}

.card-block {
  margin-bottom: 28rpx;
}

.restore-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 16rpx;
  padding: 14rpx 0;
  background: $color-primary;
  border-radius: 36rpx;
  color: #fff;
  font-size: 26rpx;
}

.restore-btn--disabled {
  opacity: 0.5;
}
</style>
