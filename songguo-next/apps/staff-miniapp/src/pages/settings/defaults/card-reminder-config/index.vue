<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchMemberCardReminderConfig, updateMemberCardReminderConfig } from "@/api/member-cards";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { MemberCardReminderConfig } from "@/types/reports";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const config = ref<MemberCardReminderConfig>({ expiringWithinDays: 30, zeroBalanceThreshold: "0.00" });

async function load() {
  if (!session.currentSiteId || !session.can("member-card.reminder.config")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    config.value = await fetchMemberCardReminderConfig(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "提醒阈值加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    config.value = await updateMemberCardReminderConfig(session.currentSiteId, {
      expiringWithinDays: Number(config.value.expiringWithinDays),
      zeroBalanceThreshold: String(config.value.zeroBalanceThreshold),
    });
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
    <u-empty v-if="!session.can('member-card.reminder.config')" mode="permission" text="暂无提醒配置权限" />

    <view v-else class="section-card">
      <view class="field">
        <text class="label">到期提醒天数</text>
        <u-input v-model="config.expiringWithinDays" type="number" />
      </view>
      <view class="field">
        <text class="label">余额为零阈值（元）</text>
        <u-input v-model="config.zeroBalanceThreshold" type="digit" />
      </view>
      <u-button type="primary" text="保存提醒阈值" :loading="saving" @click="save" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.section-card { padding: 24rpx; background: #fff; border-radius: 16rpx; }
.field { margin-bottom: 20rpx; }
.label { display: block; margin-bottom: 8rpx; color: #666; font-size: 26rpx; }
</style>
