<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import {
  fetchPlatformSiteSubscriptionStatus,
  fetchPlatformSubscriptionAgreement,
  fetchPlatformSubscriptionPricing,
  payPlatformSubscription,
} from "@/api/platform";
import type { PlatformSiteSubscriptionStatus, PlatformSubscriptionPlan } from "@/api/platform";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const loading = ref(true);
const paying = ref(false);
const errorMessage = ref("");
const plans = ref<PlatformSubscriptionPlan[]>([]);
const status = ref<PlatformSiteSubscriptionStatus | null>(null);
const selectedPlanId = ref<number | null>(null);
const agreementTitle = ref("平台服务协议");
const agreedProtocol = ref(false);

const selectedPlan = computed(() => plans.value.find((plan) => plan.configId === selectedPlanId.value) || null);
const expireText = computed(() => {
  const sub = status.value?.subscription;
  if (!sub?.expiresAt) return "暂无有效订阅";
  const days = sub.daysRemaining;
  const dateText = sub.expiresAt.slice(0, 10);
  if (days != null && days < 0) return `已于 ${dateText} 过期`;
  if (days != null) return `${dateText} 到期（剩余 ${days} 天）`;
  return `${dateText} 到期`;
});

async function load() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const tasks: Promise<void>[] = [
      fetchPlatformSubscriptionPricing().then((response) => {
        plans.value = response.list;
        if (!selectedPlanId.value && response.list.length) {
          selectedPlanId.value = response.list[0].configId;
        }
      }),
      fetchPlatformSubscriptionAgreement().then((response) => {
        agreementTitle.value = response.title || "平台服务协议";
      }).catch(() => undefined),
    ];
    if (session.currentSiteId) {
      tasks.push(
        fetchPlatformSiteSubscriptionStatus(session.currentSiteId).then((response) => {
          status.value = response;
        }).catch(() => undefined),
      );
    }
    await Promise.all(tasks);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "订阅信息加载失败";
  } finally {
    loading.value = false;
  }
}

function openAgreement() {
  uni.navigateTo({ url: "/subpackages/settings/platform/subscription/agreement" });
}

function openOrders() {
  uni.navigateTo({ url: "/subpackages/settings/platform/subscription-orders/index" });
}

async function submitPay() {
  if (!selectedPlan.value) {
    uni.showToast({ title: "请选择套餐", icon: "none" });
    return;
  }
  if (!agreedProtocol.value) {
    uni.showToast({ title: "请先阅读并同意服务协议", icon: "none" });
    return;
  }
  paying.value = true;
  try {
    const result = await payPlatformSubscription({
      planId: selectedPlan.value.configId,
      commandKey: createCommandKey(),
    });
    uni.showModal({
      title: result.demo ? "演示支付成功" : "支付成功",
      content: `套餐：${selectedPlan.value.yearName}，金额 ¥${result.amount}${result.demo ? "（演示环境未真实扣款）" : ""}`,
      showCancel: false,
    });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "支付失败", icon: "none" });
  } finally {
    paying.value = false;
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
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <!-- 当前订阅状态 -->
    <view class="status-card">
      <text class="status-site">{{ status?.siteName || "当前场馆" }}</text>
      <text class="status-plan">{{ status?.subscription.planLabel || "未订阅" }}</text>
      <text class="status-expire">{{ expireText }}</text>
      <text class="orders-link" @tap="openOrders">查看订阅订单</text>
    </view>

    <!-- 套餐选择 -->
    <view class="section-title">选择续费套餐</view>
    <view class="plan-list">
      <view
        v-for="plan in plans"
        :key="plan.configId"
        class="plan-card"
        :class="{ active: selectedPlanId === plan.configId }"
        @tap="selectedPlanId = plan.configId"
      >
        <text class="plan-name">{{ plan.yearName }}</text>
        <view class="plan-price-row">
          <text class="plan-price">¥{{ plan.realPrice }}</text>
          <text v-if="plan.originalPriceCents > plan.priceCents" class="plan-original">¥{{ plan.originalPrice }}</text>
        </view>
        <text class="plan-days">{{ plan.durationDays }} 天</text>
      </view>
    </view>
    <view v-if="!plans.length" class="nodata-box">
      <text class="sg-empty-text">暂无可购套餐，请联系客服</text>
    </view>

    <!-- 协议 -->
    <view class="protocol-row" @tap="agreedProtocol = !agreedProtocol">
      <u-icon :name="agreedProtocol ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="agreedProtocol ? '#ed920f' : '#bfbfbf'" size="20" />
      <text class="protocol-text">已阅读并同意</text>
      <text class="protocol-link" @tap.stop="openAgreement">《{{ agreementTitle }}》</text>
    </view>

    <button class="sg-btn-primary pay-btn" :disabled="paying || !plans.length" @click="submitPay">
      {{ paying ? "支付中..." : selectedPlan ? `立即支付 ¥${selectedPlan.realPrice}` : "立即支付" }}
    </button>
  </view>
</template>

<style scoped lang="scss">
.status-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 48rpx 24rpx 36rpx;
  background: linear-gradient(135deg, #303540 0%, #181818 100%);
  border-radius: $radius-lg;
}

.status-site {
  color: rgba(255, 255, 255, 0.7);
  font-size: 24rpx;
}

.status-plan {
  margin-top: 16rpx;
  color: #f7d8a5;
  font-size: 40rpx;
  font-weight: 600;
}

.status-expire {
  margin-top: 12rpx;
  color: rgba(255, 255, 255, 0.8);
  font-size: 24rpx;
}

.orders-link {
  margin-top: 24rpx;
  color: #f7d8a5;
  font-size: 24rpx;
  text-decoration: underline;
}

.plan-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20rpx;
}

.plan-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 32rpx 16rpx;
  background: $color-surface;
  border: 2rpx solid $color-border;
  border-radius: $radius-lg;

  &.active {
    border-color: $color-primary;
    background: rgba(237, 146, 15, 0.06);
  }
}

.plan-name {
  font-size: 30rpx;
  font-weight: 600;
  color: $color-text;
}

.plan-price-row {
  display: flex;
  align-items: baseline;
  gap: 12rpx;
  margin-top: 16rpx;
}

.plan-price {
  color: $color-primary;
  font-size: 44rpx;
  font-weight: 600;
}

.plan-original {
  color: $color-text-disabled;
  font-size: 24rpx;
  text-decoration: line-through;
}

.plan-days {
  margin-top: 10rpx;
  color: $color-text-tertiary;
  font-size: 22rpx;
}

.protocol-row {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-top: 36rpx;
}

.protocol-text {
  color: $color-text-secondary;
  font-size: 24rpx;
}

.protocol-link {
  color: $color-info;
  font-size: 24rpx;
}

.pay-btn {
  margin-top: 32rpx;
  border: none;
}

.pay-btn::after {
  border: 0;
}

.nodata-box {
  padding: 60rpx 0;
}
</style>
