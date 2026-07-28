<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchMemberOnboardingHelp, updateMemberOnboardingHelp } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const posterUrl = ref("");
const stepUrl = ref("");

const canRead = computed(() => session.can("tenant.member-experience.read"));
const canWrite = computed(() => session.can("tenant.member-experience.write"));

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchMemberOnboardingHelp(session.currentSiteId);
    posterUrl.value = config.posterUrl ?? "";
    stepUrl.value = config.stepUrl ?? "";
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "约课帮助加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !canWrite.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const config = await updateMemberOnboardingHelp(session.currentSiteId, {
      posterUrl: posterUrl.value || null,
      stepUrl: stepUrl.value || null,
    });
    posterUrl.value = config.posterUrl ?? "";
    stepUrl.value = config.stepUrl ?? "";
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

    <view v-else class="panel">
      <view class="hint">配置会员端「如何约课」展示图 URL（原页面为静态素材，可替换为 CDN 地址）。</view>
      <u-input v-model="posterUrl" label="海报图 URL" :disabled="!canWrite" />
      <u-input v-model="stepUrl" label="步骤图 URL" :disabled="!canWrite" />
      <u-button v-if="canWrite" type="primary" text="保存" @click="save" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f5f5f5;
}

.panel {
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 24rpx;
}

.hint {
  color: #505050;
  font-size: 26rpx;
  line-height: 1.6;
}
</style>
