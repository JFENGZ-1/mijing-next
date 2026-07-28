<script setup lang="ts">
import { computed, ref } from "vue";
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

const groupConflictOptions = [
  { value: "block", label: "禁止与团课重合" },
  { value: "allow", label: "允许重合（仅已约团课占时）" },
  { value: "overlap_warn", label: "重合时提示（员工可确认代约）" },
] as const;

const groupConflictLabels = groupConflictOptions.map((item) => item.label);

const groupConflictIndex = computed(() => {
  const mode = policy.value?.private.groupConflictMode ?? "block";
  const index = groupConflictOptions.findIndex((item) => item.value === mode);
  return index >= 0 ? index : 0;
});

function onGroupConflictChange(event: { detail: { value: string } }) {
  if (!policy.value) return;
  const picked = groupConflictOptions[Number(event.detail.value)];
  if (picked) policy.value.private.groupConflictMode = picked.value;
}

const groupMaxBookings = computed({
  get: () => (policy.value?.group.maxBookingsPerDay == null ? "" : String(policy.value.group.maxBookingsPerDay)),
  set: (value: string) => {
    if (!policy.value) return;
    policy.value.group.maxBookingsPerDay = value === "" ? null : Number(value);
  },
});

const privateMaxBookings = computed({
  get: () => (policy.value?.private.maxBookingsPerDay == null ? "" : String(policy.value.private.maxBookingsPerDay)),
  set: (value: string) => {
    if (!policy.value) return;
    policy.value.private.maxBookingsPerDay = value === "" ? null : Number(value);
  },
});

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
      <view class="tips-card">
        以下设置主要约束会员约课端。教练/管理端代约、取消不受提前预约、截止预约、每日上限等限制；私教「与团课重合·提示」仅员工确认后可代约。
      </view>
      <view class="section-card">
        <view class="section-title">团课预约</view>
        <view class="field">
          <text class="label">系统签到（开课前分钟）</text>
          <u-input v-model="policy.group.signMinutesBeforeStart" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">可提前预约天数</text>
          <u-input v-model="policy.group.advanceBookingDays" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">每日开放最远可约（时:分）</text>
          <view class="inline-pair">
            <u-input v-model="policy.group.advanceBookingDailyCutoffHour" type="number" :disabled="!session.can('booking.policy.write')" />
            <text class="pair-sep">:</text>
            <u-input v-model="policy.group.advanceBookingDailyCutoffMinute" type="number" :disabled="!session.can('booking.policy.write')" />
          </view>
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
        <view v-if="policy.group.autoCancelUnderMinStudentsEnabled" class="field">
          <text class="label">开课前判断（分钟，≤180）</text>
          <u-input
            v-model="policy.group.autoCancelUnderMinStudentsMinutesBeforeStart"
            type="number"
            :disabled="!session.can('booking.policy.write')"
          />
        </view>
        <view class="field">
          <text class="label">会员每日预约上限（空=不限）</text>
          <u-input v-model="groupMaxBookings" type="number" :disabled="!session.can('booking.policy.write')" placeholder="不限" />
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
        <view class="field">
          <text class="label">课前休息（分钟）</text>
          <u-input v-model="policy.private.preparationMinutes" type="number" :disabled="!session.can('booking.policy.write')" />
        </view>
        <view class="field">
          <text class="label">与团课重合</text>
          <picker
            mode="selector"
            :range="groupConflictLabels"
            :value="groupConflictIndex"
            :disabled="!session.can('booking.policy.write')"
            @change="onGroupConflictChange"
          >
            <view class="picker-value">{{ groupConflictLabels[groupConflictIndex] }}</view>
          </picker>
        </view>
        <view class="field">
          <text class="label">会员每日私教预约上限（空=不限）</text>
          <u-input v-model="privateMaxBookings" type="number" :disabled="!session.can('booking.policy.write')" placeholder="不限" />
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
  background: #f5f5f5;
}

.tips-card {
  margin-bottom: 24rpx;
  padding: 20rpx 24rpx;
  border-radius: 12rpx;
  background: #fff8f0;
  color: #c96a2f;
  font-size: 24rpx;
  line-height: 1.5;
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

.inline-pair {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.pair-sep {
  color: #666;
}

.picker-value {
  padding: 16rpx 0;
  color: #181818;
  font-size: 28rpx;
}
</style>
