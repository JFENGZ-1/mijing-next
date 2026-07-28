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

const activeTabIndex = computed(() => categories.value.findIndex((item) => item.key === activeCategory.value));

async function load(reset = true) {
  if (!session.currentSiteId) {
    loading.value = false;
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
    const response = await fetchReportChangeLog(session.currentSiteId, {
      category: activeCategory.value,
      page: requestedPage,
      perPage: 20,
    });
    items.value = reset ? response.items : [...items.value, ...response.items];
    if (response.categories?.length) categories.value = response.categories;
    page.value = requestedPage;
    total.value = response.pagination.total;
    lastPage.value = response.pagination.lastPage;
  } catch (error) {
    const message = error instanceof Error ? error.message : "变更记录加载失败";
    if (reset) errorMessage.value = message;
    else uni.showToast({ title: message, icon: "none" });
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function switchCategory(index: number) {
  const target = categories.value[index];
  if (!target || target.key === activeCategory.value) return;
  activeCategory.value = target.key;
  load();
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
  if (loadingMore.value || page.value >= lastPage.value) return;
  await load(false);
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <u-tabs
      :list="categories.map((item) => ({ name: item.label }))"
      :current="activeTabIndex"
      @change="switchCategory"
    />

    <view class="total-line">共 {{ total }} 条记录</view>

    <view v-if="items.length" class="log-list">
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
    <view v-else class="nodata-box">
      <text class="sg-empty-text">暂无变更记录</text>
    </view>
  </view>
</template>

<style scoped lang="scss">
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
</style>
