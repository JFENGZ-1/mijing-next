<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { createSiteClosure, fetchSiteClosures } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { SiteClosureItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const items = ref<SiteClosureItem[]>([]);
const reason = ref("");
const beginDate = ref("");
const endDate = ref("");

async function load() {
  if (!session.currentSiteId || !session.can("tenant.site.closure-calendar.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchSiteClosures(session.currentSiteId);
    items.value = config.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "闭店日历加载失败";
  } finally {
    loading.value = false;
  }
}

async function create() {
  if (!session.currentSiteId || !session.can("tenant.site.closure-calendar.write")) return;
  if (!beginDate.value || !endDate.value) {
    uni.showToast({ title: "请填写起止日期", icon: "none" });
    return;
  }
  saving.value = true;
  try {
    await createSiteClosure(session.currentSiteId, {
      reason: reason.value || undefined,
      beginDate: beginDate.value,
      endDate: endDate.value,
    });
    reason.value = "";
    beginDate.value = "";
    endDate.value = "";
    await load();
    uni.showToast({ title: "已添加", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "添加失败";
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
    <u-empty v-if="!session.can('tenant.site.closure-calendar.read')" mode="permission" text="暂无查看权限" />

    <view v-else>
      <view v-if="session.can('tenant.site.closure-calendar.write')" class="panel">
        <u-input v-model="reason" placeholder="闭店原因（可选）" />
        <u-input v-model="beginDate" placeholder="开始日期 YYYY-MM-DD" />
        <u-input v-model="endDate" placeholder="结束日期 YYYY-MM-DD" />
        <u-button type="primary" text="添加闭店" @click="create" />
      </view>

      <view class="panel list-panel">
        <view v-for="item in items" :key="item.id" class="list-item">
          <view class="title">{{ item.reason || "节假日闭店" }}</view>
          <view class="meta">{{ item.beginDate }} ~ {{ item.endDate }}</view>
          <u-tag :text="item.lifecycleStatus" size="mini" />
        </view>
        <u-empty v-if="items.length === 0" mode="list" text="暂无闭店记录" />
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f5f5f5;
}

.panel {
  margin-bottom: 24rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.list-item {
  padding: 16rpx 0;
  border-bottom: 1px solid #f0f0f0;
}

.title {
  font-size: 30rpx;
  font-weight: 600;
}

.meta {
  margin: 8rpx 0;
  color: #666;
  font-size: 26rpx;
}
</style>
