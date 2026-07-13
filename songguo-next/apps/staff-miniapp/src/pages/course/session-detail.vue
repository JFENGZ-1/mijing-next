<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { fetchMemberCards, fetchCrmMembers } from "@/api/crm";
import {
  cancelStaffAppointment,
  cancelStaffScheduleSession,
  createStaffAppointment,
  fetchStaffScheduleSession,
  fetchStaffScheduleSessions,
  fetchStaffSessionAppointments,
  fetchStaffSessionWaitlist,
  markStaffAppointmentAbsent,
  promoteStaffWaitlistAppointment,
  rescheduleStaffAppointment,
  suspendStaffScheduleSession,
  updateStaffAppointmentNotes,
} from "@/api/scheduling";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CrmMember, StaffMemberCardSummary } from "@/types/crm";
import type { StaffAppointment, ScheduleSession } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";
import {
  appointmentStatusLabel,
  formatSessionTime,
  sessionStatusLabel,
  sessionStatusType,
} from "@/utils/format";

const session = useSessionStore();
const sessionId = ref(0);
const checking = ref(true);
const loading = ref(false);
const acting = ref(false);
const forbidden = ref(false);
const errorMessage = ref("");
const detail = ref<ScheduleSession | null>(null);
const waitlist = ref<StaffAppointment[]>([]);
const confirmedAppointments = ref<StaffAppointment[]>([]);

const bookingOpen = ref(false);
const bookingQuery = ref("");
const bookingLoading = ref(false);
const bookingError = ref("");
const bookingMembers = ref<CrmMember[]>([]);
const selectedMember = ref<CrmMember | null>(null);
const memberCards = ref<StaffMemberCardSummary[]>([]);
const selectedCardId = ref<number | null>(null);
const bookingSubmitting = ref(false);

const notesOpen = ref(false);
const notesTarget = ref<StaffAppointment | null>(null);
const notesDraft = ref("");
const notesSubmitting = ref(false);

const rescheduleOpen = ref(false);
const rescheduleTarget = ref<StaffAppointment | null>(null);
const rescheduleSessions = ref<ScheduleSession[]>([]);
const rescheduleLoading = ref(false);
const rescheduleError = ref("");
const rescheduleSubmitting = ref(false);

const canRead = computed(() => session.can("schedule.session.read"));
const canWrite = computed(() => session.can("schedule.session.write"));
const canSuspend = computed(() => session.can("schedule.session.write"));
const canBook = computed(() => session.can("booking.appointment.create"));
const canCancelAppointment = computed(() => session.can("booking.appointment.cancel"));
const canPromoteWaitlist = computed(() => session.can("booking.waitlist.promote"));
const canMarkAbsent = computed(() => session.can("booking.fulfillment.absent"));
const canEditNotes = computed(() => session.can("booking.fulfillment.notes"));
const canReschedule = computed(() => session.can("booking.appointment.reschedule"));
const canSearchMembers = computed(() => session.can("crm.member.read"));
const canEdit = computed(
  () => canWrite.value && detail.value?.status === "scheduled" && (detail.value?.bookedCount ?? 0) === 0,
);
const canCancel = computed(
  () => canWrite.value && (detail.value?.status === "scheduled" || detail.value?.status === "suspended"),
);
const canAssistBook = computed(
  () => canBook.value && detail.value?.status === "scheduled" && (detail.value?.bookedCount ?? 0) < (detail.value?.capacity ?? 0),
);
const canViewMembers = computed(() => session.can("crm.member.read"));
const isPrivateSession = computed(() => detail.value?.sessionKind === "private");
const hasWaitlistSeat = computed(
  () => (detail.value?.bookedCount ?? 0) < (detail.value?.capacity ?? 0),
);

const waitlistCount = computed(() => waitlist.value.length);
const confirmedCount = computed(() => confirmedAppointments.value.length);
const selectedCard = computed(
  () => memberCards.value.find((item) => item.id === selectedCardId.value) ?? null,
);

function apiErrorMessage(error: unknown, fallback: string) {
  if (error instanceof ApiError) return error.payload.message || fallback;
  if (error instanceof Error) return error.message;
  return fallback;
}

async function loadDetail() {
  if (!session.currentSiteId || !sessionId.value || !canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  errorMessage.value = "";
  forbidden.value = false;
  try {
    const [sessionDetail, waitlistResponse, appointmentsResponse] = await Promise.all([
      fetchStaffScheduleSession(session.currentSiteId, sessionId.value),
      fetchStaffSessionWaitlist(session.currentSiteId, sessionId.value),
      fetchStaffSessionAppointments(session.currentSiteId, sessionId.value),
    ]);
    detail.value = sessionDetail;
    waitlist.value = waitlistResponse.items;
    confirmedAppointments.value = appointmentsResponse.items;
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 403) {
      forbidden.value = true;
      return;
    }
    errorMessage.value = apiErrorMessage(error, "课程详情加载失败");
  } finally {
    loading.value = false;
  }
}

onLoad((options) => {
  sessionId.value = Number(options?.id || 0);
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await loadDetail();
});

onPullDownRefresh(async () => {
  await loadDetail();
  uni.stopPullDownRefresh();
});

function memberLabel(item: StaffAppointment) {
  if (item.memberName) return item.memberName;
  if (item.memberNo && canViewMembers.value) return item.memberNo;
  return canViewMembers.value ? `会员 #${item.memberId}` : "会员";
}

function appointmentStatusType(status: string) {
  switch (status) {
    case "confirmed":
      return "success";
    case "waitlisted":
      return "warning";
    case "absent":
      return "error";
    case "cancelled":
      return "info";
    default:
      return "primary";
  }
}

function canCancelItem(item: StaffAppointment) {
  return canCancelAppointment.value && item.status === "confirmed";
}

function canMarkAbsentItem(item: StaffAppointment) {
  return canMarkAbsent.value && item.status === "confirmed";
}

function canEditNotesItem(item: StaffAppointment) {
  return canEditNotes.value && (item.status === "confirmed" || item.status === "absent");
}

function canRescheduleItem(item: StaffAppointment) {
  return canReschedule.value && isPrivateSession.value && item.status === "confirmed";
}

function canPromoteItem() {
  return canPromoteWaitlist.value && hasWaitlistSeat.value;
}

function confirmCancelAppointment(item: StaffAppointment) {
  uni.showModal({
    title: "取消预约",
    content: `确定取消「${memberLabel(item)}」的预约？`,
    confirmColor: "#d92d20",
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      acting.value = true;
      try {
        await cancelStaffAppointment(session.currentSiteId, item.id, {
          commandKey: createCommandKey(),
        });
        uni.showToast({ title: "已取消预约", icon: "success" });
        await loadDetail();
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "取消预约失败"),
          icon: "none",
        });
      } finally {
        acting.value = false;
      }
    },
  });
}

function confirmMarkAbsent(item: StaffAppointment) {
  uni.showModal({
    title: "标记缺席",
    content: `确定将「${memberLabel(item)}」标记为缺席？`,
    confirmColor: "#d92d20",
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      acting.value = true;
      try {
        await markStaffAppointmentAbsent(session.currentSiteId, item.id, {
          commandKey: createCommandKey(),
        });
        uni.showToast({ title: "已标记缺席", icon: "success" });
        await loadDetail();
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "标记缺席失败"),
          icon: "none",
        });
      } finally {
        acting.value = false;
      }
    },
  });
}

function confirmPromoteWaitlist(item: StaffAppointment) {
  if (!canPromoteItem()) {
    uni.showToast({ title: "当前课程已满，无法转正", icon: "none" });
    return;
  }
  uni.showModal({
    title: "候补转正",
    content: `将「${memberLabel(item)}」从候补转为正式预约？`,
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      acting.value = true;
      try {
        await promoteStaffWaitlistAppointment(session.currentSiteId, item.id, {
          commandKey: createCommandKey(),
        });
        uni.showToast({ title: "已转正", icon: "success" });
        await loadDetail();
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "转正失败"),
          icon: "none",
        });
      } finally {
        acting.value = false;
      }
    },
  });
}

function openNotesPanel(item: StaffAppointment) {
  notesTarget.value = item;
  notesDraft.value = item.staffNotes || "";
  notesOpen.value = true;
}

function closeNotesPanel() {
  notesOpen.value = false;
  notesTarget.value = null;
  notesDraft.value = "";
}

async function submitNotes() {
  if (!notesTarget.value || !session.currentSiteId || !notesDraft.value.trim()) return;
  notesSubmitting.value = true;
  try {
    await updateStaffAppointmentNotes(session.currentSiteId, notesTarget.value.id, {
      staffNotes: notesDraft.value.trim(),
    });
    closeNotesPanel();
    uni.showToast({ title: "备注已保存", icon: "success" });
    await loadDetail();
  } catch (error) {
    uni.showToast({
      title: apiErrorMessage(error, "保存备注失败"),
      icon: "none",
    });
  } finally {
    notesSubmitting.value = false;
  }
}

async function openReschedulePanel(item: StaffAppointment) {
  if (!session.currentSiteId || !detail.value) return;
  rescheduleTarget.value = item;
  rescheduleOpen.value = true;
  rescheduleSessions.value = [];
  rescheduleError.value = "";
  rescheduleLoading.value = true;
  try {
    const sessionDate = detail.value.startsAt.slice(0, 10);
    const from = addDaysIso(sessionDate, -3);
    const to = addDaysIso(sessionDate, 14);
    const response = await fetchStaffScheduleSessions(session.currentSiteId, from, to);
    rescheduleSessions.value = response.items.filter(
      (candidate) =>
        candidate.id !== sessionId.value
        && candidate.sessionKind === "private"
        && candidate.status === "scheduled"
        && candidate.bookedCount < candidate.capacity
        && candidate.coachStaffId === detail.value?.coachStaffId,
    );
    if (rescheduleSessions.value.length === 0) {
      rescheduleError.value = "暂无可改约的私教时段";
    }
  } catch (error) {
    rescheduleError.value = apiErrorMessage(error, "可改约时段加载失败");
  } finally {
    rescheduleLoading.value = false;
  }
}

function closeReschedulePanel() {
  rescheduleOpen.value = false;
  rescheduleTarget.value = null;
  rescheduleSessions.value = [];
  rescheduleError.value = "";
}

function addDaysIso(isoDate: string, days: number) {
  const date = new Date(`${isoDate}T00:00:00`);
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
}

function confirmReschedule(target: ScheduleSession) {
  if (!rescheduleTarget.value || !session.currentSiteId) return;
  uni.showModal({
    title: "改约确认",
    content: `改约至 ${formatSessionTime(target.startsAt, target.endsAt)}？`,
    success: async (result) => {
      if (!result.confirm) return;
      rescheduleSubmitting.value = true;
      try {
        await rescheduleStaffAppointment(session.currentSiteId!, rescheduleTarget.value!.id, {
          sessionId: target.id,
          commandKey: createCommandKey(),
        });
        closeReschedulePanel();
        uni.showToast({ title: "改约成功", icon: "success" });
        await loadDetail();
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "改约失败"),
          icon: "none",
        });
      } finally {
        rescheduleSubmitting.value = false;
      }
    },
  });
}

function openMember(memberId: number) {
  if (!canViewMembers.value) {
    uni.showToast({ title: "暂无会员档案权限", icon: "none" });
    return;
  }
  uni.navigateTo({ url: `/pages/members/detail?id=${memberId}` });
}

function confirmSuspend() {
  if (!detail.value || detail.value.status !== "scheduled") return;
  uni.showModal({
    title: "停课确认",
    content: "确定暂停本节课程？已预约会员将收到通知。",
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      acting.value = true;
      try {
        detail.value = await suspendStaffScheduleSession(session.currentSiteId, sessionId.value);
        uni.showToast({ title: "已停课", icon: "success" });
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "停课失败"),
          icon: "none",
        });
      } finally {
        acting.value = false;
      }
    },
  });
}

function confirmCancel() {
  if (!detail.value || !canCancel.value) return;
  const bookedCount = detail.value.bookedCount ?? 0;
  const content = bookedCount > 0
    ? `确定取消本节课程？当前已有 ${bookedCount} 人预约，取消排课不会自动取消预约记录。`
    : "确定取消本节排课？取消后本节课程将不再开放预约。";

  uni.showModal({
    title: "取消排课",
    content,
    confirmColor: "#d92d20",
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      acting.value = true;
      try {
        detail.value = await cancelStaffScheduleSession(session.currentSiteId, sessionId.value);
        uni.showToast({ title: "已取消排课", icon: "success" });
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "取消排课失败"),
          icon: "none",
        });
      } finally {
        acting.value = false;
      }
    },
  });
}

function resetBookingState() {
  bookingQuery.value = "";
  bookingError.value = "";
  bookingMembers.value = [];
  selectedMember.value = null;
  memberCards.value = [];
  selectedCardId.value = null;
}

function closeBookingPanel() {
  bookingOpen.value = false;
  resetBookingState();
}

function openBookingPanel() {
  if (!canAssistBook.value) {
    uni.showToast({ title: "当前课程不可代预约", icon: "none" });
    return;
  }
  if (!canSearchMembers.value) {
    uni.showToast({ title: "暂无会员查询权限", icon: "none" });
    return;
  }
  resetBookingState();
  bookingOpen.value = true;
}

async function searchBookingMembers() {
  if (!session.currentSiteId || !bookingQuery.value.trim()) {
    bookingMembers.value = [];
    return;
  }
  bookingLoading.value = true;
  bookingError.value = "";
  try {
    const response = await fetchCrmMembers(session.currentSiteId, {
      q: bookingQuery.value.trim(),
      page: 1,
      perPage: 20,
      status: "active",
    });
    bookingMembers.value = response.data.items;
  } catch (error) {
    bookingMembers.value = [];
    bookingError.value = apiErrorMessage(error, "会员搜索失败");
  } finally {
    bookingLoading.value = false;
  }
}

async function selectBookingMember(member: CrmMember) {
  if (!session.currentSiteId) return;
  selectedMember.value = member;
  selectedCardId.value = null;
  memberCards.value = [];
  bookingLoading.value = true;
  bookingError.value = "";
  try {
    const response = await fetchMemberCards(session.currentSiteId, member.id);
    memberCards.value = response.data.filter((item) => item.status === "active");
    if (memberCards.value.length === 0) {
      bookingError.value = "该会员暂无可用会员卡";
    }
  } catch (error) {
    bookingError.value = apiErrorMessage(error, "会员卡加载失败");
  } finally {
    bookingLoading.value = false;
  }
}

function backToMemberSearch() {
  selectedMember.value = null;
  memberCards.value = [];
  selectedCardId.value = null;
  bookingError.value = "";
}

function cardSummary(card: StaffMemberCardSummary) {
  const parts = [card.name || card.cardNo];
  if (card.cachedRemainingCount != null) parts.push(`剩余 ${card.cachedRemainingCount} 次`);
  if (card.cachedBalance) parts.push(`余额 ¥${card.cachedBalance}`);
  if (card.validUntil) parts.push(`至 ${card.validUntil.slice(0, 10)}`);
  return parts.join(" · ");
}

function confirmAssistBooking() {
  if (!selectedMember.value || !selectedCardId.value || !detail.value) return;
  const memberName = selectedMember.value.name || selectedMember.value.memberNo;
  const cardLabel = selectedCard.value?.name || selectedCard.value?.cardNo || "所选会员卡";
  uni.showModal({
    title: "代预约确认",
    content: `为「${memberName}」使用「${cardLabel}」预约「${detail.value.courseName || "本节课程"}」？`,
    success: async (result) => {
      if (!result.confirm || !session.currentSiteId) return;
      bookingSubmitting.value = true;
      try {
        await createStaffAppointment(session.currentSiteId, sessionId.value, {
          memberId: selectedMember.value!.id,
          memberCardId: selectedCardId.value!,
          commandKey: createCommandKey(),
        });
        closeBookingPanel();
        uni.showToast({ title: "代预约成功", icon: "success" });
        await loadDetail();
      } catch (error) {
        uni.showToast({
          title: apiErrorMessage(error, "代预约失败"),
          icon: "none",
        });
      } finally {
        bookingSubmitting.value = false;
      }
    },
  });
}

function sessionKindLabel(kind: string) {
  return kind === "private" ? "私教" : "团课";
}

function openEdit() {
  uni.navigateTo({ url: `/pages/course/session-form?id=${sessionId.value}` });
}
</script>

<template>
  <u-loading-page :loading="checking || loading || acting || bookingSubmitting || notesSubmitting || rescheduleSubmitting" />
  <view v-if="!checking && detail" class="page-container">
    <view v-if="errorMessage" class="error-text">{{ errorMessage }}</view>

    <view class="detail-card">
      <view class="detail-header">
        <text class="detail-title">{{ detail.courseName || "未命名课程" }}</text>
        <u-tag
          :text="sessionStatusLabel(detail.status)"
          size="mini"
          :type="sessionStatusType(detail.status)"
        />
      </view>
      <text class="detail-meta">{{ formatSessionTime(detail.startsAt, detail.endsAt) }}</text>
      <text class="detail-meta">{{ sessionKindLabel(detail.sessionKind) }}</text>
      <text v-if="detail.coachName" class="detail-meta">教练 {{ detail.coachName }}</text>
      <text v-if="detail.roomName" class="detail-meta">教室 {{ detail.roomName }}</text>
      <text class="detail-meta">预约 {{ detail.bookedCount }}/{{ detail.capacity }}</text>
    </view>

    <view v-if="canSuspend && detail.status === 'scheduled'" class="action-row">
      <u-button v-if="canEdit" type="primary" plain @click="openEdit">编辑</u-button>
      <u-button v-if="canAssistBook" type="primary" plain @click="openBookingPanel">代预约</u-button>
      <u-button type="warning" plain @click="confirmSuspend">停课</u-button>
      <u-button v-if="canCancel" type="error" plain @click="confirmCancel">取消排课</u-button>
    </view>
    <view v-else-if="detail.status === 'suspended'" class="action-row">
      <u-button v-if="canCancel" type="error" plain @click="confirmCancel">取消排课</u-button>
    </view>
    <view v-else-if="canEdit || canAssistBook" class="action-row">
      <u-button v-if="canEdit" type="primary" plain @click="openEdit">编辑</u-button>
      <u-button v-if="canAssistBook" type="primary" plain @click="openBookingPanel">代预约</u-button>
    </view>

    <view class="section-title">已预约（{{ confirmedCount }}）</view>
    <view v-if="confirmedAppointments.length" class="appoint-list">
      <view
        v-for="item in confirmedAppointments"
        :key="item.id"
        class="appoint-item"
      >
        <view class="appoint-row" @tap="openMember(item.memberId)">
          <text class="appoint-name">{{ memberLabel(item) }}</text>
          <u-tag :text="appointmentStatusLabel(item.status)" size="mini" :type="appointmentStatusType(item.status)" />
        </view>
        <text v-if="item.bookedAt" class="appoint-meta">预约于 {{ item.bookedAt.replace("T", " ").slice(0, 16) }}</text>
        <text v-if="item.staffNotes" class="appoint-meta">备注：{{ item.staffNotes }}</text>
        <view v-if="canCancelItem(item) || canMarkAbsentItem(item) || canEditNotesItem(item) || canRescheduleItem(item)" class="appoint-actions">
          <u-button v-if="canCancelItem(item)" size="mini" plain @click="confirmCancelAppointment(item)">取消</u-button>
          <u-button v-if="canMarkAbsentItem(item)" size="mini" type="warning" plain @click="confirmMarkAbsent(item)">缺席</u-button>
          <u-button v-if="canEditNotesItem(item)" size="mini" plain @click="openNotesPanel(item)">备注</u-button>
          <u-button v-if="canRescheduleItem(item)" size="mini" type="primary" plain @click="openReschedulePanel(item)">改约</u-button>
        </view>
      </view>
    </view>
    <u-empty v-else mode="list" text="暂无已预约会员" />

    <view class="section-title">候补（{{ waitlistCount }}）</view>
    <view v-if="waitlist.length" class="appoint-list">
      <view
        v-for="item in waitlist"
        :key="item.id"
        class="appoint-item"
      >
        <view class="appoint-row" @tap="openMember(item.memberId)">
          <text class="appoint-name">{{ memberLabel(item) }}</text>
          <u-tag :text="appointmentStatusLabel(item.status)" size="mini" type="warning" />
        </view>
        <text class="appoint-meta">排队于 {{ item.bookedAt.replace("T", " ").slice(0, 16) }}</text>
        <view v-if="canPromoteItem()" class="appoint-actions">
          <u-button size="mini" type="primary" plain @click="confirmPromoteWaitlist(item)">转正</u-button>
        </view>
      </view>
    </view>
    <u-empty v-else mode="list" text="暂无候补" />

    <u-empty v-if="forbidden" mode="permission" text="当前账号暂无课程详情权限" />
  </view>
  <u-empty v-else-if="!checking && !detail && !loading" mode="data" text="课程不存在或已删除" />

  <view v-if="bookingOpen" class="booking-overlay" @tap="closeBookingPanel">
    <view class="booking-panel" @tap.stop>
      <view class="booking-header">
        <text class="booking-title">代预约</text>
        <button class="booking-close" @click="closeBookingPanel">关闭</button>
      </view>

      <view v-if="!selectedMember">
        <u-search
          v-model="bookingQuery"
          placeholder="姓名或完整手机号"
          :show-action="false"
          @search="searchBookingMembers"
          @clear="bookingMembers = []"
        />
        <u-alert v-if="bookingError" class="booking-alert" type="error" :description="bookingError" />
        <view v-if="bookingLoading" class="booking-hint">搜索中…</view>
        <view v-else-if="bookingMembers.length" class="booking-list">
          <view
            v-for="member in bookingMembers"
            :key="member.id"
            class="booking-item"
            @tap="selectBookingMember(member)"
          >
            <u-avatar :text="member.name?.slice(0, 1) || '?'" size="40" />
            <view class="booking-item-main">
              <text class="booking-item-name">{{ member.name || "未命名会员" }}</text>
              <text class="booking-item-meta">{{ member.mobileMasked || "未留手机号" }} · {{ member.memberNo }}</text>
            </view>
            <u-icon name="arrow-right" color="#98a2b3" size="16" />
          </view>
        </view>
        <view v-else class="booking-hint">输入姓名或手机号后搜索会员</view>
      </view>

      <view v-else>
        <view class="booking-selected">
          <text class="booking-selected-label">已选会员</text>
          <text class="booking-selected-name">{{ selectedMember.name || selectedMember.memberNo }}</text>
          <button class="booking-back" @click="backToMemberSearch">重选</button>
        </view>
        <u-alert v-if="bookingError" class="booking-alert" type="error" :description="bookingError" />
        <view v-if="bookingLoading" class="booking-hint">加载会员卡…</view>
        <view v-else-if="memberCards.length" class="booking-list">
          <view
            v-for="card in memberCards"
            :key="card.id"
            class="booking-item"
            :class="{ active: selectedCardId === card.id }"
            @tap="selectedCardId = card.id"
          >
            <view class="booking-item-main">
              <text class="booking-item-name">{{ card.name || card.cardNo }}</text>
              <text class="booking-item-meta">{{ cardSummary(card) }}</text>
            </view>
            <u-icon
              :name="selectedCardId === card.id ? 'checkmark-circle-fill' : 'checkmark-circle'"
              :color="selectedCardId === card.id ? '#1677ff' : '#d0d5dd'"
              size="18"
            />
          </view>
        </view>
        <view class="booking-actions">
          <u-button plain @click="closeBookingPanel">取消</u-button>
          <u-button type="primary" :disabled="!selectedCardId" @click="confirmAssistBooking">确认预约</u-button>
        </view>
      </view>
    </view>
  </view>

  <view v-if="notesOpen" class="booking-overlay" @tap="closeNotesPanel">
    <view class="booking-panel" @tap.stop>
      <view class="booking-header">
        <text class="booking-title">员工备注</text>
        <button class="booking-close" @click="closeNotesPanel">关闭</button>
      </view>
      <text v-if="notesTarget" class="booking-hint">会员：{{ memberLabel(notesTarget) }}</text>
      <u-textarea v-model="notesDraft" maxlength="2000" placeholder="填写员工可见备注" />
      <view class="booking-actions">
        <u-button plain @click="closeNotesPanel">取消</u-button>
        <u-button type="primary" :disabled="!notesDraft.trim()" @click="submitNotes">保存</u-button>
      </view>
    </view>
  </view>

  <view v-if="rescheduleOpen" class="booking-overlay" @tap="closeReschedulePanel">
    <view class="booking-panel" @tap.stop>
      <view class="booking-header">
        <text class="booking-title">改约私教课</text>
        <button class="booking-close" @click="closeReschedulePanel">关闭</button>
      </view>
      <text v-if="rescheduleTarget" class="booking-hint">会员：{{ memberLabel(rescheduleTarget) }}</text>
      <u-alert v-if="rescheduleError" class="booking-alert" type="error" :description="rescheduleError" />
      <view v-if="rescheduleLoading" class="booking-hint">加载可改约时段…</view>
      <view v-else-if="rescheduleSessions.length" class="booking-list">
        <view
          v-for="candidate in rescheduleSessions"
          :key="candidate.id"
          class="booking-item"
          @tap="confirmReschedule(candidate)"
        >
          <view class="booking-item-main">
            <text class="booking-item-name">{{ candidate.courseName || "私教课" }}</text>
            <text class="booking-item-meta">{{ formatSessionTime(candidate.startsAt, candidate.endsAt) }} · 余 {{ candidate.capacity - candidate.bookedCount }} 位</text>
          </view>
          <u-icon name="arrow-right" color="#98a2b3" size="16" />
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped lang="scss">
.error-text {
  margin-bottom: $spacing-sm;
  color: $color-danger;
  font-size: 24rpx;
}

.detail-card {
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-md;
}

.detail-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: $spacing-sm;
}

.detail-title,
.detail-meta,
.appoint-name,
.appoint-meta,
.hint-text {
  display: block;
}

.detail-title {
  font-size: 34rpx;
  font-weight: 600;
}

.detail-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.action-row {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-sm;
  margin-top: $spacing-md;
}

.appoint-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
}

.appoint-item {
  padding: $spacing-md;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.appoint-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: $spacing-sm;
}

.appoint-name {
  font-size: 30rpx;
  font-weight: 600;
}

.appoint-meta {
  margin-top: $spacing-xs;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.appoint-actions {
  display: flex;
  flex-wrap: wrap;
  gap: $spacing-xs;
  margin-top: $spacing-sm;
}

.hint-card {
  padding: $spacing-md;
  background: rgba($color-warning, 0.08);
  border: 1rpx solid rgba($color-warning, 0.2);
  border-radius: $radius-sm;
}

.hint-text {
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.5;
}

.booking-overlay {
  position: fixed;
  z-index: 100;
  inset: 0;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.45);
}

.booking-panel {
  width: 100%;
  max-height: 78vh;
  padding: $spacing-md;
  padding-bottom: calc($spacing-md + env(safe-area-inset-bottom));
  overflow-y: auto;
  background: $color-surface;
  border-radius: $radius-md $radius-md 0 0;
}

.booking-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: $spacing-md;
}

.booking-title {
  font-size: 32rpx;
  font-weight: 600;
}

.booking-close,
.booking-back {
  margin: 0;
  padding: 0;
  color: $color-primary;
  font-size: 26rpx;
  line-height: 1.4;
  background: transparent;
}

.booking-close::after,
.booking-back::after {
  border: 0;
}

.booking-alert {
  margin-top: $spacing-sm;
}

.booking-hint {
  margin-top: $spacing-md;
  color: $color-text-secondary;
  font-size: 24rpx;
  text-align: center;
}

.booking-list {
  display: flex;
  flex-direction: column;
  gap: $spacing-sm;
  margin-top: $spacing-md;
}

.booking-item {
  display: flex;
  align-items: center;
  gap: $spacing-sm;
  padding: $spacing-md;
  background: $color-page;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.booking-item.active {
  border-color: $color-primary;
  background: rgba($color-primary, 0.06);
}

.booking-item-main {
  min-width: 0;
  flex: 1;
}

.booking-item-name,
.booking-item-meta,
.booking-selected-label,
.booking-selected-name {
  display: block;
}

.booking-item-name {
  font-size: 28rpx;
  font-weight: 600;
}

.booking-item-meta {
  margin-top: 6rpx;
  color: $color-text-secondary;
  font-size: 22rpx;
}

.booking-selected {
  display: flex;
  align-items: center;
  gap: $spacing-sm;
  padding: $spacing-sm $spacing-md;
  background: $color-page;
  border-radius: $radius-sm;
}

.booking-selected-label {
  color: $color-text-secondary;
  font-size: 22rpx;
}

.booking-selected-name {
  flex: 1;
  font-size: 28rpx;
  font-weight: 600;
}

.booking-actions {
  display: flex;
  gap: $spacing-sm;
  margin-top: $spacing-md;
}
</style>
