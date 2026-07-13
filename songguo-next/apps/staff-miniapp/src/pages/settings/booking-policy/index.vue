<script setup lang="ts">
import { ref } from "vue";
import { onShow } from "@dcloudio/uni-app";
import { fetchBookingPolicy, updateBookingPolicy } from "@/api/settings";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { BookingPolicyConfig } from "@/types/settings";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const policy = ref<BookingPolicyConfig | null>(null);

async function load() {
  if (!session.currentSiteId || !session.can("booking.policy.read")) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    policy.value = await fetchBookingPolicy(session.currentSiteId);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约设置加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !policy.value || !session.can("booking.policy.write")) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    policy.value = await updateBookingPolicy(session.currentSiteId, policy.value);
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
  <view v-if="!loading && policy" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!session.can('booking.policy.read')" mode="permission" text="暂无查看权限" />

    <view v-else>
      <view class="section-card">
        <view class="section-title">团课预约</view>
        <view class="field">
          <text class="label">可提前预约天数</text>
          <u-input v-model="policy.group.advanceBookingDays" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">日历展示天数</text>
          <u-input v-model="policy.group.calendarDisplayDays" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">开课前截止预约（分钟）</text>
          <u-input
            v-model="policy.group.bookingCutoffMinutesBeforeStart"
            type="number"
            :disabled="!session.can('booking.policy.write')"
          />
        </view>
        <view class="field">
          <text class="label">开课前截止取消（分钟）</text>
          <u-input
            v-model="policy.group.cancelCutoffMinutesBeforeStart"
            type="number"
            :disabled="!session.can('booking.policy.write')"
          />
        </view>
        <view class="row">
          <text>开启候补</text>
          <u-switch v-model="policy.group.waitlistEnabled" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="row">
          <text>显示已约人数</text>
          <u-switch v-model="policy.group.showBookedCount" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="row">
          <text>人数不足自动取消</text>
          <u-switch
            v-model="policy.group.autoCancelUnderMinStudentsEnabled"
            :disabled="!session.can('booking.policy.write')"
          />
        </view>
        <view class="row">
          <text>缺席扣次</text>
          <u-switch v-model="policy.group.absentPenaltyEnabled" :disabled="!session.can('booking.policy.write')" />
        </view>
      </view>

      <view class="section-card">
        <view class="section-title">私教预约</view>
        <view class="field">
          <text class="label">可提前预约天数</text>
          <u-input v-model="policy.private.advanceBookingDays" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">最少提前（分钟）</text>
          <u-input v-model="policy.private.minimumLeadMinutes" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">时段间隔（分钟）</text>
          <u-input v-model="policy.private.slotIntervalMinutes" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">开课前截止取消（分钟）</text>
          <u-input
            v-model="policy.private.cancelCutoffMinutesBeforeStart"
            type="number"
            :disabled="!session.can('booking.policy.write')"
          />
        </view>
        <view class="row">
          <text>已约时段置灰</text>
          <u-switch v-model="policy.private.grayOutBookedSlots" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="row">
          <text>缺席扣次</text>
          <u-switch v-model="policy.private.absentPenaltyEnabled" :disabled="!session.can('booking.policy.write')" />
        </view>
      </view>

      <u-button
        v-if="session.can('booking.policy.write')"
        type="primary"
        text="保存预约设置"
        :loading="saving"
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

.section-card {
  margin-bottom: 24rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  background: #fff;
}

.section-title {
  margin-bottom: 16rpx;
  font-size: 30rpx;
  font-weight: 600;
}

.field {
  margin-bottom: 20rpx;
}

.label {
  display: block;
  margin-bottom: 8rpx;
  color: #666;
  font-size: 26rpx;
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16rpx 0;
  border-top: 1px solid #f0f0f0;
}
</style>
