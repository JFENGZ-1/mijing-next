<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberCardBenefits } from "@/api/member";
import type { MemberCardBenefits, MemberCardWalletSummary } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { cardBalanceLabel } from "@/utils/format";

const cardId = ref(0);
const errorMessage = ref("");
const benefits = ref<MemberCardBenefits | null>(null);

const loading = ref(true);

interface CourseScopeItem {
  scopeKind?: string;
  scopeKey?: string;
  displayName?: string | null;
  priceOverride?: string | null;
}

const scopeItems = computed<CourseScopeItem[]>(() => {
  const scopes = benefits.value?.courseScopes;
  return Array.isArray(scopes) ? (scopes as CourseScopeItem[]) : [];
});

function scopeLabel(scope: CourseScopeItem) {
  return scope.displayName || scope.scopeKey || "适用课程";
}

// 数值型规则 → 宫格大数字展示
const numericRuleDefs: Record<string, { label: string; unit?: string; money?: boolean }> = {
  defaultPrice: { label: "单次扣费", money: true },
  advanceDays: { label: "可提前预约", unit: "天" },
  advanceHours: { label: "可提前预约", unit: "小时" },
  cancelCutoffHours: { label: "取消截止", unit: "小时前" },
  bookCutoffMinutes: { label: "预约截止", unit: "分钟前" },
  dailyLimit: { label: "每日可约", unit: "次" },
  weeklyLimit: { label: "每周可约", unit: "次" },
  maxActive: { label: "同时预约", unit: "节" },
};

// 布尔型规则 → 勾选行展示
const boolRuleDefs: Record<string, string> = {
  allowWaitlist: "支持排队候补",
  penaltyEnabled: "旷课违约扣费",
};

interface NumericRule {
  key: string;
  label: string;
  value: string;
  unit: string;
}

const numericRules = computed<NumericRule[]>(() => {
  const rules = benefits.value?.bookingRules;
  if (!rules || typeof rules !== "object") return [];
  const items: NumericRule[] = [];
  for (const [key, raw] of Object.entries(rules as Record<string, unknown>)) {
    const def = numericRuleDefs[key];
    if (!def || raw === null || raw === undefined || typeof raw === "object" || typeof raw === "boolean") continue;
    items.push({
      key,
      label: def.label,
      value: def.money ? `¥${raw}` : String(raw),
      unit: def.unit ?? "",
    });
  }
  return items;
});

const boolRules = computed(() => {
  const rules = benefits.value?.bookingRules;
  if (!rules || typeof rules !== "object") return [] as { label: string; enabled: boolean }[];
  const items: { label: string; enabled: boolean }[] = [];
  for (const [key, raw] of Object.entries(rules as Record<string, unknown>)) {
    const label = boolRuleDefs[key];
    if (!label || typeof raw !== "boolean") continue;
    items.push({ label, enabled: raw });
  }
  return items;
});

// 多店通用（scopeConfig.linkedSiteIds）
const linkedSiteCount = computed(() => {
  const config = benefits.value?.scopeConfig as { linkedSiteIds?: unknown } | null | undefined;
  const ids = config?.linkedSiteIds;
  return Array.isArray(ids) ? ids.length : 0;
});

const pseudoCard = computed<MemberCardWalletSummary | null>(() => {
  if (!benefits.value) return null;
  const b = benefits.value;
  return {
    id: b.memberCardId,
    siteId: 0,
    cardType: b.cardType,
    status: "active",
    cardNoMasked: "",
    name: b.name,
    balance: b.entitlements.cachedBalance,
    remainingCount: b.entitlements.cachedRemainingCount,
    validFrom: b.entitlements.validFrom,
    validUntil: b.entitlements.validUntil,
  };
});

const balanceText = computed(() => {
  if (!benefits.value) return "";
  return cardBalanceLabel({
    cardType: benefits.value.cardType,
    balance: benefits.value.entitlements.cachedBalance,
    remainingCount: benefits.value.entitlements.cachedRemainingCount,
  });
});

const validUntilText = computed(() => benefits.value?.entitlements.validUntil || "");

async function loadBenefits(refresh = false) {
  errorMessage.value = "";
  if (refresh) {
    benefits.value = null;
  }

  try {
    const response = await getMemberCardBenefits(cardId.value);
    benefits.value = response.data;
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "权益信息加载失败");
  } finally {
    loading.value = false;
  }
}

onLoad((query) => {
  cardId.value = Number(query?.id ?? 0);
});

onShow(async () => { if (await requireMemberAuth()) await loadBenefits(); });

onPullDownRefresh(async () => { await loadBenefits(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="benefits-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <template v-if="benefits">
      <view v-if="pseudoCard" class="card-block">
        <member-card :card="pseudoCard" />
      </view>

      <!-- 权益概览：紧贴卡面的两列数据 -->
      <view class="stat-strip">
        <view class="stat-item">
          <view class="stat-value">{{ balanceText || "—" }}</view>
          <view class="stat-label">当前权益</view>
        </view>
        <view class="stat-divider" />
        <view class="stat-item">
          <view class="stat-value">{{ validUntilText || "长期有效" }}</view>
          <view class="stat-label">有效期至</view>
        </view>
      </view>

      <view v-if="linkedSiteCount > 0" class="union-banner">
        <u-icon name="home" size="16" color="#9f6e29" />
        <text>多店通用 · 可在 {{ linkedSiteCount }} 家场馆使用</text>
      </view>

      <!-- 适用课程 -->
      <view class="section-card">
        <view class="section-title">
          <view class="title-bar" />
          <text>适用课程</text>
          <text v-if="scopeItems.length" class="title-count">{{ scopeItems.length }} 门</text>
        </view>

        <template v-if="scopeItems.length">
          <view v-for="(scope, index) in scopeItems" :key="index" class="course-row">
            <view class="course-dot" />
            <view class="course-name">{{ scopeLabel(scope) }}</view>
            <view v-if="scope.priceOverride" class="course-price">¥{{ scope.priceOverride }}/次</view>
          </view>
        </template>
        <view v-else class="course-all">
          <u-icon name="checkmark-circle" size="18" color="#22c788" />
          <text>本卡通用于全部课程</text>
        </view>
      </view>

      <!-- 预约规则 -->
      <view v-if="numericRules.length || boolRules.length" class="section-card">
        <view class="section-title">
          <view class="title-bar" />
          <text>预约规则</text>
        </view>

        <view v-if="numericRules.length" class="rule-grid">
          <view v-for="rule in numericRules" :key="rule.key" class="rule-cell">
            <view class="rule-value">
              {{ rule.value }}<text v-if="rule.unit" class="rule-unit">{{ rule.unit }}</text>
            </view>
            <view class="rule-label">{{ rule.label }}</view>
          </view>
        </view>

        <view v-if="boolRules.length" class="bool-list" :class="{ 'bool-list--bordered': numericRules.length }">
          <view v-for="rule in boolRules" :key="rule.label" class="bool-row">
            <u-icon
              :name="rule.enabled ? 'checkmark-circle-fill' : 'minus-circle'"
              size="18"
              :color="rule.enabled ? '#22c788' : '#bfbfbf'"
            />
            <text class="bool-text" :class="{ 'bool-text--off': !rule.enabled }">{{ rule.label }}</text>
            <text class="bool-state">{{ rule.enabled ? "已开启" : "未开启" }}</text>
          </view>
        </view>
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.benefits-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 40rpx;
}

.card-block {
  margin-bottom: 20rpx;
}

/* 权益概览数据条 */
.stat-strip {
  display: flex;
  align-items: center;
  margin-bottom: 20rpx;
  padding: 30rpx 0;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
}

.stat-item {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-items: center;
  min-width: 0;
}

.stat-value {
  max-width: 100%;
  color: $color-text;
  font-size: 34rpx;
  font-weight: 700;
  line-height: 42rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.stat-label {
  margin-top: 8rpx;
  color: $color-text-muted;
  font-size: 22rpx;
}

.stat-divider {
  width: 1rpx;
  height: 52rpx;
  background: $color-border;
}

/* 多店通用（呼应原版金色缎带） */
.union-banner {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-bottom: 20rpx;
  padding: 18rpx 24rpx;
  background: linear-gradient(-57deg, #fef7e7, #f1e4c4);
  border-radius: 16rpx;
  color: #9f6e29;
  font-size: 24rpx;
  font-weight: 500;
}

.section-card {
  margin-bottom: 20rpx;
  padding: 28rpx 28rpx 24rpx;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
}

/* 主题绿竖条标题 */
.section-title {
  display: flex;
  align-items: center;
  margin-bottom: 24rpx;
  color: $color-text;
  font-size: 30rpx;
  font-weight: 600;
}

.title-bar {
  width: 8rpx;
  height: 30rpx;
  margin-right: 14rpx;
  background: $color-primary;
  border-radius: 4rpx;
}

.title-count {
  margin-left: auto;
  color: $color-text-muted;
  font-size: 24rpx;
  font-weight: 400;
}

/* 适用课程行 */
.course-row {
  display: flex;
  align-items: center;
  padding: 20rpx 0;

  & + .course-row {
    border-top: 1rpx solid $color-border;
  }
}

.course-dot {
  flex-shrink: 0;
  width: 12rpx;
  height: 12rpx;
  margin-right: 18rpx;
  background: $color-primary;
  border-radius: 50%;
  opacity: 0.75;
}

.course-name {
  flex: 1;
  min-width: 0;
  color: $color-text;
  font-size: 28rpx;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.course-price {
  flex-shrink: 0;
  margin-left: 16rpx;
  padding: 4rpx 16rpx;
  background: rgba(220, 60, 92, 0.08);
  border-radius: 20rpx;
  color: $color-accent-pink;
  font-size: 22rpx;
  font-weight: 500;
}

.course-all {
  display: flex;
  align-items: center;
  gap: 10rpx;
  padding: 8rpx 0 12rpx;
  color: $color-text-secondary;
  font-size: 26rpx;
}

/* 数值规则宫格 */
.rule-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20rpx 12rpx;
}

.rule-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 22rpx 8rpx;
  background: $color-page;
  border-radius: 16rpx;
}

.rule-value {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 700;
  line-height: 40rpx;
}

.rule-unit {
  margin-left: 4rpx;
  color: $color-text-secondary;
  font-size: 20rpx;
  font-weight: 400;
}

.rule-label {
  margin-top: 10rpx;
  color: $color-text-muted;
  font-size: 22rpx;
}

/* 布尔规则行 */
.bool-list--bordered {
  margin-top: 24rpx;
  padding-top: 8rpx;
  border-top: 1rpx solid $color-border;
}

.bool-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
  padding: 18rpx 0;
}

.bool-text {
  flex: 1;
  color: $color-text;
  font-size: 27rpx;
}

.bool-text--off {
  color: $color-text-muted;
}

.bool-state {
  color: $color-text-placeholder;
  font-size: 22rpx;
}
</style>
