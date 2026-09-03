<script setup lang="ts">
/** 对标原版 pageMember/del-card/del-card：删除的卡 */
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchArchivedMemberCards, restoreMemberCard } from "@/api/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffMemberCardSummary } from "@/types/crm";
import { createCommandKey } from "@/utils/command-key";
import CustomNav from "@/components/custom-nav/custom-nav.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<StaffMemberCardSummary[]>([]);
const page = ref(1);
const lastPage = ref(1);
const title = ref("删除的卡");
const memberId = ref<number>();
const restoringId = ref<number | null>(null);
const showRestoreConfirm = ref(false);

const canView = computed(() => session.can("member-card.archive") || session.can("member-card.read"));
const canRestore = computed(() => session.can("member-card.archive"));

async function load(reset = true) {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }
  loading.value = reset;
  errorMessage.value = "";
  try {
    const requestedPage = reset ? 1 : page.value + 1;
    const response = await fetchArchivedMemberCards(session.currentSiteId, requestedPage);
    // 详情页进入时尽量只展示该会员相关卡（若摘要含 memberId）
    const next = memberId.value
      ? response.items.filter((item) => (item as { memberId?: number }).memberId == null || (item as { memberId?: number }).memberId === memberId.value)
      : response.items;
    items.value = reset ? next : [...items.value, ...next];
    page.value = requestedPage;
    lastPage.value = response.pagination.lastPage;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "删除的卡加载失败";
    if (reset) items.value = [];
  } finally {
    loading.value = false;
    uni.stopPullDownRefresh();
  }
}

function cardFaceStyle(card: StaffMemberCardSummary) {
  if (card.faceGradient) return { background: card.faceGradient };
  return { background: "linear-gradient(135deg, #c0c0c0 0%, #8a8a8a 100%)" };
}

function cardBalanceText(card: StaffMemberCardSummary) {
  if (card.cardType === "stored_value") return card.cachedBalance ? `余额 ${card.cachedBalance}` : "余额 —";
  if (card.cardType === "count") return card.cachedRemainingCount != null ? `剩余 ${card.cachedRemainingCount} 次` : "剩余 —";
  return card.validUntil ? `至 ${card.validUntil}` : "";
}

function openCard(cardId: number) {
  const mid = memberId.value ? `&memberId=${memberId.value}` : "";
  uni.navigateTo({ url: `/pages/members/card-detail?memberCardId=${cardId}${mid}` });
}

function askRestore(cardId: number) {
  if (!canRestore.value) return;
  restoringId.value = cardId;
  showRestoreConfirm.value = true;
}

async function confirmRestore() {
  if (!session.currentSiteId || !restoringId.value) return;
  try {
    await restoreMemberCard(session.currentSiteId, restoringId.value, {
      commandKey: createCommandKey(),
      reason: "恢复已删除会员卡",
    });
    uni.showToast({ title: "已恢复", icon: "none" });
    showRestoreConfirm.value = false;
    restoringId.value = null;
    await load(true);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "恢复失败", icon: "none" });
  }
}

onLoad((query) => {
  if (query?.memberId) memberId.value = Number(query.memberId);
  if (query?.title) title.value = decodeURIComponent(String(query.title));
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(() => load());
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page">
    <CustomNav :text="title" bg="#FBD128" />
    <view class="card-container">
      <u-empty v-if="!canView" mode="permission" text="暂无查看权限" />
      <template v-else>
        <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
        <view class="content">
          <view v-if="items.length" class="card-list">
            <view class="top-info">
              <text class="total-num">共删除{{ items.length }}张</text>
            </view>
            <view v-for="item in items" :key="item.id" class="card-type-box">
              <view class="card-wrap" @tap="openCard(item.id)">
                <view class="member-card-face" :style="cardFaceStyle(item)">
                  <view class="card-face-top">
                    <text class="card-face-name">{{ item.name || "会员卡" }}</text>
                    <text class="card-face-status">已删除</text>
                  </view>
                  <view class="card-face-bottom">
                    <text>{{ cardBalanceText(item) }}</text>
                    <text>{{ item.cardNo }}</text>
                  </view>
                </view>
              </view>
              <view v-if="canRestore" class="right-box">
                <button class="delete-btn" plain @tap.stop="askRestore(item.id)">恢复</button>
              </view>
            </view>
            <u-loadmore :status="page >= lastPage ? 'nomore' : 'loadmore'" @loadmore="load(false)" />
          </view>
          <view v-else class="card-empty">
            <u-icon name="coupon" size="72" color="#dadada" />
            <text class="tips-text">没有删除的会员卡哦</text>
          </view>
        </view>
        <FfBottomLogo />
      </template>
    </view>

    <u-modal
      :show="showRestoreConfirm"
      title="确定恢复吗？"
      content="点击确定后即可恢复"
      showCancelButton
      confirmText="确定"
      cancelText="取消"
      @confirm="confirmRestore"
      @cancel="showRestoreConfirm = false"
      @close="showRestoreConfirm = false"
    />
  </view>
</template>

<style scoped lang="scss">
.page {
  min-height: 100vh;
  background: #f5f5f5;
}

.card-container {
  min-height: 100vh;
  padding-top: calc(var(--status-bar-height, 20px) + 44px);
}

.content {
  min-height: 1230rpx;
}

.top-info {
  padding: 30rpx 40rpx 10rpx;
}

.total-num {
  color: #181818;
  font-size: 28rpx;
  font-weight: 500;
}

.card-type-box {
  display: flex;
  align-items: center;
  padding: 20rpx 28rpx;
}

.card-wrap {
  flex: 1;
  min-width: 0;
}

.member-card-face {
  position: relative;
  box-sizing: border-box;
  width: 100%;
  height: 220rpx;
  padding: 24rpx 28rpx;
  border-radius: 16rpx;
  color: #fff;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
  filter: grayscale(0.2);
}

.card-face-top {
  display: flex;
  justify-content: space-between;
}

.card-face-name {
  max-width: 70%;
  font-size: 30rpx;
  font-weight: 600;
}

.card-face-status {
  padding: 2rpx 12rpx;
  border-radius: 16rpx;
  background: rgba(0, 0, 0, 0.2);
  font-size: 20rpx;
}

.card-face-bottom {
  position: absolute;
  right: 28rpx;
  bottom: 24rpx;
  left: 28rpx;
  display: flex;
  justify-content: space-between;
  font-size: 22rpx;
}

.right-box {
  margin-left: 16rpx;
}

.delete-btn {
  margin: 0;
  padding: 0 22rpx;
  height: 56rpx;
  line-height: 56rpx;
  border: 1rpx solid #ed920f !important;
  border-radius: 28rpx;
  color: #ed920f !important;
  font-size: 24rpx;
  background: transparent;
}

.delete-btn::after {
  border: 0;
}

.card-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20rpx;
  padding: 160rpx 0;
  color: #bfbfbf;
  font-size: 26rpx;
}
</style>
