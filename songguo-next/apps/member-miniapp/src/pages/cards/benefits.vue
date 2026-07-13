<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberCardBenefits } from "@/api/member";
import type { MemberCardBenefits, MemberCardWalletSummary } from "@/types/member";
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

const validityText = computed(() => {
  if (!benefits.value) return "";
  return cardValidityLabel(benefits.value.entitlements.validFrom, benefits.value.entitlements.validUntil);
});

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

      <view class="summary-card">
        <view class="card-name">{{ benefits.name || "会员卡权益" }}</view>
        <view class="card-meta">{{ cardTypeLabel(benefits.cardType) }}</view>
        <view v-if="balanceText" class="card-balance">{{ balanceText }}</view>
        <view v-if="validityText" class="card-meta">{{ validityText }}</view>
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

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.benefits-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.card-block {
  margin-bottom: 24rpx;
}

.summary-card,
.section-card {
  margin-bottom: 20rpx;
  padding: 24rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.card-name {
  font-size: 34rpx;
  font-weight: 600;
}

.card-meta {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-balance {
  margin-top: 12rpx;
  font-size: 36rpx;
  font-weight: 600;
}

.section-title {
  margin-bottom: 12rpx;
  color: $color-text;
  font-size: 30rpx;
  font-weight: 600;
}

.scope-item,
.section-text {
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.6;
  white-space: pre-wrap;
}

.scope-item + .scope-item,
.section-text {
  margin-top: 8rpx;
}
</style>
