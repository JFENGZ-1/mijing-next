<script setup lang="ts">
// 私教教练详情 —— 对标原版 pagesCourse/personalTrainerDetails
// 头部教练卡 + 已约N人/代约 + 按天分组预约时间线 + 行操作（取消/旷课/修改预约/写备注）
import { computed, nextTick, ref, watch } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@mijing/api-client";
import { bookPrivateCoach, fetchPrivateCoaches, fetchPrivateCoachTimeSlots } from "@/api/catalog";
import type { CoachPrivateProfile, PrivateCoachTimeSlot } from "@/api/catalog";
import {
  cancelStaffAppointment,
  fetchStaffScheduleSessions,
  fetchStaffSessionAppointments,
  markStaffAppointmentAbsent,
  updateStaffAppointmentNotes,
  updateStaffScheduleSession,
} from "@/api/scheduling";
import { fetchMemberCards } from "@/api/crm";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import MemberPicker from "@/components/member-picker/member-picker.vue";
import MemberCardPicker from "@/components/member-card-picker/member-card-picker.vue";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";
import type { BookingPickerMember, StaffMemberCardSummary } from "@/types/crm";
import type { ScheduleSession, StaffAppointment } from "@/types/scheduling";
import { createCommandKey } from "@/utils/command-key";
import { todayIsoDate, splitLocalDateTime } from "@/utils/format";

const store = useSessionStore();
const checking = ref(true);
const loading = ref(true);
const acting = ref(false);
const coachStaffId = ref(0);
const coachName = ref("");
const profile = ref<CoachPrivateProfile | null>(null);
const sessions = ref<ScheduleSession[]>([]);
const appointmentsBySession = ref<Map<number, StaffAppointment[]>>(new Map());

const DAYS_FORWARD = 14;
const DAYS_BACK = 30;
const WEEK_NAMES = ["周日", "周一", "周二", "周三", "周四", "周五", "周六"];

const canBook = computed(() => store.can("booking.appointment.create"));
const canCancel = computed(() => store.can("booking.appointment.cancel"));
const canAbsent = computed(() => store.can("booking.fulfillment.absent"));
const canNotes = computed(() => store.can("booking.fulfillment.notes"));
const canEditProfile = computed(() => store.can("course-catalog.write"));

function addDays(iso: string, days: number) {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function hhmm(iso: string) {
  const idx = iso.indexOf("T");
  return idx > 0 ? iso.slice(idx + 1, idx + 6) : iso.slice(11, 16);
}

function formatDayLabel(iso: string) {
  const jsDate = new Date(`${iso}T00:00:00`);
  const month = String(jsDate.getMonth() + 1).padStart(2, "0");
  const day = String(jsDate.getDate()).padStart(2, "0");
  return `${month}.${day} ${WEEK_NAMES[jsDate.getDay()]}`;
}

function sessionLocalDate(startsAt: string) {
  return splitLocalDateTime(startsAt).date;
}

// 按天分组（日期按本地日历，避免 ISO 字符串 slice 与 today 比较错位）
const dayGroups = computed(() => {
  const byDate = new Map<string, ScheduleSession[]>();
  for (const item of sessions.value) {
    const date = sessionLocalDate(item.startsAt);
    const list = byDate.get(date) ?? [];
    list.push(item);
    byDate.set(date, list);
  }
  const today = todayIsoDate();
  const tomorrow = addDays(today, 1);
  return [...byDate.keys()].sort().map((date) => ({
    date,
    label: formatDayLabel(date),
    isToday: date === today,
    isTomorrow: date === tomorrow,
    items: (byDate.get(date) ?? []).sort((a, b) => a.startsAt.localeCompare(b.startsAt)),
  }));
});

/** 本地「今天」ISO 星期：1=周一 … 7=周日 */
function localIsoWeekday() {
  const day = new Date().getDay();
  return day === 0 ? 7 : day;
}

function parseHmToDaySeconds(hm: string): number | null {
  const parts = hm.split(":");
  const hour = Number(parts[0]);
  const minute = Number(parts[1] ?? 0);
  if (!Number.isFinite(hour) || !Number.isFinite(minute)) return null;
  return hour * 3600 + minute * 60;
}

function localNowDaySeconds() {
  const now = new Date();
  return now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
}

/** 今日所有适用 bookingWindows 里最晚的 end（秒，bookingWindows.days 为 ISO 1=周一…7=周日） */
function latestBookingEndSecondsToday(windows: CoachPrivateProfile["bookingWindows"]): number | null {
  const isoWeekday = localIsoWeekday();
  let maxEnd: number | null = null;
  for (const window of windows ?? []) {
    const days = (window.days ?? []).map((day) => Number(day));
    if (!days.includes(isoWeekday)) continue;
    const endSec = parseHmToDaySeconds(window.end);
    if (endSec === null) continue;
    if (maxEnd === null || endSec > maxEnd) maxEnd = endSec;
  }
  return maxEnd;
}

/** 当前时刻是否已到/已过「今日最晚可约 end」（当天可约时段结束后为 true） */
const pastCoachLatestBookingEnd = computed(() => {
  const windows = profile.value?.bookingWindows;
  if (!windows?.length) return false;
  const maxEnd = latestBookingEndSecondsToday(windows);
  if (maxEnd === null) return false;
  return localNowDaySeconds() >= maxEnd;
});

/**
 * 预约列表按天展示顺序：
 * - 可约时段内：今天 → 明天及以后 → 历史（教练打开先看今天）
 * - 已过今日最晚可约：明天及以后 → 今天（仍有课则保留）→ 历史
 */
const displayDayGroups = computed(() => {
  const groups = dayGroups.value;
  const today = todayIsoDate();
  const past = groups.filter((group) => group.date < today).sort((a, b) => b.date.localeCompare(a.date));
  const todayGroup = groups.filter((group) => group.date === today);
  const future = groups.filter((group) => group.date > today).sort((a, b) => a.date.localeCompare(b.date));

  if (pastCoachLatestBookingEnd.value) {
    return [...future, ...todayGroup, ...past];
  }
  return [...todayGroup, ...future, ...past];
});

const totalBooked = computed(() => {
  let count = 0;
  for (const list of appointmentsBySession.value.values()) {
    count += list.filter((item) => item.status === "confirmed" || item.status === "completed").length;
  }
  return count;
});

function appointmentsOf(sessionId: number) {
  return appointmentsBySession.value.get(sessionId) ?? [];
}

async function load() {
  if (!store.currentSiteId) return;
  loading.value = true;
  try {
    const [profiles, sessionList] = await Promise.all([
      fetchPrivateCoaches(store.currentSiteId).catch(() => []),
      fetchStaffScheduleSessions(
        store.currentSiteId,
        `${addDays(todayIsoDate(), -DAYS_BACK)}T00:00:00`,
        `${addDays(todayIsoDate(), DAYS_FORWARD)}T00:00:00`,
      ),
    ]);
    profile.value = profiles.find((item) => item.coachStaffId === coachStaffId.value) ?? null;
    if (profile.value?.coachName) coachName.value = profile.value.coachName;
    sessions.value = sessionList.items.filter(
      (item) => item.sessionKind === "private" && item.coachStaffId === coachStaffId.value && item.status !== "cancelled",
    );
    const map = new Map<number, StaffAppointment[]>();
    await Promise.all(
      sessions.value.map(async (item) => {
        try {
          const response = await fetchStaffSessionAppointments(store.currentSiteId as number, item.id);
          map.set(item.id, response.items);
        } catch {
          map.set(item.id, []);
        }
      }),
    );
    appointmentsBySession.value = map;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

// —— 行操作（原版下拉：取消预约/旷课/修改预约/写备注） ——
const dropKey = ref(0);

function toggleDrop(item: StaffAppointment) {
  dropKey.value = dropKey.value === item.id ? 0 : item.id;
}

function closeDrop() {
  dropKey.value = 0;
}

function memberLabel(item: StaffAppointment) {
  return item.memberName || item.memberNo || `会员 #${item.memberId}`;
}

function canShowAppointmentMenu(appointment: StaffAppointment) {
  return appointment.status !== "cancelled";
}

/** 对标原版：已预约、已签到均可取消/旷课/写备注；修改预约仅已预约 */
function allowsFulfillmentActions(appointment: StaffAppointment) {
  return appointment.status === "confirmed" || appointment.status === "completed";
}

function rowStatus(item: StaffAppointment): { text: string; color: string } {
  switch (item.status) {
    case "completed":
      return { text: "已签到", color: "#22c788" };
    case "absent":
      return { text: "已旷课", color: "#d95872" };
    case "cancelled":
      return { text: "预约取消", color: "#d95872" };
    default:
      return { text: "已预约", color: "#d95872" };
  }
}

/** 时段/课目胶囊置灰（对标原版 disabled_course：非「已预约」或已下课） */
function slotPillDisabled(session: ScheduleSession, appointment: StaffAppointment) {
  if (appointment.status !== "confirmed") return true;
  return new Date(session.endsAt).getTime() < Date.now();
}

function confirmCancel(item: StaffAppointment) {
  closeDrop();
  uni.showModal({
    title: "确定取消预约吗？",
    content: "将退还已扣（若有）相应费用",
    confirmColor: "#dc3c5c",
    success: async (result) => {
      if (!result.confirm || !store.currentSiteId) return;
      acting.value = true;
      try {
        await cancelStaffAppointment(store.currentSiteId, item.id, { commandKey: createCommandKey() });
        uni.showToast({ title: "已取消", icon: "none" });
        await load();
      } catch (error) {
        uni.showToast({ title: error instanceof Error ? error.message : "取消失败", icon: "none" });
      } finally {
        acting.value = false;
      }
    },
  });
}

function confirmAbsent(item: StaffAppointment) {
  closeDrop();
  uni.showModal({
    title: "确定标记旷课吗？",
    content: "标记为旷课后，不可取消！！",
    confirmColor: "#dc3c5c",
    success: async (result) => {
      if (!result.confirm || !store.currentSiteId) return;
      acting.value = true;
      try {
        await markStaffAppointmentAbsent(store.currentSiteId, item.id, { commandKey: createCommandKey() });
        uni.showToast({ title: "已标记旷课", icon: "none" });
        await load();
      } catch (error) {
        uni.showToast({ title: error instanceof Error ? error.message : "操作失败", icon: "none" });
      } finally {
        acting.value = false;
      }
    },
  });
}

const NOTES_MAX = 150;
const notesOpen = ref(false);
const notesTarget = ref<StaffAppointment | null>(null);
const notesDraft = ref("");
const notesSubmitting = ref(false);

function openNotesPanel(item: StaffAppointment) {
  closeDrop();
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
  if (!notesTarget.value || !store.currentSiteId || !notesDraft.value.trim()) return;
  notesSubmitting.value = true;
  try {
    await updateStaffAppointmentNotes(store.currentSiteId, notesTarget.value.id, {
      staffNotes: notesDraft.value.trim(),
    });
    closeNotesPanel();
    uni.showToast({ title: "操作成功", icon: "none" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    notesSubmitting.value = false;
  }
}

// 修改预约 = 改私教节时间（UI 与代约选时间一致）
const retimeVisible = ref(false);
const retimeTarget = ref<ScheduleSession | null>(null);
const retimeDate = ref(todayIsoDate());
const retimeStart = ref("");
const retimeCourseId = ref(0);
const retimeOriginalDate = ref("");
const retimeOriginalStart = ref("");
const retimeSlots = ref<PrivateCoachTimeSlot[]>([]);
const retimeSlotsLoading = ref(false);
const retimeScrollTarget = ref("");
const retimeCalendarOpen = ref(false);
const retimeGroupOverlapAck = ref(false);

function closeRetimePanel() {
  retimeVisible.value = false;
  retimeTarget.value = null;
  retimeSlots.value = [];
  retimeGroupOverlapAck.value = false;
}

function resolveRetimeCourseId(session: ScheduleSession) {
  const fromSession = session.courseId || 0;
  if (fromSession && subjectCourses.value.some((item) => item.id === fromSession)) {
    return fromSession;
  }
  return subjectCourses.value[0]?.id ?? 0;
}

function openRetime(item: StaffAppointment) {
  closeDrop();
  const target = sessions.value.find((sessionItem) => sessionItem.id === item.sessionId);
  if (!target) return;
  retimeTarget.value = target;
  retimeDate.value = sessionLocalDate(target.startsAt);
  retimeStart.value = hhmm(target.startsAt);
  retimeOriginalDate.value = retimeDate.value;
  retimeOriginalStart.value = retimeStart.value;
  retimeCourseId.value = resolveRetimeCourseId(target);
  retimeScrollTarget.value = "";
  retimeVisible.value = true;
  nextTick(() => {
    void loadRetimeSlots(true);
  });
}

function pickRetimeDate(iso: string) {
  retimeDate.value = iso;
  retimeStart.value = "";
  retimeScrollTarget.value = "";
  nextTick(() => {
    retimeScrollTarget.value = `retime-day-${iso}`;
  });
}

function backRetimeToday() {
  pickRetimeDate(todayIsoDate());
}

function onRetimeCalendarConfirm(value: string[] | string) {
  retimeCalendarOpen.value = false;
  const picked = Array.isArray(value) ? value[0] : value;
  if (!picked || picked < todayIsoDate()) return;
  pickRetimeDate(picked);
}

function pickRetimeSlot(slot: PrivateCoachTimeSlot) {
  if (!canPickBookSlot(slot) && !isRetimeCurrentSlot(slot.start)) return;
  retimeStart.value = slot.start;
  retimeGroupOverlapAck.value = Boolean(slot.groupOverlapWarn);
}

function isRetimeCurrentSlot(start: string) {
  return retimeDate.value === retimeOriginalDate.value && start === retimeOriginalStart.value;
}

async function loadRetimeSlots(keepStart = false) {
  if (!profile.value || !store.currentSiteId || !retimeDate.value) return;
  if (needSubject.value) {
    const valid = subjectCourses.value.some((item) => item.id === retimeCourseId.value);
    if (!retimeCourseId.value || !valid) {
      retimeSlots.value = [];
      if (!keepStart) retimeStart.value = "";
      return;
    }
  }
  const preferred = keepStart ? retimeStart.value : "";
  retimeSlotsLoading.value = true;
  if (!keepStart) retimeStart.value = "";
  retimeSlots.value = [];
  try {
    const result = await fetchPrivateCoachTimeSlots(store.currentSiteId, profile.value.id, {
      date: retimeDate.value,
      courseId: needSubject.value ? retimeCourseId.value : undefined,
      excludeSessionId: retimeTarget.value?.id,
    });
    retimeSlots.value = result.slots;
    if (preferred) {
      const match = result.slots.find((slot) => slot.start === preferred);
      if (match && (canPickBookSlot(match) || isRetimeCurrentSlot(preferred))) {
        retimeStart.value = preferred;
        retimeGroupOverlapAck.value = Boolean(match.groupOverlapWarn);
      }
    }
  } catch {
    retimeSlots.value = [];
    uni.showToast({ title: "时间槽加载失败，请重试", icon: "none" });
  } finally {
    retimeSlotsLoading.value = false;
  }
}

function retimeDurationMs() {
  const target = retimeTarget.value;
  if (needSubject.value && retimeCourseId.value) {
    const course = subjectCourses.value.find((item) => item.id === retimeCourseId.value);
    if (course?.durationMinutes) return course.durationMinutes * 60 * 1000;
  }
  if (target) return new Date(target.endsAt).getTime() - new Date(target.startsAt).getTime();
  return 60 * 60 * 1000;
}

async function submitRetimeWithPayload(acknowledgeGroupOverlap = false) {
  const target = retimeTarget.value;
  if (!target || !store.currentSiteId) return false;
  const duration = retimeDurationMs();
  const startDate = new Date(`${retimeDate.value}T${retimeStart.value}:00`);
  const endDate = new Date(startDate.getTime() + duration);
  const pad = (num: number) => String(num).padStart(2, "0");
  const payload: Parameters<typeof updateStaffScheduleSession>[2] = {
    version: target.version,
    startsAt: `${retimeDate.value}T${retimeStart.value}:00`,
    endsAt: `${endDate.getFullYear()}-${pad(endDate.getMonth() + 1)}-${pad(endDate.getDate())}T${pad(endDate.getHours())}:${pad(endDate.getMinutes())}:00`,
    acknowledgeGroupOverlap: acknowledgeGroupOverlap || undefined,
  };
  if (needSubject.value && retimeCourseId.value && retimeCourseId.value !== target.courseId) {
    payload.courseId = retimeCourseId.value;
  }
  await updateStaffScheduleSession(store.currentSiteId, target.id, payload);
  return true;
}

function apiErrorCode(error: unknown): string {
  if (error instanceof ApiError && error.payload?.code) return String(error.payload.code);
  if (error && typeof error === "object" && "code" in error) return String((error as { code?: string }).code);
  return "";
}

async function submitRetime() {
  const target = retimeTarget.value;
  if (!target || !store.currentSiteId) return;
  if (needSubject.value && !retimeCourseId.value) {
    uni.showToast({ title: "请选择私教课目", icon: "none" });
    return;
  }
  if (!retimeStart.value) {
    uni.showToast({ title: "请选择时间", icon: "none" });
    return;
  }
  if (retimeGroupOverlapAck.value) {
    const confirmed = await confirmGroupOverlapBook();
    if (!confirmed) return;
  }
  acting.value = true;
  try {
    const ok = await submitRetimeWithPayload(retimeGroupOverlapAck.value);
    if (!ok) return;
    closeRetimePanel();
    uni.showToast({ title: "已修改", icon: "none" });
    retimeGroupOverlapAck.value = false;
    await load();
  } catch (error) {
    const code = apiErrorCode(error);
    if (code === "COACH_PRIVATE_GROUP_OVERLAP") {
      acting.value = false;
      const confirmed = await confirmGroupOverlapBook();
      if (!confirmed) return;
      acting.value = true;
      try {
        await submitRetimeWithPayload(true);
        closeRetimePanel();
        retimeGroupOverlapAck.value = false;
        uni.showToast({ title: "已修改", icon: "none" });
        await load();
      } catch (retryError) {
        uni.showToast({ title: retryError instanceof Error ? retryError.message : "修改失败", icon: "none" });
      } finally {
        acting.value = false;
      }
      return;
    }
    uni.showToast({ title: error instanceof Error ? error.message : "修改失败", icon: "none" });
  } finally {
    acting.value = false;
  }
}

// —— 代约弹窗（原版 member-search 流程：member-picker 选会员 → [课目] → 日期时间 → 选卡） ——
const pickerOpen = ref(false);
const bookVisible = ref(false);
const bookLoading = ref(false);
const bookMember = ref<BookingPickerMember | null>(null);
const bookCards = ref<StaffMemberCardSummary[]>([]);
const bookCardId = ref<number | null>(null);
const bookCourseId = ref(0);
const bookDate = ref(todayIsoDate());
const bookStart = ref("");
const bookGroupOverlapAck = ref(false);

// —— 时间选择（对标原版 selected-course-timer：滑动日期条 + 当日时间槽） ——
const calendarOpen = ref(false);
const calendarMinDate = `${new Date().getFullYear() - 1}-01-01`;
const calendarMaxDate = `${new Date().getFullYear() + 1}-12-31`;
const calendarMonthNum = 36;
const bookSlots = ref<PrivateCoachTimeSlot[]>([]);
const slotsLoading = ref(false);
const bookRemark = ref(""); // 会员留言（原版 remake，150 字内）
const cardPickerVisible = ref(false); // 第二步选卡弹窗（原版：选完时间再选卡）
const scrollTarget = ref(""); // scroll-view scroll-into-view 定位目标

function isoOf(date: Date) {
  const pad = (num: number) => String(num).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** 横向滑动日期条：今天排第一个，向未来滚动 30 天 */
const BOOK_DAY_COUNT = 30;

const dayList = computed(() => {
  const today = todayIsoDate();
  return Array.from({ length: BOOK_DAY_COUNT }, (_, index) => {
    const day = new Date(`${today}T00:00:00`);
    day.setDate(day.getDate() + index);
    const iso = isoOf(day);
    return { iso, num: day.getDate(), week: WEEK_NAMES[day.getDay()], isToday: iso === today };
  });
});

/** 选中日期并滚动到可视区（scroll-into-view 先复位再赋值才会再次触发） */
function pickBookDate(iso: string) {
  bookDate.value = iso;
  bookStart.value = "";
  scrollTarget.value = "";
  nextTick(() => {
    scrollTarget.value = `day-${iso}`;
  });
}

function backToday() {
  pickBookDate(todayIsoDate());
}

function onCalendarConfirm(value: string[] | string) {
  calendarOpen.value = false;
  const picked = Array.isArray(value) ? value[0] : value;
  if (!picked || picked < todayIsoDate()) return;
  pickBookDate(picked);
}

function groupSlotsByPeriod(slots: PrivateCoachTimeSlot[]) {
  const groups = [
    { label: "上午", items: [] as PrivateCoachTimeSlot[] },
    { label: "下午", items: [] as PrivateCoachTimeSlot[] },
    { label: "晚上", items: [] as PrivateCoachTimeSlot[] },
  ];
  for (const slot of slots) {
    const hour = Number(slot.start.slice(0, 2));
    if (hour < 12) groups[0].items.push(slot);
    else if (hour < 18) groups[1].items.push(slot);
    else groups[2].items.push(slot);
  }
  return groups.filter((group) => group.items.length > 0);
}

const slotGroups = computed(() => groupSlotsByPeriod(bookSlots.value));
const retimeSlotGroups = computed(() => groupSlotsByPeriod(retimeSlots.value));

function canPickBookSlot(slot: PrivateCoachTimeSlot) {
  return slot.available || Boolean(slot.groupOverlapWarn);
}

function pickSlot(slot: PrivateCoachTimeSlot) {
  if (!canPickBookSlot(slot)) return;
  bookStart.value = slot.start;
  bookGroupOverlapAck.value = Boolean(slot.groupOverlapWarn);
}

function confirmGroupOverlapBook(): Promise<boolean> {
  return new Promise((resolve) => {
    uni.showModal({
      title: "与团课时间重合",
      content: "该时段教练有团课排课，是否仍要代约？",
      success: (res) => resolve(Boolean(res.confirm)),
      fail: () => resolve(false),
    });
  });
}

async function submitBookWithPayload(acknowledgeGroupOverlap = false) {
  if (!store.currentSiteId || !profile.value || !bookMember.value) return false;
  await bookPrivateCoach(store.currentSiteId, profile.value.id, {
    memberId: bookMember.value.id,
    memberCardId: bookCardId.value!,
    date: bookDate.value,
    start: bookStart.value,
    courseId: needSubject.value ? bookCourseId.value : undefined,
    remark: bookRemark.value.trim() || undefined,
    commandKey: createCommandKey(),
    acknowledgeGroupOverlap: acknowledgeGroupOverlap || undefined,
  });
  return true;
}

async function loadSlots() {
  if (!profile.value || !store.currentSiteId || !bookDate.value) return;
  slotsLoading.value = true;
  bookStart.value = "";
  bookSlots.value = [];
  try {
    const result = await fetchPrivateCoachTimeSlots(store.currentSiteId, profile.value.id, {
      date: bookDate.value,
      courseId: needSubject.value ? bookCourseId.value : undefined,
    });
    bookSlots.value = result.slots;
  } catch {
    bookSlots.value = [];
    uni.showToast({ title: "时间槽加载失败，请重试", icon: "none" });
  } finally {
    slotsLoading.value = false;
  }
}

// 切换日期/课目重拉时间槽（弹窗开着时）
watch([bookDate, bookCourseId], () => {
  if (bookVisible.value) void loadSlots();
});

const subjectCourses = computed(() => profile.value?.courses ?? []);
// 约私教（统一时长统一定价）与约私教课目是两码事：
// 仅当 per_course 且确实配置了课目时才要求选课目；无课目时回退统一时长直接约私教。
const needSubject = computed(() => profile.value?.subjectMode === "per_course" && subjectCourses.value.length > 0);

watch([retimeDate, retimeCourseId], () => {
  if (retimeVisible.value) void loadRetimeSlots();
});

function openBook() {
  if (!profile.value) {
    uni.showToast({ title: "该教练暂未配置私教档案", icon: "none" });
    return;
  }
  bookMember.value = null;
  bookCards.value = [];
  bookCardId.value = null;
  bookCourseId.value = subjectCourses.value[0]?.id ?? 0;
  bookDate.value = todayIsoDate();
  bookStart.value = "";
  bookRemark.value = "";
  bookSlots.value = [];
  scrollTarget.value = "";
  pickerOpen.value = true;
}

/** member-picker 选中会员 → 加载会员卡并进入代约表单 */
async function pickBookMember(member: BookingPickerMember) {
  bookMember.value = member;
  bookLoading.value = true;
  bookVisible.value = true;
  void loadSlots();
  try {
    const response = await fetchMemberCards(store.currentSiteId as number, member.id);
    // 不做过滤：member-card-picker 内部按「可用 / 无效（折叠置灰）」分组展示
    bookCards.value = response.data;
  } catch {
    bookCards.value = [];
  } finally {
    bookLoading.value = false;
  }
}

/** 重选会员：回到 member-picker */
function repickMember() {
  bookVisible.value = false;
  bookMember.value = null;
  bookCards.value = [];
  bookCardId.value = null;
  pickerOpen.value = true;
}

/** 第一步确定：校验时间选择后进入选卡弹窗（原版流程：选完时间再选卡） */
function proceedToCardPicker() {
  if (!bookStart.value) {
    uni.showToast({ title: "请选择时间", icon: "none" });
    return;
  }
  bookVisible.value = false;
  cardPickerVisible.value = true;
}

/** 选卡弹窗返回上一步：回到时间选择 */
function backToTimePicker() {
  cardPickerVisible.value = false;
  bookVisible.value = true;
}

async function submitBook() {
  if (!store.currentSiteId || !profile.value || !bookMember.value) return;
  if (needSubject.value && !bookCourseId.value) {
    uni.showToast({ title: "请选择私教课目", icon: "none" });
    return;
  }
  if (!bookCardId.value) {
    uni.showToast({ title: "请选择会员卡", icon: "none" });
    return;
  }
  if (bookGroupOverlapAck.value) {
    const confirmed = await confirmGroupOverlapBook();
    if (!confirmed) return;
  }
  acting.value = true;
  try {
    const ok = await submitBookWithPayload(bookGroupOverlapAck.value);
    if (!ok) return;
    cardPickerVisible.value = false;
    uni.showToast({ title: "已代约", icon: "none" });
    bookGroupOverlapAck.value = false;
    await load();
  } catch (error) {
    const message = error instanceof Error ? error.message : "代约失败";
    const code = error && typeof error === "object" && "code" in error ? String((error as { code?: string }).code) : "";
    if (code === "COACH_PRIVATE_GROUP_OVERLAP") {
      acting.value = false;
      const confirmed = await confirmGroupOverlapBook();
      if (!confirmed) return;
      acting.value = true;
      try {
        await submitBookWithPayload(true);
        cardPickerVisible.value = false;
        bookGroupOverlapAck.value = false;
        uni.showToast({ title: "已代约", icon: "none" });
        await load();
      } catch (retryError) {
        uni.showToast({ title: retryError instanceof Error ? retryError.message : "代约失败", icon: "none" });
      } finally {
        acting.value = false;
      }
      return;
    }
    uni.showToast({ title: message, icon: "none" });
  } finally {
    acting.value = false;
  }
}

function openEditProfile() {
  if (!profile.value) return;
  uni.navigateTo({ url: `/pages/settings/courses/private-edit?id=${profile.value.id}` });
}

onLoad((query) => {
  coachStaffId.value = Number(query?.staffId || 0);
  coachName.value = decodeURIComponent(String(query?.name || ""));
  uni.setNavigationBarTitle({ title: coachName.value || "私教教练" });
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  await load();
  if (pastCoachLatestBookingEnd.value) {
    const today = todayIsoDate();
    const hasFuture = displayDayGroups.value.some((group) => group.date > today);
    if (hasFuture) {
      nextTick(() => {
        uni.pageScrollTo({ selector: "#timeline-first-day", duration: 0 });
      });
    }
  }
});
</script>

<template>
  <u-loading-page :loading="checking || loading || acting" />
  <view v-if="!checking" class="page-root" @tap="closeDrop">
    <!-- 头部教练卡（原版 personalTainerModule） -->
    <view class="coach-hero">
      <view class="hero-center">
        <view class="hero-photo-wrap">
          <view v-if="profile?.tagText" class="hero-tag">{{ profile.tagText }}</view>
          <view class="hero-photo">{{ (coachName || "教")[0] }}</view>
        </view>
        <text class="hero-name">{{ coachName }}</text>
        <text v-if="profile?.specialty" class="hero-specialty">{{ profile.specialty }}</text>
      </view>
      <view class="hero-actions">
        <view v-if="canEditProfile && profile" class="hero-action" @tap="openEditProfile">编辑</view>
        <button class="hero-action share-btn" open-type="share">分享</button>
      </view>
    </view>

    <!-- 已约 N 人 + 代约（原版 appointment-info） -->
    <view class="list-card">
      <view class="appointment-info">
        <text class="booked-text">已约 {{ totalBooked }} 人</text>
        <view v-if="canBook" class="book-btn" @tap="openBook">代约</view>
      </view>

      <!-- 按天分组时间线（对标原版 list + bottom） -->
      <view v-if="sessions.length" class="timeline-list">
      <view v-for="(group, groupIndex) in displayDayGroups" :key="group.date" :id="groupIndex === 0 ? 'timeline-first-day' : undefined" class="day-group">
        <view class="day-head">
          <view v-if="group.isToday" class="day-marker-today">今</view>
          <view v-else-if="group.isTomorrow" class="day-marker-today">明</view>
          <view v-else class="day-marker-dot" />
          <text class="day-label">{{ group.label }}</text>
        </view>
        <template v-for="item in group.items" :key="item.id">
          <view
            v-for="appointment in appointmentsOf(item.id)"
            :key="appointment.id"
            class="appt-block"
          >
            <view class="slot-head">
              <text
                class="slot-pill"
                :class="{ disabled: slotPillDisabled(item, appointment) }"
              >{{ hhmm(item.startsAt) }}~{{ hhmm(item.endsAt) }}</text>
              <text
                v-if="item.courseName"
                class="slot-pill"
                :class="{ disabled: slotPillDisabled(item, appointment) }"
              >｢{{ item.courseName }}｣</text>
            </view>
            <view class="m-row">
            <view class="m-ava" :class="{ gray: appointment.status === 'cancelled' || appointment.status === 'absent' }">
              {{ memberLabel(appointment)[0] }}
            </view>
            <view class="m-main">
              <text class="m-name" :class="{ strike: appointment.status === 'cancelled' }">{{ memberLabel(appointment) }}</text>
              <text class="m-date">{{ appointment.bookedAt.replace("T", " ").slice(0, 16) }}</text>
              <view v-if="appointment.memberRemark" class="m-remake">
                留言：<text class="m-remake-text">{{ appointment.memberRemark }}</text>
              </view>
              <view
                v-if="appointment.staffNotes"
                class="m-staff-remark"
                :class="{ 'after-remake': !!appointment.memberRemark }"
              >
                备注：<text class="m-staff-remark-text">{{ appointment.staffNotes }}</text>
              </view>
            </view>
            <view class="m-right">
              <text v-if="appointment.cardName" class="m-card">
                {{ appointment.cardName }}
                <text v-if="appointment.cardBalance != null"> 余{{ appointment.cardBalance }}{{ appointment.cardUnit || "" }}</text>
              </text>
              <view class="m-status-row">
                <text v-if="appointment.operatorStaffName" class="m-operator">{{ appointment.operatorStaffName }}操作</text>
                <text class="m-status" :style="{ color: rowStatus(appointment).color }">{{ rowStatus(appointment).text }}</text>
              </view>
            </view>
            <view v-if="canShowAppointmentMenu(appointment)" class="m-more" @tap.stop="toggleDrop(appointment)">
              <view class="m-more-icon" aria-label="更多">
                <view class="m-more-dot" />
                <view class="m-more-dot" />
                <view class="m-more-dot" />
              </view>
              <view v-if="dropKey === appointment.id" class="m-drop" @tap.stop>
                <view
                  v-if="canCancel && allowsFulfillmentActions(appointment)"
                  class="m-drop-item"
                  @tap="confirmCancel(appointment)"
                >取消预约</view>
                <view
                  v-if="canAbsent && allowsFulfillmentActions(appointment)"
                  class="m-drop-item"
                  @tap="confirmAbsent(appointment)"
                >旷课</view>
                <view v-if="appointment.status === 'confirmed'" class="m-drop-item" @tap.stop="openRetime(appointment)">修改预约</view>
                <view v-if="canNotes" class="m-drop-item" @tap.stop="openNotesPanel(appointment)">写备注</view>
              </view>
            </view>
            </view>
          </view>
        </template>
      </view>
      </view>
      <view v-else class="empty-tip">~ 还没有会员预约哦 ~</view>

      <view class="sign-hint">
        <u-icon name="bell" size="16" color="#C96B30" />
        <text>下课后，将由系统5分钟内自动签到</text>
      </view>
    </view>

    <view class="brand-footer">觅境约课</view>
  </view>

  <!-- 弹层置于 page-root 外，避免被列表层叠/点击穿透影响 -->
  <member-picker
    v-model:show="pickerOpen"
    :site-id="store.currentSiteId ?? null"
    @select="pickBookMember"
  />

  <u-popup :show="bookVisible" mode="bottom" round="20" :z-index="10075" @close="bookVisible = false">
      <view class="panel tall fixed-foot">
        <text class="panel-title">代约私教</text>

        <scroll-view scroll-y class="panel-body" :enhanced="true" :show-scrollbar="false">
        <view v-if="bookMember" class="panel-body-inner">
          <view class="picked-member">
            <text>会员：{{ bookMember?.name || bookMember?.memberNo }}</text>
            <text class="repick" @tap="repickMember">重选</text>
          </view>

          <view v-if="needSubject" class="chip-row subject-row">
              <view
                v-for="course in subjectCourses"
                :key="course.id"
                class="chip course-chip"
                :class="{ active: bookCourseId === course.id }"
                @tap="bookCourseId = course.id"
              >
                <text class="course-chip-name">{{ course.name }}</text>
                <text class="course-chip-duration">({{ course.durationMinutes }}分钟)</text>
              </view>
          </view>

          <!-- 滑动日期条（今天排第一个，向未来滚动 30 天；右上角月历选日） -->
          <view class="day-row">
            <scroll-view
              scroll-x
              class="day-scroll"
              :scroll-into-view="scrollTarget"
              :scroll-with-animation="true"
              :enhanced="true"
              :show-scrollbar="false"
            >
              <view class="day-strip">
                <view
                  v-for="day in dayList"
                  :key="day.iso"
                  :id="`day-${day.iso}`"
                  class="week-day"
                  :class="{ active: bookDate === day.iso }"
                  @tap="pickBookDate(day.iso)"
                >
                  <text class="week-name">{{ day.isToday ? "今天" : day.week }}</text>
                  <text class="week-num" :class="{ today: day.isToday }">{{ day.num }}</text>
                </view>
              </view>
            </scroll-view>
            <view class="day-calendar" @tap="calendarOpen = true">
              <u-icon name="calendar" size="19" color="#505050" />
            </view>
          </view>
          <view v-if="bookDate !== todayIsoDate()" class="week-tools">
            <text class="back-today" @tap="backToday">返回今天</text>
          </view>

          <!-- 当日时间槽（对标原版上午/下午/晚上分组，占用槽置灰不可选） -->
          <view v-if="slotsLoading" class="slots-hint">加载时间槽…</view>
          <template v-else-if="slotGroups.length">
            <view v-for="group in slotGroups" :key="group.label" class="slot-group">
              <text class="slot-group-label">{{ group.label }}</text>
              <view class="slot-grid">
                <view
                  v-for="slot in group.items"
                  :key="slot.start"
                  class="slot-chip-cell"
                >
                  <text
                    class="slot-chip"
                      :class="{
                        active: bookStart === slot.start,
                        disabled: !canPickBookSlot(slot),
                        warn: slot.groupOverlapWarn,
                      }"
                      @tap="pickSlot(slot)"
                    >{{ slot.groupOverlapWarn ? "团课重合" : slot.start }}</text>
                </view>
              </view>
            </view>
          </template>
          <view v-else class="slots-hint">~ 当日无可约时段 ~</view>
          <text v-if="profile?.bookingWindows?.length" class="window-hint">
            可约时段：{{ profile.bookingWindows.map((window) => `${window.start}~${window.end}`).join("、") }}
          </text>

          <!-- 会员留言（对标原版 remake 备注栏，150 字内） -->
          <view class="remark-box">
            <textarea
              v-model="bookRemark"
              class="remark-input"
              maxlength="150"
              placeholder="备注"
              placeholder-style="color:#c8c9cc"
            />
            <text class="remark-count">已写{{ bookRemark.length }}字/ 最多150字</text>
          </view>

        </view>
        </scroll-view>
        <button v-if="bookMember" class="panel-confirm" :disabled="acting || !bookStart" @tap="proceedToCardPicker">确 定</button>
      </view>
    </u-popup>

    <!-- 代约第 3 步：选卡（对标原版 selected-member-card：选定时间后弹卡面选择） -->
    <u-popup :show="cardPickerVisible" mode="bottom" round="20" :z-index="10075" @close="cardPickerVisible = false">
      <view class="panel">
        <text class="panel-title">代约私教</text>
        <view class="picked-summary">
          <text>会员：{{ bookMember?.name || bookMember?.memberNo }}</text>
          <text>时间：{{ bookDate }} {{ bookStart }}</text>
        </view>

        <member-card-picker
          v-model="bookCardId"
          :cards="bookCards"
          :loading="bookLoading"
        />

        <view class="dialog-actions">
          <button class="dialog-btn plain" :disabled="acting" @tap="backToTimePicker">上一步</button>
          <button class="dialog-btn primary" :disabled="acting || !bookCardId" @tap="submitBook">确认代约</button>
        </view>
      </view>
    </u-popup>

    <u-calendar
      :show="calendarOpen"
      mode="single"
      :default-date="bookDate"
      :min-date="calendarMinDate"
      :max-date="calendarMaxDate"
      :month-num="calendarMonthNum"
      :close-on-click-overlay="true"
      color="#22c788"
      :month-switch="true"
      @confirm="onCalendarConfirm"
      @close="calendarOpen = false"
    />

    <ff-bottom-sheet
      v-model:show="notesOpen"
      title="写备注"
      tips="仅教练或管理员可见，会员不会看到此备注"
      :height-rpx="1100"
      :confirm-disabled="notesSubmitting || !notesDraft.trim()"
      @close="closeNotesPanel"
      @confirm="submitNotes"
    >
      <view class="notes-main-box">
        <textarea
          v-model="notesDraft"
          class="notes-textarea"
          :maxlength="NOTES_MAX"
          placeholder="请填写"
          placeholder-style="color:#dadada"
        />
        <view class="notes-meta">
          <text class="notes-clear" @tap="notesDraft = ''">清除</text>
          <text class="notes-count">已写{{ notesDraft.length }}字/ 最多{{ NOTES_MAX }}字</text>
        </view>
      </view>
    </ff-bottom-sheet>

    <u-popup :show="retimeVisible" mode="bottom" round="20" :z-index="10075" @close="closeRetimePanel">
      <view class="panel tall fixed-foot">
        <text class="panel-title">修改预约时间</text>
        <scroll-view scroll-y class="panel-body" :enhanced="true" :show-scrollbar="false">
          <view class="panel-body-inner">
            <view v-if="needSubject" class="chip-row subject-row">
              <view
                v-for="course in subjectCourses"
                :key="course.id"
                class="chip course-chip"
                :class="{ active: retimeCourseId === course.id }"
                @tap="retimeCourseId = course.id"
              >
                <text class="course-chip-name">{{ course.name }}</text>
                <text class="course-chip-duration">({{ course.durationMinutes }}分钟)</text>
              </view>
            </view>

            <view class="day-row">
              <scroll-view
                scroll-x
                class="day-scroll"
                :scroll-into-view="retimeScrollTarget"
                :scroll-with-animation="true"
                :enhanced="true"
                :show-scrollbar="false"
              >
                <view class="day-strip">
                  <view
                    v-for="day in dayList"
                    :key="`retime-${day.iso}`"
                    :id="`retime-day-${day.iso}`"
                    class="week-day"
                    :class="{ active: retimeDate === day.iso }"
                    @tap="pickRetimeDate(day.iso)"
                  >
                    <text class="week-name">{{ day.isToday ? "今天" : day.week }}</text>
                    <text class="week-num" :class="{ today: day.isToday }">{{ day.num }}</text>
                  </view>
                </view>
              </scroll-view>
              <view class="day-calendar" @tap="retimeCalendarOpen = true">
                <u-icon name="calendar" size="19" color="#505050" />
              </view>
            </view>
            <view v-if="retimeDate !== todayIsoDate()" class="week-tools">
              <text class="back-today" @tap="backRetimeToday">返回今天</text>
            </view>

            <view v-if="retimeSlotsLoading" class="slots-hint">加载时间槽…</view>
            <template v-else-if="retimeSlotGroups.length">
              <view v-for="group in retimeSlotGroups" :key="`retime-${group.label}`" class="slot-group">
                <text class="slot-group-label">{{ group.label }}</text>
                <view class="slot-grid">
                  <view
                    v-for="slot in group.items"
                    :key="slot.start"
                    class="slot-chip-cell"
                  >
                    <text
                      class="slot-chip"
                      :class="{
                        active: retimeStart === slot.start,
                        disabled: !canPickBookSlot(slot) && !isRetimeCurrentSlot(slot.start),
                        current: isRetimeCurrentSlot(slot.start),
                        warn: slot.groupOverlapWarn,
                      }"
                      @tap="pickRetimeSlot(slot)"
                    >{{
                      isRetimeCurrentSlot(slot.start)
                        ? "当前预约"
                        : slot.groupOverlapWarn
                          ? "团课重合"
                          : slot.start
                    }}</text>
                  </view>
                </view>
              </view>
            </template>
            <view v-else class="slots-hint">~ 当日无可约时段 ~</view>
            <text v-if="profile?.bookingWindows?.length" class="window-hint">
              可约时段：{{ profile.bookingWindows.map((window) => `${window.start}~${window.end}`).join("、") }}
            </text>
          </view>
        </scroll-view>
        <button class="panel-confirm" :disabled="acting || !retimeStart" @tap="submitRetime">确　定</button>
      </view>
    </u-popup>

    <u-calendar
      :show="retimeCalendarOpen"
      mode="single"
      :default-date="retimeDate"
      :min-date="calendarMinDate"
      :max-date="calendarMaxDate"
      :month-num="calendarMonthNum"
      :close-on-click-overlay="true"
      color="#22c788"
      :month-switch="true"
      @confirm="onRetimeCalendarConfirm"
      @close="retimeCalendarOpen = false"
    />
</template>

<style scoped lang="scss">
.page-root { min-height: 100vh; background: #f5f5f5; padding-bottom: 40rpx; }

// —— 头部教练卡 ——
.coach-hero {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40rpx 28rpx 90rpx;
  background: linear-gradient(160deg, #33383f 0%, #181818 100%);
}
.hero-center { display: flex; flex-direction: column; align-items: center; }
.hero-photo-wrap { position: relative; }
.hero-tag {
  position: absolute; top: -14rpx; left: 50%; z-index: 2;
  padding: 2rpx 16rpx; background: linear-gradient(90deg, #f7c873, #e89b2c);
  border-radius: 999rpx; color: #7a4a00; font-size: 20rpx; white-space: nowrap;
  transform: translateX(-50%);
}
.hero-photo {
  display: flex; align-items: center; justify-content: center;
  width: 160rpx; height: 160rpx; border-radius: 24rpx;
  background: rgba(255, 255, 255, 0.18); color: #fff; font-size: 56rpx;
}
.hero-name { margin-top: 20rpx; color: #fff; font-size: 36rpx; font-weight: 600; }
.hero-specialty { overflow: hidden; max-width: 560rpx; margin-top: 10rpx; color: rgba(255, 255, 255, 0.7); font-size: 22rpx; white-space: nowrap; text-overflow: ellipsis; }
.hero-actions { position: absolute; top: 36rpx; right: 28rpx; display: flex; flex-direction: column; gap: 18rpx; }
.hero-action {
  width: auto; height: auto; padding: 8rpx 22rpx;
  border: 1rpx solid rgba(255, 255, 255, 0.7); border-radius: 999rpx;
  background: transparent; color: #fff; font-size: 22rpx; line-height: 1.6; margin: 0;
  text-align: center;
}
.share-btn::after { border: 0; }

// —— 列表卡（对标原版 list-wrap：白底通栏，与灰色 page 背景左右贴齐） ——
.list-card {
  position: relative;
  margin: -50rpx 0 0;
  padding: 26rpx 22rpx calc(26rpx + env(safe-area-inset-bottom));
  background: #fff;
  border-radius: 21rpx 21rpx 0 0;
}
.appointment-info { display: flex; align-items: center; justify-content: space-between; padding-bottom: 20rpx; border-bottom: 1rpx solid #f0f0f0; }
.booked-text { color: #181818; font-size: 30rpx; font-weight: 600; }
.book-btn {
  width: 136rpx; height: 62rpx; background: #22c788; border-radius: 31rpx;
  color: #fff; font-size: 26rpx; line-height: 62rpx; text-align: center;
}

.timeline-list { padding: 0 20rpx 20rpx 28rpx; }
.day-group { margin-top: 26rpx; }
.day-head {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 35rpx;
  margin-bottom: 22rpx;
  padding-left: 44rpx;
}
.day-marker-today {
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 35rpx;
  height: 35rpx;
  border-radius: 50%;
  background: #22c788;
  color: #fff;
  font-size: 22rpx;
}
.day-marker-dot {
  position: absolute;
  left: 7rpx;
  top: 50%;
  transform: translateY(-50%);
  width: 21rpx;
  height: 21rpx;
  border-radius: 50%;
  background: #22c788;
}
.day-label { color: #181818; font-size: 28rpx; }

.appt-block { margin-bottom: 26rpx; }
.slot-head {
  display: flex;
  flex-wrap: wrap;
  gap: 13rpx;
  margin-bottom: 10rpx;
  margin-left: 56rpx;
}
.slot-pill {
  padding: 0 22rpx;
  height: 39rpx;
  line-height: 39rpx;
  border-radius: 28rpx;
  background: #696b99;
  color: #fff;
  font-size: 25rpx;
  &.disabled { background: #dadada; }
}

.slot-block { margin: 18rpx 0 0; padding-left: 44rpx; }
.slot-time { padding: 4rpx 14rpx; background: #ecf8f3; border-radius: 8rpx; color: #22c788; font-size: 22rpx; &.done { background: #f0f0f0; color: #989898; } }
.slot-course { color: #505050; font-size: 24rpx; }

.m-row { position: relative; display: flex; gap: 12rpx; padding: 0 0 16rpx; border-bottom: 1rpx solid #f5f5f5; }
.m-ava {
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  width: 72rpx; height: 72rpx; border-radius: 50%; background: #f0f0f0;
  color: #505050; font-size: 28rpx;
  &.gray { filter: grayscale(100%); opacity: 0.7; }
}
.m-main { flex: 1; min-width: 0; }
.m-name { display: block; color: #181818; font-size: 28rpx; font-weight: 500; &.strike { color: #989898; text-decoration: line-through; } }
.m-date { display: block; margin-top: 8rpx; color: #989898; font-size: 22rpx; }
// 会员留言（对标 remake / remake-red）
.m-remake {
  margin-top: 15rpx;
  color: #989898;
  font-size: 20rpx;
  line-height: 26rpx;
}
.m-remake-text {
  color: #dc3c5c;
}
// 员工备注（对标 remark / remarkcontent）
.m-staff-remark {
  margin-top: 15rpx;
  color: #989898;
  font-size: 20rpx;
  line-height: 26rpx;
  &.after-remake {
    margin-top: 5rpx;
  }
}
.m-staff-remark-text {
  color: #c96a2f;
}
.m-right { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; max-width: 42%; }
.m-price { color: #181818; font-size: 26rpx; font-weight: 600; }
.m-card { margin-top: 8rpx; color: #989898; font-size: 20rpx; }
.m-status-row { display: flex; align-items: center; gap: 8rpx; margin-top: 8rpx; }
.m-operator { color: #bfbfbf; font-size: 20rpx; }
.m-status { font-size: 24rpx; font-weight: 500; }
.m-more {
  position: relative;
  flex-shrink: 0;
  align-self: flex-start;
  margin-top: 0;
  margin-left: 2rpx;
  margin-right: -4rpx;
}
// 竖排三点（对标原版 handle_mumber.png 8×47，比横排图标更窄，右侧留白还给正文）
.m-more-icon {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 7rpx;
  width: 28rpx;
  height: 50rpx;
  padding: 0 0 0 4rpx;
}
.m-more-dot {
  width: 8rpx;
  height: 8rpx;
  border-radius: 50%;
  background: #989898;
}
.m-drop {
  position: absolute; top: 52rpx; right: -8rpx; z-index: 20;
  min-width: 200rpx; padding: 8rpx 0; background: #fff; border-radius: 12rpx;
  box-shadow: 0 8rpx 30rpx rgba(0, 0, 0, 0.15);
}
.m-drop-item { padding: 20rpx 28rpx; color: #181818; font-size: 26rpx; white-space: nowrap; border-bottom: 1rpx solid #f5f5f5; &:last-child { border-bottom: none; } }

.empty-tip { padding: 60rpx 0; color: #bfbfbf; font-size: 26rpx; text-align: center; }
.sign-hint { display: flex; align-items: center; gap: 8rpx; padding: 22rpx 4rpx 6rpx; color: #c96b30; font-size: 22rpx; }
.brand-footer { margin: 70rpx 0 20rpx; color: #d8d8d8; font-size: 26rpx; letter-spacing: 6rpx; text-align: center; }

// —— 弹窗 ——
.panel { display: flex; flex-direction: column; gap: 24rpx; padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom)); &.tall { max-height: 82vh; }
  // 固定底栏弹窗（对标原版 ff-popup 固定高度：内容滚动 + 确定按钮钉底）
  &.fixed-foot { height: 82vh; }
}
.panel-body { flex: 1; min-height: 0; }
.panel-body-inner { display: flex; flex-direction: column; gap: 24rpx; padding: 0 4rpx; box-sizing: border-box; }
.subject-row {
  flex: none;
  justify-content: center;
  gap: 26rpx;
  padding: 0 22rpx;
  box-sizing: border-box;
}
.panel-title { font-size: 32rpx; font-weight: 600; text-align: center; color: #181818; }
.notes-main-box { padding: 8rpx 0 0; }
.notes-textarea {
  display: block;
  box-sizing: border-box;
  width: 100%;
  height: 620rpx;
  padding: 14rpx 28rpx;
  color: #181818;
  font-size: 28rpx;
  line-height: 42rpx;
  background: #fff;
  border: 1rpx solid #dadada;
  border-radius: 22rpx;
}
.notes-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 8rpx;
  padding: 10rpx 8rpx 0;
}
.notes-clear { padding: 10rpx 36rpx; color: #505050; font-size: 26rpx; }
.notes-count { color: #989898; font-size: 26rpx; }
.remark-box.member-remark .remark-input { height: 110rpx; }
.search-box { display: flex; align-items: center; gap: 12rpx; height: 69rpx; padding: 0 24rpx; background: #f5f5f5; border-radius: 35rpx; }
.search-input { flex: 1; font-size: 26rpx; }
.search-do { flex-shrink: 0; color: #d9a400; font-size: 26rpx; }
.pick-list { max-height: 46vh; &.short { max-height: 30vh; } }
.pick-row { display: flex; align-items: center; gap: 16rpx; padding: 20rpx 4rpx; border-bottom: 1rpx solid #f5f5f5; }
.picked-member { display: flex; align-items: center; justify-content: space-between; padding: 16rpx 20rpx; background: #f5f5f5; border-radius: 12rpx; color: #181818; font-size: 26rpx; }
.repick { color: #d9a400; font-size: 24rpx; }
.field-row { display: flex; align-items: center; gap: 14rpx; }
.field-label { flex-shrink: 0; width: 110rpx; color: #181818; font-size: 28rpx; }
.chip-row { display: flex; flex-wrap: wrap; gap: 14rpx; }
.chip {
  display: inline-block; padding: 12rpx 26rpx; background: #f5f5f5; border-radius: 999rpx;
  color: #505050; font-size: 26rpx;
  &.active { background: rgba(251, 209, 40, 0.18); color: #d9a400; }
}
.window-hint {
  color: #989898;
  font-size: 22rpx;
  padding: 0 22rpx;
  box-sizing: border-box;
}

// —— 滑动日期条（对标原版 week-calendar：今天排第一个，横向滑动） ——
.day-row { display: flex; align-items: center; gap: 12rpx; }
.day-scroll { flex: 1; white-space: nowrap; width: 0; }
.day-strip { display: inline-flex; gap: 8rpx; }
.week-day {
  display: inline-flex; flex-direction: column; align-items: center; gap: 6rpx;
  padding: 10rpx 0; width: 76rpx; flex-shrink: 0; border-radius: 14rpx;
  &.active { background: #ecf8f3; }
}
.week-name { color: #989898; font-size: 20rpx; }
.week-num {
  display: flex; align-items: center; justify-content: center;
  width: 48rpx; height: 48rpx; border-radius: 50%; color: #181818; font-size: 26rpx;
  &.today { color: #22c788; font-weight: 600; }
  .week-day.active & { background: #22c788; color: #fff; font-weight: 600; }
}
.day-calendar { display: flex; align-items: center; justify-content: center; width: 56rpx; height: 88rpx; flex-shrink: 0; }
.week-tools { display: flex; align-items: center; justify-content: space-between; }
.back-today { color: #22c788; font-size: 24rpx; }

// —— 课目（对标原版 course-item：胶囊形 + 名称/时长两行） ——
.course-chip {
  display: flex; flex-direction: column; align-items: center; gap: 6rpx;
  padding: 14rpx 26rpx; border-radius: 50rpx; font-size: 25rpx;
  background: #f5f5f5; border: 1rpx solid #f5f5f5; color: #7e7e7e;
  &.active { background: #ecf8f3; border-color: #22c788; color: #22c788; font-weight: 500; }
}
.course-chip-name, .course-chip-duration { line-height: 25rpx; }

// —— 备注栏（对标原版 remake） ——
.remark-box {
  position: relative; margin-top: 8rpx; padding: 16rpx 20rpx 40rpx;
  background: #f7f7f7; border-radius: 12rpx;
}
.remark-input { width: 100%; height: 110rpx; color: #181818; font-size: 26rpx; }
.remark-count { position: absolute; right: 20rpx; bottom: 12rpx; color: #c8c9cc; font-size: 20rpx; }

// —— 选卡弹窗（第二步） ——
.picked-summary {
  display: flex; flex-direction: column; gap: 8rpx; padding: 16rpx 20rpx;
  background: #f7f7f7; border-radius: 12rpx; color: #505050; font-size: 26rpx;
}
.dialog-actions { display: flex; gap: 20rpx; margin-top: 16rpx; }
.dialog-btn {
  flex: 1; height: 83rpx; line-height: 83rpx; border-radius: 42rpx; font-size: 30rpx; font-weight: 500;
  &.plain { background: #f5f5f5; color: #505050; }
  &.primary { background: #fbd128; color: #181818; }
  &[disabled] { opacity: 0.6; }
}
.dialog-btn::after { border: 0; }

// —— 时间槽（代约等通用） ——
.slot-group { margin-top: 8rpx; }
.slot-group-label {
  display: block;
  margin-bottom: 12rpx;
  padding: 0 22rpx;
  color: #989898;
  font-size: 24rpx;
  box-sizing: border-box;
}
.slot-grid {
  display: flex;
  flex-wrap: wrap;
  padding: 0 22rpx;
  box-sizing: border-box;
}
.slot-chip-cell {
  display: flex;
  justify-content: center;
  width: 25%;
  margin-top: 14rpx;
}
.slot-chip {
  width: 144rpx;
  padding: 12rpx 0;
  text-align: center;
  background: #f5f5f5;
  border: 1rpx solid #f5f5f5;
  border-radius: 12rpx;
  color: #181818;
  font-size: 26rpx;
  box-sizing: border-box;
  &.active { background: #ecf8f3; border-color: #22c788; color: #22c788; }
  &.current {
    font-size: 22rpx;
    color: #c96a2f;
    border-color: #f0d4b8;
    background: #fff8f0;
  }
  &.warn {
    font-size: 22rpx;
    color: #c96a2f;
    border-color: #f5d0b0;
    background: #fffaf5;
  }
  &.active.warn { color: #22c788; border-color: #22c788; background: #ecf8f3; }
  &.active.current { color: #22c788; border-color: #22c788; background: #ecf8f3; }
  &.disabled {
    color: #dadada;
    background: #f8f8f9;
    border-color: #f8f8f9;
    text-decoration: line-through;
  }
}
.slots-hint { padding: 24rpx 0; color: #bfbfbf; font-size: 24rpx; text-align: center; }
.panel-confirm {
  height: 83rpx; margin-top: 6rpx; line-height: 83rpx; background: #fbd128;
  border-radius: 42rpx; color: #181818; font-size: 32rpx; font-weight: 500;

  &[disabled] { opacity: 0.6; background: #fbd128; color: #181818; }
}
.panel-confirm::after { border: 0; }
</style>
