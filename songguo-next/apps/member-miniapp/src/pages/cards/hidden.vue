<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberHiddenCards, restoreMemberCardVisibility } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import {
  cardBalanceLabel,
  cardValidityLabel,
  memberCardStatusClass,
  memberCardStatusLabel,
} from "@/utils/format";

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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="hint-text">以下会员卡已从钱包中隐藏，恢复后将重新显示在「我的会员卡」列表。</view>

    <u-empty v-if="cards.length === 0 && !errorMessage" mode="card" text="暂无已隐藏会员卡" />
    <view v-for="card in cards" :key="card.id" class="card-item">
      <view class="card-header">
        <view class="card-name">{{ card.name || "会员卡" }}</view>
        <view class="status-tag" :class="memberCardStatusClass(card.status)">
          {{ memberCardStatusLabel(card.status) }}
        </view>
      </view>
      <view class="card-meta">{{ card.cardNoMasked }}</view>
      <view v-if="cardBalanceLabel(card)" class="card-balance">{{ cardBalanceLabel(card) }}</view>
      <view v-if="cardValidityLabel(card.validFrom, card.validUntil)" class="card-meta">
        {{ cardValidityLabel(card.validFrom, card.validUntil) }}
      </view>
      <view class="card-actions">
        <u-button
          size="small"
          type="primary"
          :loading="restoringId === card.id"
          @click="confirmRestore(card)"
        >
          恢复显示
        </u-button>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.hint-text {
  margin-bottom: $spacing-md;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-item {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.card-name {
  font-size: 30rpx;
  font-weight: 600;
}

.status-tag {
  padding: 4rpx 12rpx;
  font-size: 22rpx;
  border-radius: $radius-sm;
}

.status-active {
  color: #16a34a;
  background: #dcfce7;
}

.status-pending {
  color: #ca8a04;
  background: #fef9c3;
}

.status-frozen {
  color: #2563eb;
  background: #dbeafe;
}

.status-muted {
  color: $color-text-secondary;
  background: #f3f4f6;
}

.card-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-balance {
  margin-top: $spacing-xs;
  font-size: 28rpx;
  font-weight: 600;
}

.card-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: $spacing-sm;
}
</style>
