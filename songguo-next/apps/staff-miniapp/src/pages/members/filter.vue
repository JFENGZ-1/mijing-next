<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmMemberFilterPresets } from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type { CrmFilterPresetQuery, CrmMemberFilterPresets } from "@/types/crm";
import { CRM_MEMBER_FILTER_STORAGE_KEY } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const presets = ref<CrmMemberFilterPresets | null>(null);

async function load() {
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  try {
    const response = await fetchCrmMemberFilterPresets(session.currentSiteId);
    presets.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "筛选预设加载失败";
  } finally {
    loading.value = false;
  }
}

function applyPreset(label: string, query: CrmFilterPresetQuery) {
  uni.setStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY, JSON.stringify({ label, query }));
  uni.navigateBack();
}

function clearFilters() {
  uni.removeStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  uni.navigateBack();
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container filter-page">
    <view class="page-title">高级筛选</view>
    <view class="page-hint">选择预设后返回会员列表，可与搜索和拼音索引组合使用</view>
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="presets">
      <view class="section-heading">会员概况</view>
      <view class="preset-grid">
        <button
          v-for="item in presets.sumModePresets"
          :key="item.id"
          class="preset-button"
          @click="applyPreset(item.label, item.query)"
        >
          {{ item.label }}
        </button>
      </view>

      <view class="section-heading">流失会员</view>
      <view class="preset-list">
        <button
          v-for="item in presets.runOffPresets"
          :key="item.runOff"
          class="preset-row"
          @click="applyPreset(item.label, item.query)"
        >
          {{ item.label }}
        </button>
      </view>

      <view class="section-heading">更多预设</view>
      <view class="preset-list">
        <button
          v-for="item in presets.flagPresets"
          :key="item.flag"
          class="preset-row"
          :class="{ disabled: item.listSupported === false }"
          :disabled="item.listSupported === false"
          @click="applyPreset(item.label, item.query)"
        >
          <text>{{ item.label }}</text>
          <text v-if="item.listSupported === false" class="preset-note">即将支持</text>
        </button>
      </view>

      <u-button plain @click="clearFilters">清除筛选</u-button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.filter-page { padding-bottom: 48rpx; }
.page-title { font-size: 36rpx; font-weight: 600; }
.page-hint { margin-top: 10rpx; color: $color-text-secondary; font-size: 24rpx; line-height: 1.5; }
.section-heading { margin: 32rpx 0 16rpx; font-size: 28rpx; font-weight: 600; }
.preset-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16rpx; }
.preset-button, .preset-row { margin: 0; padding: 24rpx; color: $color-text; font-size: 26rpx; text-align: left; background: $color-surface; border: 1rpx solid $color-border; border-radius: $radius-sm; }
.preset-button::after, .preset-row::after { border: 0; }
.preset-list { display: grid; gap: 12rpx; margin-bottom: 32rpx; }
.preset-row.disabled { color: $color-text-secondary; opacity: .7; }
.preset-note { display: block; margin-top: 8rpx; color: $color-text-secondary; font-size: 22rpx; }
</style>
