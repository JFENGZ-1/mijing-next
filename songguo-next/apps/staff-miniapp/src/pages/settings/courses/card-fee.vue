<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchAllCardProducts, fetchCardProduct } from "@/api/card-products";
import { fetchCardProductCourseRuleSets, updateCardProductCourseRules } from "@/api/compensation";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CoachPrivateFee } from "@/api/catalog";
import type { CardProductCourseRuleInput, CardProductCourseRules, CourseDeductionKind } from "@/types/compensation";
import type { StaffCardProductCatalogItem } from "@/types/member-cards";
import { createCommandKey } from "@/utils/command-key";
import { confirmFinancePublish } from "@/utils/finance-publish-confirm";

interface CardRuleDraft {
  card: StaffCardProductCatalogItem;
  enabled: boolean;
  amount: string;
  count: string;
  originalSignature: string;
}

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const courseId = ref(0);
const courseName = ref("");
const pickMode = ref(false);
const pickToken = ref("");
const pickFees = ref<CoachPrivateFee[]>([]);
const drafts = ref<CardRuleDraft[]>([]);
const ruleSets = new Map<number, CardProductCourseRules>();
const canWrite = computed(() => pickMode.value
  ? session.can("course-catalog.write")
  : session.can("compensation.rule.write"));

const enabledCount = computed(() => drafts.value.filter((draft) => draft.enabled).length);

function kindFor(cardType: string): CourseDeductionKind {
  if (cardType === "stored_value") return "amount";
  if (cardType === "count") return "count";
  return "period_auto";
}
function signature(draft: Pick<CardRuleDraft, "card" | "enabled" | "amount" | "count">) {
  if (!draft.enabled) return "unlinked";
  if (draft.card.cardType === "stored_value") {
    const amount = Number(draft.amount);
    return `amount:${Number.isFinite(amount) ? amount.toFixed(2) : draft.amount.trim()}`;
  }
  if (draft.card.cardType === "count") {
    const count = Number(draft.count);
    return `count:${Number.isFinite(count) ? String(count) : draft.count.trim()}`;
  }
  return "period_auto";
}
function deductionLabel(value: string) {
  if (value === "unlinked") return "未关联";
  if (value === "period_auto") return "期限卡按日自动分摊";
  if (value.startsWith("amount:")) return `每次扣 ¥${value.slice(7)}`;
  if (value.startsWith("count:")) return `每次扣 ${value.slice(6)} 次`;
  return value;
}
function cardTypeLabel(type: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[type] || type;
}

async function load() {
  if (!session.currentSiteId || !canWrite.value) { loading.value = false; return; }
  loading.value = true;
  try {
    const products = await fetchAllCardProducts(session.currentSiteId, undefined, "active");
    if (pickMode.value) {
      const pickedById = new Map(pickFees.value.map((fee) => [fee.cardProductId, fee]));
      drafts.value = products.map((card) => {
        const picked = pickedById.get(card.id);
        const raw = picked?.deductAmount == null ? "" : String(picked.deductAmount);
        const draft = {
          card,
          enabled: !!picked,
          amount: card.cardType === "stored_value" ? raw : "",
          count: card.cardType === "count" ? (raw || "1") : "1",
          originalSignature: "",
        };
        draft.originalSignature = signature(draft);
        return draft;
      });
      return;
    }

    ruleSets.clear();
    const sets = await fetchCardProductCourseRuleSets(
      session.currentSiteId,
      products.map((card) => card.id),
    );
    const legacyProducts = new Map<number, Awaited<ReturnType<typeof fetchCardProduct>> | null>();
    const missingProducts = products.filter((card) => !sets.get(card.id)?.items.length);
    for (let offset = 0; offset < missingProducts.length; offset += 8) {
      const batch = missingProducts.slice(offset, offset + 8);
      const details = await Promise.all(
        batch.map((card) => fetchCardProduct(session.currentSiteId!, card.id)),
      );
      batch.forEach((card, index) => legacyProducts.set(card.id, details[index] ?? null));
    }
    drafts.value = products.map((card) => {
      const set = sets.get(card.id);
      const legacyProduct = legacyProducts.get(card.id)?.data;
      const legacyItems = legacyProduct?.courseScopes.flatMap((scope) => {
        const legacyCourseId = Number(scope.scopeKey);
        if (!Number.isInteger(legacyCourseId) || legacyCourseId <= 0) return [];
        const legacyValue = scope.priceOverride ?? "";
        const legacyCount = Number(legacyValue);
        return [{
          courseId: legacyCourseId,
          courseName: scope.displayName || `课程 #${legacyCourseId}`,
          enabled: true,
          deductionKind: kindFor(card.cardType),
          deductionAmount: card.cardType === "stored_value" ? String(legacyValue || "") : null,
          deductionCount: card.cardType === "count" && Number.isInteger(legacyCount) && legacyCount > 0
            ? legacyCount
            : card.cardType === "count" ? 1 : null,
          version: 1,
        }];
      }) ?? [];
      const effectiveSet: CardProductCourseRules | null = set?.items.length
        ? set
        : (set || legacyProduct) ? {
            cardProductId: card.id,
            cardProductName: card.name,
            cardType: card.cardType as CardProductCourseRules["cardType"],
            version: set?.version,
            items: legacyItems,
          } : null;
      if (effectiveSet) ruleSets.set(card.id, effectiveSet);
      const current = effectiveSet?.items.find((rule) => rule.courseId === courseId.value);
      const draft = {
        card,
        enabled: current ? current.enabled !== false : false,
        amount: current?.deductionAmount ?? "",
        count: current?.deductionCount != null ? String(current.deductionCount) : "1",
        originalSignature: "",
      };
      draft.originalSignature = signature(draft);
      return draft;
    });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "会员卡规则加载失败", icon: "none" });
  } finally { loading.value = false; }
}

function validate() {
  for (const draft of drafts.value.filter((item) => item.enabled)) {
    if (draft.card.cardType === "stored_value") {
      const amount = Number(draft.amount);
      if (!Number.isFinite(amount) || amount <= 0) return `${draft.card.name} 的扣费金额应大于 0`;
    }
    if (draft.card.cardType === "count") {
      const count = Number(draft.count);
      if (!Number.isInteger(count) || count <= 0) return `${draft.card.name} 的扣减次数应为正整数`;
    }
  }
  return "";
}

async function submit() {
  if (!session.currentSiteId || saving.value || !canWrite.value) return;
  const error = validate();
  if (error) return uni.showToast({ title: error, icon: "none" });
  if (pickMode.value) {
    const arr: CoachPrivateFee[] = drafts.value.filter((draft) => draft.enabled).map((draft) => ({
      cardProductId: draft.card.id,
      cardName: draft.card.name,
      deductAmount: draft.card.cardType === "stored_value"
        ? Number(draft.amount)
        : draft.card.cardType === "count" ? Number(draft.count) : null,
    }));
    uni.setStorageSync("private_fee_pick", { token: pickToken.value, arr, checknum: arr.length });
    uni.navigateBack();
    return;
  }
  const changed = drafts.value.filter((draft) => signature(draft) !== draft.originalSignature);
  if (!changed.length) {
    uni.showToast({ title: "未检测到需要发布的变更", icon: "none" });
    return;
  }
  const addedCount = changed.filter((draft) => draft.originalSignature === "unlinked" && draft.enabled).length;
  const removalCount = changed.filter((draft) => draft.originalSignature !== "unlinked" && !draft.enabled).length;
  const adjustedCount = changed.length - addedCount - removalCount;
  const changeLines = changed.slice(0, 4).map((draft) => (
    `${draft.card.name}：${deductionLabel(draft.originalSignature)} → ${deductionLabel(signature(draft))}`
  ));
  if (changed.length > changeLines.length) {
    changeLines.push(`另有 ${changed.length - changeLines.length} 张卡的关联或扣费规则同步变更`);
  }
  const previouslyLinkedCount = drafts.value.filter((draft) => draft.originalSignature !== "unlinked").length;
  const reason = await confirmFinancePublish({
    title: "发布课程扣卡规则？",
    summaryLines: [
      `课程：${courseName.value}`,
      `关联变更：新增 ${addedCount} 张、解除 ${removalCount} 张、扣费调整 ${adjustedCount} 张`,
      ...changeLines,
      "生效：发布后立即生成各卡项新版本，历史耗卡不变",
    ],
    warning: removalCount > 0
      ? removalCount === previouslyLinkedCount
        ? "将解除该课程的全部会员卡关联，未来预约无法使用这些卡"
        : `将解除 ${removalCount} 张会员卡关联，未来预约将不能使用这些规则`
      : undefined,
    reasonPlaceholder: "例如：九月起调整该课程适用卡项",
  });
  if (!reason) return;

  saving.value = true;
  try {
    await Promise.all(changed.map((draft) => {
      const set = ruleSets.get(draft.card.id);
      const rules: CardProductCourseRuleInput[] = (set?.items ?? [])
        .filter((rule) => rule.courseId !== courseId.value)
        .map((rule) => ({
          courseId: rule.courseId,
          deductionKind: rule.deductionKind,
          deductionAmount: rule.deductionAmount,
          deductionCount: rule.deductionCount,
          version: rule.version,
        }));
      if (draft.enabled) rules.push({
        courseId: courseId.value,
        deductionKind: kindFor(draft.card.cardType),
        deductionAmount: draft.card.cardType === "stored_value" ? Number(draft.amount).toFixed(2) : null,
        deductionCount: draft.card.cardType === "count" ? Number(draft.count) : null,
        version: undefined,
      });
      return updateCardProductCourseRules(session.currentSiteId!, draft.card.id, {
        version: set?.version ?? 0,
        commandKey: createCommandKey(),
        reason,
        rules,
      });
    }));
    uni.showToast({ title: "课程扣卡规则已保存", icon: "success" });
    setTimeout(() => uni.navigateBack(), 350);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally { saving.value = false; }
}

onLoad((options) => {
  courseId.value = Number(options?.courseId || 0);
  courseName.value = decodeURIComponent(String(options?.name || "课程"));
  pickMode.value = String(options?.pick || "") === "1";
  pickToken.value = decodeURIComponent(String(options?.token || ""));
  if (options?.fees) {
    try { pickFees.value = JSON.parse(decodeURIComponent(String(options.fees))); } catch { pickFees.value = []; }
  }
});
onShow(async () => { if (await requireStaffAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canWrite" class="page-shell">
    <view class="header">
      <text class="course-name">{{ courseName }}</text>
      <text class="count">已支持 {{ enabledCount }} 张会员卡</text>
    </view>
    <view class="body-sheet">
      <u-alert type="warning" description="储值卡填每次扣金额，次卡填每次扣次数；期限卡按日自动分摊，不填写扣除天数。" />
      <view v-if="drafts.length" class="card-list">
        <view v-for="draft in drafts" :key="draft.card.id" class="card-row">
          <view class="card-toggle" @tap="draft.enabled = !draft.enabled">
            <u-icon :name="draft.enabled ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="draft.enabled ? '#22c788' : '#dadada'" size="22" />
            <view class="card-main">
              <text class="name">{{ draft.card.name }}</text>
              <text class="type">{{ cardTypeLabel(draft.card.cardType) }}</text>
            </view>
          </view>
          <view v-if="draft.enabled" class="deduction">
            <template v-if="draft.card.cardType === 'stored_value'">
              <input v-model="draft.amount" type="digit" placeholder="0.00" /><text>元/次</text>
            </template>
            <template v-else-if="draft.card.cardType === 'count'">
              <input v-model="draft.count" type="number" placeholder="1" /><text>次/次</text>
            </template>
            <text v-else class="auto-label">按日自动分摊</text>
          </view>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无会员卡" />
      <button class="save-btn" :disabled="saving" @tap="submit">{{ saving ? "保存中…" : "确定" }}</button>
    </view>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无课程扣卡规则编辑权限" />
</template>

<style scoped lang="scss">
.page-shell { min-height: 100vh; background: $color-brand-yellow; }
.header { padding: 30rpx; }
.course-name, .count { display: block; }
.course-name { font-size: 36rpx; font-weight: 600; }
.count { margin-top: 8rpx; color: rgba(24,24,24,.62); font-size: 23rpx; }
.body-sheet { min-height: calc(100vh - 130rpx); padding: 28rpx 26rpx 70rpx; background: #fff; border-radius: 22rpx 22rpx 0 0; box-sizing: border-box; }
.card-list { margin-top: 20rpx; }
.card-row { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; min-height: 104rpx; padding: 18rpx 4rpx; border-bottom: 1rpx solid #f1f1f1; box-sizing: border-box; }
.card-toggle { display: flex; flex: 1; align-items: center; gap: 16rpx; min-width: 0; }
.card-main { min-width: 0; }
.name, .type { display: block; }
.name { overflow: hidden; font-size: 27rpx; white-space: nowrap; text-overflow: ellipsis; }
.type { margin-top: 5rpx; color: $color-text-tertiary; font-size: 20rpx; }
.deduction { display: flex; flex-shrink: 0; align-items: center; gap: 7rpx; color: $color-text-secondary; font-size: 22rpx; }
.deduction input { width: 112rpx; height: 62rpx; padding: 0 10rpx; background: #f5f5f5; border-radius: 10rpx; text-align: right; box-sizing: border-box; }
.auto-label { padding: 10rpx 14rpx; color: #168d61; background: #e8f8f1; border-radius: 999rpx; font-size: 21rpx; }
.save-btn { width: 500rpx; height: 84rpx; margin: 48rpx auto 0; background: $color-brand-yellow; border-radius: 42rpx; color: $color-text; font-size: 30rpx; line-height: 84rpx; }
.save-btn::after { border: 0; }
</style>
