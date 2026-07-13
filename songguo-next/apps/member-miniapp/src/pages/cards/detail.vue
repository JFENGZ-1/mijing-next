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
  cardBalanceLabel,
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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="card">
      <view class="wallet-card">
        <view class="wallet-card-name">{{ card.name || "会员卡" }}</view>
        <view class="wallet-card-meta">{{ card.cardNoMasked }}</view>
        <view class="wallet-card-meta">{{ cardBalanceLabel(card) }}</view>
        <view class="wallet-card-meta">{{ cardValidityLabel(card.validFrom, card.validUntil) }} · {{ memberCardStatusLabel(card.status) }}</view>
      </view>
      <view class="summary-actions">
          <u-button v-if="canActivate" type="primary" size="small" :loading="activating" @click="activateCurrentCard">
            激活
          </u-button>
          <u-button v-if="canHide" plain size="small" :loading="hiding" @click="confirmHide">隐藏此卡</u-button>
          <u-button plain size="small" @click="openBenefits">查看权益</u-button>
      </view>

      <view class="section-title">变动记录</view>
      <u-empty v-if="ledgerItems.length === 0" mode="list" text="暂无变动记录" />
      <view v-for="item in ledgerItems" :key="item.id" class="ledger-card">
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
      </view>

      <u-button
        v-if="ledgerPage < ledgerLastPage"
        plain
        :loading="ledgerLoading"
        @click="loadMoreLedger"
      >
        加载更多
      </u-button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.wallet-card {
  margin-bottom: $spacing-md;
  padding: 24rpx;
  background: linear-gradient(135deg, #faf5f8 0%, #fff 100%);
  border: 1rpx solid #f3e8ee;
  border-radius: $radius-md;
}

.wallet-card-name {
  color: $color-accent-pink;
  font-size: 30rpx;
  font-weight: 600;
}

.wallet-card-meta {
  margin-top: 8rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.summary-actions {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
  margin: $spacing-md 0;
}

.ledger-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.ledger-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.ledger-title {
  font-size: 30rpx;
  font-weight: 600;
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
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}
</style>
