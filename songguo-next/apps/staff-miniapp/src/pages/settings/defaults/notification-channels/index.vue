<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchNotificationChannels, updateNotificationChannels } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { NotificationChannelItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const channels = ref<NotificationChannelItem[]>([]);

async function load() {
  if (!session.currentSiteId || !session.can("notification.channel.config.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchNotificationChannels(session.currentSiteId);
    channels.value = config.channels;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "提醒设置加载失败";
  } finally {
    loading.value = false;
  }
}

async function toggle(channel: NotificationChannelItem, enabled: boolean) {
  if (!session.currentSiteId || !session.can("notification.channel.config.write")) return;
  saving.value = true;
  try {
    const config = await updateNotificationChannels(session.currentSiteId, {
      channels: [{ key: channel.key, enabled }],
    });
    channels.value = config.channels;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!session.can('notification.channel.config.read')" mode="permission" text="暂无查看权限" />

    <view v-else class="panel">
      <view v-for="channel in channels" :key="channel.key" class="row">
        <text>{{ channel.label }}</text>
        <u-switch
          :model-value="channel.enabled"
          :disabled="!session.can('notification.channel.config.write')"
          @change="toggle(channel, $event)"
        />
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f4f6f8;
}

.panel {
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20rpx 0;
  border-bottom: 1px solid #f0f0f0;
}
</style>
