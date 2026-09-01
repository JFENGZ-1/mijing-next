<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { fetchReportChangeLog } from "@/api/reports";
import type { ReportChangeLogItem } from "@/api/reports";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const items = ref<ReportChangeLogItem[]>([]);
const categories = ref<{ key: string; label: string }[]>([
  { key: "all", label: "全部" },
  { key: "issue", label: "发卡" },
  { key: "holiday", label: "请假" },
  { key: "freeze", label: "停卡" },
  { key: "archive", label: "删卡" },
  { key: "adjust", label: "调整" },
]);
const activeCategory = ref("all");
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const requestSeq = ref(0);
const loadedQueryKey = ref("");

const activeTabIndex = computed(() => categories.value.findIndex((item) => item.key === activeCategory.value));
const canView = computed(() => session.can("report.read"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

function currentQueryKey() {
  return JSON.stringify([session.currentSiteId, activeCategory.value]);
}

async function load(reset = true) {
  const siteId = session.currentSiteId;
  if (!siteId || !canView.value) {
    requestSeq.value += 1;
    loading.value = false;
    loadingMore.value = false;
    return;
  }
  const category = activeCategory.value;
  const queryKey = currentQueryKey();
  if (!reset && (loading.value || loadingMore.value || page.value >= lastPage.value || loadedQueryKey.value !== queryKey)) return;
  const requestId = ++requestSeq.value;
  const requestedPage = reset ? 1 : page.value + 1;
  if (reset) {
    page.value = 1;
    loading.value = true;
    errorMessage.value = "";
    items.value = [];
    total.value = 0;
    lastPage.value = 1;
    loadedQueryKey.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const response = await fetchReportChangeLog(siteId, {
      category,
      page: requestedPage,
      perPage: 20,
    });
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    items.value = reset ? response.items : [...items.value, ...response.items];
    if (response.categories?.length) categories.value = response.categories;
    page.value = requestedPage;
    total.value = response.pagination.total;
    lastPage.value = response.pagination.lastPage;
    loadedQueryKey.value = queryKey;
  } catch (error) {
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    const message = error instanceof Error ? error.message : "变更记录加载失败";
    if (reset) errorMessage.value = message;
    else uni.showToast({ title: message, icon: "none" });
  } finally {
    if (requestId === requestSeq.value) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

function switchCategory(index: number) {
  const target = categories.value[index];
  if (!target || target.key === activeCategory.value) return;
  activeCategory.value = target.key;
  void load();
}

function formatTime(value: string | null) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const pad = (input: number) => String(input).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function categoryColor(category: string) {
  if (category === "issue") return "#22c788";
  if (category === "holiday") return "#5fa3ea";
  if (category === "freeze") return "#f88302";
  if (category === "archive") return "#dc3c5c";
  return "#989898";
}

function deltaText(item: ReportChangeLogItem) {
  const parts: string[] = [];
  if (item.amountDelta && Number.parseFloat(item.amountDelta) !== 0) parts.push(`金额 ${item.amountDelta}`);
  if (item.countDelta) parts.push(`次数 ${item.countDelta > 0 ? "+" : ""}${item.countDelta}`);
  return parts.join(" · ");
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});

onReachBottom(async () => {
  if (loading.value || loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <view class="report-head"><view><text class="eyebrow">操作审计</text><text class="page-title">会员卡变更</text><text class="site-name">{{ currentSiteName }}</text></view><text class="total-badge">{{ total }} 条</text></view>
    <u-empty v-if="!canView" mode="permission" text="暂无变更记录权限" />
    <template v-else>
    <view v-if="errorMessage" class="error-card"><u-alert type="error" :description="errorMessage" /><button class="retry-btn" @tap="load()">重新加载</button></view>

    <u-tabs
      :list="categories.map((item) => ({ name: item.label }))"
      :current="activeTabIndex"
      @change="switchCategory"
    />

    <view class="total-line">共 {{ total }} 条记录</view>

    <view v-if="!errorMessage && items.length" class="log-list">
      <view v-for="item in items" :key="item.id" class="log-card">
        <view class="log-head">
          <view class="log-type" :style="{ background: categoryColor(item.category) }">{{ item.entryLabel }}</view>
          <text class="log-time">{{ formatTime(item.occurredAt) }}</text>
        </view>
        <view class="log-main">
          <text class="log-member">{{ item.memberName || "会员" }}</text>
          <text class="log-card-name">{{ item.cardName || "会员卡" }}<template v-if="item.cardNo">（{{ item.cardNo }}）</template></text>
        </view>
        <text v-if="deltaText(item)" class="log-delta">{{ deltaText(item) }}</text>
        <view class="log-foot">
          <text class="log-operator">操作人：{{ item.actorStaffName || "-" }}</text>
          <text v-if="item.reason" class="log-reason">{{ item.reason }}</text>
        </view>
      </view>
      <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" />
    </view>
    <view v-else-if="!errorMessage" class="nodata-box">
      <text class="sg-empty-text">暂无变更记录</text>
    </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.report-head { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; margin-bottom: 18rpx; }
.eyebrow, .page-title, .site-name { display: block; }
.eyebrow { color: $color-primary; font-size: 20rpx; font-weight: 600; letter-spacing: 3rpx; }
.page-title { margin-top: 5rpx; font-size: 36rpx; font-weight: 650; }
.site-name { margin-top: 7rpx; color: $color-text-tertiary; font-size: 21rpx; }
.total-badge { padding: 8rpx 15rpx; color: $color-text-secondary; background: #fff; border-radius: $radius-pill; font-size: 21rpx; }
.total-line {
  margin: $spacing-sm 4rpx;
  color: $color-text-tertiary;
  font-size: 22rpx;
}

.log-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
}

.log-card {
  padding: $spacing-md;
  background: $color-surface;
  border-radius: $radius-md;
}

.log-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.log-type {
  padding: 4rpx 16rpx;
  border-radius: 8rpx;
  color: #fff;
  font-size: 22rpx;
}

.log-time {
  color: $color-text-disabled;
  font-size: 22rpx;
}

.log-main {
  display: flex;
  align-items: baseline;
  gap: 16rpx;
  margin-top: 16rpx;
}

.log-member {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.log-card-name {
  color: $color-text-secondary;
  font-size: 24rpx;
}

.log-delta {
  display: block;
  margin-top: 10rpx;
  color: $color-primary;
  font-size: 24rpx;
}

.log-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
  margin-top: 12rpx;
}

.log-operator {
  color: $color-text-tertiary;
  font-size: 22rpx;
}

.log-reason {
  overflow: hidden;
  flex: 1;
  color: $color-text-disabled;
  font-size: 22rpx;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.nodata-box {
  padding: 120rpx 0;
}

.error-card { margin-bottom: 18rpx; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
