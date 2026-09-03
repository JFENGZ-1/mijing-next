<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchPaymentMarketing } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { PaymentMarketingCard } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const cards = ref<PaymentMarketingCard[]>([]);
const supportHint = ref("");

async function load() {
  if (!session.currentSiteId || !session.can("tenant.settings.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchPaymentMarketing(session.currentSiteId);
    cards.value = config.cards;
    supportHint.value = config.supportHint;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "收款说明加载失败";
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!session.can('tenant.settings.read')" mode="permission" text="暂无查看权限" />

    <view v-else>
      <view v-for="card in cards" :key="card.key" class="card">
        <view class="title">{{ card.title }}</view>
        <view class="desc">{{ card.description }}</view>
        <u-tag :text="card.contactLabel" type="warning" size="mini" />
      </view>
      <u-alert type="info" :description="supportHint" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f5f5f5;
}

.card {
  margin-bottom: 24rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
}

.title {
  font-size: 32rpx;
  font-weight: 600;
}

.desc {
  margin: 12rpx 0 16rpx;
  color: #666;
  font-size: 28rpx;
}
</style>
