<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchChainBrand, fetchChainSites, updateChainBrand } from "@/api/chain";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ChainBrand, ChainSiteListItem } from "@/types/chain";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const brand = ref<ChainBrand | null>(null);
const sites = ref<ChainSiteListItem[]>([]);
const brandName = ref("");
const brandLogoUrl = ref("");

const canRead = computed(() => session.can("organization.site.read") || session.can("tenant.settings.chain.read"));
const canManage = computed(() => session.can("organization.site.manage"));

async function load() {
  if (!canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchChainSites();
    brand.value = response.brand;
    sites.value = response.sites;
    brandName.value = response.brand.name ?? "";
    brandLogoUrl.value = response.brand.logoUrl ?? "";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "分店列表加载失败";
  } finally {
    loading.value = false;
  }
}

async function saveBrand() {
  if (!canManage.value) return;
  saving.value = true;
  try {
    brand.value = await updateChainBrand({
      name: brandName.value || null,
      logoUrl: brandLogoUrl.value || null,
    });
    uni.showToast({ title: "品牌已保存", icon: "none" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

function openSiteProfile() {
  uni.navigateTo({ url: "/pages/settings/site/index" });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-empty v-if="!canRead" mode="permission" text="暂无分店查看权限" />
    <template v-else>
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <view class="card">
        <text class="title">连锁品牌</text>
        <u-tag :text="brand?.chainActivated ? '已激活' : '未激活'" :type="brand?.chainActivated ? 'success' : 'warning'" size="mini" />
        <u-input v-model="brandName" label="品牌名称" :disabled="!canManage" />
        <u-input v-model="brandLogoUrl" label="品牌 LOGO URL" :disabled="!canManage" />
        <u-button v-if="canManage" type="primary" text="保存品牌" @click="saveBrand" />
        <text class="hint">需填写品牌名称且至少 2 个分店后自动激活连锁功能。</text>
      </view>
      <view class="toolbar">
        <text class="summary">分店 {{ sites.length }} 家</text>
        <u-button v-if="canManage" size="small" text="编辑场馆资料" @click="openSiteProfile" />
      </view>
      <view v-for="site in sites" :key="site.id" class="card">
        <text class="title">{{ site.name }}</text>
        <text class="meta">{{ site.address || "未填写地址" }}</text>
        <text class="meta">{{ site.phone || "未填写电话" }}</text>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.card { margin-bottom: 16rpx; padding: 20rpx; background: #fff; border-radius: 16rpx; }
.title, .meta, .hint { display: block; }
.title { font-size: 30rpx; font-weight: 600; }
.meta { margin-top: 8rpx; color: #505050; font-size: 24rpx; }
.hint { margin-top: 12rpx; color: #989898; font-size: 22rpx; }
.toolbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12rpx; }
.summary { font-size: 26rpx; color: #505050; }
</style>
