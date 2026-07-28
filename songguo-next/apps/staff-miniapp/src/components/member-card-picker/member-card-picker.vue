<script setup lang="ts">
// 代约选卡（对标原版 pagesCourse/components/select-member-card + chosecard）：
// 当前卡以「卡面大卡片」居中展示（faceGradient + 右上缎带 + 余额/次数 + 有效期）；
// 多卡时「选择其它卡 ▼」展开卡面列表切换；无效卡（冻结/过期/用完/作废/余额0）
// 折叠为「已折叠 N 张无效卡」置灰展示不可选；待激活卡可用（后端允许约课激活）。
import { computed, ref, watch } from "vue";
import type { StaffMemberCardSummary } from "@/types/crm";

const props = defineProps<{
  cards: StaffMemberCardSummary[];
  modelValue: number | null;
  loading?: boolean;
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: number | null): void;
}>();

const FACE_FALLBACK = "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";

const expanded = ref(false);
const showInvalid = ref(false);

/** 卡是否可用于约课（active/pending_activation 且次数/余额未耗尽） */
function isUsable(card: StaffMemberCardSummary) {
  if (card.status === "active" || card.status === "pending_activation") {
    if (card.cardType === "count" && card.cachedRemainingCount != null && card.cachedRemainingCount <= 0) return false;
    if (card.cardType === "stored_value" && card.cachedBalance != null && Number(card.cachedBalance) <= 0) return false;
    return true;
  }
  return false;
}

const usableCards = computed(() => props.cards.filter(isUsable));
const invalidCards = computed(() => props.cards.filter((card) => !isUsable(card)));

const currentCard = computed(
  () => props.cards.find((card) => card.id === props.modelValue && isUsable(card)) ?? usableCards.value[0] ?? null,
);

// 卡列表变化后，若选中值缺失/失效，自动选中第一张可用卡（对标原版默认当前卡）
watch(
  () => [props.cards, props.modelValue] as const,
  () => {
    if (currentCard.value && currentCard.value.id !== props.modelValue) {
      emit("update:modelValue", currentCard.value.id);
    } else if (!currentCard.value && props.modelValue !== null) {
      emit("update:modelValue", null);
    }
  },
  { immediate: true },
);

// 换会员（cards 引用更换）时收起展开区
watch(
  () => props.cards,
  () => {
    expanded.value = false;
    showInvalid.value = false;
  },
);

function pick(card: StaffMemberCardSummary) {
  if (!isUsable(card)) return;
  emit("update:modelValue", card.id);
  expanded.value = false;
}

function cardFace(card: StaffMemberCardSummary) {
  return card.faceGradient || FACE_FALLBACK;
}

// 原版缎带配色：储值深金 / 计次深蓝 / 期限绿
function ribbonStyle(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") return "background: rgba(201, 106, 50, 0.88)";
  if (card.cardType === "count") return "background: rgba(0, 61, 130, 0.88)";
  return "background: rgba(52, 159, 145, 0.88)";
}

function cardTypeLabel(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") return "储值卡";
  if (card.cardType === "count") return "计次卡";
  return "期限卡";
}

/** 卡面余额行：储值 ¥余额 / 计次 N次 / 期限 剩余天数由有效期表达 */
function balanceText(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") return `¥${card.cachedBalance ?? "0"}`;
  if (card.cardType === "count") return `剩余 ${card.cachedRemainingCount ?? "—"} 次`;
  return card.status === "pending_activation" ? "待激活" : "期限内不限次";
}

function validText(card: StaffMemberCardSummary) {
  return card.validUntil ? `有效期至 ${card.validUntil.slice(0, 10)}` : "长期有效";
}

function statusLabel(card: StaffMemberCardSummary) {
  switch (card.status) {
    case "frozen":
      return "已冻结";
    case "expired":
      return "已过期";
    case "exhausted":
      return "已用完";
    case "voided":
      return "已作废";
    case "archived":
      return "已归档";
    case "pending_activation":
      return "待激活";
    default:
      // active 但次数/余额 0
      return card.cardType === "period" ? "不可用" : "余额不足";
  }
}
</script>

<template>
  <view class="card-picker">
    <view v-if="loading" class="cp-hint">加载会员卡…</view>

    <template v-else-if="currentCard">
      <!-- 当前选中卡：卡面大卡片（对标原版 member-card） -->
      <view class="face-wrap">
        <view class="face-card" :style="{ background: cardFace(currentCard) }">
          <view class="face-ribbon" :style="ribbonStyle(currentCard)">
            <text>{{ cardTypeLabel(currentCard) }}</text>
          </view>
          <view v-if="currentCard.status === 'pending_activation'" class="face-stamp">待激活</view>
          <text class="face-name">{{ currentCard.name || currentCard.cardNo }}</text>
          <view class="face-bottom">
            <text class="face-balance">{{ balanceText(currentCard) }}</text>
            <text class="face-valid">{{ validText(currentCard) }}</text>
          </view>
        </view>
      </view>

      <!-- 选择其它卡 -->
      <view v-if="usableCards.length > 1" class="switch-row" @tap="expanded = !expanded">
        <text class="switch-text">选择其它卡</text>
        <u-icon :name="expanded ? 'arrow-up' : 'arrow-down'" size="13" color="#989898" />
      </view>
      <scroll-view v-if="expanded" scroll-y class="other-list">
        <view
          v-for="card in usableCards"
          :key="card.id"
          class="face-wrap small"
          @tap="pick(card)"
        >
          <view class="face-card" :style="{ background: cardFace(card) }">
            <view class="face-ribbon" :style="ribbonStyle(card)">
              <text>{{ cardTypeLabel(card) }}</text>
            </view>
            <text class="face-name">{{ card.name || card.cardNo }}</text>
            <view class="face-bottom">
              <text class="face-balance">{{ balanceText(card) }}</text>
              <text class="face-valid">{{ validText(card) }}</text>
            </view>
            <view v-if="card.id === modelValue" class="face-check">
              <u-icon name="checkmark" size="14" color="#fff" />
            </view>
          </view>
        </view>
      </scroll-view>

      <!-- 无效卡折叠（对标原版 chosecard：已折叠 N 张无效卡） -->
      <template v-if="invalidCards.length">
        <view class="invalid-toggle" @tap="showInvalid = !showInvalid">
          <text class="invalid-toggle-text">已折叠{{ invalidCards.length }}张无效卡</text>
          <u-icon :name="showInvalid ? 'arrow-up' : 'arrow-down'" size="12" color="#989898" />
        </view>
        <template v-if="showInvalid">
          <view v-for="card in invalidCards" :key="card.id" class="face-wrap small invalid">
            <view class="face-card gray" :style="{ background: cardFace(card) }">
              <view class="face-ribbon" :style="ribbonStyle(card)">
                <text>{{ cardTypeLabel(card) }}</text>
              </view>
              <view class="face-stamp">{{ statusLabel(card) }}</view>
              <text class="face-name">{{ card.name || card.cardNo }}</text>
              <view class="face-bottom">
                <text class="face-balance">{{ balanceText(card) }}</text>
                <text class="face-valid">{{ validText(card) }}</text>
              </view>
            </view>
          </view>
        </template>
      </template>
    </template>

    <!-- 无卡（对标原版：先给他发卡后再约课） -->
    <view v-else class="cp-empty">
      <text class="cp-empty-text">该会员还没有可用卡，先给他发卡后再约课</text>
    </view>
  </view>
</template>

<style scoped lang="scss">
.card-picker {
  display: flex;
  flex-direction: column;
}

.cp-hint {
  padding: 48rpx 0;
  color: $color-text-secondary;
  font-size: 24rpx;
  text-align: center;
}

// —— 卡面（对标原版 mumber-card medium/large 比例） ——
.face-wrap {
  display: flex;
  justify-content: center;
  margin-top: 24rpx;

  &.small {
    margin-top: 20rpx;

    .face-card {
      width: 480rpx;
      height: 276rpx;
    }

    .face-name {
      font-size: 40rpx;
    }
  }

  &.invalid {
    .face-card {
      opacity: 0.75;
    }
  }
}

.face-card {
  position: relative;
  width: 560rpx;
  height: 322rpx;
  padding: 24rpx 28rpx;
  overflow: hidden;
  border-radius: 18rpx;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.12);
  box-sizing: border-box;

  &.gray {
    filter: grayscale(100%);
  }
}

.face-ribbon {
  position: absolute;
  top: 14rpx;
  right: -42rpx;
  width: 180rpx;
  height: 44rpx;
  line-height: 44rpx;
  color: #fff;
  font-size: 20rpx;
  text-align: center;
  transform: rotate(45deg);
}

.face-stamp {
  position: absolute;
  right: 24rpx;
  bottom: 24rpx;
  padding: 4rpx 14rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.85);
  border-radius: 8rpx;
  color: #fff;
  font-size: 22rpx;
  transform: rotate(-10deg);
}

.face-name {
  display: block;
  margin-top: 84rpx;
  overflow: hidden;
  color: #fff;
  font-size: 48rpx;
  font-weight: 600;
  text-align: center;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.face-bottom {
  position: absolute;
  right: 28rpx;
  bottom: 20rpx;
  left: 28rpx;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  color: #fff;
}

.face-balance {
  font-size: 32rpx;
  font-weight: 600;
}

.face-valid {
  font-size: 20rpx;
  opacity: 0.85;
}

.face-check {
  position: absolute;
  top: 16rpx;
  left: 16rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36rpx;
  height: 36rpx;
  border-radius: 50%;
  background: #00cb82;
}

// —— 选择其它卡 ——
.switch-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  margin-top: 28rpx;
}

.switch-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.other-list {
  max-height: 600rpx;
}

// —— 无效卡折叠 ——
.invalid-toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8rpx;
  margin-top: 28rpx;
  padding-top: 24rpx;
  border-top: 1rpx solid #f0f0f0;
}

.invalid-toggle-text {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

// —— 无卡 ——
.cp-empty {
  padding: 60rpx 0;
  text-align: center;
}

.cp-empty-text {
  display: inline-block;
  padding: 0 43rpx;
  height: 57rpx;
  line-height: 57rpx;
  background: #faf5f8;
  border-radius: 35rpx;
  color: #dc3c5c;
  font-size: 26rpx;
}
</style>
