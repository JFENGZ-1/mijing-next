<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchCardProducts, restoreCardProduct, updateCardProduct } from "@/api/card-products";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffCardProductCatalogItem, StaffCardProductUpdatePayload } from "@/types/member-cards";

type CatalogTab = "active" | "archived";

const session = useSessionStore();
const loading = ref(true);
const reordering = ref(false);
const errorMessage = ref("");
const activeTab = ref<CatalogTab>("active");
const items = ref<StaffCardProductCatalogItem[]>([]);
const total = ref(0);
const searchQuery = ref("");

const canRead = computed(() => session.can("card-product.catalog.read"));
const canWrite = computed(() => session.can("card-product.editor.write"));
const canArchive = computed(() => session.can("card-product.archive"));
const isArchivedTab = computed(() => activeTab.value === "archived");
const activeTabIndex = computed(() => (isArchivedTab.value ? 1 : 0));
const tabs = [
  { key: "active" as const, name: "在售卡种" },
  { key: "archived" as const, name: "回收站" },
];

function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[cardType] || cardType;
}

function saleStatusLabel(saleStatus: string) {
  return saleStatus === "on_sale" ? "在售" : "停售";
}

function productSummary(product: StaffCardProductCatalogItem) {
  const parts = [`¥${product.price}`];
  if (product.faceValue) parts.push(`面值 ¥${product.faceValue}`);
  if (product.initialCount != null) parts.push(`${product.initialCount} 次`);
  if (product.validityDays != null) parts.push(`${product.validityDays} 天`);
  return parts.join(" · ");
}

function buildSortUpdatePayload(
  item: StaffCardProductCatalogItem,
  sortOrder: number,
): StaffCardProductUpdatePayload {
  const payload: StaffCardProductUpdatePayload = {
    version: item.version,
    name: item.name,
    price: Number(item.price),
    saleStatus: item.saleStatus as StaffCardProductUpdatePayload["saleStatus"],
    sortOrder,
  };
  if (item.faceValue != null) payload.faceValue = Number(item.faceValue);
  if (item.initialCount != null) payload.initialCount = item.initialCount;
  if (item.validityDays != null) payload.validityDays = item.validityDays;
  return payload;
}

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchCardProducts(
      session.currentSiteId,
      1,
      50,
      searchQuery.value.trim() || undefined,
      activeTab.value,
    );
    items.value = response.data.items;
    total.value = response.data.pagination.total;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "卡种列表加载失败";
  } finally {
    loading.value = false;
  }
}

function switchTab(index: number) {
  const nextTab = tabs[index]?.key;
  if (!nextTab || nextTab === activeTab.value) return;
  activeTab.value = nextTab;
  load();
}

function openCreate() {
  uni.navigateTo({ url: "/pages/settings/card-products/edit" });
}

function openEdit(item: StaffCardProductCatalogItem) {
  if (!canWrite.value || isArchivedTab.value) return;
  uni.navigateTo({ url: `/pages/settings/card-products/edit?id=${item.id}` });
}

function onSearch() {
  load();
}

async function moveSort(index: number, direction: "up" | "down") {
  if (!session.currentSiteId || !canWrite.value || isArchivedTab.value || reordering.value) return;
  const targetIndex = direction === "up" ? index - 1 : index + 1;
  if (targetIndex < 0 || targetIndex >= items.value.length) return;

  const current = items.value[index];
  const adjacent = items.value[targetIndex];
  reordering.value = true;
  errorMessage.value = "";
  try {
    await updateCardProduct(
      session.currentSiteId,
      current.id,
      buildSortUpdatePayload(current, adjacent.sortOrder),
    );
    await updateCardProduct(
      session.currentSiteId,
      adjacent.id,
      buildSortUpdatePayload(adjacent, current.sortOrder),
    );
    await load();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "排序调整失败";
  } finally {
    reordering.value = false;
  }
}

async function restore(item: StaffCardProductCatalogItem) {
  if (!session.currentSiteId || !canArchive.value) return;
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认恢复",
      content: `恢复后「${item.name}」将重新出现在在售卡种列表。`,
      success: (result) => resolve(Boolean(result.confirm)),
    });
  });
  if (!confirmed) return;

  loading.value = true;
  errorMessage.value = "";
  try {
    await restoreCardProduct(session.currentSiteId, item.id);
    uni.showToast({ title: "已恢复", icon: "none" });
    await load();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "恢复失败";
    loading.value = false;
  }
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
  <u-loading-page :loading="loading || reordering" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看卡种目录权限" />

    <template v-else>
      <u-tabs
        :list="tabs.map((tab) => ({ name: tab.name }))"
        :current="activeTabIndex"
        @change="switchTab"
      />

      <view class="summary-row">
        {{ isArchivedTab ? "回收站" : "在售" }}共 {{ total }} 个卡种模板
      </view>

      <view class="search-row">
        <u-input
          v-model="searchQuery"
          placeholder="搜索卡种名称"
          clearable
          @confirm="onSearch"
          @clear="onSearch"
        />
        <u-button text="搜索" size="small" @click="onSearch" />
      </view>

      <view v-if="canWrite && !isArchivedTab" class="toolbar">
        <u-button type="primary" text="新建卡种" @click="openCreate" />
      </view>

      <view
        v-for="(item, index) in items"
        :key="item.id"
        class="product-card"
        :class="{ clickable: canWrite && !isArchivedTab }"
        @click="openEdit(item)"
      >
        <view class="product-main">
          <text class="product-name">{{ item.name }}</text>
          <text class="product-meta">{{ cardTypeLabel(item.cardType) }} · {{ productSummary(item) }}</text>
        </view>
        <view class="product-side">
          <view v-if="!isArchivedTab" class="product-tags">
            <u-tag
              :text="saleStatusLabel(item.saleStatus)"
              :type="item.saleStatus === 'on_sale' ? 'success' : 'info'"
              size="mini"
            />
          </view>
          <view v-if="canWrite && !isArchivedTab" class="sort-actions" @click.stop>
            <u-button
              text="↑"
              size="mini"
              :disabled="index === 0 || reordering"
              @click="moveSort(index, 'up')"
            />
            <u-button
              text="↓"
              size="mini"
              :disabled="index === items.length - 1 || reordering"
              @click="moveSort(index, 'down')"
            />
          </view>
          <u-button
            v-if="isArchivedTab && canArchive"
            text="恢复"
            type="primary"
            size="mini"
            plain
            @click.stop="restore(item)"
          />
        </view>
      </view>

      <u-empty
        v-if="items.length === 0"
        mode="list"
        :text="isArchivedTab ? '回收站暂无归档卡种' : '暂无卡种模板'"
      />
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.summary-row {
  margin: 16rpx 0;
  color: #5f6368;
  font-size: 26rpx;
}

.search-row {
  display: flex;
  gap: 16rpx;
  align-items: center;
  margin-bottom: 24rpx;
}

.toolbar {
  margin-bottom: 24rpx;
}

.product-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24rpx;
  margin-bottom: 16rpx;
  border-radius: 16rpx;
  background: #fff;
}

.product-card.clickable:active {
  opacity: 0.85;
}

.product-main {
  flex: 1;
  min-width: 0;
}

.product-name {
  display: block;
  font-size: 30rpx;
  font-weight: 600;
  color: #202124;
}

.product-meta {
  display: block;
  margin-top: 8rpx;
  color: #5f6368;
  font-size: 24rpx;
}

.product-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12rpx;
  margin-left: 16rpx;
  flex-shrink: 0;
}

.product-tags {
  flex-shrink: 0;
}

.sort-actions {
  display: flex;
  gap: 8rpx;
}
</style>
