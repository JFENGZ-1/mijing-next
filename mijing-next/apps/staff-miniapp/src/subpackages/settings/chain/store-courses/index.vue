<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchChainStoreCourses } from "@/api/chain";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ChainStoreCourseItem } from "@/types/chain";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<ChainStoreCourseItem[]>([]);

const canView = computed(() => session.can("card-product.editor.write"));

function openCardEdit(cardProductId: number) {
  uni.navigateTo({ url: `/subpackages/settings/card-products/edit?id=${cardProductId}` });
}

async function load() {
  if (!canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchChainStoreCourses();
    items.value = response.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "适用店与课加载失败";
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
    <u-empty v-if="!canView" mode="permission" text="暂无卡种编辑权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-empty v-if="items.length === 0" mode="data" text="暂无已关联分店的通用卡，请先在连锁通用卡中配置" />
      <view v-for="item in items" :key="item.cardProductId" class="card" @click="openCardEdit(item.cardProductId)">
        <text class="title">{{ item.name }}</text>
        <text class="meta">已关联 {{ item.linkedSiteIds.length }} 家分店 · 已配置 {{ item.courseScopeCount }} 门课程</text>
        <view v-for="site in item.sites" :key="site.siteId" class="row">
          <text>{{ site.siteName }}</text>
          <text class="link">编辑课程范围</text>
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.card { margin-bottom: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.title, .meta { display: block; }
.title { font-size: 30rpx; font-weight: 600; }
.meta { margin: 8rpx 0 12rpx; color: #505050; font-size: 24rpx; }
.row { display: flex; align-items: center; justify-content: space-between; padding: 8rpx 0; }
.link { color: #ed920f; font-size: 24rpx; }
</style>
