<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchPlatformSubscriptionOrders } from "@/api/platform";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { PlatformSubscriptionOrderItem } from "@/api/platform";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<PlatformSubscriptionOrderItem[]>([]);

const canView = computed(() => session.can("platform.subscription.read"));

async function load() {
  if (!canView.value) {
    loading.value = false;
    uni.stopPullDownRefresh();
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchPlatformSubscriptionOrders();
    items.value = response.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "平台订单加载失败";
    items.value = [];
  } finally {
    loading.value = false;
    uni.stopPullDownRefresh();
  }
}

function openRenew() {
  uni.navigateTo({ url: "/subpackages/settings/platform/subscription/index" });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onPullDownRefresh(() => load());
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canView" mode="permission" text="暂无平台订阅查看权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="list-card">
        <view v-for="item in items" :key="item.siteId" class="row">
          <view>
            <text class="name">{{ item.siteName }}</text>
            <text class="meta">{{ item.planLabel || item.planCode || "未订阅" }} · {{ item.status }}</text>
            <text class="meta">到期：{{ item.expiresAt?.slice(0, 10) || "—" }} · 剩余 {{ item.daysRemaining ?? "—" }} 天</text>
          </view>
        </view>
        <u-empty v-if="!items.length" mode="list" text="暂无平台订阅记录" />
      </view>
      <button class="sg-btn-primary renew-btn" @click="openRenew">去续费</button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: $color-page; }
.list-card { padding: 20rpx; background: #fff; border-radius: $radius-lg; }
.row { padding: 12rpx 0; border-bottom: 1rpx solid #f0f0f0; }
.name, .meta { display: block; }
.name { font-size: 30rpx; font-weight: 600; }
.meta { margin-top: 6rpx; color: $color-text-secondary; font-size: 24rpx; }
.renew-btn { margin-top: 32rpx; border: none; }
.renew-btn::after { border: 0; }
</style>
