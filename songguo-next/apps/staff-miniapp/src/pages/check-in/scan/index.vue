<script setup lang="ts">
import { computed, ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { markStaffAppointmentCheckIn, resolveStaffCheckIn } from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffAppointment } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const loading = ref(false);
const acting = ref(false);
const errorMessage = ref("");
const manualCode = ref("");
const memberName = ref("");
const appointments = ref<StaffAppointment[]>([]);

const canCheckIn = computed(() => session.can("booking.fulfillment.check-in"));

function resetResult() {
  memberName.value = "";
  appointments.value = [];
  errorMessage.value = "";
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
  acting.value = true;
  try {
    await markStaffAppointmentCheckIn(session.currentSiteId, appointment.id, createCommandKey());
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
            <text class="course">预约 #{{ item.id }}</text>
            <text class="meta">场次 {{ item.sessionId }}</text>
          </view>
          <u-button size="small" type="success" text="签到" :loading="acting" @click="checkIn(item)" />
        </view>
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container { min-height: 100vh; padding: 24rpx; background: #f4f6f8; }
.actions { margin: 16rpx 0; }
.manual-box { display: grid; gap: 12rpx; margin-bottom: 16rpx; }
.result-card { padding: 20rpx; background: #fff; border-radius: 16rpx; }
.title { display: block; margin-bottom: 12rpx; font-size: 30rpx; font-weight: 600; }
.appointment-row { display: flex; align-items: center; justify-content: space-between; padding: 12rpx 0; border-top: 1rpx solid #f2f4f7; }
.course, .meta { display: block; }
.meta { color: #667085; font-size: 24rpx; }
</style>
