<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import {
  fetchCoachConfig,
  fetchSalesConfig,
  listPayrollCoaches,
  updateCoachConfig,
  updateSalesConfig,
} from "@/api/payroll";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  PayrollCoachConfig,
  PayrollCoachListItem,
  PayrollCoachMode,
  PayrollSalesConfig,
  PayrollSalesMode,
  PayrollSalesTier,
} from "@/types/payroll";

type ConfigTab = "coach" | "sales";
type TierKind = "newSale" | "renewal";

interface TierDraft {
  fromYuan: string;
  toYuan: string;
  ratePercent: string;
}

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const activeTab = ref<ConfigTab>("coach");
const coachConfig = ref<PayrollCoachConfig>({ enabled: false, mode: null });
const salesConfig = ref<PayrollSalesConfig>({
  enabled: false,
  mode: null,
  settings: { newSaleRatePercent: null, renewalRatePercent: null, newSaleTiers: [], renewalTiers: [] },
});
const coaches = ref<PayrollCoachListItem[]>([]);
const newSaleTierDrafts = ref<TierDraft[]>([]);
const renewalTierDrafts = ref<TierDraft[]>([]);

const canRead = computed(() => session.can("payroll.config.read"));
const canWrite = computed(() => session.can("payroll.config.write"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const tabs = [
  { key: "coach" as const, label: "教练工资" },
  { key: "sales" as const, label: "销售提成" },
];

const coachModeOptions: Array<{ value: PayrollCoachMode; label: string }> = [
  { value: "fixed_hours", label: "固定课时" },
  { value: "headcount", label: "按人数" },
  { value: "amount", label: "按金额" },
];

const salesModeOptions: Array<{ value: PayrollSalesMode; label: string }> = [
  { value: "flat_rate", label: "固定比例" },
  { value: "tiered", label: "阶梯比例" },
];

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "工资配置加载失败";
}

function centsToYuan(cents: number | null) {
  if (cents === null || cents === undefined) return "";
  return (cents / 100).toFixed(2).replace(/\.00$/, "");
}

function yuanToCents(value: string) {
  const parsed = Number.parseFloat(value);
  if (!Number.isFinite(parsed) || parsed < 0) return null;
  return Math.round(parsed * 100);
}

function tierToDraft(tier: PayrollSalesTier): TierDraft {
  return {
    fromYuan: centsToYuan(tier.fromAmountCents),
    toYuan: tier.toAmountCents === null ? "" : centsToYuan(tier.toAmountCents),
    ratePercent: String(tier.ratePercent),
  };
}

function createEmptyTierDraft(): TierDraft {
  return { fromYuan: "", toYuan: "", ratePercent: "" };
}

function hydrateTierDrafts(config: PayrollSalesConfig) {
  newSaleTierDrafts.value = config.settings.newSaleTiers.length
    ? config.settings.newSaleTiers.map(tierToDraft)
    : [createEmptyTierDraft()];
  renewalTierDrafts.value = config.settings.renewalTiers.length
    ? config.settings.renewalTiers.map(tierToDraft)
    : [createEmptyTierDraft()];
}

function draftsToTiers(drafts: TierDraft[]): PayrollSalesTier[] {
  return drafts.map((draft) => ({
    fromAmountCents: yuanToCents(draft.fromYuan) ?? 0,
    toAmountCents: draft.toYuan.trim() ? yuanToCents(draft.toYuan) : null,
    ratePercent: Number.parseInt(draft.ratePercent, 10) || 0,
  }));
}

function validateTiers(tiers: PayrollSalesTier[], label: string): string | null {
  if (!tiers.length) return `${label}至少需要一个阶梯`;

  const sorted = [...tiers].sort((a, b) => a.fromAmountCents - b.fromAmountCents);
  for (let index = 0; index < sorted.length; index += 1) {
    const tier = sorted[index];
    if (tier.ratePercent < 0 || tier.ratePercent > 100) {
      return `${label}第 ${index + 1} 档比例须在 0-100 之间`;
    }
    if (tier.toAmountCents !== null && tier.toAmountCents < tier.fromAmountCents) {
      return `${label}第 ${index + 1} 档上限不能小于下限`;
    }
    if (index > 0) {
      const previous = sorted[index - 1];
      const previousEnd = previous.toAmountCents ?? Number.MAX_SAFE_INTEGER;
      if (tier.fromAmountCents <= previousEnd) {
        return `${label}阶梯区间不能重叠`;
      }
    }
  }
  return null;
}

function validateTieredSettings(): string | null {
  const newSaleTiers = draftsToTiers(newSaleTierDrafts.value);
  const renewalTiers = draftsToTiers(renewalTierDrafts.value);
  return validateTiers(newSaleTiers, "新售") ?? validateTiers(renewalTiers, "续费");
}

function addTier(kind: TierKind) {
  if (!canWrite.value) return;
  const drafts = kind === "newSale" ? newSaleTierDrafts : renewalTierDrafts;
  drafts.value = [...drafts.value, createEmptyTierDraft()];
}

function removeTier(kind: TierKind, index: number) {
  if (!canWrite.value) return;
  const drafts = kind === "newSale" ? newSaleTierDrafts : renewalTierDrafts;
  if (drafts.value.length <= 1) {
    drafts.value = [createEmptyTierDraft()];
    return;
  }
  drafts.value = drafts.value.filter((_, draftIndex) => draftIndex !== index);
}

async function load() {
  if (!session.currentSiteId || !canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    const [coach, sales, coachList] = await Promise.all([
      fetchCoachConfig(session.currentSiteId),
      fetchSalesConfig(session.currentSiteId),
      listPayrollCoaches(session.currentSiteId),
    ]);
    coachConfig.value = coach;
    salesConfig.value = sales;
    hydrateTierDrafts(sales);
    coaches.value = coachList.items;
  } catch (error) {
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

async function saveCoachConfig() {
  if (!session.currentSiteId || !canWrite.value || saving.value) return;
  if (coachConfig.value.enabled && !coachConfig.value.mode) {
    uni.showToast({ title: "请选择教练工资计算方式", icon: "none" });
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    coachConfig.value = await updateCoachConfig(session.currentSiteId, coachConfig.value);
    uni.showToast({ title: "教练配置已保存", icon: "success" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function saveSalesConfig() {
  if (!session.currentSiteId || !canWrite.value || saving.value) return;
  if (salesConfig.value.enabled && !salesConfig.value.mode) {
    uni.showToast({ title: "请选择销售提成计算方式", icon: "none" });
    return;
  }
  if (salesConfig.value.enabled && salesConfig.value.mode === "tiered") {
    const validationError = validateTieredSettings();
    if (validationError) {
      uni.showToast({ title: validationError, icon: "none" });
      return;
    }
    salesConfig.value.settings.newSaleTiers = draftsToTiers(newSaleTierDrafts.value);
    salesConfig.value.settings.renewalTiers = draftsToTiers(renewalTierDrafts.value);
    salesConfig.value.settings.newSaleRatePercent = null;
    salesConfig.value.settings.renewalRatePercent = null;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    salesConfig.value = await updateSalesConfig(session.currentSiteId, salesConfig.value);
    hydrateTierDrafts(salesConfig.value);
    uni.showToast({ title: "销售配置已保存", icon: "success" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

function selectCoachMode(mode: PayrollCoachMode) {
  if (!canWrite.value) return;
  coachConfig.value.mode = mode;
}

function selectSalesMode(mode: PayrollSalesMode) {
  if (!canWrite.value) return;
  salesConfig.value.mode = mode;
  if (mode === "tiered") {
    hydrateTierDrafts(salesConfig.value);
  }
}

function toggleCoachEnabled(enabled: boolean) {
  if (!canWrite.value) return;
  coachConfig.value.enabled = enabled;
  if (!enabled) coachConfig.value.mode = null;
}

function toggleSalesEnabled(enabled: boolean) {
  if (!canWrite.value) return;
  salesConfig.value.enabled = enabled;
  if (!enabled) salesConfig.value.mode = null;
}

function openCoachRules(staffId: number) {
  uni.navigateTo({ url: `/pages/report/payroll/config/coach-rules?staffId=${staffId}` });
}

function coachStatusLabel(coach: PayrollCoachListItem) {
  return coach.rulesConfigured ? `已配置 v${coach.matrixVersion}` : "未配置";
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">工资配置</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canRead" mode="permission" text="暂无工资配置权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <view class="chip-row">
        <view
          v-for="tab in tabs"
          :key="tab.key"
          class="chip"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </view>
      </view>

      <view v-if="activeTab === 'coach'" class="panel">
        <view class="row">
          <text>启用教练工资</text>
          <u-switch :model-value="coachConfig.enabled" :disabled="!canWrite" @change="toggleCoachEnabled" />
        </view>

        <view v-if="coachConfig.enabled" class="section-title">计算方式</view>
        <view v-if="coachConfig.enabled" class="chip-row">
          <view
            v-for="option in coachModeOptions"
            :key="option.value"
            class="chip"
            :class="{ active: coachConfig.mode === option.value }"
            @click="selectCoachMode(option.value)"
          >
            {{ option.label }}
          </view>
        </view>

        <u-button
          v-if="canWrite"
          type="primary"
          size="small"
          class="save-btn"
          :loading="saving"
          @click="saveCoachConfig"
        >
          保存教练配置
        </u-button>

        <view class="section-title">教练规则</view>
        <u-empty v-if="!coaches.length" mode="list" text="暂无员工" />
        <view v-for="coach in coaches" :key="coach.staffId" class="coach-card" @click="openCoachRules(coach.staffId)">
          <view class="coach-row">
            <text class="coach-name">{{ coach.name }}</text>
            <u-tag :text="coachStatusLabel(coach)" size="mini" :type="coach.rulesConfigured ? 'success' : 'info'" />
          </view>
          <text class="coach-meta">{{ coach.employeeNo || `员工 #${coach.staffId}` }}</text>
        </view>
      </view>

      <view v-else class="panel">
        <view class="row">
          <text>启用销售提成</text>
          <u-switch :model-value="salesConfig.enabled" :disabled="!canWrite" @change="toggleSalesEnabled" />
        </view>

        <view v-if="salesConfig.enabled" class="section-title">计算方式</view>
        <view v-if="salesConfig.enabled" class="chip-row">
          <view
            v-for="option in salesModeOptions"
            :key="option.value"
            class="chip"
            :class="{ active: salesConfig.mode === option.value }"
            @click="selectSalesMode(option.value)"
          >
            {{ option.label }}
          </view>
        </view>

        <template v-if="salesConfig.enabled && salesConfig.mode === 'flat_rate'">
          <view class="field">
            <text class="field-label">新售提成 (%)</text>
            <u-input
              v-model="salesConfig.settings.newSaleRatePercent"
              type="number"
              placeholder="0-100"
              :disabled="!canWrite"
            />
          </view>
          <view class="field">
            <text class="field-label">续费提成 (%)</text>
            <u-input
              v-model="salesConfig.settings.renewalRatePercent"
              type="number"
              placeholder="0-100"
              :disabled="!canWrite"
            />
          </view>
        </template>

        <template v-if="salesConfig.enabled && salesConfig.mode === 'tiered'">
          <view class="section-title">新售阶梯</view>
          <view v-for="(tier, index) in newSaleTierDrafts" :key="`new-${index}`" class="tier-card">
            <view class="tier-header">
              <text class="tier-label">第 {{ index + 1 }} 档</text>
              <text v-if="canWrite" class="tier-action" @click="removeTier('newSale', index)">删除</text>
            </view>
            <view class="tier-fields">
              <view class="tier-field">
                <text class="field-label">下限 (元)</text>
                <u-input v-model="tier.fromYuan" type="digit" placeholder="0" :disabled="!canWrite" />
              </view>
              <view class="tier-field">
                <text class="field-label">上限 (元)</text>
                <u-input v-model="tier.toYuan" type="digit" placeholder="留空表示无上限" :disabled="!canWrite" />
              </view>
              <view class="tier-field">
                <text class="field-label">比例 (%)</text>
                <u-input v-model="tier.ratePercent" type="number" placeholder="0-100" :disabled="!canWrite" />
              </view>
            </view>
          </view>
          <u-button v-if="canWrite" size="mini" plain class="tier-add-btn" @click="addTier('newSale')">添加新售阶梯</u-button>

          <view class="section-title">续费阶梯</view>
          <view v-for="(tier, index) in renewalTierDrafts" :key="`renewal-${index}`" class="tier-card">
            <view class="tier-header">
              <text class="tier-label">第 {{ index + 1 }} 档</text>
              <text v-if="canWrite" class="tier-action" @click="removeTier('renewal', index)">删除</text>
            </view>
            <view class="tier-fields">
              <view class="tier-field">
                <text class="field-label">下限 (元)</text>
                <u-input v-model="tier.fromYuan" type="digit" placeholder="0" :disabled="!canWrite" />
              </view>
              <view class="tier-field">
                <text class="field-label">上限 (元)</text>
                <u-input v-model="tier.toYuan" type="digit" placeholder="留空表示无上限" :disabled="!canWrite" />
              </view>
              <view class="tier-field">
                <text class="field-label">比例 (%)</text>
                <u-input v-model="tier.ratePercent" type="number" placeholder="0-100" :disabled="!canWrite" />
              </view>
            </view>
          </view>
          <u-button v-if="canWrite" size="mini" plain class="tier-add-btn" @click="addTier('renewal')">添加续费阶梯</u-button>
          <text class="hint">金额区间为左闭右闭；各档区间不可重叠。</text>
        </template>

        <u-button
          v-if="canWrite"
          type="primary"
          size="small"
          class="save-btn"
          :loading="saving"
          @click="saveSalesConfig"
        >
          保存销售配置
        </u-button>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title,
.subtitle,
.section-title,
.coach-name,
.coach-meta,
.field-label,
.hint {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.coach-meta,
.hint {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
  margin-top: $spacing-md;
}

.chip {
  padding: $spacing-xs $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  font-size: 24rpx;
}

.chip.active {
  border-color: #1a73e8;
  color: #1a73e8;
  background: #eef4ff;
}

.panel {
  margin-top: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: $spacing-sm 0;
}

.section-title {
  margin-top: $spacing-lg;
  font-size: 28rpx;
  font-weight: 600;
}

.save-btn {
  margin-top: $spacing-md;
}

.coach-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.coach-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.coach-name {
  font-size: 28rpx;
  font-weight: 600;
}

.field {
  margin-top: $spacing-md;
}

.field-label {
  margin-bottom: $spacing-xs;
  font-size: 26rpx;
}

.tier-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.tier-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.tier-label {
  font-size: 26rpx;
  font-weight: 600;
}

.tier-action {
  color: #d93025;
  font-size: 24rpx;
}

.tier-fields {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
  margin-top: $spacing-sm;
}

.tier-field {
  width: 100%;
}

.tier-add-btn {
  margin-top: $spacing-sm;
}
</style>
