<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { claimMemberCardTransfer, getMemberCardTransferPreview } from "@/api/member";
import type { MemberCardTransferPreview } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";
import { createCommandKey } from "@/utils/command-key";
import { cardBalanceLabel, cardTypeLabel } from "@/utils/format";

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
    uni.$u.toast({ message: "领取成功", type: "success" });
    setTimeout(() => {
      uni.redirectTo({ url: `/pages/cards/detail?id=${response.data.memberCardId}` });
    }, 500);
  } catch (error) {
    uni.$u.toast({
      message: formatApiErrorMessage(error, "领取失败"),
      type: "error",
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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="preview">
      <view class="hero-card hero-green">
        <view class="hero-title">{{ preview.site.name }}</view>
        <view class="hero-subtitle">给您发卡了</view>
      </view>

      <view class="card-panel">
        <view class="card-name">{{ preview.card.name || "会员卡" }}</view>
        <view class="card-meta">{{ cardTypeLabel(preview.card.cardType) }}</view>
        <view v-if="cardBalanceLabel(preview.card)" class="card-balance">{{ cardBalanceLabel(preview.card) }}</view>
        <view class="card-meta">{{ preview.card.cardNoMasked }}</view>
      </view>

      <u-alert
        v-if="preview.validMessage"
        type="warning"
        :description="preview.validMessage"
      />

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
.hero-card {
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  border-radius: $radius-md;
  text-align: center;
}

.hero-green {
  color: #fff;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.hero-title {
  font-size: 34rpx;
  font-weight: 600;
}

.hero-subtitle {
  margin-top: $spacing-xs;
  font-size: 26rpx;
  opacity: 0.92;
}

.card-panel {
  margin-bottom: $spacing-md;
  padding: $spacing-lg $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.card-name {
  font-size: 34rpx;
  font-weight: 600;
}

.card-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.card-balance {
  margin-top: $spacing-sm;
  font-size: 36rpx;
  font-weight: 600;
}

.actions {
  margin-top: $spacing-md;
}
</style>
