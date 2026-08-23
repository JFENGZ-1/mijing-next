<script setup lang="ts">
/** 对标原版 cardToolbox/member-details：从列表点卡图标弹出的会员卡抽屉 */
import { ref, watch } from "vue";
import { fetchCrmMember, fetchMemberCards } from "@/api/crm";
import type { CrmMember, StaffMemberCardSummary } from "@/types/crm";
import { useSessionStore } from "@/stores/session";

const props = defineProps<{
  show: boolean;
  memberId: number | null;
}>();

const emit = defineEmits<{
  (e: "update:show", value: boolean): void;
  (e: "close"): void;
}>();

const session = useSessionStore();
const loading = ref(false);
const member = ref<CrmMember | null>(null);
const cards = ref<StaffMemberCardSummary[]>([]);
const errorMessage = ref("");

watch(
  () => [props.show, props.memberId] as const,
  ([visible, id]) => {
    if (visible && id) void open(id);
    if (!visible) {
      member.value = null;
      cards.value = [];
      errorMessage.value = "";
    }
  },
);

async function open(id: number) {
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const [memberRes, cardsRes] = await Promise.all([
      fetchCrmMember(session.currentSiteId, id),
      fetchMemberCards(session.currentSiteId, id).catch(() => ({ data: [] as StaffMemberCardSummary[] })),
    ]);
    member.value = memberRes.data;
    cards.value = cardsRes.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员卡加载失败";
  } finally {
    loading.value = false;
  }
}

function close() {
  emit("update:show", false);
  emit("close");
}

function cardBalanceText(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") return card.cachedBalance ? `余额 ${card.cachedBalance} 元` : "余额 —";
  if (card.cardType === "count") return card.cachedRemainingCount != null ? `剩余 ${card.cachedRemainingCount} 次` : "剩余 —";
  return card.validUntil ? `有效期至 ${card.validUntil}` : "期限卡";
}

function cardStatusLabel(status: string) {
  return ({
    pending: "待激活",
    active: "有效",
    frozen: "冻结",
    expired: "已过期",
    archived: "已归档",
    voided: "已作废",
    exhausted: "已用尽",
  } as Record<string, string>)[status] || status;
}

function openCardDetail(card: StaffMemberCardSummary) {
  if (!props.memberId) return;
  close();
  uni.navigateTo({
    url: `/pages/members/card-detail?memberId=${props.memberId}&memberCardId=${card.id}`,
  });
}

function openMemberDetail() {
  if (!props.memberId) return;
  close();
  uni.navigateTo({ url: `/pages/members/detail?id=${props.memberId}` });
}

function issueCard() {
  if (!props.memberId) return;
  close();
  uni.navigateTo({ url: `/pages/members/issue-card?memberId=${props.memberId}` });
}
</script>

<template>
  <u-popup :show="show" mode="bottom" round="20" :safe-area-inset-bottom="true" :z-index="10090" @close="close">
    <view class="sheet">
      <view class="sheet-head">
        <view class="head-main" @tap="openMemberDetail">
          <text class="title">{{ member?.name || "会员卡" }}</text>
          <text class="sub">{{ member?.mobileMasked || "未留手机号" }}</text>
        </view>
        <view class="close" @tap="close">
          <u-icon name="close" size="18" color="#989898" />
        </view>
      </view>

      <view v-if="loading" class="loading-wrap">
        <u-loading-icon size="28" color="#ed920f" />
      </view>
      <view v-else-if="errorMessage" class="error-wrap">
        <u-alert type="error" :description="errorMessage" />
      </view>
      <scroll-view v-else scroll-y class="sheet-body" :enhanced="true" :show-scrollbar="false">
        <view v-if="cards.length === 0" class="empty">
          <text class="empty-title">暂无会员卡</text>
          <text class="empty-hint">可为该会员发卡后在此管理</text>
        </view>
        <view
          v-for="card in cards"
          :key="card.id"
          class="card-row"
          @tap="openCardDetail(card)"
        >
          <view class="card-main">
            <text class="card-name">{{ card.name || card.cardNo || "会员卡" }}</text>
            <text class="card-meta">{{ cardBalanceText(card) }}</text>
          </view>
          <view class="card-side">
            <text class="card-status">{{ cardStatusLabel(card.status) }}</text>
            <u-icon name="arrow-right" size="14" color="#c8c9cc" />
          </view>
        </view>
      </scroll-view>

      <view class="sheet-foot">
        <button class="ghost-btn" @tap="openMemberDetail">会员档案</button>
        <button class="primary-btn" @tap="issueCard">发卡</button>
      </view>
    </view>
  </u-popup>
</template>

<style scoped lang="scss">
.sheet {
  display: flex;
  flex-direction: column;
  height: 70vh;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
}

.sheet-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 36rpx 32rpx 20rpx;
}

.head-main {
  flex: 1;
  min-width: 0;
}

.title {
  display: block;
  color: #181818;
  font-size: 34rpx;
  font-weight: 600;
}

.sub {
  display: block;
  margin-top: 8rpx;
  color: #989898;
  font-size: 24rpx;
}

.close {
  padding: 8rpx;
}

.loading-wrap,
.error-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 48rpx;
}

.sheet-body {
  flex: 1;
  height: 0;
  padding: 0 32rpx;
  box-sizing: border-box;
}

.empty {
  padding: 80rpx 0;
  text-align: center;
}

.empty-title {
  display: block;
  color: #181818;
  font-size: 28rpx;
}

.empty-hint {
  display: block;
  margin-top: 12rpx;
  color: #989898;
  font-size: 24rpx;
}

.card-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx 0;
  border-bottom: 1rpx solid #f0f0f0;
}

.card-main {
  flex: 1;
  min-width: 0;
}

.card-name {
  display: block;
  color: #181818;
  font-size: 28rpx;
}

.card-meta {
  display: block;
  margin-top: 8rpx;
  color: #989898;
  font-size: 22rpx;
}

.card-side {
  display: flex;
  align-items: center;
  gap: 8rpx;
  flex-shrink: 0;
  margin-left: 16rpx;
}

.card-status {
  color: #ed920f;
  font-size: 22rpx;
}

.sheet-foot {
  display: flex;
  gap: 20rpx;
  padding: 20rpx 32rpx calc(20rpx + env(safe-area-inset-bottom));
  border-top: 1rpx solid #f0f0f0;
}

.ghost-btn,
.primary-btn {
  flex: 1;
  height: 80rpx;
  line-height: 80rpx;
  margin: 0;
  border-radius: 40rpx;
  font-size: 28rpx;
}

.ghost-btn {
  color: #181818;
  background: #f5f5f5;
}

.primary-btn {
  color: #181818;
  background: #fbd128;
  font-weight: 600;
}

.ghost-btn::after,
.primary-btn::after {
  border: 0;
}
</style>
