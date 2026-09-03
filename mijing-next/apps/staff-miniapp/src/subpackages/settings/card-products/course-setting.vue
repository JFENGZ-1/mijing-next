<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { fetchCardProduct } from "@/api/card-products";
import { fetchAllStaffCourseCatalog, fetchPrivateCoaches } from "@/api/catalog";
import { fetchCardProductCourseRules, updateCardProductCourseRules } from "@/api/compensation";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CardProductCourseRule, CourseDeductionKind } from "@/types/compensation";
import { createCommandKey } from "@/utils/command-key";
import { confirmFinancePublish } from "@/utils/finance-publish-confirm";

interface CourseDraft {
  courseId: number;
  courseName: string;
  courseType: "group" | "private";
  selected: boolean;
  initiallySelected: boolean;
  deductionKind: CourseDeductionKind;
  deductionAmount: string;
  deductionCount: string;
  initialDeductionAmount: string;
  initialDeductionCount: string;
  version?: number;
}

const session = useSessionStore();
const cardId = ref(0);
const cardName = ref("");
const cardType = ref<"stored_value" | "count" | "period">("stored_value");
const rulesVersion = ref(0);
const loading = ref(true);
const saving = ref(false);
const keyword = ref("");
const activeTab = ref<"all" | "group" | "private">("all");
const drafts = ref<CourseDraft[]>([]);
const canWrite = computed(() => session.can("compensation.rule.write"));

const filteredDrafts = computed(() => {
  const query = keyword.value.trim().toLowerCase();
  return drafts.value.filter((draft) =>
    (activeTab.value === "all" || draft.courseType === activeTab.value)
    && (!query || draft.courseName.toLowerCase().includes(query)),
  );
});
const selectedCount = computed(() => drafts.value.filter((draft) => draft.selected).length);
const groupSelectedCount = computed(() => drafts.value.filter((draft) => draft.selected && draft.courseType === "group").length);
const privateSelectedCount = computed(() => drafts.value.filter((draft) => draft.selected && draft.courseType === "private").length);
const visibleAllSelected = computed(() => filteredDrafts.value.length > 0 && filteredDrafts.value.every((draft) => draft.selected));

function kindForCardType(): CourseDeductionKind {
  if (cardType.value === "stored_value") return "amount";
  if (cardType.value === "count") return "count";
  return "period_auto";
}

function normalizedAmount(value: string): string {
  const amount = Number(value);
  return Number.isFinite(amount) ? amount.toFixed(2) : value.trim();
}

function normalizedCount(value: string): string {
  const count = Number(value);
  return Number.isFinite(count) ? String(count) : value.trim();
}

function ruleSignature(
  selected: boolean,
  kind: CourseDeductionKind,
  amount: string,
  count: string,
): string {
  if (!selected) return "unlinked";
  if (kind === "amount") return `amount:${normalizedAmount(amount)}`;
  if (kind === "count") return `count:${normalizedCount(count)}`;
  return "period_auto";
}

function initialSignature(draft: CourseDraft): string {
  return ruleSignature(
    draft.initiallySelected,
    draft.deductionKind,
    draft.initialDeductionAmount,
    draft.initialDeductionCount,
  );
}

function nextSignature(draft: CourseDraft): string {
  return ruleSignature(
    draft.selected,
    kindForCardType(),
    draft.deductionAmount,
    draft.deductionCount,
  );
}

function deductionLabel(signature: string): string {
  if (signature === "unlinked") return "未关联";
  if (signature === "period_auto") return "期限卡按日自动分摊";
  if (signature.startsWith("amount:")) return `每次扣 ¥${signature.slice(7)}`;
  if (signature.startsWith("count:")) return `每次扣 ${signature.slice(6)} 次`;
  return signature;
}

async function load() {
  if (!session.currentSiteId || !cardId.value || !canWrite.value) { loading.value = false; return; }
  loading.value = true;
  try {
    const [productResponse, catalog, privateProfiles, structured] = await Promise.all([
      fetchCardProduct(session.currentSiteId, cardId.value),
      fetchAllStaffCourseCatalog(session.currentSiteId),
      fetchPrivateCoaches(session.currentSiteId),
      fetchCardProductCourseRules(session.currentSiteId, cardId.value),
    ]);
    const product = productResponse.data;
    cardName.value = product.name;
    cardType.value = product.cardType as typeof cardType.value;
    rulesVersion.value = structured.version ?? 0;
    const courseMap = new Map<number, { courseId: number; courseName: string; courseType: "group" | "private" }>();
    for (const course of catalog) {
      courseMap.set(course.id, { courseId: course.id, courseName: course.name, courseType: course.courseType });
    }
    for (const profile of privateProfiles) {
      if (profile.uniformCourseId) {
        courseMap.set(profile.uniformCourseId, {
          courseId: profile.uniformCourseId,
          courseName: `${profile.coachName || "私教"} · 统一课目`,
          courseType: "private",
        });
      }
      for (const course of profile.courses) {
        courseMap.set(course.id, { courseId: course.id, courseName: course.name, courseType: "private" });
      }
    }
    const structuredByCourse = new Map<number, CardProductCourseRule>((structured?.items ?? []).map((rule) => [rule.courseId, rule]));
    // A full replacement must preserve rules that are temporarily absent from the
    // active course directory (for example an archived course). Treating a partial
    // directory response as deletion would silently unlink an existing card rule.
    for (const rule of structured?.items ?? []) {
      if (!courseMap.has(rule.courseId)) {
        courseMap.set(rule.courseId, {
          courseId: rule.courseId,
          courseName: rule.courseName || `课程 #${rule.courseId}`,
          courseType: rule.courseType === "private" ? "private" : "group",
        });
      }
    }
    const hasStructuredRules = Boolean(structured?.items.length);
    const legacyByCourse = new Map(product.courseScopes.map((scope) => [Number(scope.scopeKey), scope]));
    drafts.value = [...courseMap.values()].map((course) => {
      const current = structuredByCourse.get(course.courseId);
      const legacy = hasStructuredRules ? undefined : legacyByCourse.get(course.courseId);
      const legacyValue = legacy?.priceOverride ?? "";
      const selected = current ? current.enabled !== false : !!legacy;
      return {
        ...course,
        selected,
        initiallySelected: selected,
        deductionKind: current?.deductionKind ?? kindForCardType(),
        deductionAmount: current?.deductionAmount ?? (cardType.value === "stored_value" ? String(legacyValue || "") : ""),
        deductionCount: current?.deductionCount != null
          ? String(current.deductionCount)
          : cardType.value === "count" && legacyValue ? String(Number(legacyValue)) : "1",
        initialDeductionAmount: current?.deductionAmount ?? (cardType.value === "stored_value" ? String(legacyValue || "") : ""),
        initialDeductionCount: current?.deductionCount != null
          ? String(current.deductionCount)
          : cardType.value === "count" && legacyValue ? String(Number(legacyValue)) : "1",
        version: current?.version,
      };
    });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "关联课程加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function toggleDraft(draft: CourseDraft) { draft.selected = !draft.selected; }
function toggleVisible() {
  const next = !visibleAllSelected.value;
  for (const draft of filteredDrafts.value) draft.selected = next;
}
function ruleLabel() {
  if (cardType.value === "stored_value") return "每次扣金额";
  if (cardType.value === "count") return "每次扣次数";
  return "期限卡按日自动分摊";
}
function validateRules() {
  for (const draft of drafts.value.filter((item) => item.selected)) {
    if (cardType.value === "stored_value") {
      const amount = Number(draft.deductionAmount);
      if (!Number.isFinite(amount) || amount <= 0) return `${draft.courseName} 的扣费金额应大于 0`;
    }
    if (cardType.value === "count") {
      const count = Number(draft.deductionCount);
      if (!Number.isInteger(count) || count <= 0) return `${draft.courseName} 的扣减次数应为正整数`;
    }
  }
  return "";
}

async function submit() {
  if (!session.currentSiteId || saving.value || !canWrite.value) return;
  const validation = validateRules();
  if (validation) return uni.showToast({ title: validation, icon: "none" });
  const changed = drafts.value.filter((draft) => initialSignature(draft) !== nextSignature(draft));
  if (!changed.length) {
    uni.showToast({ title: "未检测到需要发布的变更", icon: "none" });
    return;
  }
  const addedCount = changed.filter((draft) => !draft.initiallySelected && draft.selected).length;
  const removalCount = changed.filter((draft) => draft.initiallySelected && !draft.selected).length;
  const adjustedCount = changed.length - addedCount - removalCount;
  const changeLines = changed.slice(0, 4).map((draft) => (
    `${draft.courseName}：${deductionLabel(initialSignature(draft))} → ${deductionLabel(nextSignature(draft))}`
  ));
  if (changed.length > changeLines.length) {
    changeLines.push(`另有 ${changed.length - changeLines.length} 门课程的关联或扣费规则同步变更`);
  }
  const initiallySelectedCount = drafts.value.filter((draft) => draft.initiallySelected).length;
  const reason = await confirmFinancePublish({
    title: "发布卡课规则？",
    summaryLines: [
      `卡项：${cardName.value}（${ruleLabel()}）`,
      `关联变更：新增 ${addedCount} 门、解除 ${removalCount} 门、扣费调整 ${adjustedCount} 门`,
      ...changeLines,
      "生效：发布后立即生成新版本，历史耗卡不变",
    ],
    warning: removalCount > 0
      ? removalCount === initiallySelectedCount
        ? "将清空该卡项的全部课程关联，未来预约将无法使用此卡"
        : `将解除 ${removalCount} 门课程关联，未来预约将不能使用这些规则`
      : undefined,
    reasonPlaceholder: "例如：九月起调整该卡项可用课程",
  });
  if (!reason) return;

  saving.value = true;
  try {
    await updateCardProductCourseRules(session.currentSiteId, cardId.value, {
      version: rulesVersion.value,
      commandKey: createCommandKey(),
      reason,
      rules: drafts.value.filter((draft) => draft.selected).map((draft) => ({
        courseId: draft.courseId,
        deductionKind: kindForCardType(),
        deductionAmount: cardType.value === "stored_value" ? Number(draft.deductionAmount).toFixed(2) : null,
        deductionCount: cardType.value === "count" ? Number(draft.deductionCount) : null,
        version: draft.version,
      })),
    });
    uni.showToast({ title: "关联规则已保存", icon: "success" });
    setTimeout(() => uni.navigateBack(), 350);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally { saving.value = false; }
}

onLoad((options) => {
  cardId.value = Number(options?.id || 0);
  cardName.value = decodeURIComponent(String(options?.name || "会员卡"));
});
onShow(async () => { if (await requireStaffAuth()) await load(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canWrite" class="page-shell">
    <view class="header-card">
      <text class="card-name">{{ cardName }}</text>
      <text class="card-rule">{{ ruleLabel() }}</text>
      <text class="card-count">已支持 {{ selectedCount }} 门课（团课 {{ groupSelectedCount }} · 私教 {{ privateSelectedCount }}）</text>
    </view>
    <view class="body-sheet">
      <u-alert type="warning" :description="cardType === 'period'
        ? '期限卡不填写扣除天数：系统按实付金额 / 总天数 / 当天完成耗卡次数自动分摊。'
        : cardType === 'count'
          ? '次卡规则只填写每次扣减次数，不再与金额共用字段。'
          : '储值卡规则只填写每次扣费金额，不再与次数或天数共用字段。'" />
      <u-search v-model="keyword" class="search" placeholder="搜索课程" :show-action="false" bg-color="#f5f5f5" />
      <view class="tabs">
        <view class="tab" :class="{ active: activeTab === 'all' }" @tap="activeTab = 'all'">全部</view>
        <view class="tab" :class="{ active: activeTab === 'group' }" @tap="activeTab = 'group'">团课</view>
        <view class="tab" :class="{ active: activeTab === 'private' }" @tap="activeTab = 'private'">私教</view>
      </view>
      <view class="select-all" @tap="toggleVisible">
        <text>{{ visibleAllSelected ? "取消本页全选" : "选择本页全部" }}</text>
        <u-icon :name="visibleAllSelected ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="visibleAllSelected ? '#22c788' : '#dadada'" size="21" />
      </view>
      <view v-if="filteredDrafts.length" class="course-list">
        <view v-for="draft in filteredDrafts" :key="draft.courseId" class="course-row">
          <view class="course-select" @tap="toggleDraft(draft)">
            <u-icon :name="draft.selected ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="draft.selected ? '#22c788' : '#dadada'" size="22" />
            <view class="course-main">
              <text class="course-name-text">{{ draft.courseName }}</text>
              <text class="course-type">{{ draft.courseType === "private" ? "私教" : "团课" }}</text>
            </view>
          </view>
          <view v-if="draft.selected" class="rule-input">
            <template v-if="cardType === 'stored_value'">
              <input v-model="draft.deductionAmount" type="digit" placeholder="0.00" /><text>元/次</text>
            </template>
            <template v-else-if="cardType === 'count'">
              <input v-model="draft.deductionCount" type="number" placeholder="1" /><text>次/次</text>
            </template>
            <text v-else class="auto-label">按日自动分摊</text>
          </view>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无课程" />
      <button class="save-btn" :disabled="saving" @tap="submit">{{ saving ? "保存中…" : "保存关联规则" }}</button>
    </view>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无卡课规则编辑权限" />
</template>

<style scoped lang="scss">
.page-shell { min-height: 100vh; background: $color-brand-yellow; }
.header-card { padding: 30rpx 30rpx 26rpx; }
.card-name, .card-rule, .card-count { display: block; }
.card-name { font-size: 36rpx; font-weight: 600; }
.card-rule { margin-top: 8rpx; font-size: 25rpx; }
.card-count { margin-top: 6rpx; color: rgba(24,24,24,.62); font-size: 22rpx; }
.body-sheet { min-height: calc(100vh - 160rpx); padding: 28rpx 26rpx 70rpx; background: #fff; border-radius: 22rpx 22rpx 0 0; box-sizing: border-box; }
.search { margin-top: 20rpx; }
.tabs { display: flex; margin-top: 20rpx; padding: 6rpx; background: #f5f5f5; border-radius: 14rpx; }
.tab { flex: 1; padding: 14rpx 0; color: $color-text-tertiary; font-size: 25rpx; text-align: center; border-radius: 10rpx; }
.tab.active { color: $color-text; background: #fff; font-weight: 600; }
.select-all { display: flex; align-items: center; justify-content: flex-end; gap: 10rpx; padding: 22rpx 4rpx 8rpx; color: $color-text-secondary; font-size: 23rpx; }
.course-row { display: flex; align-items: center; justify-content: space-between; gap: 18rpx; min-height: 104rpx; padding: 18rpx 4rpx; border-bottom: 1rpx solid #f1f1f1; box-sizing: border-box; }
.course-select { display: flex; flex: 1; align-items: center; gap: 16rpx; min-width: 0; }
.course-main { min-width: 0; }
.course-name-text, .course-type { display: block; }
.course-name-text { overflow: hidden; font-size: 27rpx; white-space: nowrap; text-overflow: ellipsis; }
.course-type { margin-top: 5rpx; color: $color-text-tertiary; font-size: 20rpx; }
.rule-input { display: flex; flex-shrink: 0; align-items: center; gap: 7rpx; color: $color-text-secondary; font-size: 22rpx; }
.rule-input input { width: 112rpx; height: 62rpx; padding: 0 10rpx; background: #f5f5f5; border-radius: 10rpx; text-align: right; box-sizing: border-box; }
.auto-label { padding: 10rpx 14rpx; color: #168d61; background: #e8f8f1; border-radius: 999rpx; font-size: 21rpx; }
.save-btn { width: 500rpx; height: 84rpx; margin: 48rpx auto 0; background: $color-brand-yellow; border-radius: 42rpx; color: $color-text; font-size: 30rpx; line-height: 84rpx; }
.save-btn::after { border: 0; }
</style>
