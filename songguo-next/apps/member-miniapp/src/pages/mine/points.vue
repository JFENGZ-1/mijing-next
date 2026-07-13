<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberPointsLedger } from "@/api/member";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberPointLedger, MemberPointLedgerEntry } from "@/types/member";
import { formatIsoDate } from "@/utils/format";

const loadingMore = ref(false);
const errorMessage = ref("");
const ledger = ref<MemberPointLedger | null>(null);
const page = ref(1);

const loading = ref(true);

function entryAmount(item: MemberPointLedgerEntry) {
  const prefix = item.direction === "credit" ? "+" : "-";
  return `${prefix}${Math.abs(item.amountDelta)}`;
}

function entryAmountClass(item: MemberPointLedgerEntry) {
  return item.direction === "credit" ? "amount-credit" : "amount-debit";
}

async function load() {
  loading.value = true;
  try {
    await loadLedger();
  } finally {
    loading.value = false;
  }
}

async function loadLedger(reset = true) {
  if (!reset) {
    loadingMore.value = true;
  } else {
    page.value = 1;
    ledger.value = null;
  }
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberPointsLedger(tenant.tenantId, page.value);
    if (reset || !ledger.value) {
      ledger.value = response.data;
    } else {
      ledger.value = {
        ...response.data,
        items: [...ledger.value.items, ...response.data.items],
      };
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "积分明细加载失败";
  } finally {
    loadingMore.value = false;
  }
}

async function loadMore() {
  if (!ledger.value || loadingMore.value) return;
  if (page.value >= ledger.value.pagination.lastPage) return;
  page.value += 1;
  await loadLedger(false);
}

onShow(async () => { if (await requireMemberAuth()) await load(); });

onPullDownRefresh(async () => { await load(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="ledger">
      <view class="summary-card">
        <view class="summary-total">当前积分 {{ ledger.totalPoint }}</view>
        <view v-if="ledger.displayName" class="summary-meta">{{ ledger.displayName }}</view>
      </view>

      <view v-if="ledger.descriptionText" class="description-card">
        <rich-text :nodes="ledger.descriptionText" />
      </view>

      <view class="section-title">积分明细</view>
      <u-empty v-if="ledger.items.length === 0" mode="list" text="暂无积分记录" />
      <view v-for="item in ledger.items" :key="item.id" class="ledger-card">
        <view class="ledger-header">
          <view class="ledger-title">{{ item.title || item.reason }}</view>
          <view class="ledger-amount" :class="entryAmountClass(item)">{{ entryAmount(item) }}</view>
        </view>
        <view class="ledger-meta">{{ item.reason }}</view>
        <view v-if="item.createdAt" class="ledger-meta">{{ formatIsoDate(item.createdAt) }}</view>
      </view>

      <u-button
        v-if="ledger.pagination.page < ledger.pagination.lastPage"
        plain
        :loading="loadingMore"
        @click="loadMore"
      >
        加载更多
      </u-button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.summary-card,
.description-card {
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.summary-total {
  font-size: 36rpx;
  font-weight: 600;
}

.summary-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
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
  font-size: 30rpx;
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
