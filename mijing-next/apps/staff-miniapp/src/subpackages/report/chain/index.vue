<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import {
  fetchChainCourseSummary,
  fetchChainFinanceSummary,
  fetchChainMemberSummary,
  fetchChainSites,
} from "@/api/chain";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ChainCourseSummary, ChainFinanceSummary, ChainMembersSummary, ChainSiteListItem } from "@/types/chain";

const session = useSessionStore();
const loading = ref(true);
const summariesLoading = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const sites = ref<ChainSiteListItem[]>([]);
const selectedSiteIds = ref<number[]>([]);
const financeSummary = ref<ChainFinanceSummary | null>(null);
const courseSummary = ref<ChainCourseSummary | null>(null);
const memberSummary = ref<ChainMembersSummary | null>(null);
let summaryRequestSeq = 0;

const canView = computed(() => session.can("org.chain.read"));
const accessibleSites = computed(() => sites.value.filter((site) => site.accessible));
const currentYear = new Date().getFullYear();

const financeCurrentYear = computed(() =>
  financeSummary.value?.years.find((item) => item.isCurrentYear)
  ?? financeSummary.value?.years[0]
  ?? null,
);

const courseCurrentYear = computed(() =>
  courseSummary.value?.years.find((item) => item.isCurrentYear)
  ?? courseSummary.value?.years[0]
  ?? null,
);

const financeKpis = computed(() => {
  const year = financeCurrentYear.value;
  if (!year) return [];
  const currentMonth = year.months.find((item) => item.month === new Date().getMonth() + 1);
  return [
    { label: `${year.year} 年营业额`, value: `¥${year.revenue}` },
    { label: "本年售卡", value: String(year.cardSalesCount) },
    { label: "本年新增会员", value: String(year.newMemberCount) },
    { label: "本月营业额", value: `¥${currentMonth?.revenue ?? "0.00"}` },
  ];
});

const courseKpis = computed(() => {
  const year = courseCurrentYear.value;
  if (!year) return [];
  return [
    { label: "团课排课", value: String(year.groupScheduledCount) },
    { label: "团课成课", value: String(year.groupHeldCount) },
    { label: "签到人次", value: String(year.groupSignInCount) },
    { label: "私教课时", value: String(year.privateSessionCount) },
  ];
});

const memberKpis = computed(() => {
  if (!memberSummary.value) return [];
  return [
    { label: "会员总数（去重）", value: String(memberSummary.value.totalMemberCount) },
    { label: "本月新增（去重）", value: String(memberSummary.value.monthNewMemberCount) },
  ];
});

function resolveError(error: unknown) {
  if (error instanceof ApiError && error.statusCode === 403) {
    forbidden.value = true;
    return;
  }
  errorMessage.value = error instanceof Error ? error.message : "连锁报表加载失败";
}

function toggleSite(siteId: number) {
  if (!selectedSiteIds.value.includes(siteId)) {
    selectedSiteIds.value = [...selectedSiteIds.value, siteId];
  } else if (selectedSiteIds.value.length > 1) {
    selectedSiteIds.value = selectedSiteIds.value.filter((id) => id !== siteId);
  } else {
    uni.showToast({ title: "至少保留一个场馆", icon: "none" });
    return;
  }
  void loadSummaries();
}

function selectAllSites() {
  selectedSiteIds.value = accessibleSites.value.map((site) => site.id);
  void loadSummaries();
}

function siteName(siteId: number) {
  return sites.value.find((site) => site.id === siteId)?.name || `场馆 #${siteId}`;
}

function drillToSiteReport(siteId: number) {
  uni.showActionSheet({
    itemList: ["营收统计", "课程统计"],
    success: (result) => {
      const path = result.tapIndex === 0
        ? "/subpackages/report/finance/index"
        : "/subpackages/report/courses/index";
      uni.navigateTo({
        url: `${path}?siteId=${siteId}&siteName=${encodeURIComponent(siteName(siteId))}`,
      });
    },
  });
}

async function loadSummaries() {
  if (!selectedSiteIds.value.length) {
    summaryRequestSeq += 1;
    summariesLoading.value = false;
    financeSummary.value = null;
    courseSummary.value = null;
    memberSummary.value = null;
    return;
  }
  const requestSeq = ++summaryRequestSeq;
  const siteIds = [...selectedSiteIds.value];
  summariesLoading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  const [finance, courses, members] = await Promise.allSettled([
    fetchChainFinanceSummary(siteIds),
    fetchChainCourseSummary(siteIds),
    fetchChainMemberSummary(siteIds),
  ]);
  if (requestSeq !== summaryRequestSeq) return;

  financeSummary.value = finance.status === "fulfilled" ? finance.value : null;
  courseSummary.value = courses.status === "fulfilled" ? courses.value : null;
  memberSummary.value = members.status === "fulfilled" ? members.value : null;

  const failures = [
    finance.status === "rejected" ? { label: "营收", error: finance.reason } : null,
    courses.status === "rejected" ? { label: "课程", error: courses.reason } : null,
    members.status === "rejected" ? { label: "会员", error: members.reason } : null,
  ].filter((item): item is { label: string; error: unknown } => item !== null);
  if (failures.length) {
    forbidden.value = failures.length === 3
      && failures.every((item) => item.error instanceof ApiError && item.error.statusCode === 403);
    const labels = failures.map((item) => item.label).join("、");
    const firstError = failures[0].error;
    const detail = firstError instanceof Error ? firstError.message : "服务暂不可用";
    errorMessage.value = `${labels}汇总加载失败：${detail}`;
  }
  summariesLoading.value = false;
}

async function load() {
  if (!canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  forbidden.value = false;
  errorMessage.value = "";
  try {
    const roster = await fetchChainSites();
    sites.value = roster.sites;
    const defaults = roster.sites.filter((site) => site.accessible).map((site) => site.id);
    selectedSiteIds.value = defaults.length ? defaults : roster.sites.map((site) => site.id);
    if (selectedSiteIds.value.length) await loadSummaries();
  } catch (error) {
    summaryRequestSeq += 1;
    summariesLoading.value = false;
    financeSummary.value = null;
    courseSummary.value = null;
    memberSummary.value = null;
    resolveError(error);
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading || summariesLoading" />
  <view v-if="!loading" class="page-container">
    <view class="header-row">
      <view>
        <text class="title">连锁汇总</text>
        <text class="subtitle">已选 {{ selectedSiteIds.length }} 个场馆 · {{ currentYear }} 年</text>
      </view>
      <text class="link-action" @click="selectAllSites">全选</text>
    </view>

    <u-empty v-if="forbidden || !canView" mode="permission" text="暂无连锁报表权限" />
    <template v-else>
      <view v-if="errorMessage" class="error-card"><u-alert type="error" :description="errorMessage" /><button class="retry-btn" @tap="loadSummaries">重新加载汇总</button></view>

      <view class="section-title">选择场馆</view>
      <view v-if="accessibleSites.length" class="site-grid">
        <view
          v-for="site in accessibleSites"
          :key="site.id"
          class="site-chip"
          :class="{ active: selectedSiteIds.includes(site.id) }"
          @click="toggleSite(site.id)"
        >
          <text class="site-name">{{ site.name }}</text>
          <text class="site-code">{{ site.code }}</text>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无可汇总场馆" />

      <view class="section-title">财务汇总</view>
      <view v-if="financeKpis.length" class="metric-grid">
        <view v-for="card in financeKpis" :key="card.label" class="metric-cell">
          <text class="metric-value">{{ card.value }}</text>
          <text class="metric-label">{{ card.label }}</text>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无财务数据" />

      <view class="section-title">课程汇总</view>
      <view v-if="courseKpis.length" class="metric-grid">
        <view v-for="card in courseKpis" :key="card.label" class="metric-cell">
          <text class="metric-value">{{ card.value }}</text>
          <text class="metric-label">{{ card.label }}</text>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无课程数据" />

      <view class="section-title">会员汇总</view>
      <view v-if="memberKpis.length" class="metric-grid metric-grid--compact">
        <view v-for="card in memberKpis" :key="card.label" class="metric-cell">
          <text class="metric-value">{{ card.value }}</text>
          <text class="metric-label">{{ card.label }}</text>
        </view>
      </view>
      <u-empty v-else mode="list" text="暂无会员数据" />

      <view v-if="memberSummary?.bySite.length" class="section-title">各馆会员数</view>
      <u-cell-group v-if="memberSummary?.bySite.length">
        <u-cell
          v-for="row in memberSummary.bySite"
          :key="row.siteId"
          :title="siteName(row.siteId)"
          :value="`${row.memberCount} 人`"
          is-link
          @click="drillToSiteReport(row.siteId)"
        />
      </u-cell-group>
    </template>
  </view>
</template>

<style scoped lang="scss">
.header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.title,
.subtitle,
.metric-value,
.metric-label,
.site-name,
.site-code,
.link-action {
  display: block;
}

.title {
  font-size: 38rpx;
  font-weight: 600;
}

.subtitle,
.metric-label,
.site-code {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.link-action {
  color: #ed920f;
  font-size: 26rpx;
}

.site-grid {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
  margin-top: $spacing-sm;
}

.site-chip {
  min-width: 200rpx;
  padding: $spacing-sm $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.site-chip.active {
  border-color: #ed920f;
  background: #fdf3e3;
}

.site-name {
  font-size: 28rpx;
  font-weight: 500;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  margin-top: $spacing-sm;
  overflow: hidden;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.metric-grid--compact {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.metric-cell {
  min-height: 128rpx;
  box-sizing: border-box;
  padding: $spacing-md;
  border-right: 1rpx solid $color-border;
  border-bottom: 1rpx solid $color-border;
}

.metric-value {
  font-size: 32rpx;
  font-weight: 600;
}

.error-card { margin-top: $spacing-sm; }
.retry-btn { width: 260rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
