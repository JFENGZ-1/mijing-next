<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import {
  fetchCardReminderExpiring,
  fetchCardReminderPenalized,
  fetchCardReminderPendingOpen,
  fetchCardReminderZeroBalance,
} from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { MemberCardReminderItem } from "@/types/reports";

type CardReminderTab = "expiring" | "zero-balance" | "pending-open" | "penalized";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const activeTab = ref<CardReminderTab>("expiring");
const withinDays = ref<number | null>(null);
const configDays = ref(30);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const items = ref<MemberCardReminderItem[]>([]);
const requestSeq = ref(0);
const loadedQueryKey = ref("");

const canView = computed(() => session.can("member-card.reminder.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const tabs = [
  { key: "expiring" as const, name: "即将到期" },
  { key: "zero-balance" as const, name: "权益用尽" },
  { key: "pending-open" as const, name: "待开卡" },
  { key: "penalized" as const, name: "冻结/罚扣" },
];

const dayOptions = [7, 14, 30, 60, 90];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));
const showWithinDays = computed(() => activeTab.value === "expiring");

function currentQueryKey() {
  return JSON.stringify([
    session.currentSiteId,
    activeTab.value,
    activeTab.value === "expiring" ? withinDays.value : null,
  ]);
}

function memberName(name: string | null, cardNo: string) {
  return name?.trim() || cardNo;
}

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "会员卡提醒加载失败";
}

function resetList() {
  items.value = [];
  page.value = 1;
  lastPage.value = 1;
  total.value = 0;
}

async function fetchPage(siteId: number, tab: CardReminderTab, days: number | null, requestedPage: number) {
  const query = { page: requestedPage, perPage: 20 };

  if (tab === "expiring") {
    return fetchCardReminderExpiring(siteId, {
      ...query,
      withinDays: days ?? undefined,
    });
  }
  if (tab === "zero-balance") {
    return fetchCardReminderZeroBalance(siteId, query);
  }
  if (tab === "pending-open") {
    return fetchCardReminderPendingOpen(siteId, query);
  }
  return fetchCardReminderPenalized(siteId, query);
}

async function load(reset = true) {
  const siteId = session.currentSiteId;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    loading.value = false;
    loadingMore.value = false;
    uni.stopPullDownRefresh();
    return;
  }
  const tab = activeTab.value;
  const days = withinDays.value;
  const queryKey = currentQueryKey();
  if (!reset && (loading.value || loadingMore.value || page.value >= lastPage.value || loadedQueryKey.value !== queryKey)) return;
  const requestId = ++requestSeq.value;
  const requestedPage = reset ? 1 : page.value + 1;

  if (reset) {
    loading.value = true;
    forbidden.value = false;
    errorMessage.value = "";
    resetList();
    loadedQueryKey.value = "";
  } else {
    loadingMore.value = true;
  }

  try {
    const response = await fetchPage(siteId, tab, days, requestedPage);
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;

    configDays.value = response.config.expiringWithinDays;
    items.value = reset ? response.items : [...items.value, ...response.items];
    page.value = requestedPage;
    total.value = response.pagination.total;
    lastPage.value = response.pagination.lastPage;
    loadedQueryKey.value = queryKey;
  } catch (error) {
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    if (reset) {
      resetList();
      resolveError(error);
    } else {
      uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
    }
  } finally {
    if (requestId === requestSeq.value) {
      loading.value = false;
      loadingMore.value = false;
      uni.stopPullDownRefresh();
    }
  }
}

async function switchTab(index: number) {
  const tab = tabs[index];
  if (!tab || tab.key === activeTab.value) return;
  activeTab.value = tab.key;
  withinDays.value = null;
  await load();
}

async function selectWithinDays(days: number | null) {
  if (withinDays.value === days) return;
  withinDays.value = days;
  await load();
}

async function loadMore() {
  if (loading.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

function openReminderConfig() {
  uni.navigateTo({ url: "/pages/settings/defaults/card-reminder-config/index" });
}

function cardStatusLabel(status: string) {
  return ({
    pending_activation: "待激活",
    active: "使用中",
    frozen: "已停卡",
    expired: "已过期",
    exhausted: "已用尽",
    archived: "已归档",
    voided: "已作废",
  } as Record<string, string>)[status] || "状态待核对";
}

function itemMeta(item: MemberCardReminderItem) {
  const cardLabel = item.name || item.cardNo;
  if (activeTab.value === "expiring") {
    return item.validUntil ? `${cardLabel} · 到期 ${item.validUntil}` : `${cardLabel} · 到期日待定`;
  }
  if (activeTab.value === "zero-balance") {
    if (item.cardType === "count") {
      return `${cardLabel} · 剩余 ${item.cachedRemainingCount ?? 0} 次`;
    }
    const balance = item.cachedBalance ?? "0.00";
    return `${cardLabel} · 余额 ¥${balance}`;
  }
  if (activeTab.value === "pending-open") {
    return item.issuedAt ? `${cardLabel} · 发卡 ${item.issuedAt.slice(0, 10)}` : `${cardLabel} · 待会员开卡`;
  }
  return `${cardLabel} · ${cardStatusLabel(item.status)}`;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(() => load());
onReachBottom(() => loadMore());
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="eyebrow">卡项待办</text>
        <text class="title">会员卡提醒</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无会员卡提醒权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card">
        <view>
          <text class="error-title">卡项提醒暂未更新</text>
          <text class="error-detail">{{ errorMessage }}</text>
        </view>
        <button class="retry-btn" @tap="load()">重新加载</button>
      </view>

      <u-tabs v-if="!errorMessage" :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <template v-if="!errorMessage && showWithinDays">
        <view class="section-title">到期天数（默认 {{ configDays }} 天）</view>
        <view class="chip-row">
          <view
            class="chip"
            :class="{ active: withinDays === null }"
            @click="selectWithinDays(null)"
          >
            默认
          </view>
          <view
            v-for="days in dayOptions"
            :key="days"
            class="chip"
            :class="{ active: days === withinDays }"
            @click="selectWithinDays(days)"
          >
            {{ days }} 天
          </view>
        </view>
      </template>

      <view v-if="!errorMessage" class="totals-card">共 {{ total }} 条 · 已加载 {{ items.length }} 条</view>

      <u-button
        v-if="!errorMessage && session.can('member-card.reminder.config')"
        size="small"
        text="提醒阈值设置"
        @click="openReminderConfig"
      />

      <view v-if="!errorMessage" class="list-card">
        <view v-for="item in items" :key="item.memberCardId" class="list-row">
          <view class="list-main">
            <text class="list-name">{{ memberName(item.memberName, item.cardNo) }}</text>
            <text class="list-meta">{{ itemMeta(item) }}</text>
          </view>
        </view>
        <u-empty v-if="!items.length" mode="list" text="暂无提醒数据" />
        <u-loadmore
          v-else
          :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'"
          @loadmore="loadMore"
        />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.title,
.subtitle,
.list-name,
.list-meta,
.eyebrow,
.error-title,
.error-detail {
  display: block;
}

.eyebrow {
  margin-bottom: 6rpx;
  color: #d98200;
  font-size: 22rpx;
  font-weight: 600;
}

.error-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-md;
  padding: $spacing-md;
  border: 1rpx solid rgba(225, 82, 82, 0.18);
  border-radius: $radius-md;
  background: #fff6f5;
}

.error-title {
  color: $color-danger;
  font-size: 26rpx;
  font-weight: 600;
}

.error-detail {
  margin-top: 6rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.retry-btn {
  flex: none;
  margin: 0;
  padding: 0 24rpx;
  color: $color-danger;
  font-size: 24rpx;
  line-height: 56rpx;
  border: 1rpx solid currentColor;
  border-radius: 999rpx;
  background: transparent;
}

.retry-btn::after {
  border: 0;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.list-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
}

.chip {
  padding: 12rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  background: $color-surface;
  font-size: 26rpx;
}

.chip.active {
  border-color: #ed920f;
  color: #ed920f;
  background: #fdf3e3;
}

.totals-card,
.list-card {
  margin-top: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.list-row {
  padding: $spacing-sm 0;
  border-bottom: 1rpx solid $color-border;
}

.list-row:last-child {
  border-bottom: none;
}

.list-name {
  font-size: 28rpx;
  font-weight: 500;
}
</style>
