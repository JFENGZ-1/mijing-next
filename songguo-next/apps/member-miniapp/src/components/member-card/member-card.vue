<script setup lang="ts">
import { computed } from "vue";
import type { MemberCardWalletSummary } from "@/types/member";
import { cardBalanceLabel, cardTypeLabel, cardValidityLabel } from "@/utils/format";

const props = withDefaults(
  defineProps<{
    card: MemberCardWalletSummary;
    active?: boolean;
  }>(),
  { active: false },
);

const gradient = computed(() => {
  switch (props.card.cardType) {
    case "stored_value":
      return "linear-gradient(135deg, #c96a32 0%, #a8521f 100%)";
    case "count":
      return "linear-gradient(135deg, #2a5fb6 0%, #003d82 100%)";
    case "period":
      return "linear-gradient(135deg, #349f91 0%, #2a877c 100%)";
    default:
      return "linear-gradient(135deg, #696b99 0%, #4a4d7a 100%)";
  }
});

const typeLabel = computed(() => cardTypeLabel(props.card.cardType));
const balanceText = computed(() => cardBalanceLabel(props.card));
const validityText = computed(() => cardValidityLabel(props.card.validFrom, props.card.validUntil));
const greyed = computed(() => {
  const status = props.card.status;
  if (status === "expired" || status === "archived" || status === "cancelled") return true;
  if (props.card.cardType === "count" && (props.card.remainingCount ?? 0) <= 0) return true;
  if (props.card.cardType === "stored_value" && (!props.card.balance || Number(props.card.balance) <= 0)) return true;
  return false;
});
</script>

<template>
  <view class="member-card" :class="{ 'is-grey': greyed }">
    <view class="card-box" :style="{ backgroundImage: gradient }">
      <view class="ribbon">{{ typeLabel }}</view>

      <view class="card-content">
        <view class="card-name">{{ card.name || "会员卡" }}</view>
      </view>

      <view class="bottom-info">
        <view class="balance">{{ balanceText }}</view>
        <view v-if="validityText" class="period">{{ validityText }}</view>
      </view>

      <view class="card-no">{{ card.cardNoMasked }}</view>
    </view>
    <view v-if="greyed" class="mask-view" />
  </view>
</template>

<style scoped lang="scss">
.member-card {
  position: relative;
  width: 100%;
  height: 371rpx;
  border-radius: 24rpx;
  overflow: hidden;
}

.card-box {
  position: relative;
  width: 100%;
  height: 100%;
  padding: 40rpx 36rpx;
  box-sizing: border-box;
  background-size: 100% 100%;
  color: #fff;
  overflow: hidden;
}

.ribbon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6rpx 18rpx;
  background: rgba(255, 255, 255, 0.22);
  border-radius: 8rpx;
  color: #fff;
  font-size: 22rpx;
}

.card-content {
  margin-top: 28rpx;
}

.card-name {
  color: #fff;
  font-size: 44rpx;
  font-weight: 600;
  line-height: 56rpx;
  text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.15);
}

.bottom-info {
  position: absolute;
  bottom: 36rpx;
  left: 36rpx;
  right: 36rpx;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}

.balance {
  color: #fff;
  font-size: 40rpx;
  font-weight: 600;
  line-height: 44rpx;
}

.period {
  color: rgba(255, 255, 255, 0.85);
  font-size: 22rpx;
  line-height: 30rpx;
  text-align: right;
}

.card-no {
  position: absolute;
  top: 40rpx;
  right: 36rpx;
  color: rgba(255, 255, 255, 0.7);
  font-size: 22rpx;
  letter-spacing: 2rpx;
}

.mask-view {
  position: absolute;
  inset: 0;
  background: hsla(0, 0%, 100%, 0.35);
  z-index: 2;
}

.is-grey {
  filter: grayscale(100%);
}
</style>
