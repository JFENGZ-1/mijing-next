<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  adjustMemberCardBalance,
  adjustMemberCardCount,
  archiveMemberCard,
  endMemberCardHoliday,
  extendMemberCardValidity,
  fetchMemberCardBenefits,
  fetchMemberCardDetail,
  fetchMemberCardLedgerEntries,
  freezeMemberCard,
  restoreMemberCard,
  startMemberCardHoliday,
  unfreezeMemberCard,
} from "@/api/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  StaffMemberCardBenefits,
  StaffMemberCardDetail,
  StaffMemberCardLedgerEntry,
} from "@/types/member-cards";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const memberId = ref<number>();
const memberCardId = ref<number>();
const card = ref<StaffMemberCardDetail | null>(null);
const benefits = ref<StaffMemberCardBenefits | null>(null);
const benefitsLoading = ref(false);
const ledgerItems = ref<StaffMemberCardLedgerEntry[]>([]);
const loading = ref(true);
const actionLoading = ref(false);
const ledgerLoading = ref(false);
const loadingMore = ref(false);
const errorMessage = ref("");
const reason = ref("");
const adjustDirection = ref<"credit" | "debit">("credit");
const adjustAmount = ref("");
const adjustCount = ref("");
const adjustReason = ref("");
const holidayPlannedEndDate = ref("");
const holidayReason = ref("");
const extendDays = ref("");
const extendValidUntil = ref("");
const validityReason = ref("");
const adjustMode = ref<"new" | "correct">("new");
const correctsEntryId = ref<number | null>(null);
const ledgerPage = ref(1);
const ledgerLastPage = ref(1);
type AdjustPanel = "balance" | "count" | null;
type ActionPanel = "holiday-start" | "holiday-end" | "validity" | null;
const adjustPanel = ref<AdjustPanel>(null);
const actionPanel = ref<ActionPanel>(null);

const canRead = computed(() => session.can("member-card.read") || session.can("crm.member.card.read"));
const canFreeze = computed(() => session.can("member-card.freeze"));
const canBalanceAdjust = computed(() => session.can("member-card.balance.adjust"));
const canCountAdjust = computed(() => session.can("member-card.count.adjust"));
const canArchive = computed(() => session.can("member-card.archive"));
const canHolidayManage = computed(() => session.can("member-card.holiday.manage"));
const canValidityExtend = computed(() => session.can("member-card.validity.extend"));

const isStoredValue = computed(() => card.value?.cardType === "stored_value");
const isCount = computed(() => card.value?.cardType === "count");
const isFrozen = computed(() => card.value?.status === "frozen");
const isArchived = computed(() => card.value?.status === "archived");
const isOnHoliday = computed(() => !!card.value?.freezeState?.holiday);
const canShowAdjust = computed(
  () => !isArchived.value && ((isStoredValue.value && canBalanceAdjust.value) || (isCount.value && canCountAdjust.value)),
);
const canShowFreeze = computed(() => canFreeze.value && !isArchived.value && !isOnHoliday.value && card.value?.status === "active");
const canShowUnfreeze = computed(() => canFreeze.value && isFrozen.value);
const canShowArchive = computed(() => canArchive.value && !isArchived.value);
const canShowHolidayStart = computed(
  () => canHolidayManage.value && !isArchived.value && card.value?.status === "active" && !isFrozen.value && !isOnHoliday.value,
);
const canShowHolidayEnd = computed(() => canHolidayManage.value && isOnHoliday.value);
const canShowValidityExtend = computed(
  () => canValidityExtend.value && !isArchived.value && !!card.value?.validUntil,
);
const canShowRestore = computed(() => canArchive.value && isArchived.value);
const hasCardActions = computed(
  () =>
    canShowAdjust.value ||
    canShowFreeze.value ||
    canShowUnfreeze.value ||
    canShowArchive.value ||
    canShowHolidayStart.value ||
    canShowHolidayEnd.value ||
    canShowValidityExtend.value ||
    canShowRestore.value,
);
const correctableLedgerEntries = computed(() => {
  const entryType = adjustPanel.value === "balance" ? "balance_adjust" : "count_adjust";
  return ledgerItems.value.filter((entry) => {
    if (entry.entryType !== entryType) return false;
    if (adjustPanel.value === "balance" && !entry.amountDelta) return false;
    if (adjustPanel.value === "count" && entry.countDelta == null) return false;
    return !ledgerItems.value.some((item) => item.entryType === "reversal" && item.reversalOfId === entry.id);
  });
});
const selectedCorrectEntry = computed(
  () => ledgerItems.value.find((entry) => entry.id === correctsEntryId.value) ?? null,
);

const entryTypeLabels: Record<string, string> = {
  issue: "发卡",
  purchase: "购卡",
  recharge: "充值",
  balance_adjust: "余额调整",
  count_deduct: "扣次",
  count_adjust: "次数调整",
  validity_change: "有效期变更",
  freeze: "冻结",
  freeze_lift: "解冻",
  holiday_apply: "请假停卡",
  holiday_cancel: "结束请假",
  penalty: "处罚",
  reversal: "冲正",
  correction: "更正",
  expire: "过期",
  void: "作废",
  archive: "归档",
  archive_restore: "恢复归档",
  visibility_change: "可见性变更",
};

function statusLabel(status: string) {
  return ({
    pending: "待激活",
    active: "有效",
    frozen: "冻结",
    expired: "已过期",
    archived: "已归档",
    voided: "已作废",
  } as Record<string, string>)[status] || status;
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return "—";
  return value.slice(0, 16).replace("T", " ");
}

function balanceText() {
  if (!card.value) return "";
  if (card.value.cardType === "stored_value") {
    return card.value.cachedBalance ? `余额 ¥${card.value.cachedBalance}` : "余额 —";
  }
  if (card.value.cardType === "count") {
    return card.value.cachedRemainingCount != null ? `剩余 ${card.value.cachedRemainingCount} 次` : "剩余 —";
  }
  if (card.value.validUntil) return `有效期至 ${card.value.validUntil}`;
  return "";
}

function ledgerSummary(entry: StaffMemberCardLedgerEntry) {
  return entry.reason?.trim() || entryTypeLabels[entry.entryType] || entry.entryType;
}

function scopeKindLabel(kind: string | undefined) {
  return ({ course: "课程", group: "课程组", single: "单课" } as Record<string, string>)[kind || ""] || kind || "范围";
}

function scopeDisplayName(scope: { displayName?: string | null; scopeKey?: string }) {
  return scope.displayName?.trim() || scope.scopeKey || "未命名";
}

function entitlementsText(data: StaffMemberCardBenefits) {
  const parts: string[] = [];
  if (data.entitlements.cachedBalance) parts.push(`余额 ¥${data.entitlements.cachedBalance}`);
  if (data.entitlements.cachedRemainingCount != null) parts.push(`剩余 ${data.entitlements.cachedRemainingCount} 次`);
  if (data.entitlements.validFrom || data.entitlements.validUntil) {
    parts.push(`有效期 ${data.entitlements.validFrom || "—"} ~ ${data.entitlements.validUntil || "—"}`);
  }
  return parts.length ? parts.join(" · ") : "—";
}

function bookingRulesSummary(rules: Record<string, unknown> | null) {
  if (!rules) return "";
  const parts: string[] = [];
  if (typeof rules.advanceHours === "number") parts.push(`提前 ${rules.advanceHours} 小时预约`);
  if (typeof rules.cancelHours === "number") parts.push(`提前 ${rules.cancelHours} 小时取消`);
  return parts.join(" · ");
}

async function loadCard() {
  if (!memberCardId.value || !session.currentSiteId || !canRead.value) return;
  const response = await fetchMemberCardDetail(session.currentSiteId, memberCardId.value);
  card.value = response.data;
}

async function loadBenefits() {
  if (!memberCardId.value || !session.currentSiteId || !canRead.value) return;
  benefitsLoading.value = true;
  try {
    const response = await fetchMemberCardBenefits(session.currentSiteId, memberCardId.value);
    benefits.value = response.data;
  } catch {
    benefits.value = null;
  } finally {
    benefitsLoading.value = false;
  }
}

async function loadLedger(reset = true) {
  if (!memberCardId.value || !session.currentSiteId || !canRead.value) return;
  if (reset) {
    ledgerPage.value = 1;
    ledgerItems.value = [];
    ledgerLastPage.value = 1;
    ledgerLoading.value = true;
  } else {
    loadingMore.value = true;
  }
  try {
    const response = await fetchMemberCardLedgerEntries(
      session.currentSiteId,
      memberCardId.value,
      ledgerPage.value,
    );
    if (reset) {
      ledgerItems.value = response.data.items;
    } else {
      ledgerItems.value = [...ledgerItems.value, ...response.data.items];
    }
    ledgerLastPage.value = response.data.pagination.lastPage;
  } finally {
    ledgerLoading.value = false;
    loadingMore.value = false;
  }
}

async function loadDetail() {
  if (!memberCardId.value || memberCardId.value < 1 || !session.currentSiteId) {
    errorMessage.value = "会员卡参数或场馆上下文无效";
    loading.value = false;
    return;
  }
  if (!canRead.value) {
    errorMessage.value = "暂无会员卡查看权限";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    await loadCard();
    await Promise.all([loadBenefits(), loadLedger()]);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员卡详情加载失败";
  } finally {
    loading.value = false;
  }
}

async function reloadAfterAction() {
  await loadCard();
  await loadLedger();
}

async function runStateAction(kind: "freeze" | "unfreeze" | "archive") {
  if (!card.value || !session.currentSiteId || !reason.value.trim()) {
    uni.showToast({ title: "请填写操作原因", icon: "none" });
    return;
  }
  const labels = { freeze: "冻结", unfreeze: "解冻", archive: "归档" };
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: `${labels[kind]}会员卡`,
      content: `确认${labels[kind]}「${card.value!.name || card.value!.cardNo}」？`,
      success: (result) => resolve(!!result.confirm),
    });
  });
  if (!confirmed) return;

  actionLoading.value = true;
  const commandKey = createCommandKey();
  try {
    const payload = { reason: reason.value.trim(), commandKey };
    if (kind === "freeze") {
      await freezeMemberCard(session.currentSiteId, card.value.id, payload);
    } else if (kind === "unfreeze") {
      await unfreezeMemberCard(session.currentSiteId, card.value.id, payload);
    } else {
      await archiveMemberCard(session.currentSiteId, card.value.id, payload);
    }
    reason.value = "";
    uni.showToast({ title: `${labels[kind]}成功`, icon: "success" });
    await reloadAfterAction();
    if (kind === "archive") {
      setTimeout(() => uni.navigateBack(), 500);
    }
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : `${labels[kind]}失败`, icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

function openAdjustPanel(panel: AdjustPanel) {
  adjustPanel.value = panel;
  actionPanel.value = null;
  adjustDirection.value = "credit";
  adjustAmount.value = "";
  adjustCount.value = "";
  adjustReason.value = "";
  adjustMode.value = "new";
  correctsEntryId.value = null;
}

function openActionPanel(panel: ActionPanel) {
  actionPanel.value = panel;
  adjustPanel.value = null;
  holidayPlannedEndDate.value = "";
  holidayReason.value = "";
  extendDays.value = "";
  extendValidUntil.value = "";
  validityReason.value = "";
}

function chooseCorrectEntry() {
  const entries = correctableLedgerEntries.value;
  if (entries.length === 0) {
    uni.showToast({ title: "暂无可更正记录", icon: "none" });
    return;
  }
  uni.showActionSheet({
    itemList: entries.map((entry) => `#${entry.id} ${ledgerSummary(entry)} · ${formatDateTime(entry.occurredAt)}`),
    success: ({ tapIndex }) => {
      correctsEntryId.value = entries[tapIndex].id;
    },
  });
}

function correctEntryLabel(entry: StaffMemberCardLedgerEntry) {
  const delta =
    entry.amountDelta != null
      ? `${entry.direction === "credit" ? "+" : "-"}¥${entry.amountDelta}`
      : `${entry.direction === "credit" ? "+" : "-"}${entry.countDelta}次`;
  return `#${entry.id} ${ledgerSummary(entry)} (${delta})`;
}

async function submitAdjust() {
  if (!card.value || !session.currentSiteId || !adjustReason.value.trim()) {
    uni.showToast({ title: "请填写调整原因", icon: "none" });
    return;
  }
  if (adjustMode.value === "correct" && !correctsEntryId.value) {
    uni.showToast({ title: "请选择要更正的记录", icon: "none" });
    return;
  }
  if (adjustPanel.value === "balance") {
    const amount = Number(adjustAmount.value);
    if (!Number.isFinite(amount) || amount <= 0) {
      uni.showToast({ title: "请输入有效金额", icon: "none" });
      return;
    }
  } else if (adjustPanel.value === "count") {
    const count = Number.parseInt(adjustCount.value, 10);
    if (!Number.isFinite(count) || count < 1) {
      uni.showToast({ title: "请输入有效次数", icon: "none" });
      return;
    }
  }

  actionLoading.value = true;
  const commandKey = createCommandKey();
  const correctionId = adjustMode.value === "correct" ? correctsEntryId.value ?? undefined : undefined;
  try {
    if (adjustPanel.value === "balance") {
      await adjustMemberCardBalance(session.currentSiteId, card.value.id, {
        direction: adjustDirection.value,
        amount: Number(adjustAmount.value),
        reason: adjustReason.value.trim(),
        commandKey,
        correctsEntryId: correctionId,
      });
    } else if (adjustPanel.value === "count") {
      await adjustMemberCardCount(session.currentSiteId, card.value.id, {
        direction: adjustDirection.value,
        count: Number.parseInt(adjustCount.value, 10),
        reason: adjustReason.value.trim(),
        commandKey,
        correctsEntryId: correctionId,
      });
    }
    adjustPanel.value = null;
    uni.showToast({ title: "调整成功", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "调整失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitHolidayStart() {
  if (!card.value || !session.currentSiteId || !holidayPlannedEndDate.value || !holidayReason.value.trim()) {
    uni.showToast({ title: "请填写计划结束日期和原因", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    await startMemberCardHoliday(session.currentSiteId, card.value.id, {
      plannedEndDate: holidayPlannedEndDate.value,
      reason: holidayReason.value.trim(),
      commandKey: createCommandKey(),
    });
    actionPanel.value = null;
    uni.showToast({ title: "请假已开始", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "请假开始失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitHolidayEnd() {
  if (!card.value || !session.currentSiteId || !holidayReason.value.trim()) {
    uni.showToast({ title: "请填写结束原因", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    await endMemberCardHoliday(session.currentSiteId, card.value.id, {
      reason: holidayReason.value.trim(),
      commandKey: createCommandKey(),
    });
    actionPanel.value = null;
    uni.showToast({ title: "请假已结束", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "结束请假失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitValidityExtend() {
  if (!card.value || !session.currentSiteId || !validityReason.value.trim()) {
    uni.showToast({ title: "请填写延期原因", icon: "none" });
    return;
  }
  const days = extendDays.value.trim() ? Number.parseInt(extendDays.value, 10) : undefined;
  const until = extendValidUntil.value.trim() || undefined;
  if (!days && !until) {
    uni.showToast({ title: "请填写延长天数或新到期日", icon: "none" });
    return;
  }
  if (days != null && (!Number.isFinite(days) || days < 1)) {
    uni.showToast({ title: "请输入有效延长天数", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    await extendMemberCardValidity(session.currentSiteId, card.value.id, {
      reason: validityReason.value.trim(),
      commandKey: createCommandKey(),
      extendDays: days,
      validUntil: until,
    });
    actionPanel.value = null;
    uni.showToast({ title: "有效期已延长", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "延期失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function runRestore() {
  if (!card.value || !session.currentSiteId || !reason.value.trim()) {
    uni.showToast({ title: "请填写操作原因", icon: "none" });
    return;
  }
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "恢复归档会员卡",
      content: `确认恢复「${card.value!.name || card.value!.cardNo}」？`,
      success: (result) => resolve(!!result.confirm),
    });
  });
  if (!confirmed) return;

  actionLoading.value = true;
  try {
    await restoreMemberCard(session.currentSiteId, card.value.id, {
      reason: reason.value.trim(),
      commandKey: createCommandKey(),
    });
    reason.value = "";
    uni.showToast({ title: "恢复成功", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "恢复失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function loadMoreLedger() {
  if (loadingMore.value || ledgerPage.value >= ledgerLastPage.value) return;
  ledgerPage.value += 1;
  try {
    await loadLedger(false);
  } catch (error) {
    ledgerPage.value -= 1;
    uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
  }
}

onLoad((options) => {
  memberId.value = Number(options?.memberId);
  memberCardId.value = Number(options?.memberCardId);
});

onShow(async () => {
  if (await requireStaffAuth()) await loadDetail();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <template v-else-if="card">
      <view class="identity-row">
        <view class="identity-main">
          <view class="name-row">
            <text class="name">{{ card.name || card.cardNo }}</text>
            <u-tag :text="statusLabel(card.status)" size="mini" plain />
          </view>
          <view class="meta">卡号 {{ card.cardNo }} · {{ balanceText() }}</view>
          <view v-if="card.validFrom || card.validUntil" class="meta">
            有效期 {{ card.validFrom || "—" }} ~ {{ card.validUntil || "—" }}
          </view>
          <view v-if="card.freezeState?.reason" class="meta">冻结原因：{{ card.freezeState.reason }}</view>
          <view v-if="isOnHoliday" class="meta">
            请假中 {{ card.freezeState?.holiday?.startedAt || "—" }} ~ {{ card.freezeState?.holiday?.plannedEndAt || "—" }}
          </view>
        </view>
      </view>

      <view v-if="hasCardActions" class="section-band">
        <view class="section-heading">卡操作</view>
        <view v-if="canShowAdjust || canShowHolidayStart || canShowHolidayEnd || canShowValidityExtend" class="command-grid">
          <u-button v-if="isStoredValue && canBalanceAdjust" plain @click="openAdjustPanel('balance')">余额调整</u-button>
          <u-button v-if="isCount && canCountAdjust" plain @click="openAdjustPanel('count')">次数调整</u-button>
          <u-button v-if="canShowHolidayStart" plain @click="openActionPanel('holiday-start')">开始请假</u-button>
          <u-button v-if="canShowHolidayEnd" plain @click="openActionPanel('holiday-end')">结束请假</u-button>
          <u-button v-if="canShowValidityExtend" plain @click="openActionPanel('validity')">延长有效期</u-button>
        </view>
        <template v-if="canShowFreeze || canShowUnfreeze || canShowArchive || canShowRestore">
          <u-input v-model="reason" placeholder="填写操作原因（冻结/解冻/归档/恢复）" />
          <view class="command-grid">
            <u-button v-if="canShowFreeze" type="warning" :loading="actionLoading" @click="runStateAction('freeze')">冻结</u-button>
            <u-button v-if="canShowUnfreeze" type="primary" :loading="actionLoading" @click="runStateAction('unfreeze')">解冻</u-button>
            <u-button v-if="canShowArchive" type="error" plain :loading="actionLoading" @click="runStateAction('archive')">归档</u-button>
            <u-button v-if="canShowRestore" type="primary" :loading="actionLoading" @click="runRestore">恢复归档</u-button>
          </view>
        </template>
      </view>

      <view v-if="adjustPanel" class="section-band">
        <view class="section-heading">{{ adjustPanel === 'balance' ? '余额调整' : '次数调整' }}</view>
        <view class="scope-row">
          <button
            class="scope-button"
            :class="{ active: adjustMode === 'new' }"
            @click="adjustMode = 'new'; correctsEntryId = null"
          >
            新增调整
          </button>
          <button
            class="scope-button"
            :class="{ active: adjustMode === 'correct' }"
            @click="adjustMode = 'correct'"
          >
            更正记录
          </button>
        </view>
        <view v-if="adjustMode === 'correct'" class="picker-row" @click="chooseCorrectEntry">
          <text v-if="selectedCorrectEntry" class="picker-value">{{ correctEntryLabel(selectedCorrectEntry) }}</text>
          <text v-else class="picker-placeholder">选择要更正的变动记录</text>
        </view>
        <view class="scope-row">
          <button
            class="scope-button"
            :class="{ active: adjustDirection === 'credit' }"
            @click="adjustDirection = 'credit'"
          >
            增加
          </button>
          <button
            class="scope-button"
            :class="{ active: adjustDirection === 'debit' }"
            @click="adjustDirection = 'debit'"
          >
            扣减
          </button>
        </view>
        <u-input
          v-if="adjustPanel === 'balance'"
          v-model="adjustAmount"
          type="digit"
          :placeholder="adjustMode === 'correct' ? '更正后金额' : '调整金额'"
        />
        <u-input
          v-else
          v-model="adjustCount"
          type="number"
          :placeholder="adjustMode === 'correct' ? '更正后次数' : '调整次数'"
        />
        <u-input v-model="adjustReason" placeholder="调整原因" />
        <view class="command-grid">
          <u-button type="primary" :loading="actionLoading" @click="submitAdjust">提交调整</u-button>
          <u-button plain @click="adjustPanel = null">取消</u-button>
        </view>
      </view>

      <view v-if="actionPanel === 'holiday-start'" class="section-band">
        <view class="section-heading">开始请假</view>
        <picker mode="date" :value="holidayPlannedEndDate" @change="holidayPlannedEndDate = String($event.detail.value)">
          <view class="picker-field">{{ holidayPlannedEndDate || '请选择计划结束日期' }}</view>
        </picker>
        <u-input v-model="holidayReason" placeholder="请假原因" />
        <view class="command-grid">
          <u-button type="primary" :loading="actionLoading" @click="submitHolidayStart">提交</u-button>
          <u-button plain @click="actionPanel = null">取消</u-button>
        </view>
      </view>

      <view v-if="actionPanel === 'holiday-end'" class="section-band">
        <view class="section-heading">结束请假</view>
        <u-input v-model="holidayReason" placeholder="结束原因" />
        <view class="command-grid">
          <u-button type="primary" :loading="actionLoading" @click="submitHolidayEnd">提交</u-button>
          <u-button plain @click="actionPanel = null">取消</u-button>
        </view>
      </view>

      <view v-if="actionPanel === 'validity'" class="section-band">
        <view class="section-heading">延长有效期</view>
        <u-input v-model="extendDays" type="number" placeholder="延长天数（与到期日二选一）" />
        <picker mode="date" :value="extendValidUntil" @change="extendValidUntil = String($event.detail.value)">
          <view class="picker-field">{{ extendValidUntil || '或选择新到期日' }}</view>
        </picker>
        <u-input v-model="validityReason" placeholder="延期原因" />
        <view class="command-grid">
          <u-button type="primary" :loading="actionLoading" @click="submitValidityExtend">提交</u-button>
          <u-button plain @click="actionPanel = null">取消</u-button>
        </view>
      </view>

      <view class="section-band">
        <view class="section-heading">权益范围</view>
        <u-loading-page :loading="benefitsLoading" />
        <template v-if="!benefitsLoading && benefits">
          <view class="meta">{{ entitlementsText(benefits) }}</view>
          <u-empty
            v-if="benefits.courseScopes.length === 0"
            mode="list"
            text="暂无课程范围配置"
          />
          <view v-for="(scope, index) in benefits.courseScopes" :key="index" class="benefit-row">
            <view class="card-title">{{ scopeDisplayName(scope) }}</view>
            <view class="card-meta">{{ scopeKindLabel(scope.scopeKind as string) }}</view>
          </view>
          <view v-if="bookingRulesSummary(benefits.bookingRules)" class="meta">
            预约规则：{{ bookingRulesSummary(benefits.bookingRules) }}
          </view>
        </template>
        <u-empty v-else-if="!benefitsLoading && !benefits" mode="list" text="权益信息不可用" />
      </view>

      <view class="section-band">
        <view class="section-heading">变动记录</view>
        <u-loading-page :loading="ledgerLoading" />
        <u-empty v-if="!ledgerLoading && ledgerItems.length === 0" mode="list" text="暂无变动记录" />
        <view v-for="item in ledgerItems" :key="item.id" class="ledger-row">
          <view class="ledger-header">
            <view class="card-title">{{ ledgerSummary(item) }}</view>
            <view
              v-if="item.amountDelta"
              class="ledger-amount"
              :class="item.direction === 'credit' ? 'amount-credit' : 'amount-debit'"
            >
              {{ item.direction === "credit" ? "+" : "-" }}¥{{ item.amountDelta }}
            </view>
            <view
              v-else-if="item.countDelta != null"
              class="ledger-amount"
              :class="item.direction === 'credit' ? 'amount-credit' : 'amount-debit'"
            >
              {{ item.direction === "credit" ? "+" : "-" }}{{ item.countDelta }}次
            </view>
          </view>
          <view class="card-meta">{{ formatDateTime(item.occurredAt) }}</view>
        </view>
        <u-button
          v-if="ledgerPage < ledgerLastPage"
          plain
          :loading="loadingMore"
          @click="loadMoreLedger"
        >
          加载更多
        </u-button>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.detail-page { padding-bottom: 48rpx; }
// 原版实体卡面视觉：深色渐变圆角卡
.identity-row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  margin-bottom: 8rpx;
  padding: 36rpx 32rpx;
  background: linear-gradient(135deg, #33383f 0%, #181818 100%);
  border-radius: 20rpx;
}
.identity-main { min-width: 0; flex: 1; }
.name-row { display: flex; align-items: center; gap: 12rpx; }
.name { font-size: 36rpx; font-weight: 600; color: #f7d8a5; }
.identity-row .meta { margin-top: 10rpx; color: rgba(255, 255, 255, 0.75); font-size: 24rpx; }
.meta, .card-meta { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
.section-band { margin-top: 16rpx; padding: 28rpx 24rpx; background: $color-surface; border-radius: 20rpx; }
.section-heading { font-size: 30rpx; font-weight: 600; }
.command-grid { display: grid; gap: 16rpx; margin-top: 20rpx; }
.scope-row { display: flex; gap: 12rpx; margin: 20rpx 0; }
.scope-button { margin: 0; padding: 12rpx 24rpx; color: $color-text-secondary; font-size: 24rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 999rpx; }
.scope-button.active { color: #fff; background: $color-primary; border-color: $color-primary; }
.scope-button::after { border: 0; }
.benefit-row { padding: 16rpx 0; border-bottom: 1rpx solid $color-border; font-size: 27rpx; }
.ledger-row { padding: 20rpx 0; border-bottom: 1rpx solid $color-border; font-size: 27rpx; }
.ledger-header { display: flex; align-items: center; justify-content: space-between; gap: 12rpx; }
.card-title { font-weight: 600; }
.ledger-amount { font-size: 28rpx; font-weight: 600; }
.amount-credit { color: #16a34a; }
.amount-debit { color: #dc2626; }
.picker-row { display: flex; align-items: center; margin-top: 20rpx; padding: 24rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 12rpx; }
.picker-value { font-size: 28rpx; }
.picker-placeholder { color: $color-text-secondary; font-size: 28rpx; }
.picker-field { min-height: 80rpx; box-sizing: border-box; margin-top: 20rpx; padding: 20rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: $radius-sm; }
</style>
