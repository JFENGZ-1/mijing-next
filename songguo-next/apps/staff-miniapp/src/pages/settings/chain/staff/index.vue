<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchChainStaffDirectory } from "@/api/chain";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ChainStaffDirectoryItem } from "@/types/chain";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<ChainStaffDirectoryItem[]>([]);
const activeCount = ref(0);

const canRead = computed(() => session.can("staff.directory.read"));
const canWrite = computed(() => session.can("staff.directory.write"));

function openEdit(item: ChainStaffDirectoryItem) {
  if (!canWrite.value) return;
  uni.navigateTo({ url: `/pages/settings/staff/edit?id=${item.id}` });
}

function openCreate() {
  uni.navigateTo({ url: "/pages/settings/staff/edit" });
}

async function load() {
  if (!canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchChainStaffDirectory();
    items.value = response.items.filter((item) => item.status === "active");
    activeCount.value = response.activeCount;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "总店员工加载失败";
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
    <u-empty v-if="!canRead" mode="permission" text="暂无员工目录权限" />
    <template v-else>
      <view class="hint">在此添加的员工为总店员工，可进入所有分店并拥有相应管理权限。</view>
      <view class="summary">在职 {{ activeCount }} 人</view>
      <u-button v-if="canWrite" type="primary" text="添加总店员工" @click="openCreate" />
      <view v-for="item in items" :key="item.id" class="card" @click="openEdit(item)">
        <text class="title">{{ item.displayName }}</text>
        <text class="meta">{{ item.roleName || "未分配角色" }} · {{ item.siteCount }} 家分店</text>
        <u-tag v-if="item.hasTenantWideRole" text="总店权限" type="warning" size="mini" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.hint { margin-bottom: 12rpx; color: #667085; font-size: 24rpx; line-height: 1.5; }
.summary { margin-bottom: 12rpx; font-size: 26rpx; }
.card { margin-top: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.title, .meta { display: block; }
.title { font-size: 30rpx; font-weight: 600; }
.meta { margin: 8rpx 0; color: #667085; font-size: 24rpx; }
</style>
