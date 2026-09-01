<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { closePayrollPeriod, createPayrollPeriod, fetchPayrollPeriods } from "@/api/consumption";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { PayrollPeriod } from "@/types/consumption";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const loading = ref(true);
const closingId = ref(0);
const creating = ref(false);
const selectedMonth = ref("");
const periods = ref<PayrollPeriod[]>([]);
const canRead = computed(() => session.can("payroll.period.close"));
const canManage = computed(() => session.can("payroll.period.close"));

function currentMonth() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
}
async function load() {
  if (!session.currentSiteId || !canRead.value) { loading.value = false; return; }
  loading.value = true;
  try {
    const [year, month] = selectedMonth.value.split("-").map(Number);
    const response = await fetchPayrollPeriods(session.currentSiteId, { year, month, page: 1, perPage: 20 });
    periods.value = response.items;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "月结状态加载失败", icon: "none" });
  } finally { loading.value = false; }
}
function setMonth(event: { detail: { value: string } }) { selectedMonth.value = event.detail.value; void load(); }
function statusLabel(status: string) {
  return ({ open: "未关账", closing: "关账中", closed: "已关账" } as Record<string, string>)[status] || status;
}
function selectedMonthRange() {
  const [year, month] = selectedMonth.value.split("-").map(Number);
  const lastDay = new Date(year, month, 0).getDate();
  return {
    year,
    month,
    startsOn: `${year}-${String(month).padStart(2, "0")}-01`,
    endsOn: `${year}-${String(month).padStart(2, "0")}-${String(lastDay).padStart(2, "0")}`,
  };
}
function today() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60_000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}
function canClosePeriod(period: PayrollPeriod) {
  return period.status === "open"
    && period.canClose === true
    && (period.pendingCount ?? 0) === 0
    && !!period.endsOn
    && period.endsOn < today();
}
async function createPeriod() {
  if (!session.currentSiteId || creating.value || periods.value.length || !canManage.value) return;
  const range = selectedMonthRange();
  const confirmed = await uni.showModal({
    title: `创建 ${range.year} 年 ${range.month} 月期间`,
    content: `将创建 ${range.startsOn} 至 ${range.endsOn} 的完整自然月月结期间。`,
    confirmText: "确认创建",
  });
  if (!confirmed.confirm) return;
  creating.value = true;
  try {
    const period = await createPayrollPeriod(session.currentSiteId, {
      startsOn: range.startsOn,
      endsOn: range.endsOn,
      reason: `员工端创建 ${selectedMonth.value} 月结期间`,
      commandKey: createCommandKey(),
    });
    periods.value = [period];
    uni.showToast({ title: "月结期间已创建", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "创建期间失败", icon: "none" });
  } finally {
    creating.value = false;
  }
}
function closeBlockedText(period: PayrollPeriod) {
  if (period.closeBlockedReason) return period.closeBlockedReason;
  if ((period.pendingCount ?? 0) > 0) return `仍有 ${period.pendingCount} 笔待结算，暂不能关账`;
  if (period.endsOn && period.endsOn >= today()) return `期间截至 ${period.endsOn}，尚未结束`;
  if (!period.endsOn) return "后端未返回期间结束日期，暂不能关账";
  return "期间尚未结束或后端尚未确认可关账";
}
function closePeriod(period: PayrollPeriod) {
  if (!session.currentSiteId || period.status !== "open" || !canManage.value) return;
  if (!canClosePeriod(period)) {
    uni.showToast({ title: closeBlockedText(period), icon: "none" });
    return;
  }
  uni.showModal({
    title: `确认关闭 ${period.year} 年 ${period.month} 月`,
    content: "后端已确认期间结束且不存在待结算耗卡。关账会固定本期快照，本页面不提供重开。",
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      closingId.value = period.id;
      try {
        const saved = await closePayrollPeriod(session.currentSiteId, period.id, {
          version: period.version,
          commandKey: createCommandKey(),
          reason: `员工端关闭 ${period.year}-${String(period.month).padStart(2, "0")} 月结期间`,
        });
        periods.value = periods.value.map((item) => item.id === saved.id ? saved : item);
        uni.showToast({ title: "关账已提交", icon: "success" });
      } catch (error) {
        uni.showToast({ title: error instanceof Error ? error.message : "关账失败", icon: "none" });
      } finally { closingId.value = 0; }
    },
  });
}

onShow(async () => { if (!selectedMonth.value) selectedMonth.value = currentMonth(); if (await requireStaffAuth()) await load(); });
onPullDownRefresh(async () => { await load(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canRead" class="page-container period-page">
    <u-alert type="warning" description="只有期间已结束、期限卡日结完成且无待结算记录时才能关账。关账会固定快照，员工端不提供“重开”。" />
    <picker mode="date" fields="month" :value="selectedMonth" @change="setMonth">
      <view class="month-picker"><text>{{ selectedMonth }}</text><u-icon name="arrow-down" size="14" color="#989898" /></view>
    </picker>
    <view v-if="periods.length" class="period-list">
      <view v-for="period in periods" :key="period.id" class="period-card">
        <view class="period-head"><text class="period-title">{{ period.year }} 年 {{ period.month }} 月</text><text class="status" :class="period.status">{{ statusLabel(period.status) }}</text></view>
        <view class="metrics">
          <view><text>{{ period.settlementCount ?? 0 }}</text><text>结算笔数</text></view>
          <view><text>{{ period.pendingCount ?? 0 }}</text><text>待结算</text></view>
          <view><text>¥{{ period.consumptionValue ?? "0.00" }}</text><text>耗卡价值</text></view>
          <view><text>¥{{ period.sessionFee ?? "0.00" }}</text><text>课时费</text></view>
          <view><text>¥{{ period.commissionAmount ?? "0.00" }}</text><text>提成</text></view>
        </view>
        <text v-if="period.closedAt" class="closed-meta">{{ period.closedByStaffName || "员工" }} 于 {{ period.closedAt.replace('T', ' ').slice(0, 16) }} 关账</text>
        <text v-if="period.status === 'open' && canManage && !canClosePeriod(period)" class="blocked-hint">{{ closeBlockedText(period) }}</text>
        <button v-if="period.status === 'open' && canManage" class="close-btn" :class="{ disabled: !canClosePeriod(period) }" :disabled="closingId === period.id || !canClosePeriod(period)" @tap="closePeriod(period)">{{ closingId === period.id ? "关账中…" : canClosePeriod(period) ? "关闭本月" : "暂不可关账" }}</button>
      </view>
    </view>
    <view v-else class="empty-create">
      <u-empty mode="data" text="该月份尚未创建月结期间" />
      <button v-if="canManage" class="create-btn" :disabled="creating" @tap="createPeriod">{{ creating ? "创建中…" : `创建 ${selectedMonth} 月结期间` }}</button>
    </view>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无月结查看权限" />
</template>

<style scoped lang="scss">
.period-page { padding-bottom: 60rpx; }
.month-picker { display: flex; align-items: center; justify-content: center; gap: 10rpx; width: 300rpx; margin: 22rpx auto; padding: 15rpx 20rpx; background: #fff; border-radius: 999rpx; font-size: 27rpx; }
.period-card { margin-bottom: 18rpx; padding: 26rpx 24rpx; background: #fff; border-radius: $radius-lg; }
.period-head { display: flex; align-items: center; justify-content: space-between; }
.period-title { font-size: 30rpx; font-weight: 600; }
.status { padding: 5rpx 12rpx; color: #8b6c00; background: #fff6c7; border-radius: 999rpx; font-size: 20rpx; }
.status.closed { color: #168d61; background: #e8f8f1; }
.metrics { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18rpx; margin-top: 24rpx; padding: 20rpx; background: #f8f8f8; border-radius: 14rpx; }
.metrics view { display: flex; flex-direction: column; }
.metrics text:first-child { font-size: 28rpx; font-weight: 600; }
.metrics text:last-child { margin-top: 4rpx; color: $color-text-tertiary; font-size: 20rpx; }
.closed-meta { display: block; margin-top: 16rpx; color: $color-text-tertiary; font-size: 21rpx; }
.blocked-hint { display: block; margin-top: 16rpx; color: #a56f18; font-size: 21rpx; line-height: 32rpx; }
.close-btn { height: 72rpx; margin-top: 22rpx; color: $color-text; background: $color-brand-yellow; border-radius: 36rpx; font-size: 26rpx; line-height: 72rpx; }
.close-btn.disabled { color: $color-text-tertiary; background: #e9e9e9; }
.close-btn::after { border: 0; }
.empty-create { padding-top: 30rpx; }
.create-btn { width: 520rpx; height: 76rpx; margin: 24rpx auto 0; color: $color-text; background: $color-brand-yellow; border-radius: 38rpx; font-size: 26rpx; line-height: 76rpx; }
.create-btn::after { border: 0; }
</style>
