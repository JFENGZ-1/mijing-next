<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import {
  fetchReminderAnniversary,
  fetchReminderBirthdays,
  fetchReminderHolidayDue,
  fetchReminderNoClass,
  fetchReminderVisitors,
} from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  ReminderHolidayItem,
  ReminderMemberItem,
  ReminderMemberStatus,
} from "@/types/reports";

type ReminderTab = "anniversary" | "no-class" | "birthdays" | "visitors" | "holiday-due";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const activeTab = ref<ReminderTab>("anniversary");
const selectedDays = ref(7);
const memberStatus = ref<ReminderMemberStatus>("valid");
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const items = ref<ReminderMemberItem[]>([]);
const holidayItems = ref<ReminderHolidayItem[]>([]);
const requestSeq = ref(0);
const loadedQueryKey = ref("");

const canView = computed(() => session.can("notification.reminder.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const tabs = [
  { key: "anniversary" as const, name: "入会纪念", defaultDays: 7 },
  { key: "no-class" as const, name: "未上课", defaultDays: 30 },
  { key: "birthdays" as const, name: "生日", defaultDays: 7 },
  { key: "visitors" as const, name: "潜客", defaultDays: 30 },
  { key: "holiday-due" as const, name: "假期到期", defaultDays: 7 },
];

const dayOptions = [7, 14, 30, 60, 90];
const memberStatusOptions = [
  { value: "valid" as const, label: "有效" },
  { value: "invalid" as const, label: "无效" },
  { value: "all" as const, label: "全部" },
];

const activeTabIndex = computed(() => tabs.findIndex((tab) => tab.key === activeTab.value));
const showMemberStatus = computed(() => activeTab.value === "anniversary" || activeTab.value === "birthdays");
const isHolidayTab = computed(() => activeTab.value === "holiday-due");
const listCount = computed(() => (isHolidayTab.value ? holidayItems.value.length : items.value.length));

function currentQueryKey() {
  return JSON.stringify([
    session.currentSiteId,
    activeTab.value,
    selectedDays.value,
    showMemberStatus.value ? memberStatus.value : null,
  ]);
}

function memberName(name: string | null, memberNo: string) {
  return name?.trim() || memberNo;
}

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "提醒列表加载失败";
}

function resetList() {
  items.value = [];
  holidayItems.value = [];
  page.value = 1;
  lastPage.value = 1;
  total.value = 0;
}

async function fetchPage(
  siteId: number,
  tab: ReminderTab,
  days: number,
  status: ReminderMemberStatus,
  requestedPage: number,
) {
  const query = { days, page: requestedPage, perPage: 20 };

  if (tab === "anniversary") {
    return fetchReminderAnniversary(siteId, { ...query, memberStatus: status });
  }
  if (tab === "no-class") {
    return fetchReminderNoClass(siteId, query);
  }
  if (tab === "birthdays") {
    return fetchReminderBirthdays(siteId, { ...query, memberStatus: status });
  }
  if (tab === "visitors") {
    return fetchReminderVisitors(siteId, query);
  }
  return fetchReminderHolidayDue(siteId, query);
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
  const days = selectedDays.value;
  const status = memberStatus.value;
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
    const response = await fetchPage(siteId, tab, days, status, requestedPage);
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;

    if (tab === "holiday-due") {
      const holidayResponse = response as Awaited<ReturnType<typeof fetchReminderHolidayDue>>;
      holidayItems.value = reset
        ? holidayResponse.items
        : [...holidayItems.value, ...holidayResponse.items];
    } else {
      const memberResponse = response as Awaited<ReturnType<typeof fetchReminderAnniversary>>;
      items.value = reset ? memberResponse.items : [...items.value, ...memberResponse.items];
    }

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
  selectedDays.value = tab.defaultDays;
  await load();
}

async function selectDays(days: number) {
  if (selectedDays.value === days) return;
  selectedDays.value = days;
  await load();
}

async function selectMemberStatus(status: ReminderMemberStatus) {
  if (memberStatus.value === status) return;
  memberStatus.value = status;
  await load();
}

async function loadMore() {
  if (loading.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

function itemMeta(item: ReminderMemberItem) {
  if (activeTab.value === "anniversary") {
    return item.anniversaryOn
      ? `${item.anniversaryOn} · ${item.daysUntilAnniversary ?? 0} 天后`
      : "入会日期待定";
  }
  if (activeTab.value === "no-class") {
    return item.lastClassDate
      ? `上次上课 ${item.lastClassDate} · ${item.daysSinceLastClass ?? 0} 天前`
      : "暂无上课记录";
  }
  if (activeTab.value === "birthdays") {
    return item.birthdayOn
      ? `${item.birthdayOn} · ${item.daysUntilBirthday ?? 0} 天后`
      : "生日待定";
  }
  if (activeTab.value === "visitors") {
    return item.joinedAt ? `加入 ${item.joinedAt.slice(0, 10)}` : "潜客跟进";
  }
  return item.lastClassDate ? `上次上课 ${item.lastClassDate}` : "";
}

function holidayMeta(item: ReminderHolidayItem) {
  const cardLabel = item.name || item.cardNo;
  const endLabel = item.holidayEndsAt
    ? `假期至 ${item.holidayEndsAt} · ${item.daysUntilHolidayEnds ?? 0} 天后`
    : "假期结束日待定";
  return `${cardLabel} · ${endLabel}`;
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
        <text class="eyebrow">会员待办</text>
        <text class="title">会员提醒</text>
        <text class="subtitle">{{ currentSiteName }}</text>
      </view>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无会员提醒权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card">
        <view>
          <text class="error-title">提醒数据暂未更新</text>
          <text class="error-detail">{{ errorMessage }}</text>
        </view>
        <button class="retry-btn" @tap="load()">重新加载</button>
      </view>

      <u-tabs v-if="!errorMessage" :list="tabs.map((tab) => ({ name: tab.name }))" :current="activeTabIndex" @change="switchTab" />

      <view v-if="!errorMessage" class="section-title">天数阈值</view>
      <view v-if="!errorMessage" class="chip-row">
        <view
          v-for="days in dayOptions"
          :key="days"
          class="chip"
          :class="{ active: days === selectedDays }"
          @click="selectDays(days)"
        >
          {{ days }} 天
        </view>
      </view>

      <template v-if="!errorMessage && showMemberStatus">
        <view class="section-title">会员状态</view>
        <view class="chip-row">
          <view
            v-for="option in memberStatusOptions"
            :key="option.value"
            class="chip"
            :class="{ active: option.value === memberStatus }"
            @click="selectMemberStatus(option.value)"
          >
            {{ option.label }}
          </view>
        </view>
      </template>

      <view v-if="!errorMessage" class="totals-card">共 {{ total }} 条 · 已加载 {{ listCount }} 条</view>

      <view v-if="!errorMessage && !isHolidayTab" class="list-card">
        <view v-for="item in items" :key="`${item.memberId}-${item.anniversaryOn || item.birthdayOn || item.lastClassDate || ''}`" class="list-row">
          <view class="list-main">
            <text class="list-name">{{ memberName(item.memberName, item.memberNo) }}</text>
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

      <view v-else-if="!errorMessage" class="list-card">
        <view v-for="item in holidayItems" :key="item.memberCardId" class="list-row">
          <view class="list-main">
            <text class="list-name">{{ memberName(item.memberName, item.memberNo) }}</text>
            <text class="list-meta">{{ holidayMeta(item) }}</text>
          </view>
        </view>
        <u-empty v-if="!holidayItems.length" mode="list" text="暂无提醒数据" />
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
