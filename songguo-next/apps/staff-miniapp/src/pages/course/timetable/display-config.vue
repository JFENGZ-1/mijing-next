<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchScheduleDisplayConfig, updateScheduleDisplayConfig } from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ScheduleDisplayConfig, ScheduleDisplayTag } from "@/types/scheduling";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const config = ref<ScheduleDisplayConfig>({ displayTitle: "", copyHint: "", displayTags: [] });

async function load() {
  if (!session.currentSiteId || !session.can("schedule.session.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    config.value = await fetchScheduleDisplayConfig(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课表展示设置加载失败";
  } finally {
    loading.value = false;
  }
}

function addTag() {
  config.value.displayTags.push({ key: `tag-${config.value.displayTags.length + 1}`, label: "新标签", color: "#1677ff" });
}

function removeTag(index: number) {
  config.value.displayTags.splice(index, 1);
}

async function save() {
  if (!session.currentSiteId || !session.can("schedule.session.write")) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    config.value = await updateScheduleDisplayConfig(session.currentSiteId, config.value);
    uni.showToast({ title: "已保存", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
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
    <u-empty v-if="!session.can('schedule.session.read')" mode="permission" text="暂无查看权限" />

    <view v-else class="section-card">
      <view class="section-title">课表标题</view>
      <u-input v-model="config.displayTitle" placeholder="如：本周课程安排" :disabled="!session.can('schedule.session.write')" />

      <view class="section-title">复制课表提示</view>
      <u-textarea v-model="config.copyHint" placeholder="复制课表时展示的说明文字" :disabled="!session.can('schedule.session.write')" />

      <view class="section-title">展示标签</view>
      <view v-for="(tag, index) in config.displayTags" :key="tag.key" class="tag-row">
        <u-input v-model="tag.label" placeholder="标签名" :disabled="!session.can('schedule.session.write')" />
        <u-input v-model="tag.color" placeholder="#1677ff" :disabled="!session.can('schedule.session.write')" />
        <u-button v-if="session.can('schedule.session.write')" size="mini" text="删除" @click="removeTag(index)" />
      </view>
      <u-button v-if="session.can('schedule.session.write')" size="small" text="添加标签" @click="addTag" />

      <u-button
        v-if="session.can('schedule.session.write')"
        type="primary"
        text="保存展示设置"
        :loading="saving"
        class="save-btn"
        @click="save"
      />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.section-card { padding: 24rpx; background: #fff; border-radius: 16rpx; }
.section-title { margin: 20rpx 0 12rpx; font-size: 28rpx; font-weight: 600; }
.tag-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 12rpx; margin-bottom: 12rpx; }
.save-btn { margin-top: 24rpx; }
</style>
