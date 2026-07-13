<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
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

const canView = computed(() => session.can("member-card.reminder.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const tabs = [
  { key: "expiring" as const, name: "即将到期" },
  { key: "zero-balance" as const, name: "余额为零" },
  { key: "pending-open" as const, name: "待开卡" },
  { key: "penalized" as const, name: "冻结/罚扣" },
];

const dayOptions = [7, 14, 30, 60, 90];
const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));
const showWithinDays = computed(() => activeTab.value === "expiring");

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

async function fetchPage(requestedPage: number) {
  if (!session.currentSiteId) return null;
  const siteId = session.currentSiteId;
  const query = { page: requestedPage, perPage: 20 };

  if (activeTab.value === "expiring") {
    return fetchCardReminderExpiring(siteId, {
      ...query,
      withinDays: withinDays.value ?? undefined,
    });
  }
  if (activeTab.value === "zero-balance") {
    return fetchCardReminderZeroBalance(siteId, query);
  }
  if (activeTab.value === "pending-open") {
    return fetchCardReminderPendingOpen(siteId, query);
  }
  return fetchCardReminderPenalized(siteId, query);
}

async function load(reset = true) {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }

  if (reset) {
    loading.value = true;
    forbidden.value = false;
    errorMessage.value = "";
    resetList();
  } else {
    loadingMore.value = true;
  }

  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchPage(requestedPage);
    if (!response) return;

    configDays.value = response.config.expiringWithinDays;
    items.value = reset ? response.items : [...items.value, ...response.items];
    page.value = requestedPage;
    total.value = response.pagination.total;
    lastPage.value = response.pagination.lastPage;
  } catch (error) {
    if (reset) {
      resetList();
      resolveError(error);
    } else {
      uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
    }
  } finally {
    loading.value = false;
    loadingMore.value = false;
    uni.stopPullDownRefresh();
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
  if (loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

function openReminderConfig() {
  uni.navigateTo({ url: "/pages/settings/defaults/card-reminder-config/index" });
}

function itemMeta(item: MemberCardReminderItem) {
  const cardLabel = item.name || item.cardNo;
  if (activeTab.value === "expiring") {
    return item.validUntil ? `${cardLabel} · 到期 ${item.validUntil}` : `${cardLabel} · 到期日待定`;
  }
  if (activeTab.value === "zero-balance") {
    const balance = item.cachedBalance ?? "0.00";
    return `${cardLabel} · 余额 ¥${balance}`;
  }
  if (activeTab.value === "pending-open") {
    return item.issuedAt ? `${cardLabel} · 发卡 ${item.issuedAt.slice(0, 10)}` : `${cardLabel} · 待会员开卡`;
  }
  return `${cardLabel} · ${item.status}`;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(() => load());
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">会员卡提醒</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无会员卡提醒权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

      <u-tabs :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <template v-if="showWithinDays">
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

      <view class="totals-card">共 {{ total }} 条 · 已加载 {{ items.length }} 条</view>

      <u-button
        v-if="session.can('member-card.reminder.config')"
        size="small"
        text="提醒阈值设置"
        @click="openReminderConfig"
      />

      <view class="list-card">
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
.list-meta {
  display: block;
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
  border-color: #1a73e8;
  color: #1a73e8;
  background: #e8f0fe;
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
