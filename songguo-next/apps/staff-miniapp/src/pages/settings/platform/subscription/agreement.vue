<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchPlatformSubscriptionAgreement } from "@/api/platform";
import { requireStaffAuth } from "@/auth/guard";

const loading = ref(true);
const errorMessage = ref("");
const title = ref("平台服务协议");
const html = ref("");
const version = ref("");

async function load() {
  loading.value = true;
  errorMessage.value = "";
  try {
    const agreement = await fetchPlatformSubscriptionAgreement();
    title.value = agreement.title || "平台服务协议";
    html.value = agreement.html || "";
    version.value = agreement.version || "";
    uni.setNavigationBarTitle({ title: title.value });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "协议加载失败";
  } finally {
    loading.value = false;
  }
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <view class="agreement-card">
      <text class="agreement-title">{{ title }}</text>
      <text v-if="version" class="agreement-version">版本 {{ version }}</text>
      <rich-text class="agreement-body" :nodes="html" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.agreement-card {
  padding: $spacing-md;
  background: $color-surface;
  border-radius: $radius-lg;
}

.agreement-title {
  display: block;
  font-size: 34rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.agreement-version {
  display: block;
  margin-top: 12rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
  text-align: center;
}

.agreement-body {
  display: block;
  margin-top: 24rpx;
  color: $color-text-secondary;
  font-size: 26rpx;
  line-height: 1.8;
}
</style>
