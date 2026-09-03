<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { fetchCrmMemberFilterPresets } from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type { CrmFilterPresetQuery, CrmMemberFilterPresets, CrmStoredMemberFilters } from "@/types/crm";
import { CRM_MEMBER_FILTER_STORAGE_KEY } from "@/types/crm";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const presets = ref<CrmMemberFilterPresets | null>(null);
const current = ref<CrmStoredMemberFilters | null>(null);

const currentLabel = computed(() => {
  if (!current.value || current.value.cleared) return "";
  return current.value.label || "";
});

function readCurrent() {
  const raw = uni.getStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY);
  if (!raw) {
    current.value = null;
    return;
  }
  try {
    const stored = JSON.parse(raw) as CrmStoredMemberFilters;
    current.value = stored.cleared ? null : stored;
  } catch {
    current.value = null;
  }
}

function isActive(query: CrmFilterPresetQuery) {
  if (!current.value?.query) return false;
  const q = current.value.query;
  return (
    (q.sumMode || undefined) === (query.sumMode || undefined) &&
    (q.runOff ?? undefined) === (query.runOff ?? undefined) &&
    (q.flag ?? undefined) === (query.flag ?? undefined)
  );
}

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
  const payload: CrmStoredMemberFilters = { label, query };
  uni.setStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY, JSON.stringify(payload));
  current.value = payload;
  uni.showToast({ title: `已应用：${label}`, icon: "none" });
  setTimeout(() => uni.navigateBack(), 280);
}

function clearFilters() {
  uni.setStorageSync(CRM_MEMBER_FILTER_STORAGE_KEY, JSON.stringify({ cleared: true, query: {} }));
  current.value = null;
  uni.showToast({ title: "已清除筛选", icon: "none" });
  setTimeout(() => uni.navigateBack(), 280);
}

onShow(async () => {
  readCurrent();
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container filter-page">
    <view class="page-title">高级筛选</view>
    <view class="page-hint">选择预设后返回会员列表；sumMode / 流失 / 上课分层会真正参与列表查询</view>
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view v-if="currentLabel" class="current-band">
      <view class="current-main">
        <text class="current-label">当前筛选</text>
        <text class="current-value">{{ currentLabel }}</text>
      </view>
      <button class="clear-inline" @tap="clearFilters">清除</button>
    </view>

    <template v-if="presets">
      <view class="section-heading">会员概况</view>
      <view class="preset-grid">
        <button
          v-for="item in presets.sumModePresets"
          :key="item.id"
          class="preset-button"
          :class="{ active: isActive(item.query) }"
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
          :class="{ active: isActive(item.query) }"
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
          :class="{ disabled: item.listSupported === false, active: isActive(item.query) }"
          :disabled="item.listSupported === false"
          @click="applyPreset(item.label, item.query)"
        >
          <text>{{ item.label }}</text>
          <text v-if="item.listSupported === false" class="preset-note">即将支持</text>
        </button>
      </view>

      <u-button plain @click="clearFilters">清除筛选并返回</u-button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.filter-page { padding-bottom: 48rpx; }
.page-title { font-size: 36rpx; font-weight: 600; }
.page-hint { margin-top: 10rpx; color: $color-text-secondary; font-size: 24rpx; line-height: 1.5; }
.current-band {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16rpx;
  margin-top: 24rpx;
  padding: 20rpx 24rpx;
  background: #fff8e6;
  border: 1rpx solid #f5d78e;
  border-radius: $radius-sm;
}
.current-label { display: block; color: $color-text-secondary; font-size: 22rpx; }
.current-value { display: block; margin-top: 6rpx; color: #ed920f; font-size: 28rpx; font-weight: 600; }
.clear-inline {
  margin: 0;
  padding: 10rpx 20rpx;
  color: #ed920f;
  font-size: 24rpx;
  background: #fff;
  border-radius: 999rpx;
}
.clear-inline::after { border: 0; }
.section-heading { margin: 32rpx 0 16rpx; font-size: 28rpx; font-weight: 600; }
.preset-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16rpx; }
.preset-button, .preset-row {
  margin: 0;
  padding: 24rpx;
  color: $color-text;
  font-size: 26rpx;
  text-align: left;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}
.preset-button.active, .preset-row.active {
  color: #181818;
  background: #fff8e6;
  border-color: #fbd128;
  font-weight: 600;
}
.preset-button::after, .preset-row::after { border: 0; }
.preset-list { display: grid; gap: 12rpx; margin-bottom: 32rpx; }
.preset-row.disabled { color: $color-text-secondary; opacity: .7; }
.preset-note { display: block; margin-top: 8rpx; color: $color-text-secondary; font-size: 22rpx; }
</style>
