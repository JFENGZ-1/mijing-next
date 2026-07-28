<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onReachBottom } from "@dcloudio/uni-app";
import { adjustMemberPoints, fetchMemberPointLedger } from "@/api/points";
import type { MemberPointLedgerItem } from "@/api/points";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const submitting = ref(false);
const errorMessage = ref("");
const memberId = ref(0);
const totalPoint = ref(0);
const items = ref<MemberPointLedgerItem[]>([]);
const page = ref(1);
const lastPage = ref(1);

const showAdjust = ref(false);
const adjustDirection = ref<"credit" | "debit">("credit");
const adjustAmount = ref("");
const adjustReason = ref("");

const canAdjust = computed(() => session.can("points.adjust"));

async function load(reset = true) {
  if (!session.currentSiteId || !memberId.value) {
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
    const response = await fetchMemberPointLedger(session.currentSiteId, memberId.value, requestedPage, 20);
    totalPoint.value = response.totalPoint;
    items.value = reset ? response.items : [...items.value, ...response.items];
    page.value = requestedPage;
    lastPage.value = response.pagination.lastPage;
  } catch (error) {
    const message = error instanceof Error ? error.message : "积分明细加载失败";
    if (reset) errorMessage.value = message;
    else uni.showToast({ title: message, icon: "none" });
  } finally {
    loading.value = false;
    loadingMore.value = false;
  }
}

function openAdjust(direction: "credit" | "debit") {
  adjustDirection.value = direction;
  adjustAmount.value = "";
  adjustReason.value = "";
  showAdjust.value = true;
}

async function submitAdjust() {
  const amount = Number.parseInt(adjustAmount.value, 10);
  if (!Number.isInteger(amount) || amount < 1) {
    uni.showToast({ title: "请输入正整数积分", icon: "none" });
    return;
  }
  if (!adjustReason.value.trim()) {
    uni.showToast({ title: "请填写原因", icon: "none" });
    return;
  }
  if (!session.currentSiteId || !memberId.value) return;
  submitting.value = true;
  try {
    const result = await adjustMemberPoints(session.currentSiteId, memberId.value, {
      direction: adjustDirection.value,
      amount,
      reason: adjustReason.value.trim(),
      commandKey: createCommandKey(),
    });
    totalPoint.value = result.totalPoint;
    showAdjust.value = false;
    uni.showToast({ title: "已保存", icon: "success" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "积分调整失败", icon: "none" });
  } finally {
    submitting.value = false;
  }
}

function formatTime(value: string | null) {
  if (!value) return "";
  return value.slice(0, 16).replace("T", " ");
}

onLoad(async (query) => {
  memberId.value = Number(query?.id ?? 0);
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

    <!-- 积分余额卡（对标原版 memberPoint 顶部） -->
    <view class="points-card">
      <text class="points-total">{{ totalPoint }}</text>
      <text class="points-label">当前积分</text>
      <view v-if="canAdjust" class="adjust-row">
        <button class="adjust-btn credit" @click="openAdjust('credit')">加分</button>
        <button class="adjust-btn debit" @click="openAdjust('debit')">减分</button>
      </view>
    </view>

    <!-- 积分流水 -->
    <view class="section-title">积分明细</view>
    <view v-if="items.length" class="ledger-list">
      <view v-for="item in items" :key="item.id" class="ledger-row">
        <view class="ledger-main">
          <text class="ledger-title">{{ item.title || item.reason || "积分变动" }}</text>
          <text class="ledger-time">{{ formatTime(item.createdAt) }}</text>
        </view>
        <text class="ledger-delta" :class="item.direction">
          {{ item.amountDelta > 0 ? "+" : "" }}{{ item.amountDelta }}
        </text>
      </view>
      <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" />
    </view>
    <view v-else class="nodata-box">
      <text class="sg-empty-text">暂无积分记录</text>
    </view>

    <!-- 调整弹窗 -->
    <u-popup :show="showAdjust" mode="bottom" round="20" @close="showAdjust = false">
      <view class="adjust-popup">
        <text class="popup-title">{{ adjustDirection === "credit" ? "增加积分" : "扣减积分" }}</text>
        <u-input v-model="adjustAmount" type="number" placeholder="积分数量（正整数）" border="bottom" />
        <u-input v-model="adjustReason" placeholder="原因（必填）" border="bottom" />
        <button class="sg-btn-primary popup-submit" :disabled="submitting" @click="submitAdjust">
          {{ submitting ? "提交中..." : "确认" }}
        </button>
      </view>
    </u-popup>
  </view>
</template>

<style scoped lang="scss">
.points-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 56rpx 24rpx 40rpx;
  background: $color-surface;
  border-radius: $radius-lg;
}

.points-total {
  color: $color-primary;
  font-size: 80rpx;
  font-weight: 600;
  line-height: 80rpx;
}

.points-label {
  margin-top: 14rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.adjust-row {
  display: flex;
  gap: 24rpx;
  margin-top: 36rpx;
}

.adjust-btn {
  margin: 0;
  width: 200rpx;
  height: 72rpx;
  line-height: 72rpx;
  font-size: 28rpx;
  border-radius: 36rpx;
  color: #fff;

  &.credit {
    background: $color-success;
  }

  &.debit {
    background: $color-danger;
  }
}

.adjust-btn::after {
  border: 0;
}

.ledger-list {
  background: $color-surface;
  border-radius: $radius-lg;
  padding: 0 $spacing-md;
}

.ledger-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24rpx 0;
  border-bottom: 1rpx solid $color-page;

  &:last-of-type {
    border-bottom: none;
  }
}

.ledger-main {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.ledger-title {
  font-size: 28rpx;
  color: $color-text;
}

.ledger-time {
  margin-top: 8rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
}

.ledger-delta {
  font-size: 32rpx;
  font-weight: 600;

  &.credit {
    color: $color-success;
  }

  &.debit {
    color: $color-danger;
  }
}

.nodata-box {
  padding: 100rpx 0;
}

.adjust-popup {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}

.popup-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.popup-submit {
  margin-top: 16rpx;
  border: none;
}

.popup-submit::after {
  border: 0;
}
</style>
