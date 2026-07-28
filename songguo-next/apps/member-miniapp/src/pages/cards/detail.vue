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
  // 仅首次显示全屏加载，返回本页时静默刷新
  loading.value = !card.value;
  errorMessage.value = "";

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

      <!-- 待激活：主操作按钮置顶 -->
      <view
        v-if="canActivate"
        class="btn-activate"
        :class="{ 'btn-activate--disabled': activating }"
        @tap="activateCurrentCard"
      >
        {{ activating ? "激活中…" : "立即激活" }}
      </view>

      <!-- 快捷操作卡 -->
      <view class="action-row">
        <view class="action-cell" @tap="openBenefits">
          <view class="action-icon action-icon--green">
            <u-icon name="gift" size="22" color="#22c788" />
          </view>
          <text>查看权益</text>
        </view>
        <view v-if="canHide" class="action-cell" @tap="confirmHide">
          <view class="action-icon action-icon--grey">
            <u-icon name="eye-off" size="22" color="#696b99" />
          </view>
          <text>{{ hiding ? "隐藏中…" : "隐藏此卡" }}</text>
        </view>
      </view>

      <!-- 卡片信息 -->
      <view class="meta-card">
        <view class="meta-row">
          <text class="meta-label">卡号</text>
          <text class="meta-value">{{ card.cardNoMasked }}</text>
        </view>
        <view class="meta-row">
          <text class="meta-label">有效期</text>
          <text class="meta-value">{{ cardValidityLabel(card.validFrom, card.validUntil) || "长期有效" }}</text>
        </view>
        <view class="meta-row">
          <text class="meta-label">状态</text>
          <text class="status-pill" :class="`status-pill--${card.status}`">
            {{ memberCardStatusLabel(card.status) }}
          </text>
        </view>
      </view>

      <!-- 变动记录 -->
      <view class="ledger-card">
        <view class="section-title">
          <view class="title-bar" />
          <text>变动记录</text>
        </view>

        <u-empty v-if="ledgerItems.length === 0" mode="list" text="暂无变动记录" margin-top="20" />
        <template v-else>
          <view v-for="item in ledgerItems" :key="item.id" class="ledger-row">
            <view class="ledger-icon" :class="item.direction === 'credit' ? 'ledger-icon--credit' : 'ledger-icon--debit'">
              <u-icon
                :name="item.direction === 'credit' ? 'plus' : 'minus'"
                size="16"
                :color="item.direction === 'credit' ? '#22c788' : '#dc3c5c'"
              />
            </view>
            <view class="ledger-main">
              <view class="ledger-title">{{ item.summary }}</view>
              <view class="ledger-meta">{{ formatIsoDate(item.occurredAt) }}</view>
            </view>
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

          <view v-if="ledgerPage < ledgerLastPage" class="loadmore-wrap">
            <u-loadmore
              :status="ledgerLoading ? 'loading' : 'loadmore'"
              loadmore-text="加载更多"
              @loadmore="loadMoreLedger"
            />
          </view>
        </template>
      </view>

      <bottom-logo />
    </template>
  </view>
</template>

<style scoped lang="scss">
.detail-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 40rpx;
}

.card-block {
  margin-bottom: 20rpx;
}

/* 待激活主按钮 */
.btn-activate {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 88rpx;
  margin-bottom: 20rpx;
  background: $color-primary;
  border-radius: 44rpx;
  box-shadow: 0 10rpx 24rpx rgba(34, 199, 136, 0.25);
  color: #fff;
  font-size: 30rpx;
  font-weight: 500;
}

.btn-activate--disabled {
  opacity: 0.6;
}

/* 快捷操作卡 */
.action-row {
  display: flex;
  gap: 20rpx;
  margin-bottom: 20rpx;
}

.action-cell {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  gap: 14rpx;
  height: 104rpx;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
  color: $color-text;
  font-size: 27rpx;
  font-weight: 500;

  &:active {
    opacity: 0.75;
  }
}

.action-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 56rpx;
  height: 56rpx;
  border-radius: 16rpx;
}

.action-icon--green {
  background: rgba(34, 199, 136, 0.1);
}

.action-icon--grey {
  background: rgba(105, 107, 153, 0.1);
}

/* 卡片信息 */
.meta-card {
  margin-bottom: 20rpx;
  padding: 8rpx 28rpx;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
}

.meta-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 26rpx 0;

  & + .meta-row {
    border-top: 1rpx solid $color-border;
  }
}

.meta-label {
  color: $color-text-muted;
  font-size: 26rpx;
}

.meta-value {
  color: $color-text;
  font-size: 26rpx;
}

/* 状态胶囊 */
.status-pill {
  padding: 4rpx 18rpx;
  border-radius: 20rpx;
  font-size: 22rpx;
  background: $color-page;
  color: $color-text-muted;
}

.status-pill--active {
  background: rgba(34, 199, 136, 0.1);
  color: $color-primary;
}

.status-pill--pending_activation {
  background: rgba(255, 174, 0, 0.12);
  color: #d98f00;
}

.status-pill--frozen {
  background: rgba(0, 151, 238, 0.1);
  color: #0097ee;
}

/* 变动记录 */
.ledger-card {
  padding: 28rpx 28rpx 12rpx;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
}

.section-title {
  display: flex;
  align-items: center;
  margin-bottom: 8rpx;
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

.ledger-row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 24rpx 0;

  & + .ledger-row {
    border-top: 1rpx solid $color-border;
  }
}

.ledger-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 60rpx;
  height: 60rpx;
  border-radius: 50%;
}

.ledger-icon--credit {
  background: rgba(34, 199, 136, 0.1);
}

.ledger-icon--debit {
  background: rgba(220, 60, 92, 0.08);
}

.ledger-main {
  flex: 1;
  min-width: 0;
}

.ledger-title {
  color: $color-text;
  font-size: 28rpx;
  font-weight: 500;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.ledger-meta {
  margin-top: 6rpx;
  color: $color-text-muted;
  font-size: 22rpx;
}

.ledger-amount {
  flex-shrink: 0;
  font-size: 30rpx;
  font-weight: 700;
}

.amount-credit {
  color: $color-primary;
}

.amount-debit {
  color: $color-accent-pink;
}

.loadmore-wrap {
  padding: 12rpx 0;
}
</style>
