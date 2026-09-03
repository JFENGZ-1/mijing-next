<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onReachBottom, onShow } from "@dcloudio/uni-app";
import { fetchConsumptionSettlements } from "@/api/consumption";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type {
  ConsumptionDimension,
  ConsumptionSettlementFilterStatus,
  ConsumptionSettlementListItem,
  ConsumptionSettlementSummary,
} from "@/types/consumption";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const errorMessage = ref("");
const keyword = ref("");
type ConsumptionView = "detail" | ConsumptionDimension;
const dimension = ref<ConsumptionView>("detail");
const status = ref<ConsumptionSettlementFilterStatus | "">("");
const from = ref("");
const to = ref("");
const page = ref(1);
const lastPage = ref(1);
const items = ref<ConsumptionSettlementListItem[]>([]);
const summary = ref<ConsumptionSettlementSummary | null>(null);
const requestSeq = ref(0);
const loadedQueryKey = ref("");
const canRead = computed(() => session.can("consumption.read"));
const canManagePayroll = computed(() => session.can("payroll.period.close"));
const currentSiteName = computed(() => session.sites.find((site) => site.id === session.currentSiteId)?.name || "当前场馆");

const dimensions: Array<{ value: ConsumptionView; label: string }> = [
  { value: "detail", label: "明细" },
  { value: "coach", label: "A 履约人" },
  { value: "share", label: "B 分成" },
  { value: "member", label: "学员" },
  { value: "course", label: "课程" },
  { value: "card", label: "卡项" },
];
const canLoadMore = computed(() => page.value < lastPage.value);

function currentQueryKey() {
  return JSON.stringify([
    session.currentSiteId,
    dimension.value,
    from.value,
    to.value,
    keyword.value.trim(),
    status.value,
  ]);
}

function monthRange() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const pad = (value: number) => String(value).padStart(2, "0");
  from.value = `${year}-${pad(month + 1)}-01`;
  const last = new Date(year, month + 1, 0).getDate();
  to.value = `${year}-${pad(month + 1)}-${pad(last)}`;
}

async function load(reset = true) {
  const siteId = session.currentSiteId;
  if (!siteId || !canRead.value) {
    requestSeq.value += 1;
    loading.value = false;
    loadingMore.value = false;
    return;
  }
  const queryKey = currentQueryKey();
  if (!reset && (loading.value || loadingMore.value || !canLoadMore.value || loadedQueryKey.value !== queryKey)) return;
  const requestId = ++requestSeq.value;
  const requestedPage = reset ? 1 : page.value + 1;
  const query = {
    dimension: dimension.value === "detail" ? undefined : dimension.value,
    from: from.value,
    to: to.value,
    query: keyword.value.trim() || undefined,
    status: status.value || undefined,
    page: requestedPage,
    perPage: 20,
  };
  if (reset) {
    page.value = 1;
    loading.value = true;
    errorMessage.value = "";
    items.value = [];
    summary.value = null;
    lastPage.value = 1;
    loadedQueryKey.value = "";
  } else {
    loadingMore.value = true;
  }
  try {
    const response = await fetchConsumptionSettlements(siteId, query);
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    items.value = reset ? (response.items ?? []) : [...items.value, ...(response.items ?? [])];
    summary.value = response.summary ?? null;
    page.value = requestedPage;
    lastPage.value = response.pagination?.lastPage ?? 1;
    loadedQueryKey.value = queryKey;
  } catch (error) {
    if (requestId !== requestSeq.value || queryKey !== currentQueryKey()) return;
    if (reset) errorMessage.value = error instanceof Error ? error.message : "耗卡报表加载失败";
    else uni.showToast({ title: error instanceof Error ? error.message : "加载更多失败", icon: "none" });
  } finally {
    if (requestId === requestSeq.value) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

function changeDimension(value: ConsumptionView) {
  if (dimension.value === value) return;
  dimension.value = value;
  void load(true);
}

function chooseStatus() {
  const options: Array<{ value: ConsumptionSettlementFilterStatus | ""; label: string }> = [
    { value: "", label: "全部状态" },
    { value: "provisional", label: "待当日结算" },
    { value: "final", label: "已结算" },
    { value: "adjusted", label: "已调整" },
    { value: "reversed", label: "已冲正" },
  ];
  uni.showActionSheet({
    itemList: options.map((item) => item.label),
    success: ({ tapIndex }) => { status.value = options[tapIndex].value; void load(true); },
  });
}

function statusLabel(value: string) {
  return ({
    provisional: "待当日结算",
    pending_day_close: "待当日结算",
    final: "已结算",
    settled: "已结算",
    adjusted: "已调整",
    reversed: "已冲正",
  } as Record<string, string>)[value] || value;
}
function cardTypeLabel(value: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[value] || value;
}
function itemTitle(item: ConsumptionSettlementListItem) {
  return item.dimensionName || item.memberName || item.coachName || item.courseName
    || (item.dimensionKey != null ? `#${item.dimensionKey}` : `结算 #${item.id}`);
}
function itemSubtitle(item: ConsumptionSettlementListItem) {
  if (item.isAggregate) return `${item.consumptionCount ?? 0} 笔耗卡`;
  return [item.memberName, item.courseName, item.cardName].filter((value, index, values) => value && values.indexOf(value) === index).join(" · ");
}
function itemKey(item: ConsumptionSettlementListItem) {
  return item.isAggregate
    ? `${dimension.value}:${String(item.dimensionKey ?? item.dimensionName ?? "unknown")}`
    : `settlement:${item.id}`;
}
function openDetail(item: ConsumptionSettlementListItem) {
  if (item.isAggregate || !item.id) return;
  uni.navigateTo({ url: `/pages/report/consumption/detail?id=${item.id}` });
}
function openPeriods() { uni.navigateTo({ url: "/pages/report/payroll-periods/index" }); }
function setFrom(event: { detail: { value: string } }) {
  const value = event.detail.value;
  if (to.value && value > to.value) {
    uni.showToast({ title: "开始日期不能晚于结束日期", icon: "none" });
    return;
  }
  from.value = value;
  void load(true);
}
function setTo(event: { detail: { value: string } }) {
  const value = event.detail.value;
  if (from.value && value < from.value) {
    uni.showToast({ title: "结束日期不能早于开始日期", icon: "none" });
    return;
  }
  to.value = value;
  void load(true);
}

onShow(async () => {
  if (!from.value) monthRange();
  if (await requireStaffAuth()) await load(true);
});
onPullDownRefresh(async () => { await load(true); uni.stopPullDownRefresh(); });
onReachBottom(() => { void load(false); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canRead" class="page-container consumption-page">
    <view class="summary-card">
      <view class="summary-head"><view><text class="summary-eyebrow">经营结算</text><text class="summary-title">耗卡与提成</text><text class="summary-scope">{{ currentSiteName }} · {{ from }} 至 {{ to }}</text></view><text v-if="canManagePayroll" class="period-link" @tap="openPeriods">月结 / 关账</text></view>
      <view class="summary-grid">
        <view><text class="summary-value">{{ summary?.consumptionCount ?? "—" }}</text><text class="summary-label">耗卡次数</text></view>
        <view><text class="summary-value money">{{ summary ? `¥${summary.consumptionValue}` : "—" }}</text><text class="summary-label">耗卡价值</text></view>
        <view><text class="summary-value">{{ summary ? `¥${summary.sessionFee}` : "—" }}</text><text class="summary-label">课时费</text></view>
        <view><text class="summary-value">{{ summary ? `¥${summary.commissionAmount}` : "—" }}</text><text class="summary-label">提成</text></view>
      </view>
      <text v-if="summary?.pendingCount" class="pending-hint">其中 {{ summary.pendingCount }} 笔待结算</text>
    </view>

    <view v-if="errorMessage" class="error-card">
      <u-alert type="error" :description="errorMessage" />
      <button class="retry-btn" @tap="load(true)">重新加载</button>
    </view>

    <scroll-view scroll-x class="dimension-scroll" :show-scrollbar="false">
      <view class="dimension-tabs">
        <view v-for="tab in dimensions" :key="tab.value" class="dimension-tab" :class="{ active: dimension === tab.value }" @tap="changeDimension(tab.value)">{{ tab.label }}</view>
      </view>
    </scroll-view>

    <view class="filter-card">
      <u-search v-model="keyword" placeholder="搜索教练、角色、学员、课程或卡项" :show-action="true" action-text="查询" bg-color="#f5f5f5" @search="load(true)" @custom="load(true)" />
      <view class="filter-row">
        <picker mode="date" :value="from" @change="setFrom"><view class="filter-pill">{{ from }}</view></picker>
        <text class="dash">至</text>
        <picker mode="date" :value="to" @change="setTo"><view class="filter-pill">{{ to }}</view></picker>
        <view class="filter-pill status-pill" @tap="chooseStatus">{{ status ? statusLabel(status) : "全部状态" }}</view>
      </view>
    </view>

    <view v-if="!errorMessage && items.length" class="settlement-list">
      <view v-for="item in items" :key="itemKey(item)" class="settlement-row" :class="{ aggregate: item.isAggregate }" @tap="openDetail(item)">
        <view class="row-head">
          <text class="row-title">{{ itemTitle(item) }}</text>
          <text v-if="!item.isAggregate && item.settlementStatus" class="status-tag" :class="item.settlementStatus">{{ statusLabel(item.settlementStatus) }}</text>
          <text v-else-if="item.isAggregate" class="aggregate-tag">维度汇总</text>
        </view>
        <text class="row-subtitle">{{ itemSubtitle(item) }}</text>
        <view class="row-meta">
          <text v-if="!item.isAggregate && item.cardType">{{ cardTypeLabel(item.cardType) }}</text>
          <text v-if="item.consumptionValue != null" class="money">耗卡 ¥{{ item.consumptionValue }}</text>
          <text v-if="item.sessionFee != null">课时费 ¥{{ item.sessionFee }}</text>
          <text v-if="item.commissionAmount != null">提成 ¥{{ item.commissionAmount }}</text>
        </view>
        <view v-if="!item.isAggregate" class="row-foot">
          <text>{{ item.businessDate || item.occurredAt?.slice(0, 10) || "—" }}</text>
          <text>规则 v{{ item.ruleVersion ?? "—" }}</text>
        </view>
      </view>
    </view>
    <u-empty v-else-if="!errorMessage" mode="data" text="当前筛选暂无耗卡结算" />
    <view v-if="loadingMore" class="load-more">加载中…</view>
    <view v-else-if="items.length && !canLoadMore" class="load-more">已加载全部</view>
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无耗卡结算查看权限" />
</template>

<style scoped lang="scss">
.consumption-page { padding-bottom: 60rpx; }
.summary-card, .filter-card, .settlement-row { background: #fff; border-radius: $radius-lg; }
.summary-card { padding: 28rpx 24rpx; }
.summary-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18rpx; }
.summary-eyebrow, .summary-title, .summary-scope { display: block; }
.summary-eyebrow { color: $color-primary; font-size: 20rpx; font-weight: 600; letter-spacing: 3rpx; }
.summary-title { margin-top: 5rpx; font-size: 30rpx; font-weight: 600; }
.summary-scope { margin-top: 7rpx; color: $color-text-tertiary; font-size: 20rpx; font-weight: 400; }
.period-link { color: #5fa3ea; font-size: 23rpx; font-weight: 400; }
.summary-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24rpx 16rpx; margin-top: 26rpx; }
.summary-grid > view { display: flex; flex-direction: column; }
.summary-value { font-size: 34rpx; font-weight: 600; }
.summary-label { margin-top: 5rpx; color: $color-text-tertiary; font-size: 21rpx; }
.money { color: $color-primary; }
.pending-hint { display: block; margin-top: 20rpx; color: #8b6c00; font-size: 22rpx; }
.dimension-scroll { margin: 20rpx 0; white-space: nowrap; }
.dimension-tabs { display: inline-flex; gap: 12rpx; }
.dimension-tab { padding: 13rpx 26rpx; color: $color-text-secondary; background: #fff; border-radius: 999rpx; font-size: 24rpx; }
.dimension-tab.active { color: $color-text; background: $color-brand-yellow; font-weight: 600; }
.filter-card { padding: 20rpx; }
.filter-row { display: flex; align-items: center; gap: 9rpx; margin-top: 16rpx; }
.filter-pill { padding: 11rpx 14rpx; color: $color-text-secondary; background: #f5f5f5; border-radius: 10rpx; font-size: 21rpx; }
.status-pill { margin-left: auto; }
.dash { color: $color-text-tertiary; font-size: 20rpx; }
.settlement-list { margin-top: 20rpx; }
.settlement-row { margin-bottom: 16rpx; padding: 24rpx 22rpx; }
.row-head, .row-meta, .row-foot { display: flex; align-items: center; gap: 13rpx; flex-wrap: wrap; }
.row-head { justify-content: space-between; }
.row-title { max-width: 480rpx; overflow: hidden; font-size: 28rpx; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; }
.status-tag { padding: 4rpx 11rpx; color: #8b6c00; background: #fff6c7; border-radius: 999rpx; font-size: 19rpx; }
.status-tag.settled { color: #168d61; background: #e8f8f1; }
.status-tag.adjusted { color: #3f76ad; background: #edf5ff; }
.status-tag.reversed { color: #a94156; background: #fdeef1; }
.aggregate-tag { padding: 4rpx 11rpx; color: #5d638c; background: #eff0f8; border-radius: 999rpx; font-size: 19rpx; }
.settlement-row.aggregate { cursor: default; }
.row-subtitle { display: block; margin-top: 8rpx; color: $color-text-secondary; font-size: 22rpx; }
.row-meta { margin-top: 14rpx; color: $color-text-secondary; font-size: 21rpx; }
.row-foot { justify-content: space-between; margin-top: 15rpx; padding-top: 13rpx; color: $color-text-tertiary; border-top: 1rpx solid #f2f2f2; font-size: 20rpx; }
.load-more { padding: 20rpx; color: $color-text-disabled; font-size: 22rpx; text-align: center; }
.error-card { margin-top: 18rpx; }
.retry-btn { width: 220rpx; height: 64rpx; margin: 18rpx 0 0; color: $color-primary; background: #fff; border: 1rpx solid rgba(237,146,15,.35); border-radius: 32rpx; font-size: 23rpx; line-height: 62rpx; }
.retry-btn::after { border: 0; }
</style>
