<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { createStaffMiniappCode, type StaffMiniappCode } from "@/api/sharing";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(false);
const errorMessage = ref("");
const share = ref<StaffMiniappCode | null>(null);

async function load() {
  if (!session.currentSiteId || !session.can("tenant.settings.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    share.value = await createStaffMiniappCode(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "分享码生成失败";
  } finally {
    loading.value = false;
  }
}

function copyText(value: string) {
  uni.setClipboardData({ data: value });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view class="page-container">
    <u-empty v-if="!session.can('tenant.settings.read')" mode="permission" text="暂无设置权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view v-if="share" class="card">
        <text class="title">{{ share.shareTitle }}</text>
        <text class="meta">页面：{{ share.pagePath }}</text>
        <text class="meta">场景值：{{ share.scene }}</text>
        <text class="hint">{{ share.hint }}</text>
        <u-button type="primary" text="复制场景值" @click="share && copyText(share.scene)" />
        <u-button text="复制页面路径" @click="share && copyText(share.pagePath)" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.card { padding: 24rpx; background: #fff; border-radius: 16rpx; }
.title, .meta, .hint { display: block; }
.title { font-size: 32rpx; font-weight: 600; }
.meta, .hint { margin-top: 12rpx; color: #505050; font-size: 24rpx; }
</style>
