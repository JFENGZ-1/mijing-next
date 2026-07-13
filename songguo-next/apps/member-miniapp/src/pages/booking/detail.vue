<script setup lang="ts">
import { ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  createMemberAppointment,
  getMemberBookingPayableCards,
  getMemberBookingSession,
} from "@/api/member";
import { requireMemberAuth } from "@/auth/guard";
import { ensureMemberTenant } from "@/composables/member-context";
import type { MemberBookingSessionDetail, MemberCardWalletSummary } from "@/types/member";
import { createCommandKey } from "@/utils/command-key";
import { cardBalanceLabel, formatIsoDate, appointmentStatusLabel } from "@/utils/format";

const loading = ref(true);
const booking = ref(false);
const errorMessage = ref("");
const sessionId = ref(0);
const session = ref<MemberBookingSessionDetail | null>(null);
const bookCommandKey = ref<string | null>(null);

async function loadSession() {
  if (!sessionId.value) return;

  loading.value = true;
  errorMessage.value = "";
  session.value = null;

  try {
    const tenant = await ensureMemberTenant();
    if (!tenant) {
      errorMessage.value = "请先选择场馆";
      return;
    }

    const response = await getMemberBookingSession(tenant.tenantId, sessionId.value);
    session.value = response.data;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课程详情加载失败";
  } finally {
    loading.value = false;
  }
}

function payableCardLabel(card: MemberCardWalletSummary) {
  const name = card.name || "会员卡";
  const balance = cardBalanceLabel(card);
  return balance ? `${name}（${balance}）` : name;
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

    setTimeout(() => {
      uni.navigateTo({ url: "/pages/booking/my-appointments" });
    }, 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "预约失败";
  } finally {
    booking.value = false;
  }
}

async function startBooking() {
  if (!session.value?.bookable || booking.value) return;

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

function openMyAppointments() {
  uni.navigateTo({ url: "/pages/booking/my-appointments" });
}

function statusBadgeClass() {
  if (!session.value?.memberAppointmentStatus) return "badge-pill--bookable";
  return "badge-pill--booked";
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
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />

    <template v-if="session">
      <view class="detail-hero">
        <view class="detail-title">{{ session.courseName || "课程" }}</view>
        <view class="detail-meta">{{ formatIsoDate(session.startsAt) }} - {{ formatIsoDate(session.endsAt) }}</view>
        <view v-if="session.coachName" class="detail-meta">教练 {{ session.coachName }}</view>
        <view v-if="session.roomName" class="detail-meta">教室 {{ session.roomName }}</view>
        <view v-if="session.durationMinutes" class="detail-meta">时长 {{ session.durationMinutes }} 分钟</view>
        <view class="detail-meta">
          名额 {{ session.bookedCount ?? 0 }}/{{ session.capacity }}
          <text v-if="session.waitlistEnabled"> · 支持候补</text>
        </view>
        <view v-if="session.memberAppointmentStatus || session.bookable" class="detail-status">
          <view class="badge-pill" :class="statusBadgeClass()">
            {{
              session.memberAppointmentStatus
                ? appointmentStatusLabel(session.memberAppointmentStatus)
                : session.waitlistEnabled
                  ? "可预约/候补"
                  : "可预约"
            }}
          </view>
        </view>
      </view>

      <view v-if="session.description" class="detail-description">{{ session.description }}</view>

      <view class="actions">
        <u-button
          v-if="session.memberAppointmentStatus"
          disabled
        >
          {{ appointmentStatusLabel(session.memberAppointmentStatus) }}
        </u-button>
        <u-button
          v-else-if="session.bookable"
          type="primary"
          :loading="booking"
          @click="startBooking"
        >
          {{ session.waitlistEnabled ? "立即预约 / 候补" : "立即预约" }}
        </u-button>
        <u-button v-else disabled>当前不可预约</u-button>
        <u-button plain @click="openMyAppointments">我的预约</u-button>
      </view>
    </template>

    <u-empty v-else-if="!errorMessage" mode="data" text="课程不存在或已下架" />
  </view>
</template>

<style scoped lang="scss">
.detail-hero {
  padding: 32rpx;
  color: #fff;
  background: linear-gradient(135deg, #22c788 0%, #1dac75 100%);
  border-radius: $radius-md;
}

.detail-title {
  font-size: 39rpx;
  font-weight: 500;
  line-height: 48rpx;
}

.detail-meta {
  margin-top: 12rpx;
  font-size: 26rpx;
  line-height: 36rpx;
  opacity: 0.95;
}

.detail-status {
  margin-top: 20rpx;
}

.detail-description {
  margin-top: $spacing-md;
  padding: $spacing-md;
  color: $color-text;
  font-size: 26rpx;
  line-height: 1.6;
  background: $color-surface;
  border-radius: $radius-md;
}

.actions {
  display: grid;
  gap: $spacing-sm;
  margin-top: $spacing-lg;
}
</style>
