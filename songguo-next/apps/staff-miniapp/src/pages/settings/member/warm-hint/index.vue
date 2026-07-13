<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchMemberWarmHint, updateMemberWarmHint } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { MemberWarmHint } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const hints = ref<MemberWarmHint[]>([]);
const activeIndex = ref(0);
const draftTitle = ref("");
const draftText = ref("");

const canRead = computed(() => session.can("tenant.member-experience.read"));
const canWrite = computed(() => session.can("tenant.member-experience.write"));
const activeHint = computed(() => hints.value[activeIndex.value] ?? null);

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchMemberWarmHint(session.currentSiteId);
    hints.value = config.hints;
    syncDraft();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "温馨提示加载失败";
  } finally {
    loading.value = false;
  }
}

function syncDraft() {
  const hint = activeHint.value;
  draftTitle.value = hint?.title ?? "";
  draftText.value = hint?.text ?? "";
}

function switchTab(index: number) {
  activeIndex.value = index;
  syncDraft();
}

async function save() {
  if (!session.currentSiteId || !canWrite.value || !activeHint.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const config = await updateMemberWarmHint(session.currentSiteId, {
      courseType: activeHint.value.courseType,
      title: draftTitle.value,
      text: draftText.value,
    });
    hints.value = config.hints;
    syncDraft();
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
    <u-empty v-if="!canRead" mode="permission" text="暂无查看权限" />

    <view v-else-if="activeHint">
      <u-tabs :list="hints.map((h) => ({ name: h.courseTypeLabel }))" :current="activeIndex" @change="switchTab" />

      <view class="panel">
        <u-input v-model="draftTitle" placeholder="标题" :disabled="!canWrite" />
        <u-textarea
          v-model="draftText"
          placeholder="如没有需要说明的，则保持为空即可"
          :disabled="!canWrite"
          maxlength="500"
          count
        />
        <u-button v-if="canWrite" type="primary" text="保存" @click="save" />
      </view>
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
  margin-top: 24rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 24rpx;
}
</style>
