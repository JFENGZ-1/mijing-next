<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchSupportVideoHelp, type StaffSupportVideo } from "@/api/support";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const videos = ref<StaffSupportVideo[]>([]);

const canRead = computed(() => session.can("tenant.settings.support.read"));

async function load() {
  if (!session.currentSiteId || !canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchSupportVideoHelp(session.currentSiteId);
    videos.value = response.videos;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "视频帮助加载失败";
  } finally {
    loading.value = false;
  }
}

function openVideo(video: StaffSupportVideo) {
  if (!video.url) {
    uni.showToast({ title: "演示占位链接，待接入正式 CDN", icon: "none" });
    return;
  }
  uni.setClipboardData({ data: video.url, success: () => uni.showToast({ title: "视频链接已复制", icon: "none" }) });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canRead" mode="permission" text="暂无服务中心权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="hint">以下为帮助视频目录；未配置 CDN 时链接为演示占位，标签会如实标注。</view>
      <view v-for="video in videos" :key="video.url + video.title" class="card" @click="openVideo(video)">
        <view class="row">
          <text class="title">{{ video.title }}</text>
          <u-tag v-if="video.isPlaceholder" text="演示" type="info" size="mini" />
        </view>
        <text class="meta">{{ video.durationLabel || "时长未知" }}</text>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.hint { margin-bottom: 12rpx; color: #505050; font-size: 24rpx; line-height: 1.5; }
.card { margin-bottom: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.row { display: flex; align-items: center; justify-content: space-between; }
.title, .meta { display: block; }
.title { font-size: 30rpx; font-weight: 600; }
.meta { margin-top: 8rpx; color: #505050; font-size: 24rpx; }
</style>
