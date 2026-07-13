<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffDirectoryListItem } from "@/types/staff-directory";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const activeItems = ref<StaffDirectoryListItem[]>([]);
const departedItems = ref<StaffDirectoryListItem[]>([]);
const activeCount = ref(0);
const departedCount = ref(0);

const canRead = computed(() => session.can("staff.directory.read"));
const canWrite = computed(() => session.can("staff.directory.write"));

function statusLabel(status: string) {
  return status === "active" ? "在职" : "已离职";
}

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchStaffDirectory(session.currentSiteId);
    activeItems.value = response.items.filter((item) => item.status === "active");
    departedItems.value = response.items.filter((item) => item.status === "departed");
    activeCount.value = response.activeCount;
    departedCount.value = response.departedCount;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "员工列表加载失败";
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  uni.navigateTo({ url: "/pages/settings/staff/edit" });
}

function openEdit(item: StaffDirectoryListItem) {
  uni.navigateTo({ url: `/pages/settings/staff/edit?id=${item.id}` });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看员工目录权限" />

    <template v-else>
      <view class="summary-row">
        <text>在职 {{ activeCount }} 人</text>
        <text v-if="departedCount"> / 已离职 {{ departedCount }} 人</text>
      </view>

      <view v-if="canWrite" class="toolbar">
        <u-button type="primary" text="添加员工" @click="openCreate" />
      </view>

      <view class="section-title">在职员工</view>
      <view v-for="item in activeItems" :key="item.id" class="staff-card" @click="canWrite ? openEdit(item) : undefined">
        <view class="staff-main">
          <text class="staff-name">{{ item.displayName }}</text>
          <text class="staff-meta">{{ item.role?.name || "未分配角色" }}</text>
        </view>
        <view class="staff-tags">
          <u-tag v-if="item.isSiteOwner" text="所有者" type="warning" size="mini" />
          <u-tag :text="statusLabel(item.status)" :type="item.status === 'active' ? 'success' : 'info'" size="mini" />
        </view>
      </view>

      <view v-if="departedItems.length" class="section-title">已离职</view>
      <view v-for="item in departedItems" :key="`departed-${item.id}`" class="staff-card muted">
        <view class="staff-main">
          <text class="staff-name">{{ item.displayName }}</text>
          <text class="staff-meta">{{ item.role?.name || "未分配角色" }}</text>
        </view>
        <u-tag text="已离职" type="info" size="mini" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.summary-row,
.section-title {
  margin-bottom: 16rpx;
  color: #5f6368;
  font-size: 26rpx;
}

.toolbar {
  margin-bottom: 24rpx;
}

.staff-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx;
  margin-bottom: 16rpx;
  border-radius: 16rpx;
  background: #fff;
}

.staff-card.muted {
  opacity: 0.72;
}

.staff-name {
  display: block;
  font-size: 30rpx;
  color: #202124;
}

.staff-meta {
  display: block;
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #80868b;
}

.staff-tags {
  display: flex;
  gap: 8rpx;
}
</style>
