<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchStaffRoomCatalog } from "@/api/catalog";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { RoomCatalogItem } from "@/types/catalog";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<RoomCatalogItem[]>([]);

const canRead = computed(() => session.can("site.rooms.read"));
const canWrite = computed(() => session.can("site.rooms.write"));

function roomSummary(room: RoomCatalogItem) {
  const parts: string[] = [];
  if (room.capacity != null) parts.push(`容纳 ${room.capacity} 人`);
  if (room.sortOrder != null && room.sortOrder > 0) parts.push(`排序 ${room.sortOrder}`);
  return parts.length ? parts.join(" · ") : "未设置容纳人数";
}

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchStaffRoomCatalog(session.currentSiteId);
    items.value = response.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "教室列表加载失败";
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  uni.navigateTo({ url: "/subpackages/settings/rooms/edit" });
}

function openEdit(item: RoomCatalogItem) {
  if (!canWrite.value) return;
  uni.navigateTo({ url: `/subpackages/settings/rooms/edit?id=${item.id}` });
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
    <u-empty v-if="!canRead" mode="permission" text="暂无查看教室权限" />

    <template v-else>
      <view class="summary-row">共 {{ items.length }} 间教室</view>

      <view v-if="canWrite" class="toolbar">
        <u-button type="primary" text="新建教室" @click="openCreate" />
      </view>

      <view
        v-for="item in items"
        :key="item.id"
        class="room-card"
        :class="{ clickable: canWrite }"
        @click="openEdit(item)"
      >
        <view class="room-main">
          <text class="room-name">{{ item.name }}</text>
          <text class="room-meta">{{ roomSummary(item) }}</text>
        </view>
        <u-icon v-if="canWrite" name="arrow-right" color="#9aa0a6" size="16" />
      </view>

      <u-empty v-if="items.length === 0" mode="list" text="暂无教室，点击上方按钮创建" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.summary-row {
  margin-bottom: 16rpx;
  color: #505050;
  font-size: 26rpx;
}

.toolbar {
  margin-bottom: 24rpx;
}

.room-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx;
  margin-bottom: 16rpx;
  border-radius: 16rpx;
  background: #fff;
}

.room-card.clickable:active {
  opacity: 0.85;
}

.room-main {
  flex: 1;
  min-width: 0;
  margin-right: 16rpx;
}

.room-name {
  display: block;
  font-size: 30rpx;
  font-weight: 600;
  color: #181818;
}

.room-meta {
  display: block;
  margin-top: 8rpx;
  color: #505050;
  font-size: 24rpx;
}
</style>
