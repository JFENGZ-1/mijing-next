<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchMemberCarousel, updateMemberCarousel } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { MemberCarouselItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const items = ref<MemberCarouselItem[]>([]);
const defaultImageUrl = ref("");
const newImageUrl = ref("");

const canRead = computed(() => session.can("tenant.member-experience.read"));
const canWrite = computed(() => session.can("tenant.member-experience.write"));

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchMemberCarousel(session.currentSiteId);
    items.value = config.items;
    defaultImageUrl.value = config.defaultImageUrl ?? "";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "轮播图加载失败";
  } finally {
    loading.value = false;
  }
}

async function persist(nextItems: MemberCarouselItem[]) {
  if (!session.currentSiteId || !canWrite.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const config = await updateMemberCarousel(session.currentSiteId, {
      items: nextItems.map((item, index) => ({
        imageUrl: item.imageUrl,
        linkUrl: item.linkUrl,
        sortOrder: index,
      })),
      defaultImageUrl: defaultImageUrl.value || null,
    });
    items.value = config.items;
    defaultImageUrl.value = config.defaultImageUrl ?? "";
    uni.showToast({ title: "已保存", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
    await load();
  } finally {
    saving.value = false;
  }
}

function addItem() {
  if (!newImageUrl.value || items.value.length >= 5) return;
  const next = [...items.value, { imageUrl: newImageUrl.value, sortOrder: items.value.length }];
  newImageUrl.value = "";
  void persist(next);
}

function removeItem(index: number) {
  const next = items.value.filter((_, i) => i !== index);
  void persist(next);
}

function moveUp(index: number) {
  if (index === 0) return;
  const next = [...items.value];
  [next[index - 1], next[index]] = [next[index], next[index - 1]];
  void persist(next);
}

async function saveDefault() {
  await persist(items.value);
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看权限" />

    <view v-else class="panel">
      <view class="section-title">轮播图片（最多 5 张）</view>
      <view v-for="(item, index) in items" :key="`${item.imageUrl}-${index}`" class="item-row">
        <image class="preview" :src="item.imageUrl" mode="aspectFill" />
        <view class="actions">
          <u-button v-if="canWrite && index > 0" size="mini" text="上移" @click="moveUp(index)" />
          <u-button v-if="canWrite" size="mini" type="error" text="删除" @click="removeItem(index)" />
        </view>
      </view>

      <view v-if="canWrite" class="add-row">
        <u-input v-model="newImageUrl" placeholder="图片 URL" />
        <u-button type="primary" text="添加" :disabled="items.length >= 5" @click="addItem" />
      </view>

      <view class="section-title">无轮播时默认图</view>
      <u-input v-model="defaultImageUrl" placeholder="默认图 URL" :disabled="!canWrite" />
      <u-button v-if="canWrite" text="保存默认图" @click="saveDefault" />
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
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}

.section-title {
  font-size: 28rpx;
  font-weight: 600;
}

.item-row {
  display: flex;
  gap: 16rpx;
  align-items: center;
}

.preview {
  width: 200rpx;
  height: 120rpx;
  border-radius: 12rpx;
  background: #f1f3f4;
}

.actions {
  display: flex;
  gap: 12rpx;
}

.add-row {
  display: flex;
  gap: 12rpx;
  align-items: center;
}
</style>
