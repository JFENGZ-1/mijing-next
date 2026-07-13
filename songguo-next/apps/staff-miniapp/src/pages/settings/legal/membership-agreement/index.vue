<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchMembershipAgreement, updateMembershipAgreement } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const draftHtml = ref("");

async function load() {
  if (!session.currentSiteId || !session.can("tenant.legal.membership-agreement.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchMembershipAgreement(session.currentSiteId);
    draftHtml.value = config.html;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员协议加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !session.can("tenant.legal.membership-agreement.write")) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    const config = await updateMembershipAgreement(session.currentSiteId, draftHtml.value);
    draftHtml.value = config.html;
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
    <u-empty v-if="!session.can('tenant.legal.membership-agreement.read')" mode="permission" text="暂无查看权限" />
    <view v-else class="panel">
      <u-textarea
        v-model="draftHtml"
        placeholder="请输入会员协议内容"
        :disabled="!session.can('tenant.legal.membership-agreement.write')"
        maxlength="50000"
        count
      />
      <u-button
        v-if="session.can('tenant.legal.membership-agreement.write')"
        type="primary"
        text="保存"
        @click="save"
      />
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
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 24rpx;
}
</style>
