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

// 卡面图案：后端图案库直发 faceGradient（总 Web 后台可控），未配置回退类型默认
const gradient = computed(() => {
  if (props.card.faceGradient) return props.card.faceGradient;
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

// 原版斜缎带底色（.88 透明度实色）
const ribbonColor = computed(() => {
  switch (props.card.cardType) {
    case "stored_value":
      return "rgba(201, 106, 50, 0.88)";
    case "count":
      return "rgba(0, 61, 130, 0.88)";
    case "period":
      return "rgba(52, 159, 145, 0.88)";
    default:
      return "rgba(105, 107, 153, 0.88)";
  }
});

const typeLabel = computed(() => cardTypeLabel(props.card.cardType));
const balanceText = computed(() => cardBalanceLabel(props.card));
const validityText = computed(() => cardValidityLabel(props.card.validFrom, props.card.validUntil));

const statusStamp = computed(() => {
  switch (props.card.status) {
    case "pending_activation":
      return "待启用";
    case "frozen":
      return "已冻结";
    case "expired":
      return "已过期";
    case "exhausted":
      return "已用完";
    case "voided":
    case "cancelled":
      return "已作废";
    case "archived":
      return "已归档";
    default:
      return "";
  }
});

const greyed = computed(() => {
  const status = props.card.status;
  if (status === "expired" || status === "archived" || status === "cancelled" || status === "voided" || status === "frozen") return true;
  if (props.card.cardType === "count" && (props.card.remainingCount ?? 0) <= 0) return true;
  if (props.card.cardType === "stored_value" && (!props.card.balance || Number(props.card.balance) <= 0)) return true;
  return false;
});

const longName = computed(() => (props.card.name || "").length > 6);
</script>

<template>
  <view class="member-card" :class="{ 'is-grey': greyed }">
    <view class="card-box" :style="{ backgroundImage: gradient }">
      <view class="ribbon" :style="{ background: ribbonColor }">
        <text>{{ typeLabel }}</text>
      </view>

      <view class="card-no">{{ card.cardNoMasked }}</view>

      <view class="card-name" :class="{ 'card-name--small': longName }">
        {{ card.name || "会员卡" }}
      </view>

      <view class="bottom-info">
        <view class="balance">{{ balanceText }}</view>
        <view v-if="validityText" class="period">{{ validityText }}</view>
      </view>

      <view v-if="statusStamp" class="card-status">
        <view class="stamp">{{ statusStamp }}</view>
      </view>
    </view>
    <view v-if="greyed" class="mask-view" />
  </view>
</template>

<style scoped lang="scss">
.member-card {
  position: relative;
  width: 100%;
  height: 371rpx;
  border-radius: 18rpx;
  overflow: hidden;
  box-shadow: 0 -2rpx 5rpx rgba(0, 0, 0, 0.1);
}

.card-box {
  position: relative;
  width: 100%;
  height: 100%;
  box-sizing: border-box;
  background-size: 100% 100%;
  color: #fff;
  overflow: hidden;
}

/* 对标原版：右上角 45° 斜缎带 */
.ribbon {
  position: absolute;
  top: -3rpx;
  right: -36rpx;
  z-index: 3;
  overflow: hidden;
  white-space: nowrap;
  transform: rotate(45deg);
  transform-origin: center;
}

.ribbon text {
  display: block;
  height: 52rpx;
  padding: 2rpx 29rpx 0;
  color: #fff;
  font-size: 20rpx;
  font-weight: 500;
  line-height: 65rpx;
  text-align: center;
}

.card-no {
  position: absolute;
  top: 30rpx;
  left: 35rpx;
  color: rgba(255, 255, 255, 0.75);
  font-size: 24rpx;
  letter-spacing: 2rpx;
}

.card-name {
  position: absolute;
  top: 50%;
  left: 35rpx;
  right: 35rpx;
  transform: translateY(-58%);
  color: #fff;
  font-size: 64rpx;
  font-weight: 600;
  line-height: 80rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  text-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.15);
}

.card-name--small {
  font-size: 48rpx;
  line-height: 60rpx;
}

.bottom-info {
  position: absolute;
  bottom: 25rpx;
  left: 35rpx;
  right: 35rpx;
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

/* 状态印章（原版为图片角标，此处以文字章对标） */
.card-status {
  position: absolute;
  right: 5rpx;
  bottom: 20rpx;
  z-index: 10;
  width: 135rpx;
  height: 135rpx;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stamp {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 110rpx;
  height: 110rpx;
  border: 4rpx solid rgba(255, 255, 255, 0.85);
  border-radius: 50%;
  color: rgba(255, 255, 255, 0.9);
  font-size: 26rpx;
  font-weight: 600;
  transform: rotate(-15deg);
  background: hsla(0, 0%, 9%, 0.38);
}

.mask-view {
  position: absolute;
  inset: 0;
  background: hsla(0, 0%, 100%, 0.3);
  z-index: 2;
}

.is-grey {
  filter: grayscale(100%);
}
</style>
