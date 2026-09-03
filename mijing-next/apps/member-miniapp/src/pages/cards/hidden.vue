<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberHiddenCards, restoreMemberCardVisibility } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import { cardBalanceLabel, cardTypeLabel, cardValidityLabel } from "@/utils/format";

function cardGradient(cardType: string) {
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

function cardMeta(card: MemberCardWalletSummary) {
  const parts = [cardBalanceLabel(card), cardValidityLabel(card.validFrom, card.validUntil)];
  return parts.filter(Boolean).join(" · ");
}

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

    <view v-if="cards.length" class="page-head">
      <view class="head-title">已隐藏 {{ cards.length }} 张会员卡</view>
      <view class="head-sub">恢复后将重新显示在「我的会员卡」列表</view>
    </view>

    <u-empty v-if="cards.length === 0 && !errorMessage" mode="card" text="暂无已隐藏会员卡" />

    <view v-for="card in cards" :key="card.id" class="item-card">
      <view class="thumb" :style="{ backgroundImage: cardGradient(card.cardType) }">
        <view class="thumb-shine" />
        <view class="thumb-type">{{ cardTypeLabel(card.cardType) }}</view>
      </view>

      <view class="info">
        <view class="info-name">{{ card.name || "会员卡" }}</view>
        <view class="info-meta">{{ cardMeta(card) || card.cardNoMasked }}</view>
        <view class="info-foot">
          <view class="tag-hidden">已隐藏</view>
          <view
            class="btn-restore"
            :class="{ 'btn-restore--disabled': restoringId === card.id }"
            @tap="confirmRestore(card)"
          >
            {{ restoringId === card.id ? "恢复中…" : "恢复" }}
          </view>
        </view>
      </view>
    </view>

    <bottom-logo v-if="cards.length" />
  </view>
</template>

<style scoped lang="scss">
.hidden-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 40rpx;
}

.page-head {
  padding: 12rpx 4rpx 28rpx;
}

.head-title {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 600;
  line-height: 44rpx;
}

.head-sub {
  margin-top: 8rpx;
  color: $color-text-muted;
  font-size: 24rpx;
  line-height: 32rpx;
}

/* 白色圆角卡容器：左缩略 + 右信息 */
.item-card {
  display: flex;
  gap: 24rpx;
  margin-bottom: 24rpx;
  padding: 24rpx;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
}

/* 灰度渐变缩略，表达"已隐藏"状态 */
.thumb {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 200rpx;
  height: 124rpx;
  background-size: 100% 100%;
  border-radius: 14rpx;
  overflow: hidden;
  filter: grayscale(70%);
  opacity: 0.92;
}

.thumb-shine {
  position: absolute;
  top: -50rpx;
  right: -30rpx;
  width: 160rpx;
  height: 160rpx;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0) 70%);
}

.thumb-type {
  color: #fff;
  font-size: 26rpx;
  font-weight: 600;
  letter-spacing: 2rpx;
  text-shadow: 0 2rpx 6rpx rgba(0, 0, 0, 0.2);
}

.info {
  display: flex;
  flex: 1;
  min-width: 0;
  flex-direction: column;
}

.info-name {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 600;
  line-height: 38rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.info-meta {
  margin-top: 8rpx;
  color: $color-text-muted;
  font-size: 22rpx;
  line-height: 30rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.info-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 12rpx;
}

.tag-hidden {
  padding: 4rpx 14rpx;
  background: $color-page;
  border-radius: 8rpx;
  color: $color-text-placeholder;
  font-size: 20rpx;
}

/* 主题绿描边胶囊按钮 */
.btn-restore {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 128rpx;
  height: 56rpx;
  padding: 0 28rpx;
  box-sizing: border-box;
  border: 2rpx solid $color-primary;
  border-radius: 28rpx;
  color: $color-primary;
  font-size: 26rpx;
  font-weight: 500;

  &:active {
    background: rgba(34, 199, 136, 0.08);
  }
}

.btn-restore--disabled {
  opacity: 0.5;
}
</style>
