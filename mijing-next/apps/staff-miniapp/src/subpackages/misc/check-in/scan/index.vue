<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { markStaffAppointmentCheckIn, resolveStaffCheckIn } from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffAppointment } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";
import {
  fetchAppointmentConsumptionPreview,
  fetchAppointmentConsumptionSettlement,
} from "@/api/consumption";
import ConsumptionStatus from "@/components/consumption-status/consumption-status.vue";
import type { AppointmentConsumptionPreview, ConsumptionSettlement } from "@/types/consumption";

const session = useSessionStore();
const loading = ref(false);
const acting = ref(false);
const errorMessage = ref("");
const manualCode = ref("");
const memberName = ref("");
const appointments = ref<StaffAppointment[]>([]);
const previews = ref<Record<number, AppointmentConsumptionPreview | null>>({});
const completedResult = ref<{ appointment: StaffAppointment; settlement: ConsumptionSettlement | AppointmentConsumptionPreview | null } | null>(null);

const canCheckIn = computed(() => session.can("booking.fulfillment.check-in"));

function resetResult() {
  memberName.value = "";
  appointments.value = [];
  errorMessage.value = "";
  previews.value = {};
  completedResult.value = null;
}

async function resolveCode(code: string) {
  if (!session.currentSiteId || !canCheckIn.value) return;
  loading.value = true;
  errorMessage.value = "";
  resetResult();
  try {
    const result = await resolveStaffCheckIn(session.currentSiteId, code);
    memberName.value = result.member.displayName;
    appointments.value = result.appointments;
    const previewEntries = await Promise.all(result.appointments.map(async (appointment) => {
      try {
        return [appointment.id, await fetchAppointmentConsumptionPreview(session.currentSiteId!, appointment.id)] as const;
      } catch {
        return [appointment.id, null] as const;
      }
    }));
    previews.value = Object.fromEntries(previewEntries);
    if (result.appointments.length === 0) {
      errorMessage.value = "该会员今日暂无可签到预约";
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "会员识别失败";
  } finally {
    loading.value = false;
  }
}

function scanCode() {
  if (!canCheckIn.value) return;
  uni.scanCode({
    onlyFromCamera: false,
    success: (result) => {
      if (result.result) void resolveCode(result.result);
    },
    fail: () => {
      uni.showToast({ title: "扫码取消或失败，可改用手动输入", icon: "none" });
    },
  });
}

function submitManualCode() {
  if (!manualCode.value.trim()) {
    uni.showToast({ title: "请输入会员编号", icon: "none" });
    return;
  }
  void resolveCode(manualCode.value.trim());
}

async function checkIn(appointment: StaffAppointment) {
  if (!session.currentSiteId || acting.value) return;
  const preview = previews.value[appointment.id];
  const deduction = preview?.cardType === "stored_value"
    ? `预计扣费 ¥${preview.reservedAmount ?? "待后端核定"}`
    : preview?.cardType === "count"
      ? `预计扣 ${preview.reservedCount ?? "待后端核定"} 次`
      : preview?.cardType === "period"
        ? "期限卡将按当日实际履约次数自动分摊"
        : "耗卡预览未能加载，实际权益将由后端按卡课规则扣除";
  const confirmed = await uni.showModal({
    title: preview ? "确认签到并耗卡" : "耗卡预览不可用",
    content: [
      memberName.value || "当前会员",
      preview?.courseName || `预约 #${appointment.id}`,
      appointment.cardName || preview?.cardName || "会员卡",
      deduction,
      "确认后会完成预约、实际扣卡并生成课时费与提成记录。",
    ].join("\n"),
    confirmText: preview ? "确认签到" : "仍要签到",
    confirmColor: preview ? "#168d61" : "#dc3c5c",
  });
  if (!confirmed.confirm) return;
  acting.value = true;
  try {
    const checkedIn = await markStaffAppointmentCheckIn(session.currentSiteId, appointment.id, createCommandKey());
    let settlement: ConsumptionSettlement | AppointmentConsumptionPreview | null = null;
    try {
      settlement = await fetchAppointmentConsumptionSettlement(session.currentSiteId, appointment.id)
        ?? await fetchAppointmentConsumptionPreview(session.currentSiteId, appointment.id);
    } catch {
      settlement = previews.value[appointment.id] ?? null;
    }
    completedResult.value = { appointment: checkedIn, settlement };
    uni.showToast({ title: "签到成功", icon: "success" });
    appointments.value = appointments.value.filter((item) => item.id !== appointment.id);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "签到失败", icon: "none" });
  } finally {
    acting.value = false;
  }
}

onShow(async () => {
  await requireStaffAuth();
});
</script>

<template>
  <view class="page-container">
    <u-empty v-if="!canCheckIn" mode="permission" text="暂无签到权限" />
    <template v-else>
      <u-alert type="info" description="支持 wx.scanCode 扫描会员码，或手动输入会员编号。签到将完成当日已确认预约。" />
      <view class="actions">
        <u-button type="primary" text="扫码签到" :loading="loading" @click="scanCode" />
      </view>
      <view class="manual-box">
        <u-input v-model="manualCode" placeholder="会员编号 / memberNo:编号" />
        <u-button text="查询预约" :loading="loading" @click="submitManualCode" />
      </view>
      <u-alert v-if="errorMessage" type="warning" :description="errorMessage" />
      <view v-if="memberName" class="result-card">
        <text class="title">{{ memberName }}</text>
        <view v-for="item in appointments" :key="item.id" class="appointment-row">
          <view>
            <text class="course">{{ previews[item.id]?.courseName || `预约 #${item.id}` }}</text>
            <text class="meta">{{ item.cardName || previews[item.id]?.cardName || `场次 ${item.sessionId}` }}</text>
            <ConsumptionStatus :value="previews[item.id]" compact />
          </view>
          <u-button size="small" type="success" text="签到" :loading="acting" @click="checkIn(item)" />
        </view>
      </view>
      <view v-if="completedResult" class="result-card completed-card">
        <text class="title">签到结果</text>
        <text class="course">{{ completedResult.settlement?.courseName || `预约 #${completedResult.appointment.id}` }}</text>
        <ConsumptionStatus :value="completedResult.settlement" />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f5f5f5; }
.actions { margin: 16rpx 0; }
.manual-box { display: grid; gap: 12rpx; margin-bottom: 16rpx; }
.result-card { padding: 20rpx; background: #fff; border-radius: 16rpx; }
.title { display: block; margin-bottom: 12rpx; font-size: 30rpx; font-weight: 600; }
.appointment-row { display: flex; align-items: center; justify-content: space-between; padding: 12rpx 0; border-top: 1rpx solid #f2f4f7; }
.appointment-row > view:first-child { flex: 1; min-width: 0; padding-right: 16rpx; }
.completed-card { margin-top: 18rpx; border: 1rpx solid #d8f2e7; }
.course, .meta { display: block; }
.meta { color: #505050; font-size: 24rpx; }
</style>
