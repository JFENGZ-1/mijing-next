<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { requireStaffAuth } from "@/auth/guard";
import { batchImportCrmMembers } from "@/api/crm";
import { useSessionStore } from "@/stores/session";
import type { CrmBatchImportError, CrmBatchImportResult } from "@/types/crm";

const session = useSessionStore();
const text = ref("");
const importing = ref(false);
const errorMessage = ref("");
const result = ref<CrmBatchImportResult | null>(null);

async function submit() {
  if (!session.currentSiteId) {
    errorMessage.value = "当前账号没有可用场馆";
    return;
  }
  if (!text.value.trim()) {
    errorMessage.value = "请粘贴待导入内容";
    return;
  }
  importing.value = true;
  errorMessage.value = "";
  result.value = null;
  try {
    const response = await batchImportCrmMembers(session.currentSiteId, { text: text.value });
    result.value = response.data;
    if (response.data.successCount > 0 && response.data.failCount === 0) {
      text.value = "";
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "批量导入失败";
  } finally {
    importing.value = false;
  }
}

function errorLabel(error: CrmBatchImportError) {
  return `第 ${error.line} 行：${error.message}`;
}

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="page-container import-page">
    <view class="page-title">批量导入潜客</view>
    <view class="page-hint">每行一条，格式为「手机号+姓名」，例如 13800138000张三。重复手机号会报错，不会自动合并。</view>
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="field-label">粘贴内容</view>
    <u-textarea v-model="text" maxlength="20000" height="320" placeholder="13800138000张三&#10;13800138001李四" />

    <u-button type="primary" :loading="importing" @click="submit">开始导入</u-button>

    <view v-if="result" class="result-band">
      <view class="result-summary">成功 {{ result.successCount }} 条，失败 {{ result.failCount }} 条</view>
      <view v-for="error in result.errors" :key="`${error.line}-${error.code}`" class="error-row">
        {{ errorLabel(error) }}
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.import-page { padding-bottom: 48rpx; }
.page-title { font-size: 36rpx; font-weight: 600; }
.page-hint, .field-label { margin-top: 10rpx; color: $color-text-secondary; font-size: 24rpx; line-height: 1.5; }
.field-label { margin: 28rpx 0 12rpx; }
.result-band { margin-top: 32rpx; padding: 24rpx; background: $color-surface; border: 1rpx solid $color-border; border-radius: $radius-sm; }
.result-summary { font-weight: 600; }
.error-row { margin-top: 12rpx; color: #b42318; font-size: 24rpx; line-height: 1.5; }
</style>
