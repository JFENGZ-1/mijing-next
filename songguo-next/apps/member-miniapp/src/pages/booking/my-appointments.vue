<script setup lang="ts">
import { ref } from "vue";
import { onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { cancelMemberAppointment, getMemberAppointments } from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberAppointmentSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";

const loading = ref(true);
const cancellingId = ref<number | null>(null);
const errorMessage = ref("");
const scope = ref<"upcoming" | "past">("upcoming");
const appointments = ref<MemberAppointmentSummary[]>([]);
const cancelCommandKeys = new Map<number, string>();

const scopeTabs = [
  { name: "待上课" },
  { name: "历史" },
];

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

function onTabChange(item: { index: number }) {
  const next = item.index === 1 ? "past" : "upcoming";
  if (scope.value === next) return;
  scope.value = next;
  void loadAppointments();
}

function openDetail(item: MemberAppointmentSummary) {
  uni.navigateTo({ url: `/pages/booking/detail?id=${item.sessionId}` });
}

function confirmCancel(item: MemberAppointmentSummary) {
  const title = item.status === "waitlisted" ? "取消排队" : "取消预约";
  const content = item.status === "waitlisted"
    ? "确定取消排队吗？"
    : "确定取消该预约吗？（若有扣费）将退还已扣相应费用";

  uni.showModal({
    title,
    content,
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
  <view v-if="!loading" class="record-page">
    <view class="tabs-wrap">
      <u-tabs
        :list="scopeTabs"
        :current="scope === 'past' ? 1 : 0"
        line-color="#07c160"
        :active-style="{ color: '#181818', fontWeight: 600 }"
        :inactive-style="{ color: '#989898' }"
        @change="onTabChange"
      />
    </view>

    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <view class="list-wrap">
      <view
        v-for="item in appointments"
        :key="item.id"
        class="list-item"
      >
        <appointment-row
          :item="item"
          :cancellable="canCancel(item)"
          :cancelling="cancellingId === item.id"
          @tap="openDetail(item)"
          @cancel="confirmCancel(item)"
        />
      </view>

      <u-empty
        v-if="appointments.length === 0 && !errorMessage"
        mode="list"
        :text="scope === 'upcoming' ? '~ 无预约记录哦 ~' : '~ 暂无历史记录 ~'"
      />

      <bottom-logo v-if="appointments.length" />
    </view>
  </view>
</template>

<style scoped lang="scss">
.record-page {
  min-height: 100vh;
  background: #ededed;
}

.tabs-wrap {
  background: #fff;
  border-bottom: 1rpx solid #f0f0f0;
}

.list-wrap {
  padding: 24rpx 28rpx 0;
}

.list-item {
  margin-bottom: 24rpx;
}
</style>
