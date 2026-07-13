<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmDashboardSummary, fetchCrmMembers } from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type {
  CrmDashboardSummary,
  CrmFilterPresetQuery,
  CrmMember,
  CrmPinyinBucket,
  CrmStoredMemberFilters,
  MemberStatus,
} from "@/types/crm";
import { CRM_MEMBER_FILTER_STORAGE_KEY } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const members = ref<CrmMember[]>([]);
const dashboard = ref<CrmDashboardSummary | null>(null);
const query = ref("");
const status = ref<"all" | MemberStatus>("all");
const sumMode = ref<string>();
const runOff = ref<number>();
const selectedPinyin = ref<string[]>([]);
const activeFilterLabel = ref("");
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const dashboardCards = [
  { key: "totalCount", label: "全部", sumMode: "all" },
  { key: "monthCount", label: "本月新增", sumMode: "monthNew" },
  { key: "validUserCount", label: "有效", sumMode: "valid" },
  { key: "invalidUserCount", label: "无效", sumMode: "invalid" },
  { key: "nocardUserCount", label: "无卡", sumMode: "noCard" },
  { key: "nologinUserCount", label: "屏蔽", sumMode: "blocked" },
] as const;

const statuses = [
  { value: "all", label: "全部" },
  { value: "lead", label: "潜客" },
  { value: "active", label: "正式" },
  { value: "frozen", label: "冻结" },
  { value: "closed", label: "已关闭" },
] as const;

const canCreate = computed(() => session.can("crm.member.create"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");
const pinyinIndex = computed<CrmPinyinBucket[]>(() => dashboard.value?.pinyinIndex || []);

function statusLabel(value: MemberStatus) {
  return { lead: "潜客", active: "正式", frozen: "冻结", closed: "已关闭" }[value];
}

function statusType(value: MemberStatus) {
  if (value === "active") return "success";
  if (value === "frozen") return "warning";
  if (value === "closed") return "error";
  return "info";
}

function dashboardValue(key: (typeof dashboardCards)[number]["key"]) {
  return dashboard.value?.[key] ?? 0;
}

function readStoredFilters() {
  const raw = uni.getStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  if (!raw) {
    activeFilterLabel.value = "";
    return;
  }
  try {
    const stored = JSON.parse(raw) as CrmStoredMemberFilters;
    applyFilterQuery(stored.query, stored.label);
    uni.removeStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  } catch {
    uni.removeStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  }
}

function applyFilterQuery(queryParams: CrmFilterPresetQuery, label?: string) {
  sumMode.value = queryParams.sumMode && queryParams.sumMode !== "all" ? queryParams.sumMode : undefined;
  runOff.value = queryParams.runOff;
  activeFilterLabel.value = label || "";
}

function applySumMode(mode: string) {
  sumMode.value = mode === "all" ? undefined : mode;
  runOff.value = undefined;
  activeFilterLabel.value = "";
  load();
}

function togglePinyin(initial: string) {
  if (selectedPinyin.value.includes(initial)) {
    selectedPinyin.value = selectedPinyin.value.filter((item) => item !== initial);
  } else {
    selectedPinyin.value = [...selectedPinyin.value, initial];
  }
  load();
}

async function loadDashboard() {
  if (!session.currentSiteId) return;
  try {
    const response = await fetchCrmDashboardSummary(session.currentSiteId);
    dashboard.value = response.data;
  } catch {
    dashboard.value = null;
  }
}

async function load(reset = true) {
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }
  if (reset) {
    page.value = 1;
    loading.value = true;
    errorMessage.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchCrmMembers(session.currentSiteId, {
      page: requestedPage,
      perPage: 20,
      q: query.value.trim() || undefined,
      status: status.value !== "all" ? status.value : undefined,
      pinyinInitial: selectedPinyin.value.length ? selectedPinyin.value.join(",") : undefined,
      sumMode: sumMode.value,
      runOff: runOff.value,
      includeVisitors: sumMode.value === "noCard" ? true : undefined,
    });
    members.value = reset ? response.data.items : [...members.value, ...response.data.items];
    page.value = requestedPage;
    total.value = response.data.pagination.total;
    lastPage.value = response.data.pagination.lastPage;
  } catch (error) {
    const message = error instanceof Error ? error.message : "会员列表加载失败";
    if (reset) errorMessage.value = message;
    else uni.showToast({ title: message, icon: "none" });
  } finally {
    loading.value = false;
    loadingMore.value = false;
    uni.stopPullDownRefresh();
  }
}

async function refresh() {
  readStoredFilters();
  await Promise.all([loadDashboard(), load()]);
}

async function loadMore() {
  if (loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
}

function changeStatus(value: typeof status.value) {
  status.value = value;
  load();
}

function openMember(member: CrmMember) {
  uni.navigateTo({ url: `/pages/members/detail?id=${member.id}` });
}

function createMember() {
  uni.navigateTo({ url: "/pages/members/form" });
}

function openLinkReviews() {
  uni.navigateTo({ url: "/pages/members/link-requests" });
}

function openFilter() {
  uni.navigateTo({ url: "/pages/members/filter" });
}

function openDeleted() {
  uni.navigateTo({ url: "/pages/members/deleted" });
}

function openArchivedCards() {
  uni.navigateTo({ url: "/pages/members/archived-cards/index" });
}

function openBatchImport() {
  uni.navigateTo({ url: "/pages/members/batch-import" });
}

onShow(async () => {
  if (await requireStaffAuth()) await refresh();
});
onPullDownRefresh(() => refresh());
</script>

<template>
  <view class="member-page">
    <view class="toolbar">
      <scroll-view v-if="dashboard" scroll-x class="dashboard-scroll" :show-scrollbar="false">
        <view class="dashboard-grid">
          <button
            v-for="card in dashboardCards"
            :key="card.key"
            class="dashboard-card"
            :class="{ active: (sumMode || 'all') === card.sumMode && !runOff }"
            @click="applySumMode(card.sumMode)"
          >
            <text class="dashboard-value">{{ dashboardValue(card.key) }}</text>
            <text class="dashboard-label">{{ card.label }}</text>
          </button>
        </view>
      </scroll-view>

      <u-search v-model="query" placeholder="姓名或完整手机号" :show-action="false" @search="load()" @clear="load()" />

      <scroll-view v-if="pinyinIndex.length" scroll-x class="pinyin-scroll" :show-scrollbar="false">
        <view class="pinyin-row">
          <button
            v-for="bucket in pinyinIndex"
            :key="bucket.initial"
            class="pinyin-chip"
            :class="{ active: selectedPinyin.includes(bucket.initial) }"
            @click="togglePinyin(bucket.initial)"
          >
            {{ bucket.initial }} {{ bucket.count }}
          </button>
        </view>
      </scroll-view>

      <view class="status-control">
        <button v-for="item in statuses" :key="item.value" class="status-button" :class="{ active: status === item.value }" @click="changeStatus(item.value)">
          {{ item.label }}
        </button>
      </view>

      <view class="result-line">
        <text>{{ total }} 名会员</text>
        <view class="result-actions">
          <button v-if="session.can('crm.member.link.review')" class="toolbar-link" @click="openLinkReviews">关联审核</button>
          <button class="toolbar-link" @click="openFilter">筛选</button>
          <button v-if="session.can('crm.member.deleted.read')" class="toolbar-link" @click="openDeleted">已删除</button>
          <button v-if="session.can('member-card.archive')" class="toolbar-link" @click="openArchivedCards">归档卡</button>
          <button v-if="session.can('crm.member.batch-import')" class="toolbar-link" @click="openBatchImport">批量导入</button>
          <text class="site-context">{{ currentSiteName }}</text>
        </view>
      </view>
      <view v-if="activeFilterLabel || runOff" class="filter-chip">
        <text>{{ activeFilterLabel || '流失会员筛选' }}</text>
        <button class="clear-filter" @click="applySumMode('all')">清除</button>
      </view>
    </view>

    <u-loading-page :loading="loading" />
    <view v-if="!loading" class="list-area">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-empty v-else-if="members.length === 0" mode="list" :text="canCreate ? '暂无会员，点击添加录入潜客' : '暂无可查看会员'" />
      <view v-else>
        <view v-for="member in members" :key="member.id" class="member-row" @click="openMember(member)">
          <u-avatar :text="member.name?.slice(0, 1) || '?'" size="44" />
          <view class="member-main">
            <view class="name-line">
              <text class="member-name">{{ member.name || '未命名会员' }}</text>
              <u-tag :text="statusLabel(member.status)" :type="statusType(member.status)" size="mini" plain />
              <u-icon v-if="member.appAccessStatus === 'blocked'" name="lock-fill" color="#b42318" size="16" />
            </view>
            <view class="member-meta">{{ member.mobileMasked || '未留手机号' }} · {{ member.owner?.name || '未分配顾问' }}</view>
            <view v-if="member.hasStickyRemark && member.stickyRemark" class="sticky-line">{{ member.stickyRemark }}</view>
            <view v-if="member.tags.length" class="tag-line">
              <u-tag v-for="tag in member.tags" :key="tag.id" :text="tag.name" size="mini" plain />
            </view>
          </view>
          <u-icon name="arrow-right" color="#98a2b3" size="18" />
        </view>
        <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" @loadmore="loadMore" />
      </view>
    </view>

    <button v-if="canCreate" class="add-button" title="添加会员" @click="createMember">
      <u-icon name="plus" color="#fff" size="24" />
    </button>
  </view>
</template>

<style scoped lang="scss">
.member-page { min-height: 100vh; background: $color-page; }
.toolbar { position: sticky; z-index: 5; top: 0; padding: 20rpx 24rpx 12rpx; background: $color-surface; border-bottom: 1rpx solid $color-border; }
.dashboard-scroll, .pinyin-scroll { width: 100%; white-space: nowrap; }
.dashboard-grid, .pinyin-row { display: inline-flex; gap: 12rpx; padding-bottom: 16rpx; }
.dashboard-card { display: inline-flex; flex-direction: column; align-items: flex-start; min-width: 132rpx; margin: 0; padding: 16rpx 20rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: $radius-sm; }
.dashboard-card::after { border: 0; }
.dashboard-card.active { color: #fff; background: $color-primary; border-color: $color-primary; }
.dashboard-value { font-size: 30rpx; font-weight: 600; }
.dashboard-label { margin-top: 6rpx; font-size: 22rpx; }
.pinyin-chip { margin: 0; padding: 10rpx 18rpx; color: $color-text-secondary; font-size: 22rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 999rpx; }
.pinyin-chip::after { border: 0; }
.pinyin-chip.active { color: #fff; background: $color-primary; border-color: $color-primary; }
.status-control { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); margin-top: 16rpx; border: 1rpx solid $color-border; border-radius: $radius-sm; }
.status-button { height: 64rpx; padding: 0; color: $color-text-secondary; font-size: 24rpx; line-height: 64rpx; background: transparent; border-radius: 0; }
.status-button::after { border: 0; }
.status-button.active { color: #fff; background: $color-primary; }
.result-line { display: flex; justify-content: space-between; margin-top: 14rpx; color: $color-text-secondary; font-size: 22rpx; }
.result-actions { display: flex; align-items: center; flex-wrap: wrap; justify-content: flex-end; gap: 12rpx; }
.toolbar-link { margin: 0; padding: 0; color: $color-primary; font-size: 22rpx; line-height: 1.4; background: transparent; }
.toolbar-link::after, .clear-filter::after { border: 0; }
.site-context { color: $color-primary; }
.filter-chip { display: flex; align-items: center; justify-content: space-between; margin-top: 12rpx; padding: 12rpx 16rpx; color: $color-primary; font-size: 22rpx; background: rgba(22, 119, 255, .08); border-radius: $radius-sm; }
.clear-filter { margin: 0; padding: 0; color: $color-primary; font-size: 22rpx; background: transparent; }
.list-area { padding: 0 24rpx 120rpx; }
.member-row { display: flex; align-items: center; gap: 20rpx; min-height: 132rpx; border-bottom: 1rpx solid $color-border; }
.member-main { min-width: 0; flex: 1; }
.name-line { display: flex; align-items: center; gap: 10rpx; }
.member-name { overflow: hidden; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.member-meta, .sticky-line { margin-top: 10rpx; color: $color-text-secondary; font-size: 24rpx; }
.sticky-line { overflow: hidden; color: #b54708; text-overflow: ellipsis; white-space: nowrap; }
.tag-line { display: flex; flex-wrap: wrap; gap: 8rpx; margin-top: 10rpx; }
.add-button { position: fixed; right: 32rpx; bottom: calc(40rpx + env(safe-area-inset-bottom)); width: 88rpx; height: 88rpx; padding: 0; line-height: 88rpx; background: $color-primary; border-radius: 50%; box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, .16); }
.add-button::after { border: 0; }
</style>
