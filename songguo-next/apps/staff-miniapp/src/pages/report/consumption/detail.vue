<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchConsumptionSettlement, reverseConsumptionSettlement } from "@/api/consumption";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ConsumptionFormulaInputs, ConsumptionSettlement } from "@/types/consumption";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const settlementId = ref(0);
const loading = ref(true);
const errorMessage = ref("");
const detail = ref<ConsumptionSettlement | null>(null);
const reversing = ref(false);
const reverseReason = ref("");
const canRead = computed(() => session.can("consumption.read"));
const canReverse = computed(() => session.can("consumption.adjust"));

const formulaRows = computed(() => {
  const input = detail.value?.formulaInputs;
  if (!input) return [];
  const rows: Array<{ label: string; value: string }> = [];
  const add = (key: keyof ConsumptionFormulaInputs, label: string, unit = "") => {
    const value = input[key];
    if (value != null) rows.push({ label, value: `${value}${unit}` });
  };
  add("paidAmount", "实付金额", " 元");
  add("deductedAmount", "本次扣费金额", " 元");
  add("totalCount", "总次数", " 次");
  add("deductedCount", "本次耗卡次数", " 次");
  add("totalDays", "总天数", " 天");
  add("dayUseCount", "当天完成耗卡次数", " 次");
  add("dailyBaseAmount", "当日耗卡基数", " 元");
  add("perUseBaseAmount", "本次耗卡基数", " 元");
  return rows;
});

async function load() {
  if (!canRead.value) { loading.value = false; return; }
  if (!session.currentSiteId || !settlementId.value) {
    errorMessage.value = "缺少有效的耗卡结算编号";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try { detail.value = await fetchConsumptionSettlement(session.currentSiteId, settlementId.value); }
  catch (error) {
    detail.value = null;
    errorMessage.value = error instanceof Error ? error.message : "结算明细加载失败";
  }
  finally { loading.value = false; }
}
async function reverseSettlement() {
  if (!session.currentSiteId || !detail.value || reversing.value) return;
  const reason = reverseReason.value.trim();
  if (reason.length < 4) {
    uni.showToast({ title: "冲正原因至少 4 个字符", icon: "none" });
    return;
  }
  const confirmed = await uni.showModal({
    title: "确认冲正耗卡",
    content: "冲正会退回对应卡权益，并以反向结算记录抵消课时费与提成。该操作不会物理删除原记录。",
    confirmText: "确认冲正",
    confirmColor: "#dc3c5c",
  });
  if (!confirmed.confirm) return;
  reversing.value = true;
  try {
    detail.value = await reverseConsumptionSettlement(session.currentSiteId, detail.value.id, {
      reason,
      commandKey: createCommandKey(),
    });
    reverseReason.value = "";
    uni.showToast({ title: "耗卡已冲正", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "冲正失败", icon: "none" });
  } finally {
    reversing.value = false;
  }
}
function statusLabel(status: string) {
  return ({ pending: "待结算", pending_day_close: "待当日结算", settled: "已结算", adjusted: "已调整", reversed: "已冲正" } as Record<string, string>)[status] || status;
}
function cardTypeLabel(value: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[value] || value;
}
function rateText(value: number) { return `${value / 100}%`; }
function lineAmountText(line: ConsumptionSettlement["commissionLines"][number]) {
  return line.component === "session_fee"
    ? `课时费 ¥${line.sessionFee}`
    : `耗卡提成 ¥${line.commissionAmount}`;
}
function lineRateText(line: ConsumptionSettlement["commissionLines"][number]) {
  const allocation = line.allocationBps != null && line.allocationBps !== 10000
    ? ` · 分配 ${rateText(line.allocationBps)}`
    : "";
  return line.component === "session_fee"
    ? `固定课时费${allocation}`
    : `耗卡比例 ${rateText(line.rateBasisPoints)}${allocation}`;
}

onLoad((options) => { settlementId.value = Number(options?.id || 0); });
onShow(async () => { if (await requireStaffAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && detail" class="page-container detail-page">
    <view class="hero-card">
      <view class="hero-head"><text>{{ statusLabel(detail.settlementStatus) }}</text><text>规则 v{{ detail.ruleVersion ?? "—" }}</text></view>
      <text class="hero-value">{{ detail.consumptionValue == null ? "待核定" : `¥${detail.consumptionValue}` }}</text>
      <text class="hero-label">耗卡价值</text>
      <view class="hero-meta"><text>课时费 ¥{{ detail.sessionFee ?? "0.00" }}</text><text>提成 ¥{{ detail.commissionAmount ?? "0.00" }}</text></view>
    </view>
    <u-alert v-if="detail.settlementHint" type="warning" :description="detail.settlementHint" />
    <view class="info-card">
      <text class="section-title-text">业务信息</text>
      <view v-for="row in [
        ['学员', detail.memberName || `#${detail.memberId || '—'}`],
        ['课程', detail.courseName || `#${detail.courseId || '—'}`],
        ['教练', detail.coachName || '—'],
        ['会员卡', detail.cardName || '—'],
        ['卡类型', cardTypeLabel(detail.cardType)],
        ['业务日期', detail.businessDate || detail.occurredAt?.slice(0, 10) || '—'],
      ]" :key="String(row[0])" class="info-row"><text>{{ row[0] }}</text><text>{{ row[1] }}</text></view>
    </view>
    <view class="info-card">
      <text class="section-title-text">公式输入</text>
      <text class="section-hint">以下均为后端结算时保存的输入快照，页面不重新计算。</text>
      <view v-for="row in formulaRows" :key="row.label" class="info-row"><text>{{ row.label }}</text><text>{{ row.value }}</text></view>
      <u-empty v-if="!formulaRows.length" mode="data" text="暂无公式输入快照" />
    </view>
    <view class="info-card">
      <text class="section-title-text">课时费与提成明细</text>
      <view v-for="(line, index) in detail.commissionLines" :key="`${line.id || line.roleId}-${line.staffId || 0}-${line.component || 'line'}-${index}`" class="commission-row">
        <view><text class="line-name">{{ line.roleType === "delivery" ? "A" : "B" }} · {{ line.roleName }}</text><text class="line-staff">{{ line.staffName || "未指定员工" }}</text></view>
        <view class="line-money"><text>{{ lineRateText(line) }}</text><text>{{ lineAmountText(line) }}</text></view>
      </view>
      <u-empty v-if="!detail.commissionLines.length" mode="data" text="暂无提成明细" />
    </view>
    <u-alert v-if="detail.adjustmentReason" type="warning" :description="`调整原因：${detail.adjustmentReason}`" />
    <view v-if="canReverse && detail.settlementStatus !== 'reversed'" class="reverse-card">
      <text class="section-title-text danger-title">冲正耗卡</text>
      <text class="section-hint">危险操作：只允许因签到错误、选错卡等业务纠错使用。系统会保留原记录和完整审计轨迹。</text>
      <textarea v-model="reverseReason" class="reverse-reason" maxlength="200" placeholder="必填：请输入冲正原因" />
      <button class="reverse-btn" :disabled="reversing" @tap="reverseSettlement">{{ reversing ? "冲正中…" : "确认冲正耗卡" }}</button>
    </view>
  </view>
  <view v-else-if="!loading && errorMessage" class="page-container error-wrap">
    <u-alert type="error" :description="errorMessage" />
    <button class="retry-btn" @tap="load">重新加载</button>
  </view>
  <u-empty v-else-if="!loading && !canRead" mode="permission" text="暂无耗卡结算查看权限" />
</template>

<style scoped lang="scss">
.detail-page { padding-bottom: 60rpx; }
.hero-card { padding: 30rpx 24rpx; color: #fff; background: linear-gradient(135deg, #696b99, #404269); border-radius: $radius-lg; }
.hero-head, .hero-meta { display: flex; align-items: center; justify-content: space-between; font-size: 21rpx; opacity: .78; }
.hero-value { display: block; margin-top: 26rpx; font-size: 60rpx; font-weight: 600; text-align: center; }
.hero-label { display: block; margin-top: 4rpx; font-size: 22rpx; text-align: center; opacity: .72; }
.hero-meta { margin-top: 28rpx; padding-top: 20rpx; border-top: 1rpx solid rgba(255,255,255,.18); font-size: 23rpx; }
.info-card { margin-top: 20rpx; padding: 27rpx 24rpx; background: #fff; border-radius: $radius-lg; }
.section-title-text { display: block; font-size: 29rpx; font-weight: 600; }
.section-hint { display: block; margin-top: 6rpx; color: $color-text-tertiary; font-size: 21rpx; }
.info-row { display: flex; align-items: center; justify-content: space-between; gap: 24rpx; padding: 20rpx 0; color: $color-text-secondary; border-bottom: 1rpx solid #f1f1f1; font-size: 24rpx; }
.info-row text:last-child { color: $color-text; text-align: right; }
.commission-row { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; padding: 22rpx 0; border-bottom: 1rpx solid #f1f1f1; }
.line-name, .line-staff, .line-money text { display: block; }
.line-name { font-size: 25rpx; font-weight: 600; }
.line-staff { margin-top: 5rpx; color: $color-text-tertiary; font-size: 21rpx; }
.line-money { text-align: right; }
.line-money text { color: $color-text-secondary; font-size: 21rpx; }
.line-money text:last-child { margin-top: 5rpx; color: $color-primary; }
.reverse-card { margin-top: 20rpx; padding: 27rpx 24rpx; background: #fff; border: 1rpx solid #f1c6ce; border-radius: $radius-lg; }
.danger-title { color: $color-danger; }
.reverse-reason { width: 100%; height: 140rpx; margin-top: 18rpx; padding: 16rpx; background: #faf4f5; border-radius: 12rpx; font-size: 24rpx; box-sizing: border-box; }
.reverse-btn { height: 76rpx; margin-top: 20rpx; color: #fff; background: $color-danger; border-radius: 38rpx; font-size: 27rpx; line-height: 76rpx; }
.reverse-btn::after { border: 0; }
.error-wrap { padding-top: 30rpx; }
.retry-btn { width: 240rpx; height: 68rpx; margin: 24rpx auto 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 34rpx; font-size: 24rpx; line-height: 66rpx; }
.retry-btn::after { border: 0; }
</style>
