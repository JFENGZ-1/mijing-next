<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import {
  activateMemberCard,
  getMemberCardLedgerEntries,
  getMemberWalletCard,
  hideMemberCard,
} from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberCardLedgerEntry, MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import {
  cardValidityLabel,
  formatIsoDate,
  memberCardStatusLabel,
} from "@/utils/format";

const cardId = ref(0);
const loading = ref(true);
const ledgerLoading = ref(false);
const hiding = ref(false);
const activating = ref(false);
const errorMessage = ref("");
const card = ref<MemberCardWalletSummary | null>(null);
const ledgerItems = ref<MemberCardLedgerEntry[]>([]);
const ledgerPage = ref(1);
const ledgerLastPage = ref(1);
let hideCommandKey = "";
let activateCommandKey = "";

const canActivate = computed(() => card.value?.status === "pending_activation");
const canHide = computed(() => card.value?.status === "active" || card.value?.status === "frozen");

async function loadCard() {
  const tenant = await ensureMemberTenant();
  if (!tenant) {
    errorMessage.value = "请先选择场馆";
    return;
  }

  const response = await getMemberWalletCard(tenant.tenantId, cardId.value);
  card.value = response.data;
  if (!card.value) {
    errorMessage.value = "会员卡不存在或已隐藏";
  }
}

async function loadLedger(reset = true) {
  if (reset) {
    ledgerPage.value = 1;
    ledgerItems.value = [];
    ledgerLastPage.value = 1;
  } else {
    ledgerLoading.value = true;
  }

  const response = await getMemberCardLedgerEntries(cardId.value, ledgerPage.value);
  if (reset) {
    ledgerItems.value = response.data.items;
  } else {
    ledgerItems.value = [...ledgerItems.value, ...response.data.items];
  }
  ledgerLastPage.value = response.data.pagination.lastPage;
  ledgerLoading.value = false;
}

async function loadDetail() {
  loading.value = true;
  errorMessage.value = "";
  card.value = null;
  ledgerItems.value = [];

  try {
    await loadCard();
    if (card.value) {
      await loadLedger();
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员卡详情加载失败";
  } finally {
    loading.value = false;
  }
}

async function loadMoreLedger() {
  if (ledgerLoading.value || ledgerPage.value >= ledgerLastPage.value) return;
  ledgerPage.value += 1;
  ledgerLoading.value = true;
  try {
    await loadLedger(false);
  } catch (error) {
    ledgerPage.value -= 1;
    uni.showToast({
      title: error instanceof Error ? error.message : "加载更多失败",
      icon: "none",
    });
  } finally {
    ledgerLoading.value = false;
  }
}

async function activateCurrentCard() {
  if (!card.value) return;

  if (!activateCommandKey) {
    activateCommandKey = createCommandKey();
  }

  activating.value = true;
  try {
    await activateMemberCard(card.value.id, activateCommandKey);
    activateCommandKey = "";
    uni.showToast({ title: "激活成功", icon: "success" });
    await loadCard();
    if (card.value) {
      await loadLedger();
    }
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "激活失败",
      icon: "none",
    });
  } finally {
    activating.value = false;
  }
}

function openBenefits() {
  uni.navigateTo({ url: `/pages/cards/benefits?id=${cardId.value}` });
}

function confirmHide() {
  if (!card.value) return;
  uni.showModal({
    title: "隐藏会员卡",
    content: `确定隐藏「${card.value.name || "会员卡"}」吗？`,
    success: async (result) => {
      if (!result.confirm) return;
      await hideCurrentCard();
    },
  });
}

async function hideCurrentCard() {
  const tenant = await ensureMemberTenant();
  if (!tenant || !card.value) return;

  if (!hideCommandKey) {
    hideCommandKey = createCommandKey();
  }

  hiding.value = true;
  try {
    await hideMemberCard(tenant.tenantId, card.value.id, hideCommandKey);
    hideCommandKey = "";
    uni.showToast({ title: "已隐藏", icon: "success" });
    setTimeout(() => uni.navigateBack(), 500);
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "隐藏失败",
      icon: "none",
    });
  } finally {
    hiding.value = false;
  }
}

onLoad((query) => {
  cardId.value = Number(query?.id ?? 0);
});

onShow(async () => {
  if (!cardId.value) {
    errorMessage.value = "缺少会员卡 ID";
    loading.value = false;
    return;
  }
  if (await requireMemberAuth()) await loadDetail();
});

onPullDownRefresh(async () => {
  await loadDetail();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <template v-if="card">
      <view class="card-block">
        <member-card :card="card" />
      </view>

      <view class="meta-card">
        <view class="meta-row">
          <text class="meta-label">卡号</text>
          <text class="meta-value">{{ card.cardNoMasked }}</text>
        </view>
        <u-line color="#F0F0F0" />
        <view class="meta-row">
          <text class="meta-label">有效期</text>
          <text class="meta-value">{{ cardValidityLabel(card.validFrom, card.validUntil) || "—" }}</text>
        </view>
        <u-line color="#F0F0F0" />
        <view class="meta-row">
          <text class="meta-label">状态</text>
          <text class="meta-value">{{ memberCardStatusLabel(card.status) }}</text>
        </view>
      </view>

      <view class="summary-actions">
        <view
          v-if="canActivate"
          class="btn-primary"
          :class="{ 'btn-primary--disabled': activating }"
          @tap="activateCurrentCard"
        >
          {{ activating ? "激活中..." : "激活" }}
        </view>
        <view v-else class="btn-primary" @tap="openBenefits">查看权益</view>

        <view class="btn-links">
          <text v-if="canActivate" class="link" @tap="openBenefits">查看权益</text>
          <text v-if="canActivate && canHide" class="link-divider">|</text>
          <text v-if="canHide" class="link" @tap="confirmHide">{{ hiding ? "隐藏中..." : "隐藏此卡" }}</text>
        </view>
      </view>

      <view class="section-title">变动记录</view>
      <u-empty v-if="ledgerItems.length === 0" mode="list" text="暂无变动记录" />
      <view v-else class="ledger-list">
        <view v-for="(item, index) in ledgerItems" :key="item.id" class="ledger-item">
          <view class="ledger-header">
            <view class="ledger-title">{{ item.summary }}</view>
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
          <view class="ledger-meta">{{ formatIsoDate(item.occurredAt) }}</view>
          <u-line v-if="index < ledgerItems.length - 1" color="#F0F0F0" />
        </view>
      </view>

      <view v-if="ledgerPage < ledgerLastPage" class="loadmore-wrap">
        <u-loadmore
          :status="ledgerLoading ? 'loading' : 'loadmore'"
          loadmore-text="加载更多"
          @loadmore="loadMoreLedger"
        />
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.detail-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.card-block {
  margin-bottom: 24rpx;
}

.meta-card {
  padding: 0 24rpx;
  background: $color-surface;
  border-radius: $radius-md;
}

.meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 0;
}

.meta-label {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.meta-value {
  color: $color-text;
  font-size: 26rpx;
}

.summary-actions {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24rpx;
  margin: 32rpx 0;
}

.btn-primary {
  width: 100%;
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #07c160;
  border-radius: 88rpx;
  color: #fff;
  font-size: 32rpx;
  font-weight: 500;
}

.btn-primary--disabled {
  opacity: 0.6;
}

.btn-links {
  display: flex;
  align-items: center;
  gap: 24rpx;
}

.link {
  color: #576b95;
  font-size: 28rpx;
}

.link-divider {
  color: #d0d0d0;
  font-size: 24rpx;
}

.section-title {
  margin: 8rpx 0 16rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 600;
}

.ledger-list {
  background: $color-surface;
  border-radius: $radius-md;
  padding: 0 24rpx;
}

.ledger-item {
  padding: 20rpx 0;
}

.ledger-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
}

.ledger-title {
  font-size: 28rpx;
  font-weight: 500;
}

.ledger-amount {
  font-size: 28rpx;
  font-weight: 600;
}

.amount-credit {
  color: $color-primary-dark;
}

.amount-debit {
  color: $color-accent-pink;
}

.ledger-meta {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.loadmore-wrap {
  padding: 16rpx 0;
}
</style>
