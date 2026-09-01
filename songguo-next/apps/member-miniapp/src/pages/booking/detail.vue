<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  cancelMemberAppointment,
  createMemberAppointment,
  getMemberAppointments,
  getMemberBookingPayableCards,
  getMemberBookingSession,
} from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberBookingSessionDetail, MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import { appointmentStatusLabel } from "@/utils/format";

const loading = ref(true);
const booking = ref(false);
const cancelling = ref(false);
const errorMessage = ref("");
const sessionId = ref(0);
const session = ref<MemberBookingSessionDetail | null>(null);
const bookCommandKey = ref<string | null>(null);
const cancelCommandKey = ref<string | null>(null);
const myAppointmentId = ref<number | null>(null);

const weekdayLabels = ["日", "一", "二", "三", "四", "五", "六"];

const isFull = computed(() => {
  const s = session.value;
  if (!s) return false;
  return s.capacity > 0 && (s.bookedCount ?? 0) >= s.capacity;
});

const remaining = computed(() => {
  const s = session.value;
  if (!s) return 0;
  return Math.max(0, s.capacity - (s.bookedCount ?? 0));
});

const tagText = computed(() => {
  const s = session.value;
  if (!s) return "";
  if (isFull.value && s.waitlistEnabled) return "候补";
  const kind = (s.sessionKind || s.courseType || "").toLowerCase();
  return kind === "private" || kind.includes("private") ? "私教" : "";
});

const courseTypeLabel = computed(() => {
  const s = session.value;
  if (!s) return "";
  const kind = (s.sessionKind || s.courseType || "").toLowerCase();
  return kind === "private" || kind.includes("private") ? "私教" : "团课";
});

const coachInitial = computed(() => {
  const name = session.value?.coachName || "";
  return name ? name.slice(0, 1) : "教";
});

const dateLabel = computed(() => {
  const iso = session.value?.startsAt;
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${d.getMonth() + 1}月${d.getDate()}日 周${weekdayLabels[d.getDay()]}`;
});

const timeRange = computed(() => {
  const s = session.value;
  if (!s) return "";
  return `${formatTime(s.startsAt)}~${formatTime(s.endsAt)}`;
});

type PrimaryAction = "book" | "queue" | "cancel-appoint" | "cancel-queue" | "none";

const primaryAction = computed<PrimaryAction>(() => {
  const s = session.value;
  if (!s) return "none";
  if (s.memberAppointmentStatus === "confirmed") return "cancel-appoint";
  if (s.memberAppointmentStatus === "waitlisted") return "cancel-queue";
  if (s.bookable) {
    if (isFull.value && s.waitlistEnabled) return "queue";
    if (isFull.value) return "none";
    return "book";
  }
  return "none";
});

const primaryLabel = computed(() => {
  switch (primaryAction.value) {
    case "book":
      return "立即预约";
    case "queue":
      return "我要排队";
    case "cancel-appoint":
      return "取消预约";
    case "cancel-queue":
      return "取消排队";
    default:
      return "当前不可约";
  }
});

const primaryStyle = computed(() => {
  const base = "border:none;height:88rpx;font-size:30rpx;border-radius:44rpx;line-height:88rpx;";
  switch (primaryAction.value) {
    case "book":
      return `${base}background:#22c788;color:#fff;`;
    case "queue":
      return `${base}background:#ffae00;color:#fff;`;
    case "cancel-appoint":
      return `${base}background:#fff;color:#dc3c5c;border:1rpx solid #dc3c5c;`;
    case "cancel-queue":
      return `${base}background:#fff;color:#ffae00;border:1rpx solid #ffae00;`;
    default:
      return `${base}background:#bfbfbf;color:#fff;`;
  }
});

const statusBadgeClass = computed(() => {
  if (session.value?.memberAppointmentStatus) return "badge-pill--booked";
  if (!session.value?.bookable) return "badge-pill--muted";
  return "badge-pill--bookable";
});

const statusBadgeLabel = computed(() => {
  const s = session.value;
  if (!s) return "";
  if (s.memberAppointmentStatus) return appointmentStatusLabel(s.memberAppointmentStatus);
  if (!s.bookable) return isFull.value ? "已约满" : "不可约";
  return isFull.value && s.waitlistEnabled ? "可候补" : "可预约";
});

function formatTime(iso: string) {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

async function loadSession() {
  if (!sessionId.value) return;

  // 仅首次显示全屏加载，返回本页时静默刷新
  loading.value = !session.value;
  errorMessage.value = "";

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberBookingSession(tenant.tenantId, sessionId.value);
    session.value = response.data;

    if (response.data.memberAppointmentStatus) {
      await resolveMyAppointmentId(tenant.tenantId);
    } else {
      myAppointmentId.value = null;
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课程详情加载失败";
  } finally {
    loading.value = false;
  }
}

async function resolveMyAppointmentId(tenantId: number) {
  try {
    const response = await getMemberAppointments(tenantId, "upcoming");
    const match = response.data.items.find((item) => item.sessionId === sessionId.value);
    myAppointmentId.value = match?.id ?? null;
  } catch {
    myAppointmentId.value = null;
  }
}

function payableCardLabel(card: MemberCardWalletSummary) {
  const name = card.name || "会员卡";
  const balance = cardBalanceText(card);
  return balance ? `${name}（${balance}）` : name;
}

function cardBalanceText(card: MemberCardWalletSummary) {
  if (card.cardType === "count" && card.remainingCount != null) return `剩余 ${card.remainingCount} 次`;
  if (card.cardType === "stored_value" && card.balance) return `余额 ¥${card.balance}`;
  if (card.cardType === "period") return "期限卡";
  return "";
}

async function pickPayableCard(cards: MemberCardWalletSummary[]) {
  return new Promise<MemberCardWalletSummary | null>((resolve) => {
    if (cards.length === 1) {
      resolve(cards[0] ?? null);
      return;
    }

    uni.showActionSheet({
      itemList: cards.map((card) => payableCardLabel(card)),
      success: (result) => resolve(cards[result.tapIndex] ?? null),
      fail: () => resolve(null),
    });
  });
}

async function bookWithCard(card: MemberCardWalletSummary) {
  const tenant = await ensureMemberTenant();
  if (!tenant || !session.value) return;

  if (!bookCommandKey.value) {
    bookCommandKey.value = createCommandKey();
  }

  booking.value = true;
  errorMessage.value = "";

  try {
    const response = await createMemberAppointment(tenant.tenantId, {
      sessionId: session.value.id,
      memberCardId: card.id,
      commandKey: bookCommandKey.value,
    });

    bookCommandKey.value = null;
    const statusLabel = response.data.status === "waitlisted" ? "已加入候补" : "预约成功";
    uni.showToast({ title: statusLabel, icon: "success" });
    await loadSession();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约失败";
  } finally {
    booking.value = false;
  }
}

async function startBooking() {
  if (!session.value?.bookable || booking.value) return;
  if (primaryAction.value !== "book" && primaryAction.value !== "queue") return;

  const tenant = await ensureMemberTenant();
  if (!tenant) {
    errorMessage.value = "请先选择场馆";
    return;
  }

  booking.value = true;
  errorMessage.value = "";

  try {
    const response = await getMemberBookingPayableCards(tenant.tenantId, session.value.id);
    const cards = response.data.items;

    if (cards.length === 0) {
      uni.showToast({ title: "暂无可用会员卡", icon: "none" });
      return;
    }

    const card = await pickPayableCard(cards);
    if (!card) return;

    await bookWithCard(card);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "获取可用会员卡失败";
  } finally {
    booking.value = false;
  }
}

function confirmCancel() {
  if (primaryAction.value !== "cancel-appoint" && primaryAction.value !== "cancel-queue") return;
  const title = primaryAction.value === "cancel-appoint" ? "取消预约" : "取消排队";
  const content = primaryAction.value === "cancel-appoint"
    ? "确定取消该预约吗？（若有扣费）将退还已扣相应费用"
    : "确定取消排队吗？";

  uni.showModal({
    title,
    content,
    success: async (result) => {
      if (!result.confirm) return;
      await cancelCurrent();
    },
  });
}

async function cancelCurrent() {
  const tenant = await ensureMemberTenant();
  if (!tenant || !session.value) return;
  if (!myAppointmentId.value) {
    uni.showToast({ title: "未找到预约记录", icon: "none" });
    return;
  }

  if (!cancelCommandKey.value) {
    cancelCommandKey.value = createCommandKey();
  }

  cancelling.value = true;
  errorMessage.value = "";

  try {
    await cancelMemberAppointment(tenant.tenantId, myAppointmentId.value, cancelCommandKey.value);
    cancelCommandKey.value = null;
    uni.showToast({ title: "已取消", icon: "success" });
    await loadSession();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "取消失败";
  } finally {
    cancelling.value = false;
  }
}

function openMyAppointments() {
  uni.navigateTo({ url: "/pages/booking/my-appointments" });
}

onLoad((query) => {
  const id = Number(query?.id ?? query?.sessionId ?? 0);
  if (id > 0) {
    sessionId.value = id;
  } else {
    errorMessage.value = "无效的课程";
    loading.value = false;
  }
});

onShow(async () => {
  if (sessionId.value > 0 && (await requireMemberAuth())) {
    await loadSession();
  }
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading" class="detail-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" :custom-style="{ margin: '24rpx 28rpx 0' }" />

    <template v-if="session">
      <view class="header">
        <view class="header-content">
          <view class="info_top">
            <view class="left">
              <view class="course_name_row">
                <text class="course_name">{{ session.courseName || "课程" }}</text>
                <view v-if="tagText" class="tag_wrap">
                  <text class="tag_text">{{ tagText }}</text>
                </view>
              </view>
              <view class="header-time">{{ dateLabel }}</view>
            </view>
            <view class="right">
              <view class="photo_image">{{ coachInitial }}</view>
              <view class="coach_name">{{ session.coachName || "教练" }}</view>
            </view>
          </view>

          <view class="info_center">
            <view class="row">
              <view class="item">
                <view class="title">时间</view>
                <view class="text">{{ timeRange }}</view>
              </view>
              <view class="item">
                <view class="title">时长</view>
                <view class="text">{{ session.durationMinutes ? `${session.durationMinutes}Min` : "—" }}</view>
              </view>
              <view class="item">
                <view class="title">类型</view>
                <view class="text">{{ courseTypeLabel }}</view>
              </view>
            </view>
            <view v-if="session.roomName" class="row row-single">
              <view class="item">
                <view class="title">教室</view>
                <view class="text">{{ session.roomName }}</view>
              </view>
            </view>
          </view>

          <view class="info_bottom">
            <view class="current_num">
              已约 <text class="num">{{ session.bookedCount ?? 0 }}/{{ session.capacity }}</text>
            </view>
            <view v-if="isFull" class="full-icon">爆满</view>
            <view v-else class="max_num">剩余 {{ remaining }}</view>
            <view class="status-badge">
              <view class="badge-pill" :class="statusBadgeClass">{{ statusBadgeLabel }}</view>
            </view>
          </view>
        </view>
      </view>

      <view class="main">
        <view v-if="session.description" class="section">
          <view class="section-title">课程简介</view>
          <view class="section-text">{{ session.description }}</view>
        </view>

        <view class="section">
          <view class="section-title">注意事项</view>
          <view class="notice-list">
            <text class="notice-item">· 请提前 10 分钟到馆签到</text>
            <text class="notice-item">· 取消预约请于课程开始前操作</text>
            <text class="notice-item">· 候补顺序按排队先后自动递补</text>
            <text v-if="session.waitlistEnabled" class="notice-item">· 本课程支持候补，满员后可排队等候</text>
          </view>
        </view>

        <view class="link-row" @tap="openMyAppointments">
          <text class="link-text">我的预约</text>
          <u-icon name="arrow-right" size="18" color="#989898" />
        </view>

        <view class="bottom-logo">
          <text>觅境约课</text>
        </view>
      </view>

      <view class="bottom-fixed">
        <view v-if="session.memberAppointmentStatus === 'confirmed'" class="line-up-info info-color1">
          <u-icon name="checkmark-circle" size="22" color="#dc3c5c" />
          <text class="line-up-text">已预约成功</text>
        </view>
        <view v-else-if="session.memberAppointmentStatus === 'waitlisted'" class="line-up-info info-color">
          <u-icon name="error-circle" size="22" color="#dc3c5c" />
          <text class="line-up-text">排队中</text>
        </view>
        <view v-else-if="isFull && session.waitlistEnabled && session.bookable" class="line-up-info info-color">
          <u-icon name="error-circle" size="22" color="#dc3c5c" />
          <text class="line-up-text">该课已约满，可排队候补</text>
        </view>

        <view class="btn-wrap">
          <u-button
            :custom-style="primaryStyle"
            :hairline="false"
            :loading="booking || cancelling"
            :disabled="primaryAction === 'none'"
            hover-class="none"
            @click="primaryAction === 'book' || primaryAction === 'queue' ? startBooking() : confirmCancel()"
          >
            {{ primaryLabel }}
          </u-button>
        </view>
      </view>
    </template>

    <u-empty v-else-if="!errorMessage" mode="data" text="课程不存在或已下架" />
  </view>
</template>

<style scoped lang="scss">
.detail-page {
  min-height: 100vh;
  padding-bottom: 200rpx;
  background: $color-page;
}

.header {
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
}

.header-content {
  padding: 32rpx 32rpx 36rpx;
  color: #fff;
}

.info_top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.left {
  flex: 1;
  padding-right: 24rpx;
}

.course_name_row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.course_name {
  font-size: 42rpx;
  font-weight: 500;
  line-height: 50rpx;
}

.tag_wrap {
  display: flex;
  align-items: center;
  margin-left: 12rpx;
}

.tag_text {
  padding: 2rpx 12rpx;
  background: $color-badge-orange;
  border-radius: 0 8rpx 8rpx 0;
  color: #fff;
  font-size: 22rpx;
  line-height: 32rpx;
}

.header-time {
  margin-top: 14rpx;
  font-size: 26rpx;
  opacity: 0.92;
}

.right {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.photo_image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 112rpx;
  height: 112rpx;
  border-radius: 12rpx;
  background: rgba(255, 255, 255, 0.9);
  color: $color-text;
  font-size: 44rpx;
  font-weight: 500;
}

.coach_name {
  margin-top: 12rpx;
  font-size: 24rpx;
  opacity: 0.95;
}

.info_center {
  margin-top: 36rpx;
  padding: 28rpx 24rpx;
  background: rgba(255, 255, 255, 0.14);
  border-radius: 21rpx;
}

.info_center .row {
  display: flex;
  align-items: flex-start;
}

.info_center .row-single {
  margin-top: 32rpx;
}

.info_center .item {
  flex: 1;
}

.info_center .item .title {
  font-size: 22rpx;
  opacity: 0.8;
}

.info_center .item .text {
  margin-top: 12rpx;
  font-size: 28rpx;
  font-weight: 500;
}

.info_bottom {
  display: flex;
  align-items: center;
  margin-top: 28rpx;
}

.current_num {
  font-size: 26rpx;
  opacity: 0.95;

  .num {
    margin-left: 6rpx;
    font-size: 30rpx;
    font-weight: 500;
  }
}

.full-icon {
  margin-left: 16rpx;
  padding: 4rpx 14rpx;
  background: #d95872;
  border-radius: 16rpx;
  font-size: 20rpx;
  line-height: 32rpx;
}

.max_num {
  margin-left: 16rpx;
  padding: 4rpx 14rpx;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 16rpx;
  font-size: 20rpx;
  line-height: 32rpx;
}

.status-badge {
  margin-left: auto;
}

.main {
  padding: 24rpx 28rpx 0;
}

.section {
  margin-bottom: 28rpx;
  padding: 28rpx 24rpx;
  background: $color-surface;
  border-radius: 21rpx;
}

.section-title {
  color: $color-text;
  font-size: 30rpx;
  font-weight: 500;
}

.section-text {
  margin-top: 16rpx;
  color: $color-text-body;
  font-size: 26rpx;
  line-height: 1.7;
}

.notice-list {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
  margin-top: 16rpx;
}

.notice-item {
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 34rpx;
}

.link-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 28rpx 24rpx;
  background: $color-surface;
  border-radius: 21rpx;
}

.link-text {
  color: $color-text;
  font-size: 28rpx;
}

.bottom-logo {
  margin-top: 40rpx;
  text-align: center;
  color: $color-text-muted;
  font-size: 22rpx;
}

.bottom-fixed {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 9;
  padding-bottom: constant(safe-area-inset-bottom);
  padding-bottom: env(safe-area-inset-bottom);
  background: $color-surface;
  border-top: 1rpx solid $color-border;
}

.line-up-info {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 18rpx 32rpx 8rpx;
  font-size: 24rpx;
}

.line-up-text {
  color: $color-accent-pink;
}

.btn-wrap {
  padding: 12rpx 32rpx 24rpx;
}
</style>
