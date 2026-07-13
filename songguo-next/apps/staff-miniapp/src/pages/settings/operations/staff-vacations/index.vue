<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchStaffVacationRollup } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffVacationRollupItem } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const errorMessage = ref("");
const items = ref<StaffVacationRollupItem[]>([]);

async function load() {
  if (!session.currentSiteId || !session.can("tenant.staff.vacation.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchStaffVacationRollup(session.currentSiteId);
    items.value = config.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "教练请假加载失败";
  } finally {
    loading.value = false;
  }
}

function openDetail(staffId: number) {
  uni.navigateTo({ url: `/pages/settings/operations/staff-vacations/detail?staffId=${staffId}` });
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!session.can('tenant.staff.vacation.read')" mode="permission" text="暂无查看权限" />

    <u-cell-group v-else>
      <u-cell
        v-for="item in items"
        :key="item.staff.id"
        :title="item.staff.displayName"
        :label="`进行中 ${item.activeCount} / 待开始 ${item.upcomingCount}`"
        is-link
        @click="openDetail(item.staff.id)"
      />
    </u-cell-group>
    <u-empty v-if="session.can('tenant.staff.vacation.read') && items.length === 0" mode="list" text="暂无教练请假记录" />
  </view>
</template>

<style scoped lang="scss">
.page-container {
  min-height: 100vh;
  padding: 24rpx;
  background: #f4f6f8;
}
</style>
