<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { createStaffVacation, fetchStaffVacations } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffVacationEntry } from "@/types/settings";

const session = useSessionStore();
const staffId = ref(0);
const staffName = ref("");
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const items = ref<StaffVacationEntry[]>([]);
const beginAt = ref("");
const endAt = ref("");
const remark = ref("");

onLoad((query) => {
  staffId.value = Number(query?.staffId || 0);
});

async function load() {
  if (!session.currentSiteId || !staffId.value || !session.can("tenant.staff.vacation.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    const config = await fetchStaffVacations(session.currentSiteId, staffId.value);
    staffName.value = config.staff.displayName;
    items.value = config.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "请假详情加载失败";
  } finally {
    loading.value = false;
  }
}

async function create() {
  if (!session.currentSiteId || !session.can("tenant.staff.vacation.write")) return;
  if (!beginAt.value || !endAt.value) {
    uni.showToast({ title: "请填写起止时间", icon: "none" });
    return;
  }
  saving.value = true;
  try {
    await createStaffVacation(session.currentSiteId, staffId.value, {
      beginAt: beginAt.value,
      endAt: endAt.value,
      remark: remark.value || undefined,
    });
    beginAt.value = "";
    endAt.value = "";
    remark.value = "";
    await load();
    uni.showToast({ title: "已添加", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "添加失败";
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
    <view v-if="session.can('tenant.staff.vacation.write')" class="panel">
      <view class="title">{{ staffName }} · 新增请假</view>
      <u-input v-model="beginAt" placeholder="开始时间 ISO8601" />
      <u-input v-model="endAt" placeholder="结束时间 ISO8601" />
      <u-input v-model="remark" placeholder="备注" />
      <u-button type="primary" text="保存请假" @click="create" />
    </view>

    <view class="panel">
      <view v-for="item in items" :key="item.id" class="list-item">
        <view>{{ item.beginAt }} ~ {{ item.endAt }}</view>
        <view class="meta">{{ item.remark || "无备注" }}</view>
        <u-tag :text="item.lifecycleStatus" size="mini" />
      </view>
      <u-empty v-if="items.length === 0" mode="list" text="暂无请假记录" />
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
  margin-bottom: 24rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}

.title {
  font-size: 30rpx;
  font-weight: 600;
}

.list-item {
  padding: 16rpx 0;
  border-bottom: 1px solid #f0f0f0;
}

.meta {
  margin: 8rpx 0;
  color: #666;
  font-size: 26rpx;
}
</style>
