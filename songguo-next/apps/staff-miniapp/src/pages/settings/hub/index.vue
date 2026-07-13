<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchSettingsHub } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { SettingsHub, SettingsHubItem, SettingsHubSection } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const hub = ref<SettingsHub | null>(null);

async function load() {
  if (!session.currentSiteId || !session.can("tenant.settings.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    hub.value = await fetchSettingsHub(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "设置中心加载失败";
  } finally {
    loading.value = false;
  }
}

function openItem(item: SettingsHubItem) {
  if (!item.enabled) {
    uni.showToast({ title: "暂无权限", icon: "none" });
    return;
  }
  if (!item.implemented || !item.route) {
    uni.showToast({ title: "暂未开放", icon: "none" });
    return;
  }
  uni.navigateTo({ url: item.route });
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
    <u-empty v-if="!session.can('tenant.settings.read')" mode="permission" text="暂无场馆设置权限" />

    <view v-else-if="hub">
      <view v-for="section in hub.sections" :key="section.key" class="section-card">
        <view class="section-title">{{ section.label }}</view>
        <u-cell-group>
          <u-cell
            v-for="item in section.items"
            :key="item.key"
            :title="item.label"
            :label="item.description || undefined"
            :is-link="item.enabled && item.implemented && !!item.route"
            :disabled="!item.enabled"
            @click="openItem(item)"
          >
            <template #value>
              <u-tag
                v-if="item.setupIncomplete"
                text="待完善"
                type="warning"
                size="mini"
              />
              <u-tag
                v-else-if="!item.implemented"
                text="待上线"
                type="info"
                size="mini"
              />
            </template>
          </u-cell>
        </u-cell-group>
      </view>

      <u-empty
        v-if="hub.sections.length === 0"
        mode="list"
        text="当前权限下暂无可用的设置项"
      />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f4f6f8;
}

.section-card {
  margin-bottom: 24rpx;
  background: #fff;
  border-radius: 16rpx;
  overflow: hidden;
}

.section-title {
  padding: 24rpx 28rpx 8rpx;
  font-size: 30rpx;
  font-weight: 600;
  color: #202124;
}
</style>
