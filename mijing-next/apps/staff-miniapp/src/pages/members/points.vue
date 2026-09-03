<script setup lang="ts">
/** 对标原版 pageMember/details/memberPoint */
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onReachBottom } from "@dcloudio/uni-app";
import { adjustMemberPoints, fetchMemberPointLedger } from "@/api/points";
import type { MemberPointLedgerItem } from "@/api/points";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import { createCommandKey } from "@/utils/command-key";
import CustomNav from "@/components/custom-nav/custom-nav.vue";
import FfBottomLogo from "@/components/ff-bottom-logo/ff-bottom-logo.vue";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";

const session = useSessionStore();
const loading = ref(true);
const loadingMore = ref(false);
const submitting = ref(false);
const errorMessage = ref("");
const memberId = ref(0);
const userName = ref("会员");
const userFaceurl = ref("");
const totalPoint = ref(0);
const items = ref<MemberPointLedgerItem[]>([]);
const page = ref(1);
const lastPage = ref(1);

const showAdjust = ref(false);
const adjustDirection = ref<"credit" | "debit">("credit");
const adjustAmount = ref("");
const adjustReason = ref("");

const canAdjust = computed(() => session.can("points.adjust"));
const adjustTitle = computed(() => (adjustDirection.value === "credit" ? "加积分" : "减积分"));

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
  if (!value) return "--";
  return value.slice(0, 16).replace("T", " ");
}

onLoad(async (query) => {
  memberId.value = Number(query?.id ?? 0);
  userName.value = decodeURIComponent(String(query?.name ?? "会员"));
  userFaceurl.value = decodeURIComponent(String(query?.avatar ?? ""));
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
  <view v-if="!loading" class="page">
    <CustomNav :text="userName" bg="rgba(255,255,255,0)" :head-url="userFaceurl" />
    <view class="main">
      <view class="head">
        <view class="summarize">
          <view class="num">{{ totalPoint }}</view>
          <view class="num-text">有效积分</view>
          <view v-if="canAdjust" class="edit-point">
            <view class="but" @tap.stop="openAdjust('credit')">
              <u-icon name="plus-circle-fill" size="18" color="#22C788" />
              <text>加积分</text>
            </view>
            <view class="but" @tap.stop="openAdjust('debit')">
              <u-icon name="minus-circle-fill" size="18" color="#DC3C5C" />
              <text>减积分</text>
            </view>
          </view>
        </view>
      </view>

      <view class="content">
        <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
        <view class="setting_wrap">
          <view class="list">
            <template v-if="items.length">
              <view v-for="(item, index) in items" :key="item.id" class="sale-item">
                <view class="detail-info-wrap">
                  <view class="l-info">
                    <view class="l-info-head">{{ item.title || item.reason || "积分变动" }}</view>
                    <view class="time-and-type">{{ formatTime(item.createdAt) }}</view>
                  </view>
                  <view class="r-info">
                    <view class="day-num" :class="{ redtext: item.amountDelta < 0 }">
                      <text v-if="item.amountDelta > 0">+</text>{{ item.amountDelta }}
                    </view>
                    <view class="day-num1">
                      <text :class="item.amountDelta > 0 ? 'greentext' : 'redtext'">{{ item.reason || "" }}</text>
                    </view>
                  </view>
                </view>
                <u-line v-if="index < items.length - 1" color="#F0F0F0" margin="24rpx 0 0 18rpx" />
              </view>
              <u-loadmore :status="page >= lastPage ? 'nomore' : loadingMore ? 'loading' : 'loadmore'" />
            </template>
            <view v-else class="noCourseData">
              <u-icon name="integral" size="72" color="#dadada" />
              <text class="explain">~ 没有积分记录 ~</text>
            </view>
          </view>
        </view>
      </view>
      <FfBottomLogo />
    </view>

    <FfBottomSheet
      v-model:show="showAdjust"
      :title="adjustTitle"
      :height-rpx="720"
      confirm-text="确　定"
      :confirm-disabled="submitting"
      @confirm="submitAdjust"
    >
      <view class="adjust-form">
        <u-input v-model="adjustAmount" type="number" placeholder="积分数量（正整数）" border="surround" />
        <view class="gap" />
        <u-input v-model="adjustReason" placeholder="原因（必填）" border="surround" />
      </view>
    </FfBottomSheet>
  </view>
</template>

<style scoped lang="scss">
.page {
  min-height: 100vh;
  background: #fff;
}

.main {
  padding-top: calc(var(--status-bar-height, 20px) + 44px);
}

.head {
  padding: 40rpx 0 20rpx;
}

.summarize {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.num {
  color: #ed920f;
  font-size: 80rpx;
  font-weight: 600;
  line-height: 1;
}

.num-text {
  margin-top: 14rpx;
  color: #989898;
  font-size: 24rpx;
}

.edit-point {
  display: flex;
  gap: 48rpx;
  margin-top: 36rpx;
}

.but {
  display: flex;
  align-items: center;
  gap: 8rpx;
  color: #181818;
  font-size: 26rpx;
}

.content {
  margin-top: 20rpx;
}

.setting_wrap .list {
  min-height: 700rpx;
  margin: 0 14rpx;
  padding: 8rpx 20rpx 40rpx;
  background: #fff;
  border-radius: 21rpx;
}

.sale-item {
  padding-top: 28rpx;
}

.detail-info-wrap {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-left: 18rpx;
}

.l-info-head {
  color: #181818;
  font-size: 28rpx;
  font-weight: 500;
}

.time-and-type {
  margin-top: 10rpx;
  color: #989898;
  font-size: 22rpx;
}

.r-info {
  text-align: right;
}

.day-num {
  color: #22c788;
  font-size: 32rpx;
  font-weight: 600;
}

.day-num.redtext {
  color: #dc3c5c;
}

.day-num1 {
  margin-top: 8rpx;
  font-size: 22rpx;
}

.greentext {
  color: #22c788;
}

.redtext {
  color: #dc3c5c;
}

.noCourseData {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16rpx;
  padding: 120rpx 0;
  color: #bfbfbf;
  font-size: 26rpx;
}

.adjust-form {
  padding: 20rpx 8rpx 40rpx;
}

.gap {
  height: 24rpx;
}
</style>
