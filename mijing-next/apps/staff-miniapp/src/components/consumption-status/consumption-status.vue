<script setup lang="ts">
import { computed } from "vue";
import type { AppointmentConsumptionPreview, ConsumptionSettlement } from "@/types/consumption";

const props = withDefaults(defineProps<{
  value: AppointmentConsumptionPreview | ConsumptionSettlement | null;
  compact?: boolean;
}>(), { compact: false });

const statusMeta = computed(() => {
  const status = props.value?.settlementStatus || "pending";
  const map: Record<string, { label: string; color: string; background: string }> = {
    reserved: { label: "已预占", color: "#8b6c00", background: "#fff6c7" },
    pending: { label: "待结算", color: "#8b6c00", background: "#fff6c7" },
    pending_day_close: { label: "待当日结算", color: "#8b6c00", background: "#fff6c7" },
    settled: { label: "已结算", color: "#168d61", background: "#e8f8f1" },
    adjusted: { label: "已调整", color: "#3f76ad", background: "#edf5ff" },
    reversed: { label: "已冲正", color: "#a94156", background: "#fdeef1" },
  };
  return map[status] ?? { label: status, color: "#505050", background: "#f2f2f2" };
});

const reservedText = computed(() => {
  if (!props.value) return "";
  if (props.value.reservedAmount != null) return `预占 ¥${props.value.reservedAmount}`;
  if (props.value.reservedCount != null) return `预占 ${props.value.reservedCount} 次`;
  if (props.value.deductionKind === "period_auto") return "期限权益已校验";
  return "";
});

const settlement = computed(() => props.value as ConsumptionSettlement | null);
</script>

<template>
  <view v-if="value" class="consumption-box" :class="{ compact }">
    <view class="status-row">
      <text class="status-tag" :style="{ color: statusMeta.color, background: statusMeta.background }">{{ statusMeta.label }}</text>
      <text v-if="value.ruleVersion != null" class="rule-version">规则 v{{ value.ruleVersion }}</text>
    </view>
    <view class="value-row">
      <text v-if="reservedText" class="meta">{{ reservedText }}</text>
      <text v-if="value.consumptionValue != null" class="value">耗卡价值 ¥{{ value.consumptionValue }}</text>
    </view>
    <view v-if="!compact" class="detail-row">
      <text v-if="value.sessionFee != null">课时费 ¥{{ value.sessionFee }}</text>
      <text v-if="value.estimatedCommissionAmount != null">预计提成 ¥{{ value.estimatedCommissionAmount }}</text>
      <text v-if="settlement?.commissionAmount != null">提成 ¥{{ settlement.commissionAmount }}</text>
    </view>
    <text v-if="value.settlementHint" class="hint">{{ value.settlementHint }}</text>
    <text v-else-if="value.settlementStatus === 'pending_day_close'" class="hint">期限卡将在场馆当天耗卡次数确定后自动分摊</text>
  </view>
</template>

<style scoped lang="scss">
.consumption-box { margin-top: 14rpx; padding: 16rpx 18rpx; background: #fafafa; border-radius: 12rpx; }
.consumption-box.compact { margin-top: 8rpx; padding: 12rpx 14rpx; }
.status-row, .value-row, .detail-row { display: flex; align-items: center; gap: 12rpx; flex-wrap: wrap; }
.status-tag { padding: 4rpx 12rpx; border-radius: 999rpx; font-size: 20rpx; }
.rule-version { color: #b0b0b0; font-size: 19rpx; }
.value-row { margin-top: 9rpx; }
.meta, .value, .detail-row { color: #505050; font-size: 21rpx; }
.value { color: #ed920f; }
.detail-row { margin-top: 7rpx; }
.hint { display: block; margin-top: 8rpx; color: #989898; font-size: 20rpx; line-height: 30rpx; }
</style>
