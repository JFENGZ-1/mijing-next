<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShareAppMessage, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import { fetchMemberCards } from "@/api/crm";
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
import MemberPicker from "@/components/member-picker/member-picker.vue";
import MemberCardPicker from "@/components/member-card-picker/member-card-picker.vue";
import type { BookingPickerMember, StaffMemberCardSummary } from "@/types/crm";
import type { StaffAppointment, ScheduleSession } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";
import {
  fetchAppointmentConsumptionPreview,
  fetchAppointmentConsumptionSettlement,
} from "@/api/consumption";
import ConsumptionStatus from "@/components/consumption-status/consumption-status.vue";
import type { AppointmentConsumptionPreview, ConsumptionSettlement } from "@/types/consumption";
import {
  appointmentStatusLabel,
  formatClock,
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
const consumptionByAppointment = ref<Record<number, AppointmentConsumptionPreview | ConsumptionSettlement | null>>({});

// —— 代约（对标原版 member-search 流程：member-picker 选会员 → 选卡确认） ——
const pickerOpen = ref(false);
const bookingOpen = ref(false);
const bookingLoading = ref(false);
const bookingError = ref("");
const selectedMember = ref<BookingPickerMember | null>(null);
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
// 有预约的排课同样允许编辑（员工排错课可修正）；
// 后端仅拦截破坏性变更：容量小于已约人数、有预约时更改课程类型（SCHEDULE_SESSION_UPDATE_BLOCKED）
const canEdit = computed(
  () => canWrite.value && detail.value?.status === "scheduled",
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
const actualDeliveryAssignments = computed(() => {
  if (detail.value?.deliveryAssignments?.length) return detail.value.deliveryAssignments;
  if (!detail.value?.coachStaffId) return [];
  return [{
    staffId: detail.value.coachStaffId,
    staffName: detail.value.coachName,
    compensationRoleId: 0,
    roleName: "主教练（兼容）",
    allocationBps: 10000,
    isPrimary: true,
  }];
});

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
    await loadAppointmentConsumptions(appointmentsResponse.items);
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

async function loadAppointmentConsumptions(items: StaffAppointment[]) {
  if (!session.currentSiteId) return;
  const entries = await Promise.all(items.map(async (item) => {
    try {
      const value = item.status === "completed"
        ? await fetchAppointmentConsumptionSettlement(session.currentSiteId!, item.id)
          ?? await fetchAppointmentConsumptionPreview(session.currentSiteId!, item.id)
        : await fetchAppointmentConsumptionPreview(session.currentSiteId!, item.id);
      return [item.id, value] as const;
    } catch {
      return [item.id, null] as const;
    }
  }));
  consumptionByAppointment.value = Object.fromEntries(entries);
}

const pendingAutoBook = ref(false);

onLoad((options) => {
  sessionId.value = Number(options?.id || 0);
  // 对标原版「代 约/代排队」按钮：进入详情后自动打开代预约面板
  pendingAutoBook.value = options?.action === "book";
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await loadDetail();
  if (pendingAutoBook.value) {
    pendingAutoBook.value = false;
    if (canAssistBook.value) openBookingPanel();
  }
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

// —— 对标原版 leagueClassDetails：分组列表 + 行内下拉操作 ——
const absentList = computed(() => confirmedAppointments.value.filter((item) => item.status === "absent"));
const validList = computed(() =>
  confirmedAppointments.value.filter((item) => item.status === "confirmed" || item.status === "completed"),
);
const cancelList = computed(() => confirmedAppointments.value.filter((item) => item.status === "cancelled"));
const checkedInCount = computed(() => validList.value.filter((item) => item.status === "completed").length);
const sessionEnded = computed(() => (detail.value ? new Date(detail.value.endsAt).getTime() < Date.now() : false));

// 行内下拉（原版 showDrop）
const dropKey = ref(0);

function toggleDrop(item: StaffAppointment) {
  dropKey.value = dropKey.value === item.id ? 0 : item.id;
}

function closeDrop() {
  dropKey.value = 0;
}

// 状态文字与颜色（原版 unionStatusName）
function rowStatus(item: StaffAppointment): { text: string; color: string } {
  switch (item.status) {
    case "completed":
      return { text: "已签到", color: "#22c788" };
    case "absent":
      return { text: "已旷课", color: "#dc3c5c" };
    case "cancelled":
      return { text: "已取消", color: "#989898" };
    case "waitlisted":
      return { text: "排队中", color: "#e98900" };
    default:
      return { text: "已预约", color: "#22c788" };
  }
}

// 课程管理弹窗（对标原版编辑按钮 → course-management）
const manageVisible = ref(false);

async function onManageSuccess() {
  await loadDetail();
}

function onManageDeleted() {
  setTimeout(() => uni.navigateBack(), 400);
}

function arrangeDateText() {
  const value = detail.value?.startsAt;
  if (!value) return "";
  const date = new Date(value);
  const weeks = ["周日", "周一", "周二", "周三", "周四", "周五", "周六"];
  return `${date.getMonth() + 1}月${date.getDate()}日 ${weeks[date.getDay()]}`;
}

onShareAppMessage(() => ({
  title: `${detail.value?.courseName || "课程"} ${arrangeDateText()} ${detail.value ? formatClock(detail.value.startsAt) : ""}`,
  path: `/pages/course/session-detail?id=${sessionId.value}`,
}));

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
    title: "确定取消预约吗？",
    content: "将退还已扣（若有）相应费用",
    confirmColor: "#dc3c5c",
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
    title: "确定标记旷课吗？",
    content: "标记为旷课后，不可取消！！",
    confirmColor: "#dc3c5c",
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
    confirmColor: "#dc3c5c",
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
  bookingError.value = "";
  selectedMember.value = null;
  memberCards.value = [];
  selectedCardId.value = null;
}

function closeBookingPanel() {
  bookingOpen.value = false;
  resetBookingState();
}

/** 打开代约流程（对标原版代约按钮 → member-search 弹窗） */
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
  pickerOpen.value = true;
}

/** member-picker 选中会员 → 加载会员卡并进入选卡面板 */
async function selectBookingMember(member: BookingPickerMember) {
  if (!session.currentSiteId) return;
  selectedMember.value = member;
  selectedCardId.value = null;
  memberCards.value = [];
  bookingLoading.value = true;
  bookingError.value = "";
  bookingOpen.value = true;
  try {
    const response = await fetchMemberCards(session.currentSiteId, member.id);
    // 不做过滤：member-card-picker 内部按「可用 / 无效（折叠置灰）」分组展示
    memberCards.value = response.data;
  } catch (error) {
    bookingError.value = apiErrorMessage(error, "会员卡加载失败");
  } finally {
    bookingLoading.value = false;
  }
}

/** 重选会员：回到 member-picker */
function backToMemberSearch() {
  bookingOpen.value = false;
  selectedMember.value = null;
  memberCards.value = [];
  selectedCardId.value = null;
  bookingError.value = "";
  pickerOpen.value = true;
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
  <view v-if="!checking && detail" class="page-shell" @tap="closeDrop">
    <view v-if="errorMessage" class="error-text">{{ errorMessage }}</view>

    <!-- 沉浸头卡（对标原版 fixed-box：背景图 + 日期 + 课程信息 + 编辑/分享） -->
    <view class="hero" :style="{ background: detail.courseFaceGradient || 'linear-gradient(120deg, #2b5876, #4e4376)' }">
      <view class="hero-date">{{ arrangeDateText() }}</view>
      <view class="hero-main">
        <view class="hero-left">
          <text class="hero-name">{{ detail.courseName || "未命名课程" }}</text>
          <view class="hero-info">
            <text v-if="detail.roomName">{{ detail.roomName }}</text>
            <text v-if="detail.roomName" class="hero-sep">|</text>
            <text>{{ sessionKindLabel(detail.sessionKind) }}</text>
          </view>
          <view class="hero-coach">
            <view class="hero-ava">{{ (detail.coachName || "教")[0] }}</view>
            <text class="hero-coach-name">{{ detail.coachName || "待定教练" }}</text>
            <text class="hero-limit">限{{ detail.capacity }}人</text>
            <view v-if="detail.bookedCount >= detail.capacity" class="hero-full">爆满</view>
          </view>
        </view>
        <view class="hero-right">
          <text class="hero-start">{{ formatClock(detail.startsAt) }}</text>
          <text class="hero-end">{{ formatClock(detail.endsAt) }}结束</text>
          <view class="hero-actions">
            <view v-if="canEdit" class="hero-action" @tap="manageVisible = true">编辑</view>
            <button class="hero-action share-btn" open-type="share">分享</button>
          </view>
        </view>
      </view>
      <view v-if="detail.status === 'suspended' || detail.status === 'cancelled'" class="hero-mask" />
      <view v-if="detail.status === 'suspended'" class="hero-stamp">已停课</view>
      <view v-else-if="detail.status === 'cancelled'" class="hero-stamp grey">已取消</view>
      <view v-else-if="sessionEnded" class="hero-stamp grey">已结束</view>
    </view>

    <!-- 统计行 + 操作按钮（对标原版 mumber-num） -->
    <view class="stat-row">
      <view class="stat-left">
        <text class="stat-item">已约{{ validList.length }}人</text>
        <text v-if="waitlist.length" class="stat-item">排队{{ waitlist.length }}人</text>
        <template v-if="sessionEnded">
          <text class="stat-item">签到{{ checkedInCount }}人</text>
          <text class="stat-item">旷课{{ absentList.length }}人</text>
        </template>
      </view>
      <view class="stat-btns">
        <view
          v-if="detail.status === 'scheduled' && !sessionEnded && detail.bookedCount < detail.capacity && canAssistBook"
          class="stat-btn green"
          @tap="openBookingPanel"
        >
          代 约
        </view>
        <view
          v-else-if="detail.status === 'scheduled' && !sessionEnded && detail.bookedCount >= detail.capacity && canAssistBook"
          class="stat-btn light"
          @tap="openBookingPanel"
        >
          代排队
        </view>
        <view
          v-else-if="detail.status === 'scheduled' && sessionEnded && canAssistBook"
          class="stat-btn light"
          @tap="openBookingPanel"
        >
          补约
        </view>
        <view v-else-if="detail.status === 'suspended'" class="stat-btn grey">已停课</view>
      </view>
    </view>

    <view v-if="actualDeliveryAssignments.length" class="delivery-summary">
      <view class="delivery-summary-head">
        <text>实际授课人员（A）</text>
        <text>{{ detail.deliveryAssignments?.length ? "按结算分配" : "沿用单教练" }}</text>
      </view>
      <view class="delivery-summary-list">
        <view v-for="assignment in actualDeliveryAssignments" :key="`${assignment.staffId}-${assignment.compensationRoleId}`" class="delivery-summary-item">
          <view class="delivery-avatar">{{ (assignment.staffName || detail.coachName || "教")[0] }}</view>
          <view class="delivery-summary-main">
            <text>{{ assignment.staffName || (assignment.staffId === detail.coachStaffId ? detail.coachName : null) || `员工 #${assignment.staffId}` }}</text>
            <text>{{ assignment.roleName || `A 角色 #${assignment.compensationRoleId}` }}</text>
          </view>
          <text class="delivery-ratio">{{ assignment.allocationBps / 100 }}%</text>
        </view>
      </view>
    </view>

    <!-- 会员列表（对标原版：旷课/有效/排队/已取消 四组） -->
    <view v-if="absentList.length || validList.length || waitlist.length || cancelList.length" class="member-list">
      <template v-for="group in [absentList, validList]" :key="group === absentList ? 'absent' : 'valid'">
        <view v-for="item in group" :key="item.id" class="m-item">
          <view class="m-ava" :class="{ gray: item.status === 'absent' }" @tap="openMember(item.memberId)">
            {{ memberLabel(item)[0] }}
          </view>
          <view class="m-body">
            <view class="m-flex">
              <view class="m-left">
                <text class="m-name" @tap="openMember(item.memberId)">{{ memberLabel(item) }}</text>
                <text class="m-date">{{ item.bookedAt.replace("T", " ").slice(5, 16) }}</text>
                <text v-if="item.staffNotes" class="m-remark">备注：<text class="m-remark-text">{{ item.staffNotes }}</text></text>
              </view>
              <view class="m-right">
                <text v-if="item.deductAmount" class="m-price">-{{ item.deductAmount }}{{ item.cardUnit || "" }}</text>
                <text class="m-card">
                  {{ item.cardName || "" }}
                  <text v-if="item.cardBalance != null" class="m-balance">余{{ item.cardBalance }}{{ item.cardUnit || "" }}</text>
                </text>
                <view class="m-status-row">
                  <text v-if="item.operatorStaffName" class="m-operator">{{ item.operatorStaffName }}操作</text>
                  <text class="m-status" :style="{ color: rowStatus(item).color }">{{ rowStatus(item).text }}</text>
                </view>
              </view>
              <view v-if="item.status === 'confirmed'" class="m-more" @tap.stop="toggleDrop(item)">
                <u-icon name="more-dot-fill" size="18" color="#989898" />
                <view v-if="dropKey === item.id" class="m-drop" @tap.stop>
                  <view v-if="canCancelItem(item)" class="m-drop-item" @tap="closeDrop(); confirmCancelAppointment(item)">取消预约</view>
                  <view v-if="canMarkAbsentItem(item)" class="m-drop-item" @tap="closeDrop(); confirmMarkAbsent(item)">旷课</view>
                  <view v-if="canEditNotesItem(item)" class="m-drop-item" @tap="closeDrop(); openNotesPanel(item)">写备注</view>
                  <view v-if="canRescheduleItem(item)" class="m-drop-item" @tap="closeDrop(); openReschedulePanel(item)">修改预约</view>
                </view>
              </view>
            </view>
            <view v-if="item.status === 'absent'" class="m-truant-tag">旷课</view>
            <ConsumptionStatus :value="consumptionByAppointment[item.id]" compact />
          </view>
        </view>
      </template>

      <!-- 排队组 -->
      <view v-for="(item, index) in waitlist" :key="`w-${item.id}`" class="m-item">
        <view class="m-ava" @tap="openMember(item.memberId)">{{ memberLabel(item)[0] }}</view>
        <view class="m-body">
          <view class="m-flex">
            <view class="m-left">
              <text class="m-name" @tap="openMember(item.memberId)">{{ memberLabel(item) }}</text>
              <text class="m-date">{{ item.bookedAt.replace("T", " ").slice(5, 16) }}</text>
              <text v-if="item.staffNotes" class="m-remark">备注：<text class="m-remark-text">{{ item.staffNotes }}</text></text>
            </view>
            <view class="m-right">
              <text class="m-card">
                {{ item.cardName || "" }}
                <text v-if="item.cardBalance != null" class="m-balance">余{{ item.cardBalance }}{{ item.cardUnit || "" }}</text>
              </text>
              <view class="m-status-row">
                <text class="m-queue-no">第{{ index + 1 }}位</text>
                <text class="m-status" :style="{ color: rowStatus(item).color }">{{ rowStatus(item).text }}</text>
              </view>
            </view>
            <view class="m-more" @tap.stop="toggleDrop(item)">
              <u-icon name="more-dot-fill" size="18" color="#989898" />
              <view v-if="dropKey === item.id" class="m-drop" @tap.stop>
                <view v-if="canPromoteItem()" class="m-drop-item" @tap="closeDrop(); confirmPromoteWaitlist(item)">转正预约</view>
                <view v-if="canCancelAppointment" class="m-drop-item" @tap="closeDrop(); confirmCancelAppointment(item)">取消排队</view>
                <view v-if="canEditNotes" class="m-drop-item" @tap="closeDrop(); openNotesPanel(item)">写备注</view>
              </view>
            </view>
          </view>
        </view>
      </view>

      <!-- 已取消组（划线灰） -->
      <view v-for="item in cancelList" :key="`c-${item.id}`" class="m-item cancelled">
        <view class="m-ava gray" @tap="openMember(item.memberId)">{{ memberLabel(item)[0] }}</view>
        <view class="m-body">
          <view class="m-flex">
            <view class="m-left">
              <text class="m-name strike" @tap="openMember(item.memberId)">{{ memberLabel(item) }}</text>
              <text class="m-date">{{ item.bookedAt.replace("T", " ").slice(5, 16) }}</text>
            </view>
            <view class="m-right">
              <view class="m-status-row">
                <text v-if="item.operatorStaffName" class="m-operator">{{ item.operatorStaffName }}操作</text>
                <text class="m-status" :style="{ color: rowStatus(item).color }">{{ rowStatus(item).text }}</text>
              </view>
            </view>
          </view>
        </view>
      </view>

      <!-- 自动签到提示（原版 sign_hint_wrap） -->
      <view class="sign-hint">
        <u-icon name="bell" size="16" color="#C96B30" />
        <text>下课后，将由系统5分钟内自动签到</text>
      </view>
    </view>
    <view v-else class="nodata-box">
      <text class="sg-empty-text">~ 还没有会员预约哦 ~</text>
    </view>

    <u-empty v-if="forbidden" mode="permission" text="当前账号暂无课程详情权限" />
    <view class="brand-footer">觅境约课</view>

    <!-- 课程管理弹窗组（对标原版编辑按钮 → course-management） -->
    <session-manage
      v-model:show="manageVisible"
      :session="detail"
      @success="onManageSuccess"
      @deleted="onManageDeleted"
    />
  </view>

  <u-empty v-else-if="!checking && !detail && !loading" mode="data" text="课程不存在或已删除" />

  <!-- 代约第 1 步：选会员（对标原版 member-search 弹窗） -->
  <member-picker
    v-model:show="pickerOpen"
    :site-id="session.currentSiteId ?? null"
    @select="selectBookingMember"
  />

  <!-- 代约第 2 步：选卡确认（对标原版 select-member-card：卡面大卡 + 选择其它卡） -->
  <view v-if="bookingOpen && selectedMember" class="booking-overlay" @tap="closeBookingPanel">
    <view class="booking-panel" @tap.stop>
      <view class="booking-header">
        <text class="booking-title">代预约</text>
        <button class="booking-close" @click="closeBookingPanel">关闭</button>
      </view>

      <view class="booking-selected">
        <text class="booking-selected-label">会员</text>
        <text class="booking-selected-name">{{ selectedMember.name || selectedMember.memberNo }}</text>
        <button class="booking-back" @click="backToMemberSearch">重选</button>
      </view>
      <u-alert v-if="bookingError" class="booking-alert" type="error" :description="bookingError" />

      <member-card-picker
        v-model="selectedCardId"
        :cards="memberCards"
        :loading="bookingLoading"
      />

      <button
        class="booking-confirm"
        :disabled="!selectedCardId || bookingSubmitting"
        @tap="confirmAssistBooking"
      >
        确认预约
      </button>
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
          <u-icon name="arrow-right" color="#989898" size="16" />
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
  border-radius: $radius-lg;
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

// 代约确认按钮（对标原版 ff-popup 底部「确 定」：黄底大胶囊）
.booking-confirm {
  margin: 40rpx 0 10rpx;
  height: 83rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;

  &[disabled] {
    background: #e8e8e8;
    color: #b0b0b0;
  }
}

.booking-confirm::after {
  border: 0;
}

// ===== 对标原版 leagueClassDetails =====
.page-shell { min-height: 100vh; background: #f5f5f5; padding-bottom: 40rpx; }

.hero {
  position: relative;
  padding: 26rpx 28rpx 34rpx;
  overflow: hidden;
}
.hero-date { color: #fff; font-size: 30rpx; font-weight: 500; text-align: center; padding-bottom: 20rpx; }
.hero-main { display: flex; justify-content: space-between; }
.hero-left { flex: 1; min-width: 0; }
.hero-name { display: block; color: #fff; font-size: 44rpx; font-weight: 600; }
.hero-info { display: flex; gap: 12rpx; margin-top: 16rpx; color: rgba(255,255,255,.9); font-size: 24rpx; }
.hero-sep { color: rgba(255,255,255,.5); }
.hero-coach { display: flex; align-items: center; gap: 12rpx; margin-top: 26rpx; }
.hero-ava { display: flex; align-items: center; justify-content: center; width: 56rpx; height: 56rpx; border: 2rpx solid rgba(255,255,255,.6); border-radius: 50%; background: rgba(255,255,255,.25); color: #fff; font-size: 24rpx; }
.hero-coach-name { color: #fff; font-size: 26rpx; }
.hero-limit { margin-left: 8rpx; color: rgba(255,255,255,.85); font-size: 22rpx; }
.hero-full { padding: 2rpx 12rpx; background: #d95872; border-radius: 999rpx; color: #fff; font-size: 20rpx; }
.hero-right { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; margin-left: 20rpx; }
.hero-start { color: #fff; font-size: 44rpx; line-height: 44rpx; }
.hero-end { margin-top: 12rpx; color: rgba(255,255,255,.85); font-size: 22rpx; }
.hero-actions { display: flex; gap: 20rpx; margin-top: 30rpx; }
.hero-action { width: auto; height: auto; padding: 8rpx 22rpx; border: 1rpx solid rgba(255,255,255,.7); border-radius: 999rpx; background: transparent; color: #fff; font-size: 22rpx; line-height: 1.6; margin: 0; text-align: center; }
.share-btn::after { border: 0; }
.hero-mask { position: absolute; inset: 0; background: rgba(255,255,255,.45); pointer-events: none; }
.hero-stamp {
  position: absolute; top: 50%; right: 60rpx; z-index: 2;
  padding: 8rpx 22rpx; border: 4rpx solid #dc3c5c; border-radius: 12rpx;
  color: #dc3c5c; font-size: 34rpx; font-weight: 600; letter-spacing: 4rpx;
  transform: translateY(-50%) rotate(-14deg);
  &.grey { border-color: #989898; color: #989898; }
}

.manage-row { display: flex; justify-content: flex-end; gap: 30rpx; padding: 18rpx 28rpx 0; }
.manage-link { color: #505050; font-size: 24rpx; &.danger { color: #dc3c5c; } }

.stat-row { display: flex; align-items: center; justify-content: space-between; padding: 24rpx 28rpx 10rpx; }
.stat-left { display: flex; gap: 20rpx; }
.stat-item { color: #181818; font-size: 28rpx; font-weight: 500; }
.stat-btn {
  width: 136rpx; height: 62rpx; border-radius: 31rpx; font-size: 26rpx; line-height: 62rpx; text-align: center;
  &.green { background: #22c788; color: #fff; }
  &.light { background: #ecf8f3; color: #22c788; }
  &.grey { background: #bababa; color: #fff; }
}

.delivery-summary { margin: 14rpx 24rpx 0; padding: 22rpx 24rpx; background: #fff; border-radius: 20rpx; }
.delivery-summary-head { display: flex; align-items: center; justify-content: space-between; font-size: 25rpx; font-weight: 600; }
.delivery-summary-head text:last-child { color: #989898; font-size: 20rpx; font-weight: 400; }
.delivery-summary-list { margin-top: 12rpx; }
.delivery-summary-item { display: flex; align-items: center; gap: 14rpx; padding: 12rpx 0; border-top: 1rpx solid #f3f3f3; }
.delivery-avatar { display: flex; align-items: center; justify-content: center; width: 58rpx; height: 58rpx; color: #fff; background: #696b99; border-radius: 50%; font-size: 23rpx; }
.delivery-summary-main { flex: 1; min-width: 0; }
.delivery-summary-main text { display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.delivery-summary-main text:first-child { color: #181818; font-size: 24rpx; }
.delivery-summary-main text:last-child { margin-top: 4rpx; color: #989898; font-size: 20rpx; }
.delivery-ratio { color: #8b6c00; font-size: 23rpx; font-weight: 600; }

.member-list { margin: 16rpx 24rpx 0; padding: 6rpx 24rpx; background: #fff; border-radius: 20rpx; }
.m-item { position: relative; display: flex; gap: 18rpx; padding: 26rpx 0; border-bottom: 1rpx solid #f5f5f5; &:last-of-type { border-bottom: none; } }
.m-ava { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 80rpx; height: 80rpx; border-radius: 50%; background: #f0f0f0; color: #505050; font-size: 30rpx; &.gray { filter: grayscale(100%); opacity: .7; } }
.m-body { flex: 1; min-width: 0; }
.m-flex { display: flex; gap: 10rpx; }
.m-left { flex: 1; min-width: 0; }
.m-name { display: block; color: #181818; font-size: 28rpx; font-weight: 500; &.strike { color: #989898; text-decoration: line-through; } }
.m-date { display: block; margin-top: 10rpx; color: #989898; font-size: 22rpx; }
.m-remark { display: block; margin-top: 10rpx; color: #989898; font-size: 22rpx; }
.m-remark-text { color: #505050; }
.m-right { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; }
.m-price { color: #181818; font-size: 26rpx; font-weight: 600; }
.m-card { margin-top: 10rpx; color: #989898; font-size: 22rpx; }
.m-balance { margin-left: 8rpx; }
.m-status-row { display: flex; align-items: center; gap: 10rpx; margin-top: 10rpx; }
.m-operator { color: #bfbfbf; font-size: 20rpx; }
.m-status { font-size: 24rpx; font-weight: 500; }
.m-queue-no { color: #e98900; font-size: 22rpx; }
.m-more { position: relative; flex-shrink: 0; padding: 4rpx; }
.m-drop {
  position: absolute; top: 40rpx; right: 0; z-index: 20;
  min-width: 200rpx; padding: 8rpx 0; background: #fff; border-radius: 12rpx;
  box-shadow: 0 8rpx 30rpx rgba(0,0,0,.15);
}
.m-drop-item { padding: 20rpx 28rpx; color: #181818; font-size: 26rpx; white-space: nowrap; border-bottom: 1rpx solid #f5f5f5; &:last-child { border-bottom: none; } }
.m-truant-tag { position: absolute; top: 18rpx; right: 0; padding: 2rpx 10rpx; border: 2rpx solid #dc3c5c; border-radius: 8rpx; color: #dc3c5c; font-size: 18rpx; transform: rotate(12deg); }
.m-item.cancelled { opacity: .75; }

.sign-hint { display: flex; align-items: center; gap: 8rpx; padding: 22rpx 4rpx; color: #c96b30; font-size: 22rpx; }
.nodata-box { padding: 120rpx 0; text-align: center; }
.brand-footer { margin: 70rpx 0 20rpx; color: #d8d8d8; font-size: 26rpx; letter-spacing: 6rpx; text-align: center; }

</style>
