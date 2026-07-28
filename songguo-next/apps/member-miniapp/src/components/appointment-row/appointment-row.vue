<script setup lang="ts">
import { computed } from "vue";
import type { AppointmentStatus, MemberAppointmentSummary } from "@/types/member";
import { appointmentStatusLabel } from "@/utils/format";

const props = withDefaults(
  defineProps<{
    item: MemberAppointmentSummary;
    cancellable?: boolean;
    cancelling?: boolean;
    variant?: "card" | "legacy";
  }>(),
  { cancellable: false, cancelling: false, variant: "card" },
);

const emit = defineEmits<{
  (e: "tap"): void;
  (e: "cancel"): void;
}>();

const weekdayCn = ["日", "一", "二", "三", "四", "五", "六"];

function timeLabel(iso: string | null | undefined) {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

const dateWeekday = computed(() => {
  const iso = props.item.startsAt;
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${d.getMonth() + 1}月${d.getDate()}日 周${weekdayCn[d.getDay()]}`;
});

const legacyDate = computed(() => {
  const iso = props.item.startsAt;
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${d.getMonth() + 1}-${d.getDate()} 周${weekdayCn[d.getDay()]}`;
});

const timeRange = computed(() => {
  if (!props.item.startsAt) {
    const booked = bookedDateLabel.value;
    return booked ? `预约于 ${booked}` : "";
  }
  return `${timeLabel(props.item.startsAt)}~${timeLabel(props.item.endsAt)}`;
});

const bookedDateLabel = computed(() => {
  const iso = props.item.bookedAt;
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${d.getMonth() + 1}月${d.getDate()}日`;
});

const coachInitial = computed(() => {
  const name = props.item.coachName || "";
  return name ? name.slice(0, 1) : "教";
});

const tagText = computed(() => (props.item.status === "waitlisted" ? "候补" : ""));

// 对标原版右侧扣费信息（-1次 / -¥88）
const chargeText = computed(() => {
  const count = props.item.chargeCountDelta;
  if (count != null && count !== 0) return `-${Math.abs(count)}次`;
  const amount = props.item.chargeAmountDelta;
  if (amount && Number(amount) !== 0) return `-¥${amount}`;
  return "";
});

const cancelLabel = computed(() =>
  props.item.status === "waitlisted" ? "取消排队" : "取消预约",
);

// 对标原版：已签到绿色、失败/取消红色、排队橙色
function statusColor(status: AppointmentStatus) {
  switch (status) {
    case "confirmed":
      return "#22c788";
    case "waitlisted":
      return "#ff9c00";
    case "cancelled":
      return "#dc3c5c";
    case "absent":
      return "#dc3c5c";
    case "completed":
      return "#22c788";
    default:
      return "#989898";
  }
}

function statusBg(status: AppointmentStatus) {
  switch (status) {
    case "confirmed":
      return "rgba(7,193,96,0.12)";
    case "waitlisted":
      return "rgba(255,156,0,0.12)";
    case "cancelled":
      return "rgba(136,136,136,0.12)";
    case "absent":
      return "rgba(250,81,81,0.12)";
    case "completed":
      return "rgba(136,136,136,0.12)";
    default:
      return "rgba(136,136,136,0.12)";
  }
}

function onTap() {
  emit("tap");
}

function onCancel() {
  emit("cancel");
}
</script>

<template>
  <!-- 对标原版：照片 + 课程信息 + 右侧扣费/状态列 -->
  <view v-if="variant === 'legacy'" class="appt-legacy" @tap="onTap">
    <view class="photo_img">
      <image v-if="item.coachAvatarUrl" class="coach-photo" :src="item.coachAvatarUrl" mode="aspectFill" />
      <view v-else class="coach-avatar">{{ coachInitial }}</view>
    </view>
    <view class="info_box">
      <view class="info_box_wrap">
        <view class="row_item first_row_item">
          <view class="course_name">
            <text class="name">{{ item.courseName || `课程 #${item.sessionId}` }}</text>
            <view v-if="tagText" class="tag_wrap">
              <view class="tag_text">{{ tagText }}</view>
            </view>
          </view>
          <view v-if="chargeText" class="charge_num">{{ chargeText }}</view>
        </view>

        <view v-if="item.coachName" class="row_item">
          <view class="multiterm_info">
            <text class="coach_name">{{ item.coachName }}</text>
          </view>
        </view>

        <view v-if="legacyDate" class="row_item">
          <view class="time_info">
            <text class="time">{{ legacyDate }}</text>
            <view class="time_quantum">{{ timeRange }}</view>
          </view>
          <view class="status">
            <text :style="{ color: statusColor(item.status) }">{{ appointmentStatusLabel(item.status) }}</text>
          </view>
        </view>

        <view v-if="cancellable" class="row_item row_foot">
          <view class="foot-left" />
          <view
            class="cancel-btn"
            :class="{ 'cancel-btn--disabled': cancelling }"
            @tap.stop="onCancel"
          >
            <text>{{ cancelling ? "取消中..." : cancelLabel }}</text>
          </view>
        </view>
      </view>
      <u-line color="#F0F0F0" />
    </view>
  </view>

  <!-- 微信卡片布局 -->
  <view v-else class="appt-card" @tap="onTap">
    <view class="card-head">
      <text class="course-name">{{ item.courseName || `课程 #${item.sessionId}` }}</text>
      <view v-if="tagText" class="tag-pill tag-pill--waitlist">{{ tagText }}</view>
      <view class="status-pill" :style="{ color: statusColor(item.status), background: statusBg(item.status) }">
        {{ appointmentStatusLabel(item.status) }}
      </view>
    </view>

    <view class="card-body">
      <view v-if="item.coachName" class="body-row">
        <text class="body-label">教练</text>
        <text class="body-value">{{ item.coachName }}</text>
      </view>
      <view v-if="dateWeekday" class="body-row">
        <text class="body-label">时间</text>
        <text class="body-value">{{ dateWeekday }} {{ timeRange }}</text>
      </view>
    </view>

    <view v-if="cancellable" class="card-foot">
      <view
        class="cancel-link"
        :class="{ 'cancel-link--disabled': cancelling }"
        @tap.stop="onCancel"
      >
        {{ cancelling ? "取消中..." : cancelLabel }}
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
/* ============ legacy 变体 ============ */
.appt-legacy {
  display: flex;
  padding: 16rpx 0 12rpx;
}

.photo_img {
  flex-shrink: 0;
  width: 125rpx;
  height: 125rpx;
  margin-top: 8rpx;
  border-radius: 20rpx;
  overflow: hidden;
}

.coach-photo {
  display: block;
  width: 100%;
  height: 100%;
}

.coach-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
  color: #fff;
  font-size: 44rpx;
  font-weight: 500;
}

.info_box {
  flex: 1;
  margin-left: 12rpx;
  padding-top: 10rpx;
}

.info_box_wrap {
  margin-right: 11rpx;
  min-height: 162rpx;
}

.row_item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 13rpx;
}

.first_row_item {
  margin-top: 0;
}

.course_name {
  display: flex;
  align-items: center;
  flex: 1;
}

.course_name .name {
  color: #181818;
  font-size: 32rpx;
  font-weight: 500;
  line-height: 34rpx;
}

/* 对标原版右上角扣费大字 */
.charge_num {
  flex-shrink: 0;
  margin-left: 16rpx;
  color: #181818;
  font-size: 32rpx;
  font-weight: 600;
  line-height: 34rpx;
}

.tag_wrap {
  display: flex;
  align-items: center;
  margin-left: 10rpx;
}

.tag_text {
  padding: 1rpx 10rpx;
  background: #fc8c00;
  border-radius: 0 8rpx 8rpx 0;
  color: #fff;
  font-size: 18rpx;
  line-height: 28rpx;
}

.multiterm_info {
  display: flex;
  align-items: center;
  flex: 1;
  color: #989898;
  font-size: 22rpx;
}

.coach_name {
  color: #989898;
}

.time_info {
  display: flex;
  align-items: center;
  border-radius: 16rpx;
  font-size: 22rpx;
}

.time_info .time {
  margin-right: 12rpx;
  color: #989898;
}

.time_quantum {
  padding: 0 18rpx;
  height: 32rpx;
  line-height: 32rpx;
  background: #faf5f8;
  border-radius: 18rpx;
  color: #dc3c5c;
}

.status {
  flex: 1;
  display: flex;
  justify-content: flex-end;
  font-size: 22rpx;
}

.row_foot {
  margin-top: 6rpx;
  align-items: flex-start;
}

.foot-left {
  flex: 1;
}

.cancel-btn {
  padding: 6rpx 22rpx;
  border: 1rpx solid #dc3c5c;
  border-radius: 30rpx;
  color: #dc3c5c;
  font-size: 22rpx;
  line-height: 32rpx;
}

.cancel-btn--disabled {
  opacity: 0.5;
}

/* ============ card 变体 ============ */
.appt-card {
  background: #fff;
  border-radius: 16rpx;
  padding: 28rpx 32rpx;
}

.card-head {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.course-name {
  flex: 1;
  color: #181818;
  font-size: 32rpx;
  font-weight: 600;
  line-height: 40rpx;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tag-pill {
  padding: 2rpx 14rpx;
  border-radius: 8rpx;
  font-size: 20rpx;
  line-height: 30rpx;
}

.tag-pill--waitlist {
  background: #fff4e0;
  color: #ff9c00;
}

.status-pill {
  flex-shrink: 0;
  padding: 4rpx 16rpx;
  border-radius: 8rpx;
  font-size: 22rpx;
  line-height: 32rpx;
}

.card-body {
  margin-top: 16rpx;
}

.body-row {
  display: flex;
  align-items: center;
  gap: 16rpx;
  padding: 8rpx 0;
  font-size: 26rpx;
}

.body-label {
  color: #888;
  flex-shrink: 0;
  width: 96rpx;
}

.body-value {
  color: #181818;
  flex: 1;
}

.card-foot {
  margin-top: 20rpx;
  display: flex;
  justify-content: flex-end;
  border-top: 1rpx solid #f0f0f0;
  padding-top: 20rpx;
}

.cancel-link {
  color: #fa5151;
  font-size: 26rpx;
  padding: 4rpx 12rpx;
}

.cancel-link--disabled {
  color: #c0c0c0;
}
</style>
