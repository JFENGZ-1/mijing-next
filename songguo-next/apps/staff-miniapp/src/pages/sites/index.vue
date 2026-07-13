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

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="page-container">
    <u-empty v-if="session.sites.length === 0" mode="list" text="暂无可用场馆" />
    <view v-for="site in session.sites" :key="site.id" class="site-row" @click="selectSite(site.id)">
      <view>
        <text class="site-name">{{ site.name }}</text>
        <text class="site-status">{{ site.status }}</text>
      </view>
      <u-icon :name="session.currentSiteId === site.id ? 'checkmark-circle-fill' : 'arrow-right'" :color="session.currentSiteId === site.id ? '#1677ff' : '#667085'" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.site-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: $spacing-sm; padding: $spacing-md; background: $color-surface; border: 1rpx solid $color-border; border-radius: $radius-md; }
.site-name, .site-status { display: block; }
.site-name { font-weight: 600; }
.site-status { margin-top: $spacing-xs; color: $color-text-secondary; font-size: 24rpx; }
</style>
