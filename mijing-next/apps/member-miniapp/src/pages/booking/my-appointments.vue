<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import {
  getMemberAppointments,
  getMemberCardLedgerEntries,
  getMemberWalletCards,
  promoteMemberAppointment,
} from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberAppointmentSummary, MemberCardLedgerEntry, MemberCardWalletSummary } from "@/types/member";
import { formatIsoDate } from "@/utils/format";
import { createCommandKey } from "@/utils/command-key";
import { navigateToOnce } from "@/utils/navigate";

const loading = ref(true);
const hasLoaded = ref(false);
const errorMessage = ref("");

// 对标原版 useRecord：顶部当前会员卡 + 预约记录/余额核对 tabs
const tabCurrent = ref(0);
const tabs = [{ name: "预约记录" }, { name: "余额核对" }];

const requestedCardId = ref(0);
const currentCard = ref<MemberCardWalletSummary | null>(null);

const appointments = ref<MemberAppointmentSummary[]>([]);
const promotingId = ref<number | null>(null);
const promoteCommandKeys = new Map<number, string>();

const ledgerItems = ref<MemberCardLedgerEntry[]>([]);
const ledgerPage = ref(1);
const ledgerLastPage = ref(1);
const ledgerLoading = ref(false);

function sortTime(item: MemberAppointmentSummary) {
  const iso = item.startsAt || item.bookedAt;
  const t = iso ? new Date(iso).getTime() : 0;
  return Number.isNaN(t) ? 0 : t;
}

async function loadAll() {
  loading.value = !hasLoaded.value;
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    // 当前会员卡（指定 cardId 或默认第一张）
    const cardsPromise = getMemberWalletCards(tenant.tenantId);

    // 预约记录：合并待上课 + 历史，按时间倒序（对标原版全部记录列表）
    const [upcoming, past, cardsResponse] = await Promise.all([
      getMemberAppointments(tenant.tenantId, "upcoming"),
      getMemberAppointments(tenant.tenantId, "past"),
      cardsPromise,
    ]);
    appointments.value = [...upcoming.data.items, ...past.data.items].sort(
      (a, b) => sortTime(b) - sortTime(a),
    );

    const cards = cardsResponse.data;
    currentCard.value =
      cards.find((card) => card.id === requestedCardId.value) ?? cards[0] ?? null;

    if (currentCard.value) {
      await loadLedger(true);
    } else {
      ledgerItems.value = [];
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "使用记录加载失败";
  } finally {
    loading.value = false;
    hasLoaded.value = true;
  }
}

async function loadLedger(reset: boolean) {
  if (!currentCard.value) return;
  if (reset) {
    ledgerPage.value = 1;
    ledgerLastPage.value = 1;
  }

  const response = await getMemberCardLedgerEntries(currentCard.value.id, ledgerPage.value);
  ledgerItems.value = reset
    ? response.data.items
    : [...ledgerItems.value, ...response.data.items];
  ledgerLastPage.value = response.data.pagination.lastPage;
}

async function loadMoreLedger() {
  if (ledgerLoading.value || ledgerPage.value >= ledgerLastPage.value) return;
  ledgerPage.value += 1;
  ledgerLoading.value = true;
  try {
    await loadLedger(false);
  } catch (error) {
    ledgerPage.value -= 1;
    uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
  } finally {
    ledgerLoading.value = false;
  }
}

function onTabChange(item: { index: number }) {
  tabCurrent.value = item.index;
}

function openDetail(item: MemberAppointmentSummary) {
  navigateToOnce(`/pages/booking/detail?id=${item.sessionId}`);
}

function confirmPromote(item: MemberAppointmentSummary) {
  uni.showModal({
    title: "确认候补名额",
    content: "确认后将尝试转为正式预约，并按课程规则扣减会员卡。",
    confirmText: "确认",
    success: async (result) => {
      if (!result.confirm) return;
      await promoteAppointment(item);
    },
  });
}

async function promoteAppointment(item: MemberAppointmentSummary) {
  const tenant = await ensureMemberTenant();
  if (!tenant || promotingId.value === item.id) return;

  let commandKey = promoteCommandKeys.get(item.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    promoteCommandKeys.set(item.id, commandKey);
  }

  promotingId.value = item.id;
  try {
    await promoteMemberAppointment(tenant.tenantId, item.id, commandKey);
    promoteCommandKeys.delete(item.id);
    uni.showToast({ title: "候补已确认", icon: "success" });
    await loadAll();
  } catch (error) {
    uni.showToast({
      title: error instanceof Error ? error.message : "候补确认失败",
      icon: "none",
    });
  } finally {
    promotingId.value = null;
  }
}

onLoad((query) => {
  requestedCardId.value = Number(query?.cardId ?? 0);
  if (query?.tab === "1") tabCurrent.value = 1;
});

onShow(async () => {
  if (await requireMemberAuth()) await loadAll();
});

onPullDownRefresh(async () => {
  await loadAll();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="record-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <!-- 对标原版：顶部当前会员卡 -->
    <view v-if="currentCard" class="card-wrap">
      <member-card :card="currentCard" />
    </view>

    <view class="record-list">
      <view class="tabs-wrap">
        <u-tabs
          :list="tabs"
          :current="tabCurrent"
          :is-scroll="false"
          line-color="#22C788"
          :active-style="{ color: '#181818', fontWeight: 600 }"
          :inactive-style="{ color: '#989898' }"
          @change="onTabChange"
        />
      </view>

      <!-- 预约记录（纯展示，点击进详情） -->
      <view v-show="tabCurrent === 0" class="list-wrap">
        <u-empty
          v-if="appointments.length === 0 && !errorMessage"
          mode="list"
          text="~ 无预约记录哦 ~"
          margin-top="60"
        />
        <view v-for="item in appointments" :key="item.id" class="list-item">
          <appointment-row
            :item="item"
            variant="legacy"
            :promotable="item.status === 'waitlisted'"
            :promoting="promotingId === item.id"
            @tap="openDetail(item)"
            @promote="confirmPromote(item)"
          />
        </view>
      </view>

      <!-- 余额核对（当前卡变动流水） -->
      <view v-show="tabCurrent === 1" class="list-wrap">
        <u-empty
          v-if="ledgerItems.length === 0 && !errorMessage"
          mode="list"
          :text="currentCard ? '~ 无变动记录 ~' : '~ 暂无会员卡 ~'"
          margin-top="60"
        />
        <template v-else>
          <view v-for="item in ledgerItems" :key="item.id" class="ledger-row">
            <view
              class="ledger-icon"
              :class="item.direction === 'credit' ? 'ledger-icon--credit' : 'ledger-icon--debit'"
            >
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
    </view>

    <bottom-logo />
  </view>
</template>

<style scoped lang="scss">
.record-page {
  min-height: 100vh;
  background: $color-page;
  padding-bottom: 40rpx;
}

.card-wrap {
  padding: 24rpx 28rpx 0;
}

.record-list {
  margin: 24rpx 28rpx 0;
  background: $color-surface;
  border-radius: 24rpx;
  box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.tabs-wrap {
  border-bottom: 1rpx solid $color-border;
}

.list-wrap {
  min-height: 400rpx;
  padding: 8rpx 28rpx 24rpx;
}

.list-item {
  &:last-child :deep(.u-line) {
    display: none;
  }
}

/* 余额核对流水（与卡详情页一致的账单式行） */
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
