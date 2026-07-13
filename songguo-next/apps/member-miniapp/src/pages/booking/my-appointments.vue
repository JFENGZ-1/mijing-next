<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { cancelMemberAppointment, getMemberAppointments } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberAppointmentSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import { appointmentStatusLabel, formatIsoDate } from "@/utils/format";

const loading = ref(true);
const cancellingId = ref<number | null>(null);
const errorMessage = ref("");
const scope = ref<"upcoming" | "past">("upcoming");
const appointments = ref<MemberAppointmentSummary[]>([]);
const cancelCommandKeys = new Map<number, string>();

const scopeOptions = [
  { label: "待上课", value: "upcoming" as const },
  { label: "历史", value: "past" as const },
];

function appointmentTitle(item: MemberAppointmentSummary) {
  return item.courseName || `课程 #${item.sessionId}`;
}

function appointmentTime(item: MemberAppointmentSummary) {
  if (item.startsAt && item.endsAt) {
    return `${formatIsoDate(item.startsAt)} - ${formatIsoDate(item.endsAt)}`;
  }
  return `预约于 ${formatIsoDate(item.bookedAt)}`;
}

function canCancel(item: MemberAppointmentSummary) {
  return scope.value === "upcoming" && (item.status === "confirmed" || item.status === "waitlisted");
}

async function loadAppointments() {
  loading.value = true;
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberAppointments(tenant.tenantId, scope.value);
    appointments.value = response.data.items;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约列表加载失败";
  } finally {
    loading.value = false;
  }
}

function switchScope(nextScope: "upcoming" | "past") {
  if (scope.value === nextScope) return;
  scope.value = nextScope;
  void loadAppointments();
}

function confirmCancel(item: MemberAppointmentSummary) {
  uni.showModal({
    title: "取消预约",
    content: `确定取消「${appointmentTitle(item)}」吗？`,
    success: async (result) => {
      if (!result.confirm) return;
      await cancelAppointment(item);
    },
  });
}

async function cancelAppointment(item: MemberAppointmentSummary) {
  const tenant = await ensureMemberTenant();
  if (!tenant) return;

  let commandKey = cancelCommandKeys.get(item.id);
  if (!commandKey) {
    commandKey = createCommandKey();
    cancelCommandKeys.set(item.id, commandKey);
  }

  cancellingId.value = item.id;
  errorMessage.value = "";

  try {
    await cancelMemberAppointment(tenant.tenantId, item.id, commandKey);
    cancelCommandKeys.delete(item.id);
    uni.showToast({ title: "已取消", icon: "success" });
    await loadAppointments();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "取消失败";
  } finally {
    cancellingId.value = null;
  }
}

onShow(async () => {
  if (await requireMemberAuth()) await loadAppointments();
});

onPullDownRefresh(async () => {
  await loadAppointments();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <view class="scope-strip">
      <u-button
        v-for="item in scopeOptions"
        :key="item.value"
        size="small"
        type="primary"
        :plain="scope !== item.value"
        @click="switchScope(item.value)"
      >
        {{ item.label }}
      </u-button>
    </view>

    <u-empty
      v-if="appointments.length === 0 && !errorMessage"
      mode="list"
      :text="scope === 'upcoming' ? '暂无待上课程' : '暂无历史预约'"
    />
    <view
      v-for="item in appointments"
      :key="item.id"
      class="appointment-card"
    >
      <view class="appointment-title">{{ appointmentTitle(item) }}</view>
      <view class="appointment-meta">{{ appointmentTime(item) }}</view>
      <view class="appointment-meta">状态 {{ appointmentStatusLabel(item.status) }}</view>
      <view v-if="item.cancelledAt" class="appointment-meta">取消于 {{ formatIsoDate(item.cancelledAt) }}</view>
      <u-button
        v-if="canCancel(item)"
        type="error"
        plain
        size="small"
        :loading="cancellingId === item.id"
        @click="confirmCancel(item)"
      >
        取消预约
      </u-button>
    </view>
  </view>
</template>

<style scoped lang="scss">
.scope-strip {
  display: flex;
  gap: $spacing-sm;
  margin-bottom: $spacing-md;
}

.appointment-card {
  margin-bottom: $spacing-sm;
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.appointment-title {
  font-size: 30rpx;
  font-weight: 600;
}

.appointment-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}
</style>
