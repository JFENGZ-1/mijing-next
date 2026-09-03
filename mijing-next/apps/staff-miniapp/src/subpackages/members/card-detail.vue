<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  adjustMemberCardBalance,
  adjustMemberCardCount,
  archiveMemberCard,
  endMemberCardHoliday,
  extendMemberCardValidity,
  fetchMemberCardDetail,
  fetchMemberCardLedgerEntries,
  freezeMemberCard,
  restoreMemberCard,
  startMemberCardHoliday,
  unfreezeMemberCard,
  updateMemberCardRemark,
} from "@/api/member-cards";
import { fetchCrmMember, fetchMemberBookingHistory } from "@/api/crm";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffBookingHistoryItem } from "@/types/crm";
import type { StaffMemberCardDetail, StaffMemberCardLedgerEntry } from "@/types/member-cards";
import { createCommandKey } from "@/utils/command-key";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";

type SheetKind =
  | "balance"
  | "count"
  | "validity"
  | "holiday-start"
  | "holiday-end"
  | "freeze"
  | "unfreeze"
  | "remark"
  | "archive"
  | "restore"
  | null;

const CHANGE_ENTRY_TYPES = new Set([
  "validity_change",
  "freeze",
  "freeze_lift",
  "holiday_apply",
  "holiday_cancel",
  "archive",
  "archive_restore",
  "issue",
  "visibility_change",
]);

const BALANCE_ENTRY_TYPES = new Set([
  "issue",
  "purchase",
  "recharge",
  "balance_adjust",
  "count_deduct",
  "count_adjust",
  "period_use",
  "penalty",
  "reversal",
  "correction",
]);

const session = useSessionStore();
const memberId = ref<number>();
const memberCardId = ref<number>();
const memberName = ref("");
const memberAvatar = ref("");
const card = ref<StaffMemberCardDetail | null>(null);
const ledgerItems = ref<StaffMemberCardLedgerEntry[]>([]);
const bookingItems = ref<StaffBookingHistoryItem[]>([]);
const bookingFiltered = ref(false);
const loading = ref(true);
const actionLoading = ref(false);
const ledgerLoading = ref(false);
const bookingLoading = ref(false);
const loadingMore = ref(false);
const errorMessage = ref("");
const ledgerPage = ref(1);
const ledgerLastPage = ref(1);
const tabIndex = ref(0);
const tabList = [
  { name: "卡管理" },
  { name: "卡信息" },
  { name: "上课记录" },
  { name: "余额变动" },
  { name: "变更记录" },
  { name: "惩罚记录" },
];

const activeSheet = ref<SheetKind>(null);
const adjustDirection = ref<"credit" | "debit">("credit");
const adjustAmount = ref("");
const adjustCount = ref("");
const adjustReason = ref("");
const adjustMode = ref<"new" | "correct">("new");
const correctsEntryId = ref<number | null>(null);
const holidayPlannedEndDate = ref("");
const holidayReason = ref("");
const extendDays = ref("");
const extendValidUntil = ref("");
const validityReason = ref("");
const freezeReason = ref("");
const remarkText = ref("");

const statusBarHeight = uni.getSystemInfoSync().statusBarHeight ?? 20;
const customBarHeight = (() => {
  try {
    const menu = uni.getMenuButtonBoundingClientRect();
    return menu.height + (menu.top - statusBarHeight) * 2;
  } catch {
    return 44;
  }
})();
const navTotalHeight = statusBarHeight + customBarHeight;

const canRead = computed(() => session.can("member-card.read") || session.can("crm.member.card.read"));
const canFreeze = computed(() => session.can("member-card.freeze"));
const canBalanceAdjust = computed(() => session.can("member-card.balance.adjust"));
const canCountAdjust = computed(() => session.can("member-card.count.adjust"));
const canArchive = computed(() => session.can("member-card.archive"));
const canHolidayManage = computed(() => session.can("member-card.holiday.manage"));
const canValidityExtend = computed(() => session.can("member-card.validity.extend"));
const canReadShareAssignments = computed(() => session.can("compensation.rule.read"));

const isStoredValue = computed(() => card.value?.cardType === "stored_value");
const isCount = computed(() => card.value?.cardType === "count");
const isFrozen = computed(() => card.value?.status === "frozen");
const isArchived = computed(() => card.value?.status === "archived");
const isPending = computed(() => card.value?.status === "pending_activation");
const isOnHoliday = computed(() => !!card.value?.freezeState?.holiday);

const sheetTitle = computed(() => {
  switch (activeSheet.value) {
    case "balance":
      return "改余额";
    case "count":
      return "改次数";
    case "validity":
      return "有效期";
    case "holiday-start":
      return "请假";
    case "holiday-end":
      return "结束请假";
    case "freeze":
      return "停卡";
    case "unfreeze":
      return "恢复停卡";
    case "remark":
      return "卡备注";
    case "archive":
      return "删除卡";
    case "restore":
      return "恢复归档";
    default:
      return "";
  }
});

const correctableLedgerEntries = computed(() => {
  const entryType = activeSheet.value === "balance" ? "balance_adjust" : "count_adjust";
  return ledgerItems.value.filter((entry) => {
    if (entry.entryType !== entryType) return false;
    if (activeSheet.value === "balance" && !entry.amountDelta) return false;
    if (activeSheet.value === "count" && entry.countDelta == null) return false;
    return !ledgerItems.value.some((item) => item.entryType === "reversal" && item.reversalOfId === entry.id);
  });
});

const selectedCorrectEntry = computed(
  () => ledgerItems.value.find((entry) => entry.id === correctsEntryId.value) ?? null,
);

const balanceChangeItems = computed(() =>
  ledgerItems.value.filter(
    (entry) =>
      BALANCE_ENTRY_TYPES.has(entry.entryType) ||
      entry.amountDelta != null ||
      entry.countDelta != null,
  ),
);

const changeLogItems = computed(() =>
  ledgerItems.value.filter((entry) => CHANGE_ENTRY_TYPES.has(entry.entryType)),
);

const penaltyItems = computed(() => ledgerItems.value.filter((entry) => entry.entryType === "penalty"));

const holidayMsg = computed(() => {
  if (!isOnHoliday.value || !card.value?.freezeState?.holiday) return "";
  const end = card.value.freezeState.holiday.plannedEndAt || "--";
  return `请假中至 ${end}`;
});

const stopCardMsg = computed(() => {
  if (!isFrozen.value) return "";
  return card.value?.freezeState?.reason ? `已停卡：${card.value.freezeState.reason}` : "已停卡";
});

const paidDisplay = computed(() => card.value?.paidAmount ?? "--");
const unitConvertDisplay = computed(() => card.value?.unitConvert ?? "--");
const residualDisplay = computed(() => card.value?.residualValue ?? "--");

const entryTypeLabels: Record<string, string> = {
  issue: "发卡",
  purchase: "购卡",
  recharge: "充值",
  balance_adjust: "余额调整",
  count_deduct: "扣次",
  count_adjust: "次数调整",
  period_use: "入场使用",
  validity_change: "有效期变更",
  freeze: "停卡",
  freeze_lift: "恢复停卡",
  holiday_apply: "请假",
  holiday_cancel: "结束请假",
  penalty: "惩罚",
  reversal: "冲正",
  correction: "更正",
  expire: "过期",
  void: "作废",
  archive: "删除卡",
  archive_restore: "恢复归档",
  visibility_change: "可见性变更",
};

function statusLabel(status: string) {
  return (
    (
      {
        pending_activation: "待激活",
        pending: "待激活",
        active: "有效",
        frozen: "冻结",
        expired: "已过期",
        exhausted: "已用尽",
        archived: "已归档",
        voided: "已作废",
      } as Record<string, string>
    )[status] || status
  );
}

function cardTypeLabel(type: string | undefined) {
  return (
    (
      {
        stored_value: "储值卡",
        count: "计次卡",
        period: "期限卡",
      } as Record<string, string>
    )[type || ""] ||
    type ||
    "--"
  );
}

function ribbonColor(type: string | undefined) {
  if (type === "stored_value") return "rgba(201, 106, 50, 0.88)";
  if (type === "count") return "rgba(0, 61, 130, 0.88)";
  return "rgba(52, 159, 145, 0.88)";
}

function cardFaceStyle() {
  if (card.value?.faceGradient) return { background: card.value.faceGradient };
  return { background: "linear-gradient(135deg, #fbd128 0%, #f0a020 100%)" };
}

function convertLabel() {
  if (isStoredValue.value) return "每元折算";
  if (isCount.value) return "每次折算";
  return "每天折算";
}

function infoConvertLabel() {
  if (isStoredValue.value) return "价值折算";
  if (isCount.value) return "单次折算";
  return "每天折算";
}

function balanceText() {
  if (!card.value) return "";
  if (card.value.cardType === "stored_value") {
    return card.value.cachedBalance != null ? `${card.value.cachedBalance}元` : "—";
  }
  if (card.value.cardType === "count") {
    return card.value.cachedRemainingCount != null ? `${card.value.cachedRemainingCount}次` : "—";
  }
  if (card.value.validUntil) {
    const days = remainingDays(card.value.validUntil);
    return days != null ? `${days}天` : `至 ${card.value.validUntil}`;
  }
  return "—";
}

function balanceDisplay() {
  if (!card.value) return "--";
  if (isStoredValue.value) return card.value.cachedBalance != null ? `￥${card.value.cachedBalance}` : "--";
  if (isCount.value) return card.value.cachedRemainingCount != null ? `${card.value.cachedRemainingCount}次` : "--";
  const days = card.value.validUntil ? remainingDays(card.value.validUntil) : null;
  return days != null ? `${days}天` : card.value.validUntil || "--";
}

function initialTotalDisplay() {
  if (!card.value) return "--";
  const total = card.value.initialTotal;
  if (total == null || total === "") return "--";
  if (isStoredValue.value) return `￥${total}`;
  if (isCount.value) return `${total}次`;
  return `${total}天`;
}

function holidaySummaryText() {
  const s = card.value?.holidaySummary;
  if (!s) return "已请假0次，合计0天";
  return `已请假${s.count}次，合计${s.days}天`;
}

function freezeSummaryText() {
  const s = card.value?.freezeSummary;
  if (!s) return "已停卡0次，合计0天";
  return `已停卡${s.count}次，合计${s.days}天`;
}

function remainingDays(validUntil: string) {
  const end = new Date(`${validUntil}T00:00:00`);
  if (Number.isNaN(end.getTime())) return null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.max(0, Math.round((end.getTime() - today.getTime()) / 86400000));
}

function goBack() {
  const pages = getCurrentPages();
  if (pages.length > 1) uni.navigateBack();
  else uni.switchTab({ url: "/pages/settings/hub/index" });
}

function openShareAssignments() {
  if (!memberCardId.value || !canReadShareAssignments.value) {
    uni.showToast({ title: "暂无耗卡分成归属查看权限", icon: "none" });
    return;
  }
  const name = encodeURIComponent(card.value?.name || "会员卡");
  uni.navigateTo({ url: `/subpackages/members/card-share-assignments?memberCardId=${memberCardId.value}&name=${name}` });
}

function onTabChange(e: { index: number }) {
  tabIndex.value = e.index;
  if (e.index === 2 && bookingItems.value.length === 0 && !bookingLoading.value) {
    void loadBookings();
  }
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return "—";
  return value.slice(0, 16).replace("T", " ");
}

function formatDate(value: string | null | undefined) {
  if (!value) return "--";
  return value.slice(0, 10);
}

function ledgerSummary(entry: StaffMemberCardLedgerEntry) {
  return entry.reason?.trim() || entryTypeLabels[entry.entryType] || entry.entryType;
}

function ledgerDeltaText(entry: StaffMemberCardLedgerEntry) {
  if (entry.amountDelta != null) {
    return `${entry.direction === "credit" ? "+" : "-"}¥${entry.amountDelta}`;
  }
  if (entry.countDelta != null) {
    return `${entry.direction === "credit" ? "+" : "-"}${entry.countDelta}次`;
  }
  if (entry.validUntilAfter) return `有效期至 ${entry.validUntilAfter}`;
  return "";
}

function bookingStatusLabel(status: string) {
  return (
    (
      {
        booked: "已预约",
        checked_in: "已签到",
        completed: "已完成",
        cancelled: "已取消",
        absent: "缺席",
        no_show: "未到",
      } as Record<string, string>
    )[status] || status
  );
}

function bookingStatusColor(status: string) {
  if (["checked_in", "completed", "booked"].includes(status)) return "#22C788";
  return "#D95872";
}

function closeSheet() {
  activeSheet.value = null;
}

function openSheet(kind: SheetKind) {
  activeSheet.value = kind;
  adjustDirection.value = "credit";
  adjustAmount.value = "";
  adjustCount.value = "";
  adjustReason.value = "";
  adjustMode.value = "new";
  correctsEntryId.value = null;
  holidayPlannedEndDate.value = "";
  holidayReason.value = "";
  extendDays.value = "";
  extendValidUntil.value = "";
  validityReason.value = "";
  freezeReason.value = "";
  remarkText.value = card.value?.staffRemark || "";
}

function onManageTap(id: number) {
  if (!card.value) return;
  if (isArchived.value && id !== 6) {
    if (id === 6 && canArchive.value) {
      openSheet("restore");
      return;
    }
    uni.showToast({ title: "卡已归档", icon: "none" });
    return;
  }

  switch (id) {
    case 1: {
      if (isStoredValue.value && canBalanceAdjust.value) openSheet("balance");
      else if (isCount.value && canCountAdjust.value) openSheet("count");
      else uni.showToast({ title: "暂无调整权限", icon: "none" });
      break;
    }
    case 2: {
      if (!canValidityExtend.value) {
        uni.showToast({ title: "暂无有效期权限", icon: "none" });
        return;
      }
      openSheet("validity");
      break;
    }
    case 3: {
      if (!canHolidayManage.value) {
        uni.showToast({ title: "暂无请假权限", icon: "none" });
        return;
      }
      if (isFrozen.value) {
        uni.showToast({ title: "停卡中不可请假", icon: "none" });
        return;
      }
      openSheet(isOnHoliday.value ? "holiday-end" : "holiday-start");
      break;
    }
    case 4: {
      if (!canFreeze.value) {
        uni.showToast({ title: "暂无停卡权限", icon: "none" });
        return;
      }
      if (isOnHoliday.value) {
        uni.showToast({ title: "请假中不可停卡", icon: "none" });
        return;
      }
      openSheet(isFrozen.value ? "unfreeze" : "freeze");
      break;
    }
    case 5:
      uni.showToast({ title: "续费功能即将开放", icon: "none" });
      break;
    case 7:
      openSheet("remark");
      break;
    case 6: {
      if (!canArchive.value) {
        uni.showToast({ title: "暂无删除权限", icon: "none" });
        return;
      }
      openSheet(isArchived.value ? "restore" : "archive");
      break;
    }
  }
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

async function loadMemberHeader() {
  if (!memberId.value || !session.currentSiteId) return;
  try {
    const response = await fetchCrmMember(session.currentSiteId, memberId.value);
    memberName.value = response.data.name || "";
    memberAvatar.value = response.data.avatarUrl || "";
  } catch {
    /* optional */
  }
}

async function loadCard() {
  if (!memberCardId.value || !session.currentSiteId || !canRead.value) return;
  const response = await fetchMemberCardDetail(session.currentSiteId, memberCardId.value);
  card.value = response.data;
  if (!memberId.value && response.data.memberId) memberId.value = response.data.memberId;
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
      50,
    );
    if (reset) ledgerItems.value = response.data.items;
    else ledgerItems.value = [...ledgerItems.value, ...response.data.items];
    ledgerLastPage.value = response.data.pagination.lastPage;
  } finally {
    ledgerLoading.value = false;
    loadingMore.value = false;
  }
}

async function loadBookings() {
  if (!memberId.value || !session.currentSiteId || !memberCardId.value) return;
  bookingLoading.value = true;
  try {
    const response = await fetchMemberBookingHistory(session.currentSiteId, memberId.value, "past");
    const items = response.data.items || [];
    const hasCardId = items.some((item) => item.memberCardId != null);
    if (hasCardId) {
      bookingItems.value = items.filter((item) => item.memberCardId === memberCardId.value);
      bookingFiltered.value = true;
    } else {
      bookingItems.value = items;
      bookingFiltered.value = false;
    }
  } catch {
    bookingItems.value = [];
    bookingFiltered.value = false;
  } finally {
    bookingLoading.value = false;
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
    await loadLedger();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员卡详情加载失败";
  } finally {
    loading.value = false;
  }
}

async function reloadAfterAction() {
  closeSheet();
  await loadCard();
  await loadLedger();
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
  if (activeSheet.value === "balance") {
    const amount = Number(adjustAmount.value);
    if (!Number.isFinite(amount) || amount <= 0) {
      uni.showToast({ title: "请输入有效金额", icon: "none" });
      return;
    }
  } else if (activeSheet.value === "count") {
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
    if (activeSheet.value === "balance") {
      await adjustMemberCardBalance(session.currentSiteId, card.value.id, {
        direction: adjustDirection.value,
        amount: Number(adjustAmount.value),
        reason: adjustReason.value.trim(),
        commandKey,
        correctsEntryId: correctionId,
      });
    } else if (activeSheet.value === "count") {
      await adjustMemberCardCount(session.currentSiteId, card.value.id, {
        direction: adjustDirection.value,
        count: Number.parseInt(adjustCount.value, 10),
        reason: adjustReason.value.trim(),
        commandKey,
        correctsEntryId: correctionId,
      });
    }
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
    uni.showToast({ title: "有效期已延长", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "延期失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitFreezeAction() {
  if (!card.value || !session.currentSiteId || !freezeReason.value.trim()) {
    uni.showToast({ title: "请填写操作原因", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    const payload = { reason: freezeReason.value.trim(), commandKey: createCommandKey() };
    if (activeSheet.value === "freeze") {
      await freezeMemberCard(session.currentSiteId, card.value.id, payload);
      uni.showToast({ title: "已停卡", icon: "success" });
    } else {
      await unfreezeMemberCard(session.currentSiteId, card.value.id, payload);
      uni.showToast({ title: "已恢复", icon: "success" });
    }
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "操作失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitRemark() {
  if (!card.value || !session.currentSiteId) return;
  actionLoading.value = true;
  try {
    await updateMemberCardRemark(session.currentSiteId, card.value.id, remarkText.value.trim());
    uni.showToast({ title: "备注已保存", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "备注保存失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitArchive() {
  if (!card.value || !session.currentSiteId || !freezeReason.value.trim()) {
    uni.showToast({ title: "请填写删除原因", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    await archiveMemberCard(session.currentSiteId, card.value.id, {
      reason: freezeReason.value.trim(),
      commandKey: createCommandKey(),
    });
    uni.showToast({ title: "已删除", icon: "success" });
    closeSheet();
    setTimeout(() => uni.navigateBack(), 500);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "删除失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function submitRestore() {
  if (!card.value || !session.currentSiteId || !freezeReason.value.trim()) {
    uni.showToast({ title: "请填写恢复原因", icon: "none" });
    return;
  }
  actionLoading.value = true;
  try {
    await restoreMemberCard(session.currentSiteId, card.value.id, {
      reason: freezeReason.value.trim(),
      commandKey: createCommandKey(),
    });
    uni.showToast({ title: "恢复成功", icon: "success" });
    await reloadAfterAction();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "恢复失败", icon: "none" });
  } finally {
    actionLoading.value = false;
  }
}

async function onSheetConfirm() {
  switch (activeSheet.value) {
    case "balance":
    case "count":
      await submitAdjust();
      break;
    case "validity":
      await submitValidityExtend();
      break;
    case "holiday-start":
      await submitHolidayStart();
      break;
    case "holiday-end":
      await submitHolidayEnd();
      break;
    case "freeze":
    case "unfreeze":
      await submitFreezeAction();
      break;
    case "remark":
      await submitRemark();
      break;
    case "archive":
      await submitArchive();
      break;
    case "restore":
      await submitRestore();
      break;
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
  memberCardId.value = Number(options?.memberCardId ?? options?.id);
});

onShow(async () => {
  if (!(await requireStaffAuth())) return;
  await loadDetail();
  if (memberId.value) await loadMemberHeader();
});
</script>

<template>
  <view class="page">
    <u-loading-page :loading="loading" />
    <template v-if="!loading">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <template v-else-if="card">
        <view class="personalTainerModule" :style="{ height: `${navTotalHeight + 120}px` }">
          <view class="info-module">
            <view :style="{ height: `${statusBarHeight}px` }" />
            <view class="capsule-wrap" :style="{ height: `${customBarHeight}px` }">
              <view class="back" :style="{ width: `${customBarHeight}px`, height: `${customBarHeight}px` }" @tap="goBack">
                <u-icon name="arrow-left" size="18" color="#181818" />
              </view>
              <view class="head-img">
                <text class="head-realName">{{ memberName || card.name || "会员卡" }}</text>
              </view>
            </view>
            <view
              class="photo-filter"
              :style="{
                height: `${navTotalHeight + 120}px`,
                backgroundImage: memberAvatar ? `url(${memberAvatar})` : 'none',
                backgroundColor: '#c8c8c8',
              }"
            >
              <view class="photo-mask" />
            </view>
          </view>
        </view>

        <view class="wrap" :style="{ marginTop: `${navTotalHeight}px` }">
          <view class="top-card">
            <view class="member-card-face large-size" :style="cardFaceStyle()">
              <view class="ribbon" :style="{ background: ribbonColor(card.cardType) }">
                <text>{{ cardTypeLabel(card.cardType) }}</text>
              </view>
              <view v-if="isPending" class="face-stamp">待激活</view>
              <view v-else-if="isFrozen" class="face-stamp">已停卡</view>
              <view v-else-if="isArchived" class="face-stamp">已归档</view>
              <text class="card-face-name" :class="{ max: (card.name || '').length > 6 }">{{ card.name || "会员卡" }}</text>
              <view class="card-face-bottom">
                <text class="card-face-balance">{{ balanceText() }}</text>
                <text class="card-face-valid">{{ card.validUntil ? `有效期${card.validUntil}` : "有效期--" }}</text>
              </view>
            </view>
          </view>
        </view>

        <view class="head-top-space" :style="{ top: `${navTotalHeight + 50}px` }" />
        <view class="head-top" :style="{ top: `${navTotalHeight + 92}px` }">
          <view class="top-text">
            <view class="top-left">
              <view class="top-left-top">
                <text class="top-left-top-icon">￥</text>
                <text class="top-left-top-money">{{ paidDisplay }}</text>
              </view>
              <view class="top-left-bottom">实收款</view>
            </view>
            <view class="top-right">
              <view class="top-right-top">
                <text class="top-right-top-left">{{ convertLabel() }}</text>
                <text class="top-right-top-right"><text class="bold">{{ unitConvertDisplay }}</text> 元</text>
              </view>
              <view class="top-right-bottom">
                <text class="top-right-top-left">剩余价值</text>
                <text class="top-right-top-right"><text class="bold">{{ residualDisplay }}</text>元</text>
              </view>
            </view>
          </view>

          <view v-if="card.staffRemark" class="remake-box">
            <text class="remarkfont">备注：</text>
            <text class="remarkcontent">{{ card.staffRemark }}</text>
          </view>

          <view class="top-button">
            <view class="top-button-item">
              <u-tabs
                :list="tabList"
                :current="tabIndex"
                :is-scroll="true"
                line-color="#FBD128"
                :active-style="{ fontSize: '28rpx', color: '#181818', fontWeight: '500' }"
                :inactive-style="{ fontSize: '25rpx', color: '#7E7E7E' }"
                @change="onTabChange"
              />
            </view>
          </view>
        </view>

        <view class="list-wrap">
          <!-- 卡管理 -->
          <view v-if="tabIndex === 0" class="card-member">
            <view class="manage-grid">
              <view class="manage-item" @tap="onManageTap(1)">
                <u-icon name="rmb-circle" size="28" color="#181818" />
                <text>改余额</text>
              </view>
              <view class="manage-item" @tap="onManageTap(2)">
                <u-icon name="calendar" size="28" color="#181818" />
                <text>有效期</text>
              </view>
              <view class="manage-item" @tap="onManageTap(3)">
                <u-icon name="clock" size="28" color="#181818" />
                <text>请假</text>
                <text v-if="holidayMsg" class="manage-hint">{{ holidayMsg }}</text>
              </view>
              <view class="manage-item" @tap="onManageTap(4)">
                <u-icon name="lock" size="28" color="#181818" />
                <text>停卡</text>
                <text v-if="stopCardMsg" class="manage-hint">{{ stopCardMsg }}</text>
              </view>
              <view class="manage-item" @tap="onManageTap(5)">
                <u-icon name="plus-circle" size="28" color="#181818" />
                <text>续费</text>
              </view>
              <view class="manage-item" @tap="onManageTap(7)">
                <u-icon name="edit-pen" size="28" color="#181818" />
                <text>卡备注</text>
              </view>
              <view v-if="canReadShareAssignments" class="manage-item" @tap="openShareAssignments">
                <u-icon name="account-fill" size="28" color="#181818" />
                <text>分成归属</text>
              </view>
              <view class="manage-item" @tap="onManageTap(6)">
                <u-icon name="trash" size="28" color="#181818" />
                <text>{{ isArchived ? "恢复卡" : "删除卡" }}</text>
              </view>
            </view>
          </view>

          <!-- 卡信息 -->
          <view v-else-if="tabIndex === 1" class="card-detail-tab">
            <view class="info-row"><text class="name">卡名称</text><text class="value">{{ card.name || "--" }}</text></view>
            <view class="info-row"><text class="name">卡类型</text><text class="value">{{ cardTypeLabel(card.cardType) }}</text></view>
            <view class="info-row"><text class="name">卡　号</text><text class="value">{{ card.cardNo }}</text></view>
            <view class="info-row"><text class="name">卡状态</text><text class="value">{{ statusLabel(card.status) }}</text></view>
            <view class="info-row"><text class="name">现余额</text><text class="value">{{ balanceDisplay() }}</text></view>
            <view class="info-row"><text class="name">有效期至</text><text class="value">{{ isPending ? "--" : (card.validUntil || "--") }}</text></view>
            <u-line color="#F0F0F0" margin="20rpx 0 20rpx 40rpx" length="640" />
            <view class="info-row"><text class="name">发卡人员</text><text class="value">{{ card.issuedByStaffName || "--" }}</text></view>
            <view class="info-row"><text class="name">发卡日期</text><text class="value">{{ formatDateTime(card.issuedAt) }}</text></view>
            <view class="info-row"><text class="name">开卡日期</text><text class="value">{{ card.validFrom || "--" }}</text></view>
            <view class="info-row"><text class="name">实收金额</text><text class="value">{{ card.paidAmount != null ? `￥${card.paidAmount}` : "--" }}</text></view>
            <view class="info-row">
              <text class="name">初始总额</text>
              <text class="value">{{ initialTotalDisplay() }}</text>
              <text class="hint">*会员卡内的初始总余额</text>
            </view>
            <view class="info-row">
              <text class="name">{{ infoConvertLabel() }}</text>
              <text class="value">{{ card.unitConvert != null ? `${card.unitConvert}元` : "--" }}</text>
            </view>
            <view class="info-row"><text class="name">已耗金额</text><text class="value">{{ card.consumedAmount != null ? `￥${card.consumedAmount}` : "--" }}</text></view>
            <view class="info-row"><text class="name">剩余折算</text><text class="value">{{ card.residualValue != null ? `￥${card.residualValue}` : "--" }}</text></view>
            <u-line color="#F0F0F0" margin="30rpx 0 30rpx 50rpx" length="635" />
            <view class="info-row"><text class="name">请假情况</text><text class="value">{{ holidaySummaryText() }}</text></view>
            <view class="info-row"><text class="name">停卡情况</text><text class="value">{{ freezeSummaryText() }}</text></view>
          </view>

          <!-- 上课记录 -->
          <view v-else-if="tabIndex === 2" class="booking-tab">
            <view v-if="!bookingFiltered && bookingItems.length" class="filter-note">未按卡过滤（历史记录无卡关联），显示会员全部上课记录</view>
            <view v-if="bookingLoading" class="empty-tip">加载中...</view>
            <view v-else-if="!bookingItems.length" class="noCourseData">
              <text class="tex">~ 没有上课记录 ~</text>
            </view>
            <view v-for="item in bookingItems" :key="item.id" class="booking-row">
              <view class="booking-main">
                <view class="booking-top">
                  <text class="course_name_tex">{{ item.courseName || item.coachName || "课程" }}</text>
                  <text class="booking-status" :style="{ color: bookingStatusColor(item.status) }">{{ bookingStatusLabel(item.status) }}</text>
                </view>
                <view class="booking-meta">
                  <text v-if="item.coachName && item.courseName">{{ item.coachName }}</text>
                  <text v-if="item.roomName">{{ item.roomName }}</text>
                </view>
                <view class="booking-time">
                  <text>{{ formatDateTime(item.startsAt) }}</text>
                  <text v-if="item.endsAt"> ~ {{ formatDateTime(item.endsAt).slice(11) }}</text>
                </view>
                <view v-if="item.staffNotes" class="remake-box inline">
                  <text class="remarkfont">备注：</text>
                  <text class="remarkcontent">{{ item.staffNotes }}</text>
                </view>
              </view>
              <u-line color="#F0F0F0" />
            </view>
          </view>

          <!-- 余额变动 -->
          <view v-else-if="tabIndex === 3" class="ledger-tab">
            <view v-if="ledgerLoading" class="empty-tip">加载中...</view>
            <view v-else-if="!balanceChangeItems.length" class="noCourseData">
              <text class="tex">~ 无余额变动 ~</text>
            </view>
            <template v-else>
              <view v-for="item in balanceChangeItems" :key="item.id" class="change-item">
                <view class="change_name">{{ entryTypeLabels[item.entryType] || item.entryType }}</view>
                <view class="change_record">
                  <view class="change_top">
                    <view class="change_top_time">
                      <text class="change_top_lefts">{{ ledgerSummary(item) }}</text>
                      <text class="change_top_rights">{{ ledgerDeltaText(item) }}</text>
                    </view>
                    <view class="change_top_time">
                      <text class="change_top_left">{{ formatDateTime(item.occurredAt) }}</text>
                      <text class="change_top_right">{{ item.direction === "credit" ? "增加" : item.direction === "debit" ? "扣减" : "" }}</text>
                    </view>
                  </view>
                </view>
              </view>
              <u-button v-if="ledgerPage < ledgerLastPage" plain :loading="loadingMore" @click="loadMoreLedger">加载更多</u-button>
            </template>
          </view>

          <!-- 变更记录 -->
          <view v-else-if="tabIndex === 4" class="card-member">
            <view v-if="ledgerLoading" class="empty-tip">加载中...</view>
            <view v-else-if="!changeLogItems.length" class="noCourseData">
              <text class="tex">~ 没有变更记录 ~</text>
            </view>
            <view v-for="item in changeLogItems" :key="item.id" class="change-item">
              <view class="change_name">{{ entryTypeLabels[item.entryType] || item.entryType }}</view>
              <view class="change_record">
                <view class="change_top">
                  <view class="change_top_time">
                    <text class="change_top_lefts">{{ ledgerSummary(item) }}</text>
                    <text class="change_top_rights">{{ ledgerDeltaText(item) || formatDate(item.validUntilAfter) }}</text>
                  </view>
                </view>
                <view class="change_bottom">
                  <text class="change_bottom_time">{{ formatDateTime(item.occurredAt) }}</text>
                </view>
              </view>
            </view>
          </view>

          <!-- 惩罚记录 -->
          <view v-else class="card-member">
            <view v-if="ledgerLoading" class="empty-tip">加载中...</view>
            <view v-else-if="!penaltyItems.length" class="noCourseData">
              <text class="tex">~ 无惩罚记录 ~</text>
            </view>
            <view v-for="item in penaltyItems" :key="item.id" class="change-item">
              <view class="change_name">{{ entryTypeLabels[item.entryType] || "惩罚" }}</view>
              <view class="change_record">
                <view class="change_top">
                  <view class="change_top_time">
                    <text class="change_top_lefts">{{ ledgerSummary(item) }}</text>
                    <text class="change_top_rights">{{ ledgerDeltaText(item) }}</text>
                  </view>
                </view>
                <view class="change_bottom">
                  <text class="change_bottom_time">{{ formatDateTime(item.occurredAt) }}</text>
                </view>
              </view>
            </view>
          </view>
        </view>

        <FfBottomLogo />
      </template>
    </template>

    <FfBottomSheet
      :show="!!activeSheet"
      :title="sheetTitle"
      :height-rpx="activeSheet === 'remark' ? 867 : 980"
      :confirm-disabled="actionLoading"
      confirm-text="确　定"
      @update:show="(v) => { if (!v) closeSheet() }"
      @confirm="onSheetConfirm"
    >
      <!-- 改余额 / 改次数 -->
      <view v-if="activeSheet === 'balance' || activeSheet === 'count'" class="sheet-form">
        <view class="scope-row">
          <button class="scope-button" :class="{ active: adjustMode === 'new' }" @click="adjustMode = 'new'; correctsEntryId = null">新增调整</button>
          <button class="scope-button" :class="{ active: adjustMode === 'correct' }" @click="adjustMode = 'correct'">更正记录</button>
        </view>
        <view v-if="adjustMode === 'correct'" class="picker-row" @click="chooseCorrectEntry">
          <text v-if="selectedCorrectEntry" class="picker-value">{{ correctEntryLabel(selectedCorrectEntry) }}</text>
          <text v-else class="picker-placeholder">选择要更正的变动记录</text>
        </view>
        <view class="scope-row">
          <button class="scope-button" :class="{ active: adjustDirection === 'credit' }" @click="adjustDirection = 'credit'">增加</button>
          <button class="scope-button" :class="{ active: adjustDirection === 'debit' }" @click="adjustDirection = 'debit'">扣减</button>
        </view>
        <u-input v-if="activeSheet === 'balance'" v-model="adjustAmount" type="digit" :placeholder="adjustMode === 'correct' ? '更正后金额' : '调整金额'" border="surround" />
        <u-input v-else v-model="adjustCount" type="number" :placeholder="adjustMode === 'correct' ? '更正后次数' : '调整次数'" border="surround" />
        <view class="gap" />
        <u-input v-model="adjustReason" placeholder="调整原因（必填）" border="surround" />
      </view>

      <!-- 有效期 -->
      <view v-else-if="activeSheet === 'validity'" class="sheet-form">
        <u-input v-model="extendDays" type="number" placeholder="延长天数（与到期日二选一）" border="surround" />
        <view class="gap" />
        <picker mode="date" :value="extendValidUntil" @change="extendValidUntil = String($event.detail.value)">
          <view class="picker-field">{{ extendValidUntil || "或选择新到期日" }}</view>
        </picker>
        <view class="gap" />
        <u-input v-model="validityReason" placeholder="延期原因（必填）" border="surround" />
      </view>

      <!-- 请假开始 -->
      <view v-else-if="activeSheet === 'holiday-start'" class="sheet-form">
        <picker mode="date" :value="holidayPlannedEndDate" @change="holidayPlannedEndDate = String($event.detail.value)">
          <view class="picker-field">{{ holidayPlannedEndDate || "请选择计划结束日期" }}</view>
        </picker>
        <view class="gap" />
        <u-input v-model="holidayReason" placeholder="请假原因（必填）" border="surround" />
      </view>

      <!-- 结束请假 -->
      <view v-else-if="activeSheet === 'holiday-end'" class="sheet-form">
        <u-input v-model="holidayReason" placeholder="结束原因（必填）" border="surround" />
      </view>

      <!-- 停卡 / 解冻 / 删除 / 恢复 -->
      <view v-else-if="activeSheet === 'freeze' || activeSheet === 'unfreeze' || activeSheet === 'archive' || activeSheet === 'restore'" class="sheet-form">
        <view v-if="activeSheet === 'archive'" class="warn-tip">将删除该卡以及该卡的约课记录</view>
        <u-input
          v-model="freezeReason"
          :placeholder="activeSheet === 'archive' ? '删除原因（必填）' : activeSheet === 'restore' ? '恢复原因（必填）' : '操作原因（必填）'"
          border="surround"
        />
      </view>

      <!-- 卡备注 -->
      <view v-else-if="activeSheet === 'remark'" class="sheet-form remark-form">
        <textarea
          v-model="remarkText"
          class="remark-textarea"
          maxlength="200"
          placeholder="仅管理员可见，会员不会看到此备注"
          placeholder-style="color:#d0d2d7;"
        />
        <view class="remark-count">已写{{ remarkText.length }}字/ 最多200字</view>
      </view>
    </FfBottomSheet>
  </view>
</template>

<style scoped lang="scss">
.page { min-height: 100vh; background: #f5f5f5; }
.personalTainerModule { position: relative; width: 100%; }
.info-module { display: flex; flex-direction: column; left: 0; position: fixed; top: 0; width: 100%; z-index: 99; }
.capsule-wrap { display: flex; z-index: 100; }
.head-img { align-items: center; display: flex; justify-content: center; margin-right: 42rpx; text-align: center; width: 100%; }
.head-realName { color: #181818; font-size: 30rpx; margin-right: 25rpx; }
.back { align-items: center; display: flex; justify-content: center; }
.photo-filter {
  filter: blur(25rpx);
  position: fixed;
  left: 0;
  top: 0;
  transform: scale(1.5);
  width: 100%;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  z-index: -1;
}
.photo-mask { background: #000; opacity: 0.3; width: 100%; height: 100%; }
.wrap { flex: 1; left: 0; pointer-events: none; position: fixed; top: 0; width: 100%; z-index: 100; }
.top-card { align-items: center; display: flex; flex-direction: column; justify-content: center; padding-top: 20rpx; }
.member-card-face {
  pointer-events: auto;
  position: relative;
  overflow: hidden;
  border-radius: 18rpx;
  box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.18);
  color: #fff;
  box-sizing: border-box;
  transform: scale(0.97);
}
.large-size {
  width: 620rpx;
  height: 370rpx;
}
.ribbon {
  position: absolute;
  top: -3rpx;
  right: -36rpx;
  width: 160rpx;
  height: 52rpx;
  line-height: 65rpx;
  text-align: center;
  transform: rotate(45deg);
  color: #fff;
  font-size: 20rpx;
}
.face-stamp {
  position: absolute;
  right: 28rpx;
  bottom: 90rpx;
  padding: 4rpx 14rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.85);
  border-radius: 8rpx;
  color: #fff;
  font-size: 22rpx;
  transform: rotate(-10deg);
}
.card-face-name {
  display: block;
  margin-top: 110rpx;
  padding: 0 40rpx;
  overflow: hidden;
  color: #fff;
  font-size: 52rpx;
  font-weight: 600;
  text-align: center;
  white-space: nowrap;
  text-overflow: ellipsis;
  &.max { font-size: 42rpx; }
}
.card-face-bottom {
  position: absolute;
  left: 36rpx;
  right: 36rpx;
  bottom: 28rpx;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}
.card-face-balance { font-size: 36rpx; font-weight: 500; }
.card-face-valid { font-size: 22rpx; opacity: 0.95; }
.head-top-space {
  height: 300rpx;
  margin-top: -30px;
  background: #fff;
  border-top-left-radius: 35rpx;
  border-top-right-radius: 35rpx;
  position: sticky;
  width: 100%;
  z-index: 98;
}
.head-top {
  background: #fff;
  border-top-left-radius: 35rpx;
  border-top-right-radius: 35rpx;
  position: sticky;
  width: 100%;
  z-index: 99;
  margin-top: -75px;
  padding-top: 50rpx;
}
.top-text { display: flex; justify-content: space-between; padding: 0 80rpx; }
.top-left { color: #181818; font-weight: 500; }
.top-left-top-icon { font-size: 22rpx; }
.top-left-top-money { font-size: 38rpx; line-height: 50rpx; }
.top-left-bottom { color: #989898; font-size: 22rpx; font-weight: 400; line-height: 28rpx; margin-top: 4rpx; }
.top-right { color: #181818; font-size: 22rpx; line-height: 28rpx; }
.bold { font-weight: 500; }
.top-right-top, .top-right-bottom { align-items: center; display: flex; justify-content: flex-end; }
.top-right-top { margin-top: 15rpx; }
.top-right-bottom { font-weight: 400; margin-top: 8rpx; }
.top-right-top-left { color: #989898; }
.top-right-top-right { padding-left: 10rpx; }
.remake-box {
  display: flex;
  margin: 16rpx 40rpx 0;
  &.inline { margin: 8rpx 0 0; }
}
.remarkfont { color: #989898; font-size: 22rpx; flex-shrink: 0; }
.remarkcontent { color: #181818; font-size: 22rpx; }
.top-button { background: #f5f5f5; overflow: hidden; width: 100%; margin-top: 24rpx; }
.top-button-item {
  background: #fff;
  border-radius: 21rpx 21rpx 0 0;
  margin: 23rpx 15rpx 0;
  padding-top: 6rpx;
}
.list-wrap {
  background: #fff;
  border-radius: 0 0 21rpx 21rpx;
  margin: 0 15rpx;
  min-height: 800rpx;
  overflow: hidden;
  padding: 40rpx 24rpx 40rpx;
}
.manage-grid {
  display: flex;
  flex-wrap: wrap;
  padding: 10rpx 0 30rpx;
}
.manage-item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12rpx;
  width: 25%;
  padding: 0 0 50rpx;
  color: #181818;
  font-size: 24rpx;
  box-sizing: border-box;
}
.manage-hint {
  position: absolute;
  top: 70rpx;
  left: 50%;
  transform: translateX(-50%);
  width: 160rpx;
  color: #ed920f;
  font-size: 18rpx;
  text-align: center;
  line-height: 1.2;
}
.info-row { display: flex; align-items: flex-start; margin: 0 10rpx; }
.info-row .name { color: #989898; font-size: 28rpx; line-height: 60rpx; text-align: end; width: 130rpx; flex-shrink: 0; }
.info-row .value { color: #181818; font-size: 28rpx; line-height: 60rpx; margin-left: 40rpx; }
.info-row .hint { color: #c9cacb; font-size: 22rpx; line-height: 60rpx; margin-left: 15rpx; }
.filter-note { color: #ed920f; font-size: 22rpx; margin-bottom: 20rpx; }
.booking-row { padding: 16rpx 0; }
.booking-top { display: flex; justify-content: space-between; align-items: center; gap: 12rpx; }
.course_name_tex { color: #181818; font-size: 30rpx; font-weight: 500; }
.booking-status { font-size: 24rpx; }
.booking-meta { display: flex; gap: 16rpx; margin-top: 8rpx; color: #989898; font-size: 24rpx; }
.booking-time { margin-top: 8rpx; color: #989898; font-size: 24rpx; }
.change-item { padding-bottom: 43rpx; }
.change_name { color: #181818; font-size: 32rpx; padding-bottom: 17rpx; padding-left: 10rpx; }
.change_record { background: #f5f5f5; border-radius: 21rpx; padding: 32rpx 21rpx 0 22rpx; }
.change_top { border-bottom: 1rpx solid #dadada; padding-bottom: 17rpx; }
.change_top_time { display: flex; align-items: center; padding-bottom: 10rpx; }
.change_top_left { color: #7e7e7e; flex: 1; font-size: 21rpx; margin-right: 10rpx; }
.change_top_lefts { color: #181818; flex: 1; font-size: 32rpx; margin-right: 10rpx; }
.change_top_right { color: #7e7e7e; flex: 0.7; font-size: 21rpx; margin-left: 10rpx; text-align: right; }
.change_top_rights { color: #181818; flex: 1; font-size: 32rpx; margin-left: 10rpx; text-align: right; }
.change_bottom { display: flex; align-items: center; padding: 21rpx 0 30rpx; }
.change_bottom_time { color: #989898; font-size: 22rpx; }
.empty-tip { color: #bfbfbf; font-size: 25rpx; text-align: center; padding: 60rpx 0; }
.noCourseData { align-items: center; display: flex; flex-direction: column; height: 400rpx; justify-content: center; }
.tex { color: #bfbfbf; font-size: 25rpx; }
.sheet-form { padding-top: 8rpx; }
.gap { height: 20rpx; }
.warn-tip { color: #989898; font-size: 25rpx; margin-bottom: 20rpx; }
.scope-row { display: flex; gap: 12rpx; margin: 12rpx 0 20rpx; }
.scope-button {
  margin: 0;
  padding: 12rpx 24rpx;
  color: #989898;
  font-size: 24rpx;
  background: #f5f5f5;
  border: 1rpx solid #dadada;
  border-radius: 999rpx;
}
.scope-button.active { color: #181818; background: #fbd128; border-color: #fbd128; }
.scope-button::after { border: 0; }
.picker-row {
  display: flex;
  align-items: center;
  margin-bottom: 20rpx;
  padding: 24rpx;
  background: #f5f5f5;
  border: 1rpx solid #dadada;
  border-radius: 12rpx;
}
.picker-value { font-size: 28rpx; color: #181818; }
.picker-placeholder { color: #989898; font-size: 28rpx; }
.picker-field {
  min-height: 80rpx;
  box-sizing: border-box;
  padding: 20rpx;
  background: #f5f5f5;
  border: 1rpx solid #dadada;
  border-radius: 12rpx;
  color: #181818;
}
.remark-form { padding: 0 4rpx; }
.remark-textarea {
  display: block;
  box-sizing: border-box;
  width: 100%;
  height: 400rpx;
  padding: 20rpx 28rpx;
  background: #fff;
  border: 1px solid #dadada;
  border-radius: 22rpx;
  color: #989898;
  font-size: 28rpx;
  line-height: 42rpx;
}
.remark-count { color: #989898; font-size: 25rpx; text-align: right; margin-top: 16rpx; }
</style>
