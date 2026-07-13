<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberCardBenefits } from "@/api/member";
import type { MemberCardBenefits } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { cardBalanceLabel, cardTypeLabel, cardValidityLabel } from "@/utils/format";

const cardId = ref(0);
const errorMessage = ref("");
const benefits = ref<MemberCardBenefits | null>(null);

const loading = ref(true);

function formatScope(scope: unknown) {
  if (!scope) return "";
  if (typeof scope === "string") return scope;
  try {
    return JSON.stringify(scope, null, 2);
  } catch {
    return String(scope);
  }
}

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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="benefits">
      <view class="summary-card">
        <view class="card-name">{{ benefits.name || "会员卡权益" }}</view>
        <view class="card-meta">{{ cardTypeLabel(benefits.cardType) }}</view>
        <view
          v-if="cardBalanceLabel({
            cardType: benefits.cardType,
            balance: benefits.entitlements.cachedBalance,
            remainingCount: benefits.entitlements.cachedRemainingCount,
          })"
          class="card-balance"
        >
          {{
            cardBalanceLabel({
              cardType: benefits.cardType,
              balance: benefits.entitlements.cachedBalance,
              remainingCount: benefits.entitlements.cachedRemainingCount,
            })
          }}
        </view>
        <view
          v-if="cardValidityLabel(benefits.entitlements.validFrom, benefits.entitlements.validUntil)"
          class="card-meta"
        >
          {{ cardValidityLabel(benefits.entitlements.validFrom, benefits.entitlements.validUntil) }}
        </view>
      </view>

      <view v-if="benefits.courseScopes?.length" class="section-card">
        <view class="section-title">适用课程</view>
        <view v-for="(scope, index) in benefits.courseScopes" :key="index" class="scope-item">
          {{ formatScope(scope) }}
        </view>
      </view>

      <view v-if="benefits.bookingRules" class="section-card">
        <view class="section-title">预约规则</view>
        <view class="section-text">{{ formatScope(benefits.bookingRules) }}</view>
      </view>

      <view v-if="benefits.scopeConfig" class="section-card">
        <view class="section-title">适用范围</view>
        <view class="section-text">{{ formatScope(benefits.scopeConfig) }}</view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.summary-card,
.section-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.card-name {
  font-size: 34rpx;
  font-weight: 600;
}

.card-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-balance {
  margin-top: $spacing-sm;
  font-size: 36rpx;
  font-weight: 600;
}

.scope-item,
.section-text {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.6;
  white-space: pre-wrap;
}
</style>
