<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import {
  batchCancelStaffScheduleSessions,
  batchCopyStaffScheduleSessions,
  batchSuspendStaffScheduleSessions,
  fetchStaffScheduleSessions,
} from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { ScheduleSession } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";
import { formatSessionTime, sessionStatusLabel, todayIsoDate } from "@/utils/format";

type BatchAction = "copy" | "suspend" | "cancel";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(false);
const submitting = ref(false);
const errorMessage = ref("");
const resultMessage = ref("");
const action = ref<BatchAction>("suspend");
const sourceFrom = ref(todayIsoDate());
const sourceTo = ref(todayIsoDate());
const targetFrom = ref(todayIsoDate());
const reason = ref("");
const sessions = ref<ScheduleSession[]>([]);
const selectedIds = ref<number[]>([]);

const canCopy = computed(() => session.can("schedule.batch.copy"));
const canSuspend = computed(() => session.can("schedule.batch.suspend"));
const canCancel = computed(() => session.can("schedule.batch.cancel"));
const hasAnyPermission = computed(() => canCopy.value || canSuspend.value || canCancel.value);

const actionOptions = computed(() => {
  const items: Array<{ key: BatchAction; label: string; enabled: boolean }> = [
    { key: "copy", label: "批量复制", enabled: canCopy.value },
    { key: "suspend", label: "批量停课", enabled: canSuspend.value },
    { key: "cancel", label: "批量取消", enabled: canCancel.value },
  ];
  return items.filter((item) => item.enabled);
});

const allSelected = computed(
  () => sessions.value.length > 0 && selectedIds.value.length === sessions.value.length,
);

function toggleSession(id: number) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((item) => item !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
}

function toggleSelectAll() {
  selectedIds.value = allSelected.value ? [] : sessions.value.map((item) => item.id);
}

function dayOffsetBetween(from: string, to: string) {
  const start = new Date(`${from}T00:00:00`);
  const end = new Date(`${to}T00:00:00`);
  return Math.round((end.getTime() - start.getTime()) / 86_400_000);
}

async function loadSessions() {
  if (!session.currentSiteId) {
    errorMessage.value = "请先选择场馆";
    return;
  }
  if (sourceFrom.value > sourceTo.value) {
    errorMessage.value = "开始日期不能晚于结束日期";
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  resultMessage.value = "";
  try {
    const from = `${sourceFrom.value}T00:00:00`;
    const to = `${sourceTo.value}T23:59:59`;
    const response = await fetchStaffScheduleSessions(session.currentSiteId, from, to);
    sessions.value = response.items;
    selectedIds.value = response.items.map((item) => item.id);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课表加载失败";
    sessions.value = [];
    selectedIds.value = [];
  } finally {
    loading.value = false;
  }
}

async function submit() {
  if (!session.currentSiteId) return;
  if (action.value !== "copy" && !reason.value.trim()) {
    uni.showToast({ title: "请填写操作原因", icon: "none" });
    return;
  }
  const confirmation = await uni.showModal({
    title: "确认批量操作",
    content: action.value === "copy"
      ? `将复制 ${sourceFrom.value} 至 ${sourceTo.value} 的课表到目标日期？`
      : `将对 ${selectedIds.value.length} 节课执行${action.value === "suspend" ? "停课" : "取消"}？`,
  });
  if (!confirmation.confirm) return;

  submitting.value = true;
  errorMessage.value = "";
  resultMessage.value = "";
  const commandKey = createCommandKey();
  try {
    if (action.value === "copy") {
      const result = await batchCopyStaffScheduleSessions(session.currentSiteId, {
        commandKey,
        sourceFrom: `${sourceFrom.value}T00:00:00`,
        sourceTo: `${sourceTo.value}T23:59:59`,
        targetFrom: `${targetFrom.value}T00:00:00`,
        dayOffset: dayOffsetBetween(sourceFrom.value, targetFrom.value),
      });
      resultMessage.value = `已复制 ${result.createdSessionIds.length} 节课`;
    } else if (action.value === "suspend") {
      const result = await batchSuspendStaffScheduleSessions(session.currentSiteId, {
        commandKey,
        sessionIds: selectedIds.value,
        reason: reason.value.trim(),
      });
      resultMessage.value = `停课成功 ${result.succeededSessionIds.length} 节`;
    } else {
      const result = await batchCancelStaffScheduleSessions(session.currentSiteId, {
        commandKey,
        sessionIds: selectedIds.value,
        reason: reason.value.trim(),
      });
      const failed = result.failed.length;
      resultMessage.value = failed
        ? `取消成功 ${result.succeededSessionIds.length} 节，${failed} 节因有预约未取消`
        : `取消成功 ${result.succeededSessionIds.length} 节`;
    }
    uni.showToast({ title: "操作完成", icon: "success" });
    await loadSessions();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "批量操作失败";
  } finally {
    submitting.value = false;
  }
}

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  if (actionOptions.value.length && !actionOptions.value.some((item) => item.key === action.value)) {
    action.value = actionOptions.value[0].key;
  }
});
</script>

<template>
  <u-loading-page :loading="checking || loading" />
  <view v-if="!checking" class="page-container batch-page">
    <view class="page-title">批量课表工具</view>
    <view class="page-hint">按日期范围加载排课，支持批量复制、停课或取消</view>

    <u-empty v-if="!hasAnyPermission" mode="permission" text="当前账号暂无批量课表权限" />

    <template v-else>
      <view class="action-row">
        <button
          v-for="item in actionOptions"
          :key="item.key"
          class="action-button"
          :class="{ active: action === item.key }"
          @click="action = item.key"
        >
          {{ item.label }}
        </button>
      </view>

      <view class="field-label">来源日期</view>
      <view class="date-row">
        <picker mode="date" :value="sourceFrom" @change="sourceFrom = String($event.detail.value)">
          <view class="picker-field">{{ sourceFrom }}</view>
        </picker>
        <text class="date-sep">至</text>
        <picker mode="date" :value="sourceTo" @change="sourceTo = String($event.detail.value)">
          <view class="picker-field">{{ sourceTo }}</view>
        </picker>
      </view>

      <view v-if="action === 'copy'" class="field-label">目标起始日期</view>
      <picker
        v-if="action === 'copy'"
        mode="date"
        :value="targetFrom"
        @change="targetFrom = String($event.detail.value)"
      >
        <view class="picker-field">{{ targetFrom }}</view>
      </picker>

      <u-button plain :loading="loading" @click="loadSessions">加载课表</u-button>

      <template v-if="action !== 'copy'">
        <view class="field-label">操作原因</view>
        <u-input v-model="reason" maxlength="500" placeholder="填写停课或取消原因" />

        <view v-if="sessions.length" class="select-row">
          <text class="section-title">已选 {{ selectedIds.length }} / {{ sessions.length }} 节</text>
          <button class="link-button" @click="toggleSelectAll">{{ allSelected ? "取消全选" : "全选" }}</button>
        </view>

        <view v-for="item in sessions" :key="item.id" class="session-row" @tap="toggleSession(item.id)">
          <checkbox :checked="selectedIds.includes(item.id)" color="#1677ff" />
          <view class="session-main">
            <text class="session-title">{{ item.courseName || "未命名课程" }}</text>
            <text class="session-meta">
              {{ formatSessionTime(item.startsAt, item.endsAt) }}
              · {{ sessionStatusLabel(item.status) }}
              · 预约 {{ item.bookedCount }}/{{ item.capacity }}
            </text>
          </view>
        </view>
      </template>

      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-alert v-if="resultMessage" type="success" :description="resultMessage" />

      <u-button
        type="primary"
        :loading="submitting"
        :disabled="action !== 'copy' && selectedIds.length === 0"
        @click="submit"
      >
        {{ action === "copy" ? "执行复制" : action === "suspend" ? "批量停课" : "批量取消" }}
      </u-button>
    </template>
  </view>
</template>

<style scoped lang="scss">
.batch-page { padding-bottom: 48rpx; }
.page-title { font-size: 36rpx; font-weight: 600; }
.page-hint { margin-top: 10rpx; color: $color-text-secondary; font-size: 24rpx; }
.action-row { display: flex; flex-wrap: wrap; gap: 12rpx; margin: 28rpx 0; }
.action-button, .link-button { margin: 0; padding: 14rpx 24rpx; color: $color-text-secondary; font-size: 26rpx; background: $color-page; border: 1rpx solid $color-border; border-radius: 999rpx; }
.action-button.active { color: #fff; background: $color-primary; border-color: $color-primary; }
.action-button::after, .link-button::after { border: 0; }
.field-label { margin: 24rpx 0 12rpx; color: $color-text-secondary; font-size: 24rpx; }
.date-row { display: flex; align-items: center; gap: 12rpx; }
.date-sep { color: $color-text-secondary; font-size: 24rpx; }
.picker-field { min-height: 80rpx; box-sizing: border-box; padding: 20rpx; background: $color-surface; border: 1rpx solid $color-border; border-radius: $radius-sm; }
.select-row { display: flex; align-items: center; justify-content: space-between; margin: 28rpx 0 12rpx; }
.section-title { font-size: 28rpx; font-weight: 600; }
.link-button { padding: 0 12rpx; color: $color-primary; background: transparent; border: 0; }
.session-row { display: flex; align-items: flex-start; gap: 16rpx; padding: 20rpx 0; border-bottom: 1rpx solid $color-border; }
.session-main { flex: 1; min-width: 0; }
.session-title, .session-meta { display: block; }
.session-title { font-size: 28rpx; font-weight: 600; }
.session-meta { margin-top: 8rpx; color: $color-text-secondary; font-size: 24rpx; }
</style>
