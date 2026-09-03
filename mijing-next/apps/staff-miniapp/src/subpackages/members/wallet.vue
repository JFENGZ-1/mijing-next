<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { createMemberWalletAdjustment, fetchMemberWallet } from "@/api/wallet";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { MemberWallet } from "@/types/wallet";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const memberId = ref(0);
const memberName = ref("会员");
const loading = ref(true);
const saving = ref(false);
const loadError = ref("");
const wallet = ref<MemberWallet | null>(null);
const adjustmentDirection = ref<"credit" | "debit">("credit");
const amount = ref("");
const reason = ref("");
const canRead = computed(() => session.can("wallet.read"));
const canAdjust = computed(() => session.can("wallet.adjust"));

async function load() {
  if (!session.currentSiteId || !memberId.value || !canRead.value) { loading.value = false; return; }
  loading.value = true;
  loadError.value = "";
  try {
    wallet.value = await fetchMemberWallet(session.currentSiteId, memberId.value);
  } catch (error) {
    wallet.value = null;
    loadError.value = error instanceof Error ? error.message : "钱包加载失败";
  } finally { loading.value = false; }
}

function quickAmount(value: string) { amount.value = value; }

async function submit() {
  if (!session.currentSiteId || !memberId.value || saving.value || !canAdjust.value || !wallet.value || loadError.value) return;
  const numeric = Number(amount.value);
  if (!Number.isFinite(numeric) || numeric <= 0) {
    uni.showToast({ title: "请输入大于 0 的调整金额", icon: "none" });
    return;
  }
  if (reason.value.trim().length < 4) {
    uni.showToast({ title: "调整原因至少 4 个字符", icon: "none" });
    return;
  }
  const direction = adjustmentDirection.value === "credit" ? "增加" : "扣减";
  const signedAmount = adjustmentDirection.value === "credit" ? numeric : -numeric;
  const confirmed = await uni.showModal({
    title: `确认${direction}钱包余额`,
    content: `${memberName.value}：${direction} ¥${numeric.toFixed(2)}。本操作将写入不可变更的余额流水。`,
  });
  if (!confirmed.confirm) return;
  saving.value = true;
  try {
    const result = await createMemberWalletAdjustment(session.currentSiteId, memberId.value, {
      amount: signedAmount.toFixed(2),
      reason: reason.value.trim(),
      commandKey: createCommandKey(),
      version: wallet.value?.version,
    });
    wallet.value = result.wallet;
    amount.value = "";
    reason.value = "";
    uni.showToast({ title: "余额已调整", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "调整失败", icon: "none" });
  } finally { saving.value = false; }
}

onLoad((options) => {
  memberId.value = Number(options?.memberId || options?.id || 0);
  memberName.value = decodeURIComponent(String(options?.name || "会员"));
});
onShow(async () => { if (await requireStaffAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canRead" class="page-container wallet-page">
    <u-alert v-if="loadError" type="error" :description="loadError" />
    <button v-if="loadError" class="retry-btn" @tap="load">重新加载钱包</button>
    <template v-else>
    <view class="balance-card">
      <text class="member-name">{{ memberName }}</text>
      <text class="balance-label">会员钱包可用余额</text>
      <text class="balance">¥{{ wallet?.balance ?? "0.00" }}</text>
      <text class="updated">{{ wallet?.updatedAt ? `更新于 ${wallet.updatedAt.replace('T', ' ').slice(0, 16)}` : "独立钱包余额，不等同于储值卡" }}</text>
    </view>

    <view v-if="canAdjust" class="form-card">
      <text class="section-title-text">安全调整</text>
      <text class="hint">先选择增加或扣减，再输入正数金额；钱包支付由后端进行余额锁定和防超扣。</text>
      <view class="direction-row">
        <text class="direction" :class="{ active: adjustmentDirection === 'credit' }" @tap="adjustmentDirection = 'credit'">增加余额</text>
        <text class="direction debit" :class="{ active: adjustmentDirection === 'debit' }" @tap="adjustmentDirection = 'debit'">扣减余额</text>
      </view>
      <view class="amount-box"><text>¥</text><input v-model="amount" type="digit" placeholder="请输入正数金额" /></view>
      <view class="quick-row">
        <text v-for="value in ['100', '500', '1000']" :key="value" class="quick" @tap="quickAmount(value)">¥{{ value }}</text>
      </view>
      <textarea v-model="reason" class="reason" maxlength="200" placeholder="必填：调整原因" />
      <button class="submit-btn" :disabled="saving" @tap="submit">{{ saving ? "处理中…" : "确认调整" }}</button>
    </view>

    <view class="ledger-card">
      <text class="section-title-text">余额流水</text>
      <text class="hint">流水仅追加不可删除，购卡扣款与人工调整分开记录。</text>
      <view v-for="entry in wallet?.ledgerEntries ?? []" :key="entry.id" class="ledger-row">
        <view>
          <text class="ledger-reason">{{ entry.reason || (entry.entryType === "card_purchase" ? "余额购卡" : "余额调整") }}</text>
          <text class="ledger-time">{{ entry.occurredAt?.replace("T", " ").slice(0, 16) || "—" }}</text>
        </view>
        <view class="ledger-amount" :class="entry.direction">
          <text>{{ entry.direction === "credit" ? "+" : "-" }}¥{{ entry.amount }}</text>
          <small>余额 ¥{{ entry.balanceAfter }}</small>
        </view>
      </view>
      <u-empty v-if="!(wallet?.ledgerEntries?.length)" mode="list" text="暂无余额流水" />
    </view>

    <u-alert type="warning" description="余额调整不会修改任何会员卡额度；售卡选择“余额支付”时才会从该钱包扣款。" />
    </template>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无会员钱包查看权限" />
</template>

<style scoped lang="scss">
.wallet-page { padding-bottom: 60rpx; }
.balance-card { display: flex; flex-direction: column; align-items: center; padding: 44rpx 24rpx; color: #fff; background: linear-gradient(135deg, #696b99, #404269); border-radius: $radius-lg; }
.member-name { font-size: 25rpx; opacity: .8; }
.balance-label { margin-top: 18rpx; font-size: 23rpx; opacity: .75; }
.balance { margin-top: 8rpx; font-size: 62rpx; font-weight: 600; }
.updated { margin-top: 16rpx; font-size: 21rpx; opacity: .68; }
.form-card, .ledger-card { margin: 20rpx 0; padding: 28rpx 24rpx; background: #fff; border-radius: $radius-lg; }
.section-title-text { display: block; font-size: 30rpx; font-weight: 600; }
.hint { display: block; margin-top: 8rpx; color: $color-text-tertiary; font-size: 22rpx; line-height: 34rpx; }
.direction-row { display: flex; gap: 12rpx; margin-top: 22rpx; }
.direction { flex: 1; padding: 14rpx; color: $color-text-secondary; background: #f5f5f5; border: 1rpx solid transparent; border-radius: 12rpx; font-size: 24rpx; text-align: center; }
.direction.active { color: #168d61; background: #e8f8f1; border-color: #b9e8d4; }
.direction.debit.active { color: $color-danger; background: #fdeef1; border-color: #f1c6ce; }
.amount-box { display: flex; align-items: center; gap: 12rpx; margin-top: 28rpx; padding: 22rpx; background: #f5f5f5; border-radius: 14rpx; font-size: 36rpx; }
.amount-box input { flex: 1; font-size: 34rpx; }
.quick-row { display: flex; gap: 14rpx; margin-top: 16rpx; }
.quick { padding: 9rpx 20rpx; color: $color-text-secondary; background: #f5f5f5; border-radius: 999rpx; font-size: 23rpx; }
.reason { width: 100%; height: 150rpx; margin-top: 22rpx; padding: 18rpx; background: #f5f5f5; border-radius: 14rpx; font-size: 25rpx; box-sizing: border-box; }
.submit-btn { height: 82rpx; margin-top: 28rpx; color: $color-text; background: $color-brand-yellow; border-radius: 41rpx; font-size: 29rpx; line-height: 82rpx; }
.submit-btn::after { border: 0; }
.retry-btn { width: 360rpx; margin: 32rpx auto; color: $color-text; background: $color-brand-yellow; border-radius: 40rpx; }
.retry-btn::after { border: 0; }
.ledger-row { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; padding: 22rpx 0; border-bottom: 1rpx solid #f1f1f1; }
.ledger-row:last-child { border-bottom: 0; }
.ledger-reason, .ledger-time, .ledger-amount small { display: block; }
.ledger-reason { font-size: 25rpx; }
.ledger-time, .ledger-amount small { margin-top: 5rpx; color: $color-text-tertiary; font-size: 20rpx; }
.ledger-amount { flex-shrink: 0; color: $color-danger; font-size: 26rpx; text-align: right; }
.ledger-amount.credit { color: #168d61; }
</style>
