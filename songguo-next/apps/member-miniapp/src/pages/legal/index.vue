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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!currentDocument && !errorMessage" mode="data" text="暂无协议内容" />

    <template v-if="currentDocument">
      <view class="doc-header">
        <view class="doc-title">{{ currentDocument.title }}</view>
        <view class="doc-version">版本 {{ currentDocument.version }}</view>
      </view>
      <view class="doc-content">{{ currentDocument.content }}</view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.doc-header {
  margin-bottom: $spacing-md;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.doc-title {
  font-size: 34rpx;
  font-weight: 600;
}

.doc-version {
  margin-top: $spacing-xs;
  margin-bottom: $spacing-md;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.doc-content {
  padding: $spacing-md;
  color: $color-text;
  font-size: 28rpx;
  line-height: 1.7;
  white-space: pre-wrap;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}
</style>
