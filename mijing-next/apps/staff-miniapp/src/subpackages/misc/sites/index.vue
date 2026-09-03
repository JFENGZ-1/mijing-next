<script setup lang="ts">
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();

function selectSite(siteId: number) {
  if (!session.selectSite(siteId)) return;
  uni.showToast({ title: "已切换场馆", icon: "success" });
  setTimeout(() => uni.navigateBack(), 300);
}

function statusLabel(status: string) {
  return ({ active: "营业中", inactive: "已停用" } as Record<string, string>)[status] || status;
}

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="page-container">
    <u-empty v-if="session.sites.length === 0" mode="list" text="暂无可用场馆" />
    <view
      v-for="site in session.sites"
      :key="site.id"
      class="site-row"
      :class="{ current: session.currentSiteId === site.id }"
      @click="selectSite(site.id)"
    >
      <view class="site-badge">{{ (site.name || "馆").slice(0, 1) }}</view>
      <view class="site-main">
        <text class="site-name">{{ site.name }}</text>
        <text class="site-status">{{ statusLabel(site.status) }}</text>
      </view>
      <u-icon :name="session.currentSiteId === site.id ? 'checkmark-circle-fill' : 'arrow-right'" :color="session.currentSiteId === site.id ? '#ed920f' : '#bfbfbf'" size="20" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.site-row {
  display: flex;
  align-items: center;
  gap: 24rpx;
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 2rpx solid transparent;
  border-radius: $radius-lg;

  &.current {
    border-color: $color-primary;
    background: rgba(237, 146, 15, 0.04);
  }
}

.site-badge {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 88rpx;
  height: 88rpx;
  border-radius: 20rpx;
  background: linear-gradient(135deg, #5fa3ea, #3f77c9);
  color: #fff;
  font-size: 34rpx;
}

.site-main { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.site-name { font-size: 30rpx; font-weight: 600; color: $color-text; }
.site-status { margin-top: $spacing-xs; color: $color-text-tertiary; font-size: 24rpx; }
</style>
