<script setup lang="ts">
import { computed, ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { fetchCardProducts, restoreCardProduct, updateCardProduct } from "@/api/card-products";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffCardProductCatalogItem, StaffCardProductUpdatePayload } from "@/types/member-cards";


type CatalogTab = "active" | "archived";
type TypeKey = "all" | "period" | "count" | "stored_value";

const session = useSessionStore();
const loading = ref(true);
const reordering = ref(false);
const errorMessage = ref("");
const activeTab = ref<CatalogTab>("active");
const items = ref<StaffCardProductCatalogItem[]>([]);
const searchQuery = ref("");
const sortMode = ref(false);
const archivedCount = ref(0);
const activeType = ref<TypeKey>("all"); // 原版类型筛选 tab

const canRead = computed(() => session.can("card-product.catalog.read"));
const canWrite = computed(() => session.can("card-product.editor.write"));
const canArchive = computed(() => session.can("card-product.archive"));
const isArchivedTab = computed(() => activeTab.value === "archived");
const currentSiteName = computed(
  () => session.sites.find((site) => site.id === session.currentSiteId)?.name || "",
);

// 原版：全部(N) 期限卡(N) 计次卡 储值卡
const typeTabs = computed(() => {
  const countOf = (type: string) => items.value.filter((item) => item.cardType === type).length;
  return [
    { key: "all" as const, label: "全部", count: items.value.length },
    { key: "period" as const, label: "期限卡", count: countOf("period") },
    { key: "count" as const, label: "计次卡", count: countOf("count") },
    { key: "stored_value" as const, label: "储值卡", count: countOf("stored_value") },
  ];
});

const displayItems = computed(() => {
  let list = items.value;
  if (!isArchivedTab.value && activeType.value !== "all") {
    list = list.filter((item) => item.cardType === activeType.value);
  }
  const keyword = searchQuery.value.trim();
  if (keyword && !sortMode.value) {
    list = list.filter((item) => item.name.includes(keyword));
  }
  return list;
});

// 原版：超过 10 张卡才显示搜索
const showSearch = computed(() => !isArchivedTab.value && items.value.length > 10);

function cardTypeLabel(cardType: string) {
  return ({ stored_value: "储值卡", count: "计次卡", period: "期限卡" } as Record<string, string>)[cardType] || cardType;
}

// 卡面图案：后端图案库直发 faceGradient（总 Web 后台可控），无值回退默认
function cardFace(item: StaffCardProductCatalogItem) {
  return item.faceGradient || "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";
}

// 原版缎带：期限卡绿 / 计次深蓝 / 储值深金
function ribbonColor(cardType: string) {
  if (cardType === "stored_value") return "rgba(122, 78, 31, 0.92)";
  if (cardType === "count") return "rgba(0, 61, 130, 0.88)";
  return "rgba(38, 145, 113, 0.92)";
}

function quotaText(product: StaffCardProductCatalogItem) {
  if (product.cardType === "stored_value") return product.faceValue ? `额度${product.faceValue}元` : "";
  if (product.cardType === "count") return product.initialCount != null ? `${product.initialCount}次` : "";
  if (product.validityDays == null) return "";
  const days = product.validityDays;
  if (days % 365 === 0) return `有效期${days / 365}年`;
  if (days % 30 === 0) return `有效期${days / 30}个月`;
  return `有效期${days}天`;
}

// 卡面副标题（原版「年卡」位置）：显示类型速记
function faceSubtitle(product: StaffCardProductCatalogItem) {
  if (product.cardType === "period" && product.validityDays != null) {
    if (product.validityDays % 365 === 0) return `${product.validityDays / 365 > 1 ? product.validityDays / 365 : ""}年卡`;
    if (product.validityDays % 30 === 0) return `${product.validityDays / 30}个月卡`;
    return `${product.validityDays}天卡`;
  }
  return cardTypeLabel(product.cardType);
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
    const response = await fetchCardProducts(session.currentSiteId, 1, 50, undefined, activeTab.value);
    items.value = response.data.items;
    if (isArchivedTab.value) {
      archivedCount.value = response.data.pagination.total;
    } else {
      fetchCardProducts(session.currentSiteId, 1, 1, undefined, "archived")
        .then((archived) => {
          archivedCount.value = archived.data.pagination.total;
        })
        .catch(() => undefined);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "卡种列表加载失败";
  } finally {
    loading.value = false;
  }
}

function switchType(key: TypeKey) {
  if (activeType.value === key) return;
  activeType.value = key;
  sortMode.value = false;
}

function toggleRecycle() {
  activeTab.value = isArchivedTab.value ? "active" : "archived";
  sortMode.value = false;
  activeType.value = "all";
  load();
}

function toggleSortMode() {
  if (activeType.value !== "all") activeType.value = "all"; // 原版仅「全部」tab 可排序
  sortMode.value = !sortMode.value;
}

function openCreate() {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无卡种编辑权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: "/subpackages/settings/card-products/edit" });
}

function openEdit(item: StaffCardProductCatalogItem) {
  if (!canWrite.value || isArchivedTab.value || sortMode.value) return;
  uni.navigateTo({ url: `/subpackages/settings/card-products/edit?id=${item.id}` });
}

async function moveSort(index: number, direction: "up" | "down") {
  if (!session.currentSiteId || !canWrite.value || isArchivedTab.value || reordering.value) return;
  const list = displayItems.value;
  const targetIndex = direction === "up" ? index - 1 : index + 1;
  if (targetIndex < 0 || targetIndex >= list.length) return;

  const current = list[index];
  const adjacent = list[targetIndex];
  reordering.value = true;
  errorMessage.value = "";
  try {
    await updateCardProduct(session.currentSiteId, current.id, buildSortUpdatePayload(current, adjacent.sortOrder));
    await updateCardProduct(session.currentSiteId, adjacent.id, buildSortUpdatePayload(adjacent, current.sortOrder));
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
      content: `恢复后「${item.name}」将重新出现在卡种列表。`,
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
  <view v-if="!loading" class="page-shell">
    <!-- 白色圆角主体上盖黄色导航（原版布局） -->
    <view class="body-sheet">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-empty v-if="!canRead" mode="permission" text="暂无查看卡种目录权限" />

      <template v-else>
        <!-- 类型 tabs + 回收站/排序图标（原版同一行） -->
        <view class="top-nav">
          <view v-if="!isArchivedTab" class="type-tabs">
            <view
              v-for="tab in typeTabs"
              :key="tab.key"
              class="type-tab"
              :class="{ active: activeType === tab.key }"
              @tap="switchType(tab.key)"
            >
              <text class="type-tab-label">{{ tab.label }}</text>
              <text v-if="tab.count > 0" class="type-tab-count">({{ tab.count }})</text>
            </view>
          </view>
          <view v-else class="recycle-title">回收站</view>

          <!-- 原版 right-info：图标 35rpx + 右侧灰色小数字 -->
          <view class="nav-icons">
            <view class="nav-icon" @tap="toggleRecycle">
              <u-icon :name="isArchivedTab ? 'arrow-left' : 'trash'" size="18" color="#d9a400" />
              <text v-if="!isArchivedTab && archivedCount > 0" class="recycle-num">{{ archivedCount }}</text>
            </view>
            <view
              v-if="canWrite && !isArchivedTab && items.length > 1"
              class="nav-icon"
              @tap="toggleSortMode"
            >
              <u-icon :name="sortMode ? 'checkmark' : 'list'" size="18" :color="sortMode ? '#22c788' : '#d9a400'" />
            </view>
          </view>
        </view>

        <!-- 搜索（原版 >10 张卡才显示） -->
        <view v-if="showSearch" class="search-wrap">
          <u-search
            v-if="!sortMode"
            v-model="searchQuery"
            placeholder="会员卡名称"
            search-icon-color="#FBD128"
            :show-action="false"
          />
          <view v-else class="sort-mode-tip">排序模式下已禁用搜索</view>
        </view>

        <!-- 卡列表：左卡面 + 右售价（原版布局） -->
        <view v-for="(item, index) in displayItems" :key="item.id" class="card-row" @click="openEdit(item)">
          <view
            class="card-face"
            :style="{ background: cardFace(item) }"
            :class="{ gray: item.saleStatus !== 'on_sale' && !isArchivedTab }"
          >
            <view class="ribbon" :style="{ background: ribbonColor(item.cardType) }">{{ cardTypeLabel(item.cardType) }}</view>
            <view class="shop-info">
              <view class="shop-dot">{{ (currentSiteName || "馆").slice(0, 1) }}</view>
              <text class="shop-name">{{ currentSiteName }}</text>
            </view>
            <text class="face-name">{{ item.name }}</text>
            <view class="face-bottom">
              <text class="face-subtitle">{{ faceSubtitle(item) }}</text>
              <text class="face-quota">{{ quotaText(item) }}</text>
            </view>
            <view v-if="item.saleStatus !== 'on_sale' && !isArchivedTab" class="stopped-mask">
              <text class="stopped-text">已停售</text>
            </view>
          </view>

          <view class="right-box">
            <template v-if="!sortMode || isArchivedTab">
              <text class="price-label">售价</text>
              <view class="sale-money">
                <text class="money-symbol">¥</text>
                <text class="money-value">{{ item.price }}</text>
              </view>
              <view v-if="isArchivedTab && canArchive" class="restore-btn" @tap.stop="restore(item)">恢复</view>
            </template>
            <view v-else class="sort-actions" @click.stop>
              <view class="sort-btn" :class="{ disabled: index === 0 || reordering }" @tap="moveSort(index, 'up')">
                <u-icon name="arrow-up" size="16" color="#505050" />
              </view>
              <view
                class="sort-btn"
                :class="{ disabled: index === displayItems.length - 1 || reordering }"
                @tap="moveSort(index, 'down')"
              >
                <u-icon name="arrow-down" size="16" color="#505050" />
              </view>
            </view>
          </view>
        </view>

        <!-- 空态（原版说明文案） -->
        <view v-if="displayItems.length === 0" class="empty-guide">
          <text class="empty-text" v-if="isArchivedTab">回收站暂无删除的卡</text>
          <text class="empty-text" v-else>
            在此创建和管理您所出售的会员卡类型，如包年卡、计次卡等。但并不是给每个会员创建一张卡，而是您场馆出售的卡的种类。创建完毕后即可在「会员」中给会员发卡了。
          </text>
        </view>

        <view class="brand-footer">觅境约课</view>

        <!-- 浮动创建按钮（原版右下黄色圆钮两行字） -->
        <view v-if="canWrite && !isArchivedTab" class="create-fab" @tap="openCreate">
          <text class="fab-line1">创 建</text>
          <text class="fab-line2">会员卡</text>
        </view>
      </template>
    </view>
  </view>
</template>

<style scoped lang="scss">
// 黄色导航下的白色圆角主体
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 28rpx 60rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  box-sizing: border-box;
}

// —— 类型 tabs 行（原版 category-nav：sticky 吸顶，黑字+绿色下划线，右侧图标） ——
.top-nav {
  position: sticky;
  top: 0;
  z-index: 99;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
  margin: -30rpx -28rpx 0;
  padding: 40rpx 28rpx 0;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
}

.type-tabs {
  display: flex;
  overflow-x: auto;
  flex: 1;
  gap: 40rpx;
}

.type-tab {
  position: relative;
  display: flex;
  flex-shrink: 0;
  align-items: baseline;
  gap: 4rpx;
  padding-bottom: 16rpx;
}

// 原版 u-tabs：未选 25rpx #989898，选中 27rpx #181818
.type-tab-label {
  color: #989898;
  font-size: 25rpx;
}

.type-tab-count {
  color: #989898;
  font-size: 20rpx;
}

.type-tab.active {
  .type-tab-label {
    color: #181818;
    font-size: 27rpx;
    font-weight: 600;
  }

  .type-tab-count {
    color: #181818;
  }

  &::after {
    content: "";
    position: absolute;
    right: 25%;
    bottom: 0;
    left: 25%;
    height: 6rpx;
    border-radius: 3rpx;
    background: $color-success;
  }
}

.recycle-title {
  flex: 1;
  font-size: 32rpx;
  font-weight: 600;
  color: $color-text;
}

.nav-icons {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 30rpx;
}

.nav-icon {
  display: flex;
  align-items: center;
}

// 原版 shownum：图标右侧 3rpx 灰色 18rpx 行内数字
.recycle-num {
  margin-left: 3rpx;
  color: #989898;
  font-size: 18rpx;
  line-height: 35rpx;
}

.search-wrap {
  margin-top: 20rpx;
}

.sort-mode-tip {
  padding: 20rpx 24rpx;
  border-radius: 12rpx;
  background: $color-page;
  color: $color-text-disabled;
  font-size: 26rpx;
}

// —— 卡片行（原版 card-type-wrap：mb 50rpx + 分割线；right-box 底对齐） ——
.card-row {
  display: flex;
  align-items: flex-end;
  gap: 25rpx;
  margin-top: 30rpx;
  padding-bottom: 50rpx;
  border-bottom: 1rpx solid #f0f0f0;

  &:last-of-type {
    border-bottom: none;
  }
}

.card-face {
  position: relative;
  display: flex;
  overflow: hidden;
  flex-direction: column;
  flex-shrink: 0;
  width: 403rpx;
  height: 240rpx;
  border-radius: 18rpx;
  box-shadow: 0 2rpx 5rpx rgba(0, 0, 0, 0.1);
  box-sizing: border-box;

  &.gray {
    filter: grayscale(0.9);
    opacity: 0.75;
  }
}

.ribbon {
  position: absolute;
  top: 20rpx;
  right: -58rpx;
  width: 210rpx;
  transform: rotate(45deg);
  color: #fff;
  font-size: 19rpx;
  line-height: 34rpx;
  text-align: center;
}

.shop-info {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin: 19rpx 0 0 22rpx;
}

.shop-dot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 45rpx;
  height: 45rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.35);
  color: #fff;
  font-size: 22rpx;
}

.shop-name {
  overflow: hidden;
  max-width: 220rpx;
  color: rgba(255, 255, 255, 0.9);
  font-size: 22rpx;
  white-space: nowrap;
  text-overflow: ellipsis;
}

// 中央大字卡名（原版）
.face-name {
  overflow: hidden;
  margin-top: 14rpx;
  color: #fff;
  font-size: 44rpx;
  font-weight: 600;
  letter-spacing: 6rpx;
  text-align: center;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.face-bottom {
  display: flex;
  flex-direction: column;
  margin: auto 0 16rpx 22rpx;
}

.face-subtitle {
  color: #fff;
  font-size: 26rpx;
  font-weight: 500;
}

.face-quota {
  margin-top: 4rpx;
  color: rgba(255, 255, 255, 0.8);
  font-size: 20rpx;
}

.stopped-mask {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.35);
}

.stopped-text {
  padding: 8rpx 28rpx;
  border: 2rpx solid rgba(255, 255, 255, 0.9);
  border-radius: 10rpx;
  transform: rotate(-12deg);
  color: #fff;
  font-size: 30rpx;
  font-weight: 600;
  letter-spacing: 4rpx;
  text-shadow: 0 1rpx 3rpx rgba(0, 0, 0, 0.3);
}

// —— 右侧售价（原版 right-box：底对齐；label #dadada、金额 #989898 47rpx） ——
.right-box {
  display: flex;
  flex: 1;
  flex-direction: column;
  align-self: flex-end;
  margin-bottom: 6rpx;
}

.price-label {
  color: #dadada;
  font-size: 22rpx;
}

.sale-money {
  display: flex;
  align-items: baseline;
  color: #989898;
}

.money-symbol {
  font-size: 22rpx;
}

.money-value {
  font-size: 47rpx;
}

.restore-btn {
  margin-top: 12rpx;
  padding: 10rpx 0;
  width: 130rpx;
  border: 1rpx solid $color-brand-yellow;
  border-radius: 999rpx;
  color: #d9a400;
  font-size: 24rpx;
  text-align: center;
}

.sort-actions {
  display: flex;
  flex-direction: row;
  gap: 20rpx;
}

.sort-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64rpx;
  height: 64rpx;
  border: 1rpx solid $color-border;
  border-radius: 50%;
  background: $color-surface;

  &.disabled {
    opacity: 0.35;
  }
}

.empty-guide {
  padding: 100rpx 30rpx;
}

.empty-text {
  color: $color-text-disabled;
  font-size: 24rpx;
  line-height: 1.8;
}

.brand-footer {
  margin: 100rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 浮动创建按钮（原版右下黄色圆钮） ——
.create-fab {
  position: fixed;
  right: 40rpx;
  bottom: 140rpx;
  z-index: 10;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 150rpx;
  height: 150rpx;
  border-radius: 50%;
  background: $color-brand-yellow;
  box-shadow: 0 6rpx 24rpx rgba(251, 209, 40, 0.55);
}

.fab-line1 {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 600;
}

.fab-line2 {
  margin-top: 4rpx;
  color: $color-text;
  font-size: 20rpx;
}
</style>
