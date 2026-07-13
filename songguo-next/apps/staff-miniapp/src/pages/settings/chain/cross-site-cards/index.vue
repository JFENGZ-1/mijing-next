<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchCrossSiteCardProducts, updateCrossSiteCardProductLink, type CrossSiteCardProductItem } from "@/api/card-products";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const savingId = ref<number | null>(null);
const errorMessage = ref("");
const items = ref<CrossSiteCardProductItem[]>([]);

const canView = computed(() => session.can("card-product.catalog.read"));
const canWrite = computed(() => session.can("card-product.editor.write"));

async function load() {
  if (!session.currentSiteId || !canView.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchCrossSiteCardProducts(session.currentSiteId);
    items.value = response.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "连锁卡种加载失败";
  } finally {
    loading.value = false;
  }
}

async function toggleSite(item: CrossSiteCardProductItem, siteId: number, linked: boolean) {
  if (!session.currentSiteId || !canWrite.value) return;
  const next = linked
    ? [...item.linkedSiteIds, siteId]
    : item.linkedSiteIds.filter((id) => id !== siteId);
  savingId.value = item.cardProductId;
  try {
    await updateCrossSiteCardProductLink(session.currentSiteId, item.cardProductId, next);
    item.linkedSiteIds = next;
    item.sites = item.sites.map((site) => ({ ...site, linked: next.includes(site.siteId) }));
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    savingId.value = null;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canView" mode="permission" text="暂无卡种查看权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view v-for="item in items" :key="item.cardProductId" class="card">
        <text class="title">{{ item.name }}</text>
        <text class="meta">¥{{ item.price }} · {{ item.cardType }}</text>
        <view v-for="site in item.sites" :key="site.siteId" class="row">
          <text>{{ site.siteName }}</text>
          <u-switch
            :model-value="site.linked"
            :disabled="!canWrite || savingId === item.cardProductId"
            @change="toggleSite(item, site.siteId, $event)"
          />
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.card { margin-bottom: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.title, .meta { display: block; }
.title { font-size: 30rpx; font-weight: 600; }
.meta { margin: 8rpx 0 12rpx; color: #667085; font-size: 24rpx; }
.row { display: flex; align-items: center; justify-content: space-between; padding: 10rpx 0; }
</style>
