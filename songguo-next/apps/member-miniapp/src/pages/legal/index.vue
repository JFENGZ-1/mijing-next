<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { requireMemberAuth } from "@/auth/guard";
import { getMemberLegalDocuments } from "@/api/member";
import type { LegalDocumentData } from "@/types/member";
import { formatApiErrorMessage } from "@/utils/api-error";

const errorMessage = ref("");
const documents = ref<LegalDocumentData[]>([]);
const selectedId = ref<number | null>(null);
const selectedType = ref<string | null>(null);

const loading = ref(true);

const currentDocument = computed(() => {
  if (selectedId.value != null) {
    return documents.value.find((item) => item.id === selectedId.value) ?? null;
  }
  if (selectedType.value) {
    return documents.value.find((item) => item.type === selectedType.value) ?? null;
  }
  return documents.value[0] ?? null;
});

async function loadDocuments() {
  errorMessage.value = "";
  documents.value = [];

  try {
    const response = await getMemberLegalDocuments();
    documents.value = response.data;
    if (!currentDocument.value && documents.value.length > 0) {
      selectedId.value = documents.value[0].id;
    }
  } catch (error) {
    errorMessage.value = formatApiErrorMessage(error, "协议内容加载失败");
  } finally {
    loading.value = false;
  }
}

onLoad((query) => {
  if (query?.id) selectedId.value = Number(query.id);
  if (query?.type) selectedType.value = String(query.type);
});

onShow(async () => { if (await requireMemberAuth()) await loadDocuments(); });
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="legal-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />
    <u-empty v-if="!currentDocument && !errorMessage" mode="data" text="还没有数据哦" />

    <template v-if="currentDocument">
      <view class="doc-title">{{ currentDocument.title }}</view>
      <view class="doc-version">版本 {{ currentDocument.version }}</view>
      <view class="doc-content">{{ currentDocument.content }}</view>
    </template>

    <view class="bottom-logo">
      <text>松果约课</text>
    </view>
  </view>
</template>

<style scoped lang="scss">
.legal-page {
  min-height: 100vh;
  padding: 50rpx 40rpx 80rpx;
  background: $color-surface;
}

.doc-title {
  color: $color-text;
  font-size: 36rpx;
  font-weight: 600;
  line-height: 48rpx;
}

.doc-version {
  margin-top: 12rpx;
  margin-bottom: 32rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.doc-content {
  color: $color-text;
  font-size: 25rpx;
  line-height: 40rpx;
  white-space: pre-wrap;
}

.bottom-logo {
  margin-top: 60rpx;
  text-align: center;
  color: $color-text-muted;
  font-size: 22rpx;
}
</style>
