<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  archiveCardProduct,
  createCardProduct,
  fetchCardFaceLibrary,
  fetchCardProduct,
  updateCardProduct,
} from "@/api/card-products";
import type { CardFaceLibraryItem } from "@/api/card-products";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

import type {
  CardProductBookingRules,
  StaffCardProductCourseScopeInput,
  StaffCardProductDetail,
  StaffCardProductUpsertPayload,
} from "@/types/member-cards";

type CardType = StaffCardProductUpsertPayload["cardType"];
type PanelKey =
  | "face"
  | "quota"
  | "validity"
  | "timeRange"
  | "activation"
  | "bookingLimit"
  | "advanceLimit"
  | "cancelLimit"
  | "absencePenalty"
  | "repeatBooking"
  | "multiPerson"
  | "description";

// 对标原版 create/create 三卡类型选择
const CARD_TYPE_OPTIONS: { type: CardType; label: string; desc: string }[] = [
  { type: "count", label: "计次卡", desc: "即次卡，按次进行消费" },
  { type: "period", label: "期限卡", desc: "即包月/季/年卡，可设置任意天数" },
  { type: "stored_value", label: "储值卡", desc: "即充值卡，每次消费时支付相应的金额" },
];



const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const productId = ref<number | null>(null);
const product = ref<StaffCardProductDetail | null>(null);
const cardType = ref<CardType>("count");
const typeChosen = ref(false);
const name = ref("");
const description = ref("");
const price = ref("");
const faceValue = ref("");
const initialCount = ref("");
const validityDays = ref("");
const saleOn = ref(true);
const sortOrder = ref("0");
const faceIndex = ref(0);
const faceLibrary = ref<CardFaceLibraryItem[]>([]); // 平台图案库（总 Web 后台可控）
const savedFaceGradient = ref<string | null>(null);

// 高级选项状态
const advancedOpen = ref(false);
const rawBookingRules = ref<CardProductBookingRules>({});
const timeRanges = ref<{ start: string; end: string }[]>([]); // 原版支持多时段
const activationMode = ref<"immediate" | "first-use" | "manual" | "delayed" | "first-class">("immediate");
const activationDays = ref("");
const limitPerDay = ref("");
const limitPerWeek = ref("");
const limitPerMonth = ref("");
const advanceLimit = ref("");
const cancelPerDay = ref("");
const cancelPerWeek = ref("");
const cancelPerMonth = ref("");
const repeatMode = ref<"allow" | "deny" | "limit">("deny"); // 原版默认：不允许
const repeatMax = ref("");
const multiPersonMode = ref<"self" | "unlimited" | "limited">("self"); // 原版三选项
const multiPersonMax = ref("");
const penaltyWeekThreshold = ref(""); // 原版：周/月双窗口可同设
const penaltyMonthThreshold = ref("");
const penaltyAction = ref<"mark_or_no_refund" | "forbid" | "deduct">("mark_or_no_refund");
const penaltyForbidDays = ref("");
const penaltyDeductValue = ref("");

// 弹窗编辑
const activePanel = ref<PanelKey | null>(null);

const isEdit = computed(() => productId.value !== null);
const canWrite = computed(() => session.can("card-product.editor.write"));
const canArchive = computed(() => session.can("card-product.archive"));
const isStoredValue = computed(() => cardType.value === "stored_value");
const isCount = computed(() => cardType.value === "count");
const isPeriod = computed(() => cardType.value === "period");
const cardTypeLabel = computed(
  () => ({ stored_value: "储值卡", count: "计次卡", period: "期限卡" })[cardType.value],
);
const currentSiteName = computed(
  () => session.sites.find((site) => site.id === session.currentSiteId)?.name || "场馆",
);
const FACE_FALLBACK = "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";
const cardFaceStyle = computed(() => {
  const picked = faceLibrary.value.find((item) => item.id === faceIndex.value);
  return picked?.gradient || savedFaceGradient.value || FACE_FALLBACK;
});

// —— 摘要文案（原版行式「值 + 箭头」） ——
const quotaSummary = computed(() => {
  if (isStoredValue.value) return faceValue.value ? `${faceValue.value}元` : "";
  if (isCount.value) return initialCount.value ? `${initialCount.value}次` : "";
  return "";
});
const validitySummary = computed(() => {
  const days = Number.parseInt(validityDays.value || "0", 10);
  if (!days) return isPeriod.value ? "" : "永久有效";
  if (days % 365 === 0) return `${days / 365}年`;
  if (days % 30 === 0) return `${days / 30}个月`;
  return `${days}天`;
});
const facePriceText = computed(() => `¥${price.value || "0"}`);
const timeRangeSummary = computed(() =>
  timeRanges.value.length
    ? timeRanges.value.map((range) => `${range.start}-${range.end}`).join("、")
    : "全部时段",
);
// 原版 cardOpenTypeDesList 五种开卡模式
const ACTIVATION_OPTIONS = [
  { value: "immediate" as const, label: "购卡后立即开卡" },
  { value: "first-use" as const, label: "首次使用时自动开卡" },
  { value: "first-class" as const, label: "首次上课时自动开卡" },
  { value: "delayed" as const, label: "购卡X天后自动开卡" },
  { value: "manual" as const, label: "购卡后不开卡" },
];
const activationSummary = computed(() => {
  if (activationMode.value === "delayed") return `购卡${activationDays.value || "?"}天后自动开卡`;
  return ACTIVATION_OPTIONS.find((option) => option.value === activationMode.value)?.label || "购卡后立即开卡";
});
const bookingLimitSummary = computed(() => {
  const parts: string[] = [];
  if (limitPerDay.value) parts.push(`每日${limitPerDay.value}次`);
  if (limitPerWeek.value) parts.push(`每周${limitPerWeek.value}次`);
  if (limitPerMonth.value) parts.push(`每月${limitPerMonth.value}次`);
  return parts.length ? parts.join("、") : "不限制";
});
const advanceSummary = computed(() => (advanceLimit.value ? `最多预约${advanceLimit.value}次` : "不限制"));
const cancelSummary = computed(() => {
  const parts: string[] = [];
  if (cancelPerDay.value) parts.push(`每日${cancelPerDay.value}次`);
  if (cancelPerWeek.value) parts.push(`每周${cancelPerWeek.value}次`);
  if (cancelPerMonth.value) parts.push(`每月${cancelPerMonth.value}次`);
  return parts.length ? parts.join("、") : "不限制";
});
// 原版动作1文案按卡类型：期限卡「仅标记旷课」、储值/次卡「不退课费」
const markActionLabel = computed(() => (isPeriod.value ? "仅标记旷课" : "不退课费"));
const deductUnit = computed(() => (isStoredValue.value ? "元" : isCount.value ? "次" : "天"));
const penaltySummary = computed(() => {
  const parts: string[] = [];
  if (penaltyWeekThreshold.value) parts.push(`每周累计达${penaltyWeekThreshold.value}次`);
  if (penaltyMonthThreshold.value) parts.push(`每月累计达${penaltyMonthThreshold.value}次`);
  if (!parts.length) return "不限制";
  const actionText = {
    mark_or_no_refund: markActionLabel.value,
    forbid: `禁止约课${penaltyForbidDays.value || "?"}天`,
    deduct: `扣除${penaltyDeductValue.value || "?"}${deductUnit.value}`,
  }[penaltyAction.value];
  return `${parts.join("，")}，${actionText}`;
});
const repeatSummary = computed(() => {
  if (repeatMode.value === "deny") return "不允许";
  if (repeatMode.value === "limit") return `最多可预约${repeatMax.value || "?"}次`;
  return "允许且不限制次数";
});
// 原版 useRuleDesList 三选项
const multiPersonSummary = computed(() => {
  if (multiPersonMode.value === "unlimited") return "允许多人使用，且不限制人数";
  if (multiPersonMode.value === "limited") return `允许多人使用，最多${multiPersonMax.value || "?"}人`;
  return "仅持卡会员自己可用";
});
const descriptionSummary = computed(() => (description.value.trim() ? "已填写" : "无"));

const PENALTY_ACTIONS = computed(() => [
  { value: "mark_or_no_refund" as const, label: markActionLabel.value },
  { value: "forbid" as const, label: "禁止约课" },
  { value: "deduct" as const, label: "扣除" },
]);
const REPEAT_OPTIONS = [
  { value: "deny" as const, label: "不允许" },
  { value: "limit" as const, label: "最多可预约" },
  { value: "allow" as const, label: "允许且不限制次数" },
];
const VALIDITY_QUICK = [
  { label: "1个月", days: 30 },
  { label: "3个月", days: 90 },
  { label: "半年", days: 180 },
  { label: "1年", days: 365 },
  { label: "2年", days: 730 },
];

function pickType(type: CardType) {
  cardType.value = type;
  typeChosen.value = true;
  faceValue.value = "";
  initialCount.value = "";
  validityDays.value = "365";
  uni.setNavigationBarTitle({ title: `添加${cardTypeLabel.value}` });
}

async function openFacePicker() {
  if (!faceLibrary.value.length && session.currentSiteId) {
    try {
      const library = await fetchCardFaceLibrary(session.currentSiteId);
      faceLibrary.value = library.items;
    } catch {
      uni.showToast({ title: "图案库加载失败", icon: "none" });
      return;
    }
  }
  activePanel.value = "face";
}

function pickFace(id: number) {
  faceIndex.value = id;
  closePanel();
}

function openPanel(panel: PanelKey) {
  activePanel.value = panel;
}

function closePanel() {
  activePanel.value = null;
}

function fillBookingRules(rules: CardProductBookingRules | null | undefined) {
  rawBookingRules.value = rules && typeof rules === "object" ? { ...rules } : {};
  timeRanges.value = (rules?.timeRanges ?? []).filter((range) => range?.start && range?.end).map((range) => ({ ...range }));
  activationDays.value = rules?.activationDays != null ? String(rules.activationDays) : "";
  limitPerDay.value = rules?.bookingLimit?.perDay != null ? String(rules.bookingLimit.perDay) : "";
  limitPerWeek.value = rules?.bookingLimit?.perWeek != null ? String(rules.bookingLimit.perWeek) : "";
  limitPerMonth.value = rules?.bookingLimit?.perMonth != null ? String(rules.bookingLimit.perMonth) : "";
  advanceLimit.value = rules?.advanceLimit != null ? String(rules.advanceLimit) : "";
  cancelPerDay.value = rules?.cancelLimit?.perDay != null ? String(rules.cancelLimit.perDay) : "";
  cancelPerWeek.value = rules?.cancelLimit?.perWeek != null ? String(rules.cancelLimit.perWeek) : "";
  cancelPerMonth.value = rules?.cancelLimit?.perMonth != null ? String(rules.cancelLimit.perMonth) : "";
  repeatMode.value = rules?.repeatBooking?.mode ?? "deny";
  repeatMax.value = rules?.repeatBooking?.max != null ? String(rules.repeatBooking.max) : "";
  // 多人使用：新结构 mode 优先，兼容旧 enabled
  if (rules?.multiPerson?.mode) {
    multiPersonMode.value = rules.multiPerson.mode;
  } else if (rules?.multiPerson?.enabled) {
    multiPersonMode.value = "limited";
  } else {
    multiPersonMode.value = "self";
  }
  multiPersonMax.value = rules?.multiPerson?.max != null ? String(rules.multiPerson.max) : "";
  // 旷课处罚：新结构双窗口，兼容旧 window/threshold
  const penalty = rules?.absencePenalty;
  penaltyWeekThreshold.value = penalty?.weekThreshold != null ? String(penalty.weekThreshold) : "";
  penaltyMonthThreshold.value = penalty?.monthThreshold != null ? String(penalty.monthThreshold) : "";
  if (!penaltyWeekThreshold.value && !penaltyMonthThreshold.value && penalty?.threshold != null) {
    if (penalty.window === "week") penaltyWeekThreshold.value = String(penalty.threshold);
    else penaltyMonthThreshold.value = String(penalty.threshold);
  }
  const rawAction = penalty?.action;
  penaltyAction.value = rawAction === "forbid" ? "forbid" : rawAction === "deduct" ? "deduct" : "mark_or_no_refund";
  penaltyForbidDays.value = penalty?.forbidDays != null ? String(penalty.forbidDays) : "";
  penaltyDeductValue.value = penalty?.deductValue != null ? String(penalty.deductValue) : "";
}

function fillForm(detail: StaffCardProductDetail) {
  cardType.value = detail.cardType as CardType;
  typeChosen.value = true;
  name.value = detail.name;
  description.value = detail.description || "";
  price.value = detail.price;
  faceValue.value = detail.faceValue || "";
  initialCount.value = detail.initialCount != null ? String(detail.initialCount) : "";
  validityDays.value = detail.validityDays != null ? String(detail.validityDays) : "";
  saleOn.value = detail.saleStatus === "on_sale";
  sortOrder.value = String(detail.sortOrder);
  const storedActivation = detail.activationMode ?? "immediate";
  activationMode.value = (["immediate", "first-use", "manual", "delayed", "first-class"].includes(storedActivation)
    ? storedActivation
    : "immediate") as typeof activationMode.value;
  const face = (detail.scopeConfig as Record<string, unknown> | null)?.faceStyle;
  faceIndex.value = typeof face === "number" ? face : 0;
  savedFaceGradient.value = (detail as unknown as { faceGradient?: string | null }).faceGradient ?? null;
  fillBookingRules(detail.bookingRules as CardProductBookingRules | null);
}

function setValidityQuick(days: number) {
  validityDays.value = String(days);
}

function onSaleSwitchChange(value: boolean) {
  if (value) {
    saleOn.value = true;
    return;
  }
  uni.showModal({
    title: "确定停售？",
    content: "确定后，会员约课端的购卡列表中不再显示该卡。",
    success: (result) => {
      saleOn.value = !result.confirm;
    },
  });
}

function parsePrice(value: string, label: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number(trimmed);
  if (!Number.isFinite(amount) || amount < 0) {
    uni.showToast({ title: `请输入有效${label}`, icon: "none" });
    return null;
  }
  return amount;
}

function parsePositiveInt(value: string, label: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number.parseInt(trimmed, 10);
  if (!Number.isFinite(amount) || amount < 1) {
    uni.showToast({ title: `请输入有效${label}`, icon: "none" });
    return null;
  }
  return amount;
}

function parseOptionalPrice(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number(trimmed);
  if (!Number.isFinite(amount) || amount < 0) return null;
  return amount;
}

function intOrUndefined(value: string): number | undefined {
  const trimmed = value.trim();
  if (!trimmed) return undefined;
  const parsed = Number.parseInt(trimmed, 10);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : undefined;
}

function buildCourseScopePayload(): StaffCardProductCourseScopeInput[] {
  // 「支持的课」在「设置支持的课及课时费」页维护（对标原版 card-subject），
  // 此处原样透传已有配置，避免保存卡种资料时清空关联。
  return (product.value?.courseScopes ?? [])
    .filter((scope) => scope.scopeKind === "single")
    .map((scope, index) => ({
      scopeKind: "single" as const,
      scopeKey: scope.scopeKey,
      displayName: scope.displayName ?? undefined,
      priceOverride: scope.priceOverride != null ? Number(scope.priceOverride) : undefined,
      sortOrder: index,
    }));
}

function buildBookingRulesPayload(): CardProductBookingRules | null {
  const rules: CardProductBookingRules = { ...rawBookingRules.value };

  const validRanges = timeRanges.value.filter((range) => range.start && range.end);
  for (const range of validRanges) {
    if (range.start >= range.end) {
      uni.showToast({ title: "可用时段结束需晚于开始", icon: "none" });
      return null;
    }
  }
  if (validRanges.length) rules.timeRanges = validRanges.map((range) => ({ ...range }));
  else delete rules.timeRanges;

  if (activationMode.value === "delayed") {
    const days = intOrUndefined(activationDays.value);
    if (!days) {
      uni.showToast({ title: "请填写购卡后自动开卡的天数", icon: "none" });
      return null;
    }
    rules.activationDays = days;
  } else {
    delete rules.activationDays;
  }

  const bookingLimit = {
    perDay: intOrUndefined(limitPerDay.value),
    perWeek: intOrUndefined(limitPerWeek.value),
    perMonth: intOrUndefined(limitPerMonth.value),
  };
  if (bookingLimit.perDay || bookingLimit.perWeek || bookingLimit.perMonth) rules.bookingLimit = bookingLimit;
  else delete rules.bookingLimit;

  const advance = intOrUndefined(advanceLimit.value);
  if (advance) rules.advanceLimit = advance;
  else delete rules.advanceLimit;

  const cancelLimit = {
    perDay: intOrUndefined(cancelPerDay.value),
    perWeek: intOrUndefined(cancelPerWeek.value),
    perMonth: intOrUndefined(cancelPerMonth.value),
  };
  if (cancelLimit.perDay != null || cancelLimit.perWeek != null || cancelLimit.perMonth != null) {
    rules.cancelLimit = cancelLimit;
  } else {
    delete rules.cancelLimit;
  }

  if (repeatMode.value === "allow") {
    delete rules.repeatBooking;
  } else if (repeatMode.value === "deny") {
    rules.repeatBooking = { mode: "deny" };
  } else {
    const max = intOrUndefined(repeatMax.value);
    if (!max) {
      uni.showToast({ title: "请填写同课程最多可预约次数", icon: "none" });
      return null;
    }
    rules.repeatBooking = { mode: "limit", max };
  }

  // 多人使用（原版三选项）
  if (multiPersonMode.value === "self") {
    delete rules.multiPerson;
  } else if (multiPersonMode.value === "unlimited") {
    rules.multiPerson = { mode: "unlimited", enabled: true };
  } else {
    const max = intOrUndefined(multiPersonMax.value);
    if (!max || max < 2) {
      uni.showToast({ title: "多人使用请填写最多人数（≥2）", icon: "none" });
      return null;
    }
    rules.multiPerson = { mode: "limited", enabled: true, max };
  }

  // 旷课处罚（原版：周/月双窗口可同设 + 三动作）
  const weekThreshold = intOrUndefined(penaltyWeekThreshold.value);
  const monthThreshold = intOrUndefined(penaltyMonthThreshold.value);
  if (weekThreshold || monthThreshold) {
    const penalty: NonNullable<CardProductBookingRules["absencePenalty"]> = {
      action: penaltyAction.value,
    };
    if (weekThreshold) penalty.weekThreshold = weekThreshold;
    if (monthThreshold) penalty.monthThreshold = monthThreshold;
    if (penaltyAction.value === "forbid") {
      const forbidDays = intOrUndefined(penaltyForbidDays.value);
      if (!forbidDays) {
        uni.showToast({ title: "请填写禁止约课天数", icon: "none" });
        return null;
      }
      penalty.forbidDays = forbidDays;
    }
    if (penaltyAction.value === "deduct") {
      const deductValue = Number(penaltyDeductValue.value);
      if (!Number.isFinite(deductValue) || deductValue <= 0) {
        uni.showToast({ title: `请填写扣除的${deductUnit.value}数`, icon: "none" });
        return null;
      }
      penalty.deductValue = deductValue;
    }
    rules.absencePenalty = penalty;
  } else {
    delete rules.absencePenalty;
  }

  return rules;
}

function buildPayload(): StaffCardProductUpsertPayload | null {
  if (!name.value.trim()) {
    uni.showToast({ title: "请输入卡名称", icon: "none" });
    return null;
  }
  const parsedPrice = parsePrice(price.value, "售价");
  if (parsedPrice == null) return null;
  const courseScopePayload = buildCourseScopePayload();
  const bookingRules = buildBookingRulesPayload();
  if (bookingRules == null) return null;

  const payload: StaffCardProductUpsertPayload = {
    cardType: cardType.value,
    name: name.value.trim(),
    price: parsedPrice,
    saleStatus: saleOn.value ? "on_sale" : "stopped",
    sortOrder: Number.parseInt(sortOrder.value, 10) || 0,
    bookingRules,
    courseScopes: courseScopePayload,
  };
  if (description.value.trim()) payload.description = description.value.trim();
  payload.validityMode = product.value?.validityMode ?? null;
  payload.activationMode = activationMode.value;
  payload.scopeConfig = {
    ...((product.value?.scopeConfig as Record<string, unknown> | null) ?? {}),
    faceStyle: faceIndex.value,
  };

  if (isStoredValue.value) {
    const parsedFaceValue = parsePrice(faceValue.value, "卡额度");
    if (parsedFaceValue == null) return null;
    payload.faceValue = parsedFaceValue;
  }
  if (isCount.value) {
    const parsedCount = parsePositiveInt(initialCount.value, "卡额度（次数）");
    if (parsedCount == null) return null;
    payload.initialCount = parsedCount;
  }
  if (isPeriod.value) {
    const parsedDays = parsePositiveInt(validityDays.value, "有效天数");
    if (parsedDays == null) return null;
    payload.validityDays = parsedDays;
  }
  if (!isPeriod.value && validityDays.value.trim()) {
    const parsedDays = parsePositiveInt(validityDays.value, "有效天数");
    if (parsedDays == null) return null;
    payload.validityDays = parsedDays;
  }

  return payload;
}

async function load() {
  if (!canWrite.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && session.currentSiteId && productId.value) {
      const response = await fetchCardProduct(session.currentSiteId, productId.value);
      product.value = response.data;
      fillForm(response.data);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "卡种资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId) return;
  const payload = buildPayload();
  if (!payload) return;

  saving.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && product.value) {
      const response = await updateCardProduct(session.currentSiteId, product.value.id, {
        ...payload,
        version: product.value.version,
      });
      product.value = response.data;
      fillForm(response.data);
    } else {
      const response = await createCardProduct(session.currentSiteId, payload);
      product.value = response.data;
      productId.value = response.data.id;
      fillForm(response.data);
    }
    uni.showToast({ title: "保存成功", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function archive() {
  if (!session.currentSiteId || !product.value || !canArchive.value) return;
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "警示!确定删除吗？",
      content: "若已有会员持有该卡，建议「停售」，不建议删除。删除后，可在回收站内恢复。",
      confirmText: "删除",
      confirmColor: "#dc3c5c",
      success: (result) => resolve(Boolean(result.confirm)),
    });
  });
  if (!confirmed) return;

  saving.value = true;
  try {
    await archiveCardProduct(session.currentSiteId, product.value.id);
    uni.showToast({ title: "已删除，可在回收站恢复", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "删除失败";
  } finally {
    saving.value = false;
  }
}

onLoad((query) => {
  if (query?.id) {
    productId.value = Number(query.id);
    typeChosen.value = true;
    uni.setNavigationBarTitle({ title: "编辑会员卡" });
  } else {
    uni.setNavigationBarTitle({ title: "创建会员卡" });
  }
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="edit-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canWrite" mode="permission" text="暂无编辑卡种权限" />

    <template v-else>
      <!-- 步骤一：卡类型选择 -->
      <view v-if="!typeChosen" class="type-pick">
        <view
          v-for="option in CARD_TYPE_OPTIONS"
          :key="option.type"
          class="type-card"
          @tap="pickType(option.type)"
        >
          <view class="type-main">
            <text class="type-label">{{ option.label }}</text>
            <text class="type-desc">{{ option.desc }}</text>
          </view>
          <u-icon name="arrow-right" size="18" color="#bfbfbf" />
        </view>
      </view>

      <!-- 步骤二：对标原版添加卡页 -->
      <template v-else>
        <!-- 卡面预览（原版：左上店名、中央大字卡名、左下价格/有效期、右上缎带） -->
        <view class="face-preview" :style="{ background: cardFaceStyle }">
          <view class="face-ribbon">{{ cardTypeLabel }}</view>
          <view class="face-shop">
            <view class="shop-dot">{{ currentSiteName.slice(0, 1) }}</view>
            <text class="shop-name">{{ currentSiteName }}</text>
          </view>
          <view class="face-center">{{ name || "会员卡" }}</view>
          <view class="face-bottom">
            <text class="face-price">{{ facePriceText }}</text>
            <text class="face-validity">有效期{{ validitySummary || "待设置" }}</text>
          </view>
        </view>
        <view class="change-face-wrap">
          <view class="change-face-btn" @tap="openFacePicker">更换图案</view>
        </view>

        <!-- 基础字段（原版通栏行式：label + 值左对齐） -->
        <view class="plain-form">
          <view class="p-row">
            <text class="p-label required">卡名称</text>
            <input v-model="name" class="p-input" placeholder="会员卡" maxlength="120" />
          </view>
          <view class="p-row">
            <text class="p-label required">售&nbsp;&nbsp;&nbsp;价</text>
            <input v-model="price" class="p-input" type="digit" placeholder="请输入售价" />
          </view>
          <view v-if="isStoredValue || isCount" class="p-row" @tap="openPanel('quota')">
            <text class="p-label required">卡额度</text>
            <text class="p-value" :class="{ placeholder: !quotaSummary }">{{ quotaSummary || "填写卡内的额度" }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('validity')">
            <text class="p-label" :class="{ required: isPeriod }">有效期</text>
            <text class="p-value" :class="{ placeholder: !validitySummary }">{{ validitySummary || "请选择有效期" }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
        </view>

        <!-- 更多高级选项（原版：绿色字 + 折叠） -->
        <view class="adv-toggle" @tap="advancedOpen = !advancedOpen">
          <text class="adv-toggle-plain">更多</text>
          <text class="adv-toggle-green">高级选项</text>
          <u-icon :name="advancedOpen ? 'arrow-up' : 'arrow-down'" size="13" color="#22c788" />
        </view>

        <view v-if="advancedOpen" class="plain-form">
          <view class="p-row" @tap="openPanel('timeRange')">
            <text class="p-label wide">可用时段</text>
            <text class="p-value right">{{ timeRangeSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('activation')">
            <text class="p-label wide">开卡时间</text>
            <text class="p-value right">{{ activationSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('bookingLimit')">
            <text class="p-label wide">每日/周/月可预约上限</text>
            <text class="p-value right">{{ bookingLimitSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('advanceLimit')">
            <text class="p-label wide">提前预约次数限制</text>
            <text class="p-value right">{{ advanceSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('cancelLimit')">
            <text class="p-label wide">取消预约次数限制</text>
            <text class="p-value right">{{ cancelSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('absencePenalty')">
            <text class="p-label wide">旷课处罚</text>
            <text class="p-value right">{{ penaltySummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('repeatBooking')">
            <text class="p-label wide">是否允许同一课程重复预约</text>
            <text class="p-value right">{{ repeatSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('multiPerson')">
            <text class="p-label wide">是否允许一次预约多人</text>
            <text class="p-value right">{{ multiPersonSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row" @tap="openPanel('description')">
            <text class="p-label wide">权益说明</text>
            <text class="p-value right">{{ descriptionSummary }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row">
            <text class="p-label wide">是否售卖</text>
            <view class="p-switch">
              <text class="switch-hint">{{ saleOn ? "在售" : "已停售" }}</text>
              <u-switch :model-value="saleOn" active-color="#22c788" size="22" @change="onSaleSwitchChange" />
            </view>
          </view>
        </view>

        <!-- 保存 / 删除（原版黄色大胶囊） -->
        <view class="btn-box">
          <button class="save-btn" :disabled="saving" @click="save">保存</button>
          <button v-if="isEdit && canArchive" class="delete-btn" :disabled="saving" @click="archive">删除</button>
        </view>

        <view class="brand-footer">觅境约课</view>

        <!-- ============ 编辑弹窗 ============ -->
        <u-popup :show="activePanel !== null" mode="bottom" round="20" @close="closePanel">
          <view class="panel">
            <!-- 更换图案（平台图案库） -->
            <template v-if="activePanel === 'face'">
              <text class="panel-title">更换图案</text>
              <view class="face-grid">
                <view
                  v-for="item in faceLibrary"
                  :key="item.id"
                  class="face-cell"
                  :class="{ active: faceIndex === item.id }"
                  :style="{ background: item.gradient }"
                  @tap="pickFace(item.id)"
                >
                  <text class="face-cell-name">{{ item.name }}</text>
                  <u-icon v-if="faceIndex === item.id" name="checkmark-circle-fill" size="20" color="#ffffff" />
                </view>
              </view>
            </template>

            <!-- 卡额度 -->
            <template v-else-if="activePanel === 'quota'">
              <text class="panel-title">卡额度</text>
              <view class="panel-inline">
                <input
                  v-if="isStoredValue"
                  v-model="faceValue"
                  class="panel-input"
                  type="digit"
                  placeholder="填写卡内的额度"
                />
                <input v-else v-model="initialCount" class="panel-input" type="number" placeholder="填写卡内的额度" />
                <text class="panel-unit">{{ isStoredValue ? "元" : "次" }}</text>
              </view>
            </template>

            <!-- 有效期 -->
            <template v-else-if="activePanel === 'validity'">
              <text class="panel-title">有效期</text>
              <view class="panel-chips">
                <text
                  v-for="quick in VALIDITY_QUICK"
                  :key="quick.days"
                  class="panel-chip"
                  :class="{ active: validityDays === String(quick.days) }"
                  @tap="setValidityQuick(quick.days)"
                >
                  {{ quick.label }}
                </text>
                <text
                  v-if="!isPeriod"
                  class="panel-chip"
                  :class="{ active: !validityDays }"
                  @tap="validityDays = ''"
                >
                  永久有效
                </text>
              </view>
              <view class="panel-inline">
                <text class="panel-text">自定义</text>
                <input v-model="validityDays" class="panel-input" type="number" placeholder="天数" />
                <text class="panel-unit">天</text>
              </view>
            </template>

            <!-- 可用时段（原版 time-popup1：多时段 + 添加时间） -->
            <template v-else-if="activePanel === 'timeRange'">
              <text class="panel-title">可用时段</text>
              <text class="panel-desc">限制该卡只能预约此时段内开课的课程，不添加为全部时段</text>
              <view v-for="(range, index) in timeRanges" :key="index" class="panel-inline">
                <text class="panel-text">时段{{ index + 1 }}</text>
                <picker mode="time" :value="range.start" @change="(e: any) => (timeRanges[index].start = e.detail.value)">
                  <text class="time-chip">{{ range.start || "开始" }}</text>
                </picker>
                <text class="panel-text">至</text>
                <picker mode="time" :value="range.end" @change="(e: any) => (timeRanges[index].end = e.detail.value)">
                  <text class="time-chip">{{ range.end || "结束" }}</text>
                </picker>
                <text class="panel-clear" @tap="timeRanges.splice(index, 1)">删除</text>
              </view>
              <view class="panel-add" @tap="timeRanges.push({ start: '', end: '' })">
                <u-icon name="plus" size="14" color="#22c788" />
                <text class="panel-add-text">添加时间</text>
              </view>
            </template>

            <!-- 开卡时间（原版五种模式） -->
            <template v-else-if="activePanel === 'activation'">
              <text class="panel-title">开卡时间</text>
              <view class="panel-options">
                <view
                  v-for="option in ACTIVATION_OPTIONS"
                  :key="option.value"
                  class="panel-option"
                  :class="{ active: activationMode === option.value }"
                  @tap="activationMode = option.value"
                >
                  <text>{{ option.label }}</text>
                  <u-icon v-if="activationMode === option.value" name="checkmark" size="16" color="#22c788" />
                </view>
              </view>
              <view v-if="activationMode === 'delayed'" class="panel-inline">
                <text class="panel-text">购卡</text>
                <input v-model="activationDays" class="panel-input small" type="number" placeholder="天数" />
                <text class="panel-text">天后自动开卡</text>
              </view>
            </template>

            <!-- 预约上限 -->
            <template v-else-if="activePanel === 'bookingLimit'">
              <text class="panel-title">每日/周/月可预约上限</text>
              <text class="panel-desc">留空为不限制</text>
              <view class="panel-inline">
                <text class="panel-text">每日</text>
                <input v-model="limitPerDay" class="panel-input small" type="number" placeholder="不限" />
                <text class="panel-text">每周</text>
                <input v-model="limitPerWeek" class="panel-input small" type="number" placeholder="不限" />
                <text class="panel-text">每月</text>
                <input v-model="limitPerMonth" class="panel-input small" type="number" placeholder="不限" />
              </view>
            </template>

            <!-- 提前预约 -->
            <template v-else-if="activePanel === 'advanceLimit'">
              <text class="panel-title">提前预约次数限制</text>
              <text class="panel-desc">同时最多持有的未上课预约数，留空为不限制</text>
              <view class="panel-inline">
                <text class="panel-text">最多预约</text>
                <input v-model="advanceLimit" class="panel-input small" type="number" placeholder="不限" />
                <text class="panel-text">次</text>
              </view>
            </template>

            <!-- 取消限制 -->
            <template v-else-if="activePanel === 'cancelLimit'">
              <text class="panel-title">取消预约次数限制</text>
              <text class="panel-desc">留空为不限制</text>
              <view class="panel-inline">
                <text class="panel-text">每日</text>
                <input v-model="cancelPerDay" class="panel-input small" type="number" placeholder="不限" />
                <text class="panel-text">每周</text>
                <input v-model="cancelPerWeek" class="panel-input small" type="number" placeholder="不限" />
                <text class="panel-text">每月</text>
                <input v-model="cancelPerMonth" class="panel-input small" type="number" placeholder="不限" />
              </view>
            </template>

            <!-- 旷课处罚（原版：周/月双窗口 + 三动作） -->
            <template v-else-if="activePanel === 'absencePenalty'">
              <text class="panel-title">旷课处罚</text>
              <text class="panel-desc">周/月可同时设置，任一达标即触发；留空为不限制</text>
              <view class="panel-inline">
                <text class="panel-text">每周累计达</text>
                <input v-model="penaltyWeekThreshold" class="panel-input small" type="number" placeholder="不启用" />
                <text class="panel-text">次</text>
              </view>
              <view class="panel-inline">
                <text class="panel-text">每月累计达</text>
                <input v-model="penaltyMonthThreshold" class="panel-input small" type="number" placeholder="不启用" />
                <text class="panel-text">次</text>
              </view>
              <view class="panel-chips">
                <text
                  v-for="option in PENALTY_ACTIONS"
                  :key="option.value"
                  class="panel-chip"
                  :class="{ active: penaltyAction === option.value }"
                  @tap="penaltyAction = option.value"
                >
                  {{ option.label }}
                </text>
              </view>
              <view v-if="penaltyAction === 'forbid'" class="panel-inline">
                <text class="panel-text">禁止约课</text>
                <input v-model="penaltyForbidDays" class="panel-input small" type="number" placeholder="天数" />
                <text class="panel-text">天</text>
              </view>
              <view v-if="penaltyAction === 'deduct'" class="panel-inline">
                <text class="panel-text">扣除</text>
                <input v-model="penaltyDeductValue" class="panel-input small" type="digit" placeholder="数值" />
                <text class="panel-text">{{ deductUnit }}</text>
              </view>
            </template>

            <!-- 同课重复预约 -->
            <template v-else-if="activePanel === 'repeatBooking'">
              <text class="panel-title">是否允许同一课程重复预约</text>
              <view class="panel-options">
                <view
                  v-for="option in REPEAT_OPTIONS"
                  :key="option.value"
                  class="panel-option"
                  :class="{ active: repeatMode === option.value }"
                  @tap="repeatMode = option.value"
                >
                  <text>{{ option.label }}</text>
                  <u-icon v-if="repeatMode === option.value" name="checkmark" size="16" color="#22c788" />
                </view>
              </view>
              <view v-if="repeatMode === 'limit'" class="panel-inline">
                <text class="panel-text">最多可预约</text>
                <input v-model="repeatMax" class="panel-input small" type="number" placeholder="次数" />
                <text class="panel-text">次</text>
              </view>
            </template>

            <!-- 多人使用（原版 useRuleDesList 三选项） -->
            <template v-else-if="activePanel === 'multiPerson'">
              <text class="panel-title">是否允许一次预约多人</text>
              <view class="panel-options">
                <view class="panel-option" :class="{ active: multiPersonMode === 'self' }" @tap="multiPersonMode = 'self'">
                  <text>仅持卡会员自己可用</text>
                  <u-icon v-if="multiPersonMode === 'self'" name="checkmark" size="16" color="#22c788" />
                </view>
                <view class="panel-option" :class="{ active: multiPersonMode === 'unlimited' }" @tap="multiPersonMode = 'unlimited'">
                  <text>允许多人使用，且不限制人数</text>
                  <u-icon v-if="multiPersonMode === 'unlimited'" name="checkmark" size="16" color="#22c788" />
                </view>
                <view class="panel-option" :class="{ active: multiPersonMode === 'limited' }" @tap="multiPersonMode = 'limited'">
                  <text>允许多人使用，最多X人</text>
                  <u-icon v-if="multiPersonMode === 'limited'" name="checkmark" size="16" color="#22c788" />
                </view>
              </view>
              <view v-if="multiPersonMode === 'limited'" class="panel-inline">
                <text class="panel-text">最多</text>
                <input v-model="multiPersonMax" class="panel-input small" type="number" placeholder="人数" />
                <text class="panel-text">人</text>
              </view>
            </template>

            <!-- 权益说明 -->
            <template v-else-if="activePanel === 'description'">
              <text class="panel-title">权益说明</text>
              <u-textarea v-model="description" maxlength="500" placeholder="向会员说明该卡的权益（可不填写）" />
            </template>

            <button class="panel-confirm" @tap="closePanel">确 定</button>
          </view>
        </u-popup>
      </template>
    </template>
  </view>
</template>

<style scoped lang="scss">
.edit-page {
  min-height: 100vh;
  padding: $spacing-md $spacing-md 60rpx;
  box-sizing: border-box;
}

// —— 类型选择 ——
.type-pick {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  margin-top: 20rpx;
}

.type-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 44rpx 36rpx;
  background: $color-surface;
  border-radius: 20rpx;
}

.type-main {
  display: flex;
  flex-direction: column;
}

.type-label {
  font-size: 34rpx;
  font-weight: 600;
  color: $color-text;
}

.type-desc {
  margin-top: 12rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

// —— 卡面预览（原版布局） ——
.face-preview {
  position: relative;
  display: flex;
  overflow: hidden;
  flex-direction: column;
  height: 360rpx;
  padding: 28rpx 32rpx;
  border-radius: 20rpx;
  box-sizing: border-box;
}

.face-ribbon {
  position: absolute;
  top: 24rpx;
  right: -64rpx;
  width: 230rpx;
  transform: rotate(45deg);
  background: rgba(216, 122, 42, 0.95);
  color: #fff;
  font-size: 20rpx;
  line-height: 38rpx;
  text-align: center;
}

.face-shop {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.shop-dot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 56rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.35);
  color: #fff;
  font-size: 24rpx;
}

.shop-name {
  color: #fff;
  font-size: 26rpx;
}

.face-center {
  overflow: hidden;
  margin-top: 30rpx;
  color: #fff;
  font-size: 64rpx;
  font-weight: 600;
  letter-spacing: 8rpx;
  text-align: center;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.face-bottom {
  display: flex;
  flex-direction: column;
  margin-top: auto;
}

.face-price {
  color: #fff;
  font-size: 40rpx;
  font-weight: 500;
}

.face-validity {
  margin-top: 8rpx;
  color: rgba(255, 255, 255, 0.85);
  font-size: 24rpx;
}

// 更换图案（原版白底黄边胶囊）
.change-face-wrap {
  display: flex;
  justify-content: center;
  margin: 24rpx 0 8rpx;
}

.change-face-btn {
  padding: 14rpx 48rpx;
  border: 2rpx solid $color-brand-yellow;
  border-radius: 40rpx;
  background: $color-surface;
  color: #d9a400;
  font-size: 26rpx;
}

// —— 通栏行式表单（原版白底细分割线） ——
.plain-form {
  margin-top: 8rpx;
  padding: 0 8rpx;
  background: transparent;
}

.p-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
  min-height: 104rpx;
  padding: 26rpx 4rpx;
  box-sizing: border-box;
  border-bottom: 1rpx solid #efefef;
}

.p-label {
  flex-shrink: 0;
  color: $color-text;
  font-size: 30rpx;

  &.required::after {
    content: "*";
    margin-left: 2rpx;
    color: $color-danger;
    font-size: 30rpx;
  }

  &.wide {
    flex-shrink: 1;
  }
}

.p-input {
  flex: 1;
  color: $color-text;
  font-size: 30rpx;
}

.p-value {
  overflow: hidden;
  flex: 1;
  color: $color-text;
  font-size: 28rpx;
  white-space: nowrap;
  text-overflow: ellipsis;

  &.placeholder {
    color: $color-text-disabled;
  }

  &.right {
    color: $color-text-secondary;
    text-align: right;
  }
}

.p-switch {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: flex-end;
  gap: 14rpx;
}

.switch-hint {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

// —— 更多高级选项（原版绿色字） ——
.adv-toggle {
  display: flex;
  align-items: center;
  gap: 6rpx;
  padding: 32rpx 12rpx 12rpx;
}

.adv-toggle-plain {
  color: $color-text;
  font-size: 28rpx;
  font-weight: 600;
}

.adv-toggle-green {
  color: $color-success;
  font-size: 28rpx;
  font-weight: 600;
}

// —— 保存/删除（原版黄色大胶囊） ——
.btn-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 70rpx;
}

.save-btn,
.delete-btn {
  width: 458rpx;
  height: 83rpx;
  line-height: 83rpx;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.save-btn {
  background: $color-brand-yellow;

  &[disabled] {
    opacity: 0.6;
    color: $color-text;
    background: $color-brand-yellow;
  }
}

.delete-btn {
  margin-top: 39rpx;
  background: $color-surface;
  border: 1rpx solid $color-brand-yellow;

  &[disabled] {
    opacity: 0.6;
  }
}

.save-btn::after,
.delete-btn::after {
  border: 0;
}

.brand-footer {
  margin: 80rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 弹窗 ——
.panel {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  max-height: 70vh;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.panel-desc {
  color: $color-text-tertiary;
  font-size: 24rpx;
  line-height: 1.6;
}

.panel-inline {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14rpx;
}

.panel-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.panel-input {
  flex: 1;
  height: 72rpx;
  padding: 0 20rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 28rpx;

  &.small {
    flex: none;
    width: 130rpx;
    text-align: center;
  }
}

.panel-unit {
  color: $color-text-secondary;
  font-size: 28rpx;
}

.panel-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;

  &.inline {
    display: inline-flex;
  }
}

.panel-chip {
  padding: 12rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  color: $color-text-secondary;
  font-size: 26rpx;

  &.active {
    border-color: $color-success;
    background: rgba(34, 199, 136, 0.08);
    color: $color-success;
  }
}

.panel-options {
  display: flex;
  flex-direction: column;
}

.panel-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx 8rpx;
  border-bottom: 1rpx solid $color-page;
  color: $color-text;
  font-size: 28rpx;

  &.active {
    color: $color-success;
  }
}

.panel-scroll {
  max-height: 44vh;
}

.time-chip {
  display: inline-block;
  min-width: 130rpx;
  padding: 14rpx 20rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 26rpx;
  text-align: center;
}

.panel-clear {
  color: $color-danger;
  font-size: 24rpx;
}

.panel-add {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 12rpx 0;
}

// 图案库网格
.face-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20rpx;
}

.face-cell {
  position: relative;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 140rpx;
  padding: 16rpx 20rpx;
  border: 3rpx solid transparent;
  border-radius: 16rpx;
  box-sizing: border-box;

  &.active {
    border-color: -brand-yellow;
  }
}

.face-cell-name {
  color: #fff;
  font-size: 24rpx;
}

.panel-add-text {
  color: $color-success;
  font-size: 26rpx;
}

.panel-confirm {
  height: 83rpx;
  margin-top: 8rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.panel-confirm::after {
  border: 0;
}
</style>
