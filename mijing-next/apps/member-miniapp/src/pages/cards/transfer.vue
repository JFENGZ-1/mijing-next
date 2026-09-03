<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { claimMemberCardTransfer, getMemberCardTransferPreview } from "@/api/member";
import type { MemberCardTransferPreview } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { createCommandKey } from "@/utils/command-key";

const token = ref("");
const claiming = ref(false);
const errorMessage = ref("");
const preview = ref<MemberCardTransferPreview | null>(null);
const confirmModalShow = ref(false);
let claimCommandKey = "";

const loading = ref(true);

async function loadPreview() {
  errorMessage.value = "";
  preview.value = null;

  try {
    const response = await getMemberCardTransferPreview(token.value);
    preview.value = response.data;
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "转赠卡信息加载失败");
  } finally {
    loading.value = false;
  }
}

function goMine() {
  uni.switchTab({ url: "/pages/mine/index" });
}

function claimCard() {
  if (!preview.value?.claimable) return;
  confirmModalShow.value = true;
}

async function onConfirmClaim() {
  confirmModalShow.value = false;
  if (!preview.value?.claimable) return;

  if (!claimCommandKey) {
    claimCommandKey = createCommandKey();
  }

  claiming.value = true;
  try {
    const response = await claimMemberCardTransfer(token.value, claimCommandKey);
    claimCommandKey = "";
    uni.showToast({ title: "领取成功", icon: "success" });
    setTimeout(() => {
      uni.redirectTo({ url: `/pages/cards/detail?id=${response.data.memberCardId}` });
    }, 600);
  } catch (error) {
    uni.showToast({
      title: formatApiErrorMessage(error, "领取失败"),
      icon: "none",
    });
  } finally {
    claiming.value = false;
  }
}

onLoad((query) => {
  token.value = String(query?.token ?? "");
});

onShow(async () => { if (await requireMemberAuth()) await loadPreview(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="transfer-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <template v-if="preview">
      <view class="hero-card hero-green">
        <view class="hero-title">{{ preview.site.name }}</view>
        <view class="hero-subtitle">给您发卡了</view>
      </view>

      <view class="card-block">
        <member-card :card="preview.card" />
      </view>

      <view v-if="preview.validMessage" class="status-card" :class="{ 'status-warn': preview.claimable, 'status-blocked': !preview.claimable }">
        <u-icon
          :name="preview.alreadyClaimed ? 'checkmark-circle' : 'info-circle'"
          size="18"
          :color="preview.alreadyClaimed ? '#22c788' : '#ed920f'"
        />
        <text class="status-text">{{ preview.validMessage }}</text>
      </view>

      <view class="actions">
        <u-button
          v-if="preview.claimable"
          type="primary"
          :loading="claiming"
          @click="claimCard"
        >
          立即领取
        </u-button>
        <u-button v-else plain @click="goMine">返回我的</u-button>
      </view>

      <bottom-logo />
    </template>
  </view>

  <u-modal
    :show="confirmModalShow"
    title="确认领取"
    :content="`确定领取「${preview?.card.name || '会员卡'}」吗？`"
    confirm-text="立即领取"
    cancel-text="取消"
    :show-cancel-button="true"
    @confirm="onConfirmClaim"
    @cancel="confirmModalShow = false"
    @close="confirmModalShow = false"
  />
</template>

<style scoped lang="scss">
.transfer-page {
  min-height: 100vh;
  background: $color-page;
  padding: 24rpx 28rpx 0;
}

.hero-card {
  margin-bottom: 24rpx;
  padding: 36rpx 24rpx;
  border-radius: $radius-md;
  text-align: center;
}

.hero-green {
  color: #fff;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.hero-title {
  font-size: 36rpx;
  font-weight: 600;
}

.hero-subtitle {
  margin-top: 10rpx;
  font-size: 26rpx;
  opacity: 0.92;
}

.card-block {
  margin-bottom: 24rpx;
}

.status-card {
  display: flex;
  align-items: center;
  gap: 12rpx;
  margin-bottom: 24rpx;
  padding: 20rpx 24rpx;
  border-radius: $radius-md;
}

.status-warn {
  background: #fef9de;
}

.status-blocked {
  background: #f4f4f5;
}

.status-text {
  flex: 1;
  color: $color-text;
  font-size: 26rpx;
  line-height: 1.5;
}

.actions {
  margin-top: 8rpx;
}
</style>
