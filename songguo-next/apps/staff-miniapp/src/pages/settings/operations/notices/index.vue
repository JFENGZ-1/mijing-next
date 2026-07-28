<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { archiveSiteNotice, createSiteNotice, fetchSiteNotices } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { SiteNoticeAdminItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const items = ref<SiteNoticeAdminItem[]>([]);
const title = ref("");
const body = ref("");
const displayDays = ref("7");

async function load() {
  if (!session.currentSiteId || !session.can("notice.announcement.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchSiteNotices(session.currentSiteId);
    items.value = config.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "公告加载失败";
  } finally {
    loading.value = false;
  }
}

async function create() {
  if (!session.currentSiteId || !session.can("notice.announcement.write")) return;
  if (!title.value || !body.value) {
    uni.showToast({ title: "请填写标题和内容", icon: "none" });
    return;
  }
  saving.value = true;
  try {
    await createSiteNotice(session.currentSiteId, {
      title: title.value,
      body: body.value,
      displayDays: Number(displayDays.value || 7),
    });
    title.value = "";
    body.value = "";
    await load();
    uni.showToast({ title: "已发布", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "发布失败";
  } finally {
    saving.value = false;
  }
}

async function archive(noticeId: number) {
  if (!session.currentSiteId || !session.can("notice.announcement.write")) return;
  await archiveSiteNotice(session.currentSiteId, noticeId);
  await load();
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!session.can('notice.announcement.read')" mode="permission" text="暂无查看权限" />

    <view v-else>
      <view v-if="session.can('notice.announcement.write')" class="panel">
        <u-input v-model="title" placeholder="公告标题（最多18字）" maxlength="18" />
        <u-textarea v-model="body" placeholder="公告内容" maxlength="5000" />
        <u-input v-model="displayDays" placeholder="展示天数" type="number" />
        <u-button type="primary" text="发布公告" @click="create" />
      </view>

      <view class="panel">
        <view v-for="item in items" :key="item.id" class="list-item">
          <view class="title">{{ item.title }}</view>
          <view class="meta">{{ item.body }}</view>
          <view class="actions">
            <u-tag :text="item.displayStatus" size="mini" />
            <u-button
              v-if="session.can('notice.announcement.write') && item.displayStatus === 'active'"
              size="mini"
              text="下架"
              @click="archive(item.id)"
            />
          </view>
        </view>
        <u-empty v-if="items.length === 0" mode="list" text="暂无公告" />
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

.actions {
  display: flex;
  align-items: center;
  gap: 16rpx;
}
</style>
