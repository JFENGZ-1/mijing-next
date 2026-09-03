<script setup lang="ts">
// 课程/排课页 —— 对标原版 pagesCourse/index/index
// 结构：黄壳+白圆角主体 / tool-warp 四工具 / course-scroll 横向课表 / 全套弹窗
import { computed, nextTick, ref } from "vue";
import { onPageScroll, onShow } from "@dcloudio/uni-app";
import {
  batchCancelStaffScheduleSessions,
  batchCopyStaffScheduleSessions,
  cancelStaffScheduleSession,
  exportScheduleImage,
  fetchScheduleSessionColors,
  fetchStaffScheduleSessions,
  suspendStaffScheduleSession,
  unsuspendStaffScheduleSession,
  updateStaffScheduleSession,
} from "@/api/scheduling";
import { fetchStaffCourse, fetchStaffCourseCatalog, updateStaffCourse } from "@/api/catalog";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { ScheduleSession, ScheduleSessionColorItem } from "@/types/scheduling";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import { createCommandKey } from "@/utils/command-key";
import { todayIsoDate } from "@/utils/format";

const session = useSessionStore();
const checking = ref(true);
const pageLoading = ref(true);
const working = ref(false);

const canView = computed(() => session.can("schedule.session.read"));
const canWrite = computed(() => session.can("schedule.session.write"));
const canCopy = computed(() => session.can("schedule.batch.copy"));
const canCancelBatch = computed(() => session.can("schedule.batch.cancel"));

// ================= 日期工具 =================
function fmt(date: Date) {
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function mondayOf(iso: string) {
  const date = new Date(`${iso}T00:00:00`);
  const weekday = date.getDay();
  date.setDate(date.getDate() + (weekday === 0 ? -6 : 1 - weekday));
  return date;
}

function addDays(iso: string, days: number) {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  return fmt(date);
}

function diffDays(fromIso: string, toIso: string) {
  return Math.round(
    (new Date(`${toIso}T00:00:00`).getTime() - new Date(`${fromIso}T00:00:00`).getTime()) / 86_400_000,
  );
}

function hhmm(iso: string) {
  const idx = iso.indexOf("T");
  return idx > 0 ? iso.slice(idx + 1, idx + 6) : iso.slice(11, 16);
}

const WEEK_NAMES = ["周日", "周一", "周二", "周三", "周四", "周五", "周六"];
const thisMonday = computed(() => fmt(mondayOf(todayIsoDate())));
const thisSunday = computed(() => addDays(thisMonday.value, 6));
const monthEnd = computed(() => {
  const now = new Date();
  return fmt(new Date(now.getFullYear(), now.getMonth() + 1, 0));
});

// ================= 课表数据（原版 course-scroll：90 天） =================
const DAYS_BACK = 7;
const DAYS_TOTAL = 90;

interface DayColumn {
  value: string;
  dayName: string;
  weekName: string;
  list: ScheduleSession[];
}

const bannerList = ref<DayColumn[]>([]);
const todayIndex = ref(DAYS_BACK);
const gridFrom = computed(() => addDays(todayIsoDate(), -DAYS_BACK));
const gridTo = computed(() => addDays(todayIsoDate(), DAYS_TOTAL - DAYS_BACK - 1));

function buildColumns(): DayColumn[] {
  const today = todayIsoDate();
  const columns: DayColumn[] = [];
  for (let index = 0; index < DAYS_TOTAL; index++) {
    const value = addDays(today, index - DAYS_BACK);
    const date = new Date(`${value}T00:00:00`);
    columns.push({
      value,
      dayName: value.slice(5).replace("-", "-"),
      weekName: value === today ? "今天" : WEEK_NAMES[date.getDay()],
      list: [],
    });
  }
  return columns;
}

async function loadGrid() {
  if (!session.currentSiteId || !canView.value) {
    pageLoading.value = false;
    return;
  }
  try {
    const columns = buildColumns();
    const response = await fetchStaffScheduleSessions(
      session.currentSiteId,
      `${gridFrom.value}T00:00:00`,
      `${gridTo.value}T23:59:59`,
    );
    const byDate = new Map<string, ScheduleSession[]>();
    for (const item of response.items) {
      if (item.status === "cancelled") continue;
      const date = item.startsAt.slice(0, 10);
      const bucket = byDate.get(date) ?? [];
      bucket.push(item);
      byDate.set(date, bucket);
    }
    for (const column of columns) {
      column.list = byDate.get(column.value) ?? [];
    }
    todayIndex.value = DAYS_BACK;
    bannerList.value = columns;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "课表加载失败", icon: "none" });
    bannerList.value = buildColumns();
  } finally {
    pageLoading.value = false;
  }
}

// ================= 横向滚动 / 吸顶 / 返回今天（原版逻辑） =================
const boxWidthPx = uni.upx2px(222);
const mainScrollLeft = ref(0);
const headScrollLeft = ref(0);
const curLeft = ref(0);
const scrollLock = ref(0);
const showFixedHead = ref(false);
const gridTopPx = ref(0);
let lockTimer: ReturnType<typeof setTimeout> | null = null;

function locateToday() {
  const target = boxWidthPx * Math.max(0, todayIndex.value - 1);
  mainScrollLeft.value = 0;
  nextTick(() => {
    mainScrollLeft.value = target;
    headScrollLeft.value = target;
    curLeft.value = target;
  });
}

function onMainScroll(event: { detail: { scrollLeft: number } }) {
  if (scrollLock.value !== 0 && scrollLock.value !== 2) return;
  scrollLock.value = 2;
  curLeft.value = event.detail.scrollLeft || 0;
  headScrollLeft.value = event.detail.scrollLeft || 0;
  if (lockTimer) clearTimeout(lockTimer);
  lockTimer = setTimeout(() => (scrollLock.value = 0), 200);
}

function onHeadScroll(event: { detail: { scrollLeft: number } }) {
  if (scrollLock.value !== 0 && scrollLock.value !== 1) return;
  scrollLock.value = 1;
  curLeft.value = event.detail.scrollLeft || 0;
  mainScrollLeft.value = event.detail.scrollLeft || 0;
  if (lockTimer) clearTimeout(lockTimer);
  lockTimer = setTimeout(() => (scrollLock.value = 0), 200);
}

const showBackToday = computed(() => Math.abs(curLeft.value - boxWidthPx * Math.max(0, todayIndex.value - 1)) > boxWidthPx * 2);

function measureGridTop() {
  nextTick(() => {
    uni
      .createSelectorQuery()
      .select("#courseGrid")
      .boundingClientRect((rect) => {
        if (rect && !Array.isArray(rect)) gridTopPx.value = (rect.top ?? 0);
      })
      .exec();
  });
}

onPageScroll((event) => {
  showFixedHead.value = event.scrollTop > Math.max(60, gridTopPx.value);
});

// 新手提示（原版 tips：点击日期显示 3 秒）
const showTipsIndex = ref(-1);
let tipsTimer: ReturnType<typeof setTimeout> | null = null;

function handleShowTips(index: number) {
  if (tipsTimer) clearTimeout(tipsTimer);
  showTipsIndex.value = index;
  tipsTimer = setTimeout(() => (showTipsIndex.value = -1), 3000);
}

// ================= 课程目录（选课弹窗共用） =================
const courses = ref<CourseCatalogItem[]>([]);
const coursesLoaded = ref(false);

async function loadCourses() {
  if (!session.currentSiteId) return;
  try {
    const catalog = await fetchStaffCourseCatalog(session.currentSiteId, 1, 200, undefined, "group");
    courses.value = catalog.items;
    coursesLoaded.value = true;
  } catch {
    courses.value = [];
  }
}

const FACE_FALLBACK = "linear-gradient(135deg, #3f4756 0%, #23272f 100%)";

function cardFace(course?: { faceGradient?: string | null } | null) {
  return course?.faceGradient || FACE_FALLBACK;
}

function sessionFace(item?: ScheduleSession | null) {
  return item?.courseFaceGradient || FACE_FALLBACK;
}

function courseRuleLabel(course: CourseCatalogItem) {
  if (!course.maxCapacity && !course.minCapacity) return "不限制";
  const parts: string[] = [];
  if (course.maxCapacity) parts.push(`限${course.maxCapacity}人`);
  if (course.minCapacity && course.minCapacity > 0) parts.push(`满${course.minCapacity}人开课`);
  return parts.join(" · ") || "开课规则";
}

// ================= 选择课程弹窗（原版 select-courses） =================
type CoursePickMode = "add" | "change";
const coursePickVisible = ref(false);
const coursePickMode = ref<CoursePickMode>("add");
const courseKeyword = ref("");
const pendingDay = ref("");

const filteredCourses = computed(() => {
  const keyword = courseKeyword.value.trim();
  if (!keyword) return courses.value;
  return courses.value.filter((item) => item.name.includes(keyword));
});

async function openAddCourse(day: DayColumn) {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无排课权限", icon: "none" });
    return;
  }
  pendingDay.value = day.value;
  coursePickMode.value = "add";
  courseKeyword.value = "";
  coursePickVisible.value = true;
  if (!coursesLoaded.value) await loadCourses();
}

// ================= 选择时间弹窗（原版 select-time） =================
type TimePickMode = "create" | "retime";
const timePickVisible = ref(false);
const timePickMode = ref<TimePickMode>("create");
const pickerValue = ref<number[]>([10, 0, 0]);
const pendingCourse = ref<CourseCatalogItem | null>(null);
const HOURS = Array.from({ length: 24 }, (_, index) => index);
const MINUTES = Array.from({ length: 60 }, (_, index) => index);

function openTimeFor(course: CourseCatalogItem) {
  if (coursePickMode.value === "change") {
    void confirmChangeCourse(course);
    return;
  }
  pendingCourse.value = course;
  timePickMode.value = "create";
  pickerValue.value = [10, 0, 0];
  timePickVisible.value = true;
}

function onPickerChange(event: { detail: { value: number[] } }) {
  pickerValue.value = event.detail.value;
}

async function submitTime() {
  const hour = String(HOURS[pickerValue.value[0]] ?? 10).padStart(2, "0");
  const minute = String(MINUTES[pickerValue.value[2]] ?? 0).padStart(2, "0");
  if (timePickMode.value === "create") {
    await submitAddCourse(`${hour}:${minute}`);
  } else {
    await submitRetime(`${hour}:${minute}`);
  }
}

async function submitAddCourse(time: string) {
  const course = pendingCourse.value;
  if (!course || !session.currentSiteId || !pendingDay.value) return;
  timePickVisible.value = false;
  coursePickVisible.value = false;
  const url = `/subpackages/course/session-form?date=${encodeURIComponent(pendingDay.value)}&courseId=${course.id}&startTime=${encodeURIComponent(time)}`;
  await uni.navigateTo({ url });
}

// ================= 课程管理弹窗（原版 course-management） =================
const manageVisible = ref(false);
const current = ref<ScheduleSession | null>(null);

function openManagement(item: ScheduleSession) {
  current.value = item;
  manageVisible.value = true;
}

async function reloadCurrent(updated?: ScheduleSession) {
  if (updated) current.value = updated;
  await loadGrid();
  if (current.value) {
    for (const column of bannerList.value) {
      const hit = column.list.find((item) => item.id === current.value?.id);
      if (hit) {
        current.value = hit;
        break;
      }
    }
  }
}

function editSession() {
  if (!current.value) return;
  manageVisible.value = false;
  uni.navigateTo({ url: `/subpackages/course/session-form?id=${current.value.id}` });
}

function openAllArrange() {
  if (!current.value) return;
  manageVisible.value = false;
  uni.navigateTo({
    url: `/subpackages/course/timetable/all-course?courseId=${current.value.courseId}&name=${encodeURIComponent(current.value.courseName || "")}`,
  });
}

// —— 换课（原版：有预约先确认「课时费一致」） ——
const changeConfirmVisible = ref(false);
const pendingChangeCourse = ref<CourseCatalogItem | null>(null);

async function openChangeCourse() {
  coursePickMode.value = "change";
  courseKeyword.value = "";
  coursePickVisible.value = true;
  if (!coursesLoaded.value) await loadCourses();
}

async function confirmChangeCourse(course: CourseCatalogItem) {
  if (!current.value) return;
  if (course.id === current.value.courseId) {
    uni.showToast({ title: "已是当前课程", icon: "none" });
    return;
  }
  if ((current.value.bookedCount ?? 0) > 0) {
    pendingChangeCourse.value = course;
    changeConfirmVisible.value = true;
    return;
  }
  await executeChangeCourse(course);
}

function confirmChangeNow() {
  if (pendingChangeCourse.value) void executeChangeCourse(pendingChangeCourse.value);
}

async function executeChangeCourse(course: CourseCatalogItem) {
  if (!current.value || !session.currentSiteId) return;
  changeConfirmVisible.value = false;
  working.value = true;
  try {
    const updated = await updateStaffScheduleSession(session.currentSiteId, current.value.id, {
      version: current.value.version,
      courseId: course.id,
    });
    coursePickVisible.value = false;
    uni.showToast({ title: "换课成功", icon: "none" });
    await reloadCurrent(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "换课失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 换老师（原版 subject-trainer） ——
const coachVisible = ref(false);
const coaches = ref<StaffDirectoryListItem[]>([]);

async function openReplaceCoach() {
  if (!session.currentSiteId) return;
  coachVisible.value = true;
  if (!coaches.value.length && session.can("staff.directory.read")) {
    try {
      const response = await fetchStaffDirectory(session.currentSiteId);
      coaches.value = response.items.filter((item) => {
        const capabilities = item.capabilities ?? [];
        return capabilities.length === 0 || capabilities.includes("coach");
      });
    } catch {
      coaches.value = [];
    }
  }
}

async function pickCoach(coach: StaffDirectoryListItem) {
  if (!current.value || !session.currentSiteId) return;
  if (coach.id === current.value.coachStaffId) {
    coachVisible.value = false;
    return;
  }
  working.value = true;
  try {
    const updated = await updateStaffScheduleSession(session.currentSiteId, current.value.id, {
      version: current.value.version,
      coachStaffId: coach.id,
    });
    coachVisible.value = false;
    uni.showToast({ title: "更换成功", icon: "none" });
    await reloadCurrent(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "更换失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 修改时间 ——
function openRetime() {
  if (!current.value) return;
  const start = hhmm(current.value.startsAt);
  const [hour, minute] = start.split(":").map((part) => Number.parseInt(part, 10));
  pickerValue.value = [Number.isFinite(hour) ? hour : 10, 0, Number.isFinite(minute) ? minute : 0];
  timePickMode.value = "retime";
  timePickVisible.value = true;
}

async function submitRetime(time: string) {
  if (!current.value || !session.currentSiteId) return;
  working.value = true;
  try {
    const date = current.value.startsAt.slice(0, 10);
    const startDate = new Date(`${date}T${time}:00`);
    const duration = new Date(current.value.endsAt).getTime() - new Date(current.value.startsAt).getTime();
    const endDate = new Date(startDate.getTime() + duration);
    const endsAt = `${fmt(endDate)}T${String(endDate.getHours()).padStart(2, "0")}:${String(endDate.getMinutes()).padStart(2, "0")}:00`;
    const updated = await updateStaffScheduleSession(session.currentSiteId, current.value.id, {
      version: current.value.version,
      startsAt: `${date}T${time}:00`,
      endsAt,
    });
    timePickVisible.value = false;
    uni.showToast({ title: "已修改", icon: "none" });
    await reloadCurrent(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "修改失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 停课 / 解除停课（原版 stop/start） ——
const stopConfirmVisible = ref(false);

async function confirmStop() {
  if (!current.value || !session.currentSiteId) return;
  stopConfirmVisible.value = false;
  working.value = true;
  try {
    const updated = await suspendStaffScheduleSession(session.currentSiteId, current.value.id);
    uni.showToast({ title: "已停课", icon: "none" });
    await reloadCurrent(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "停课失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

async function startSession() {
  if (!current.value || !session.currentSiteId) return;
  working.value = true;
  try {
    const updated = await unsuspendStaffScheduleSession(session.currentSiteId, current.value.id);
    uni.showToast({ title: "已解除停课", icon: "none" });
    await reloadCurrent(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "操作失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 删除（原版 del：有预约 → 未删除弹窗） ——
const delConfirmVisible = ref(false);
const terminateVisible = ref(false);

async function confirmDelete() {
  if (!current.value || !session.currentSiteId) return;
  delConfirmVisible.value = false;
  working.value = true;
  try {
    await cancelStaffScheduleSession(session.currentSiteId, current.value.id);
    manageVisible.value = false;
    uni.showToast({ title: "已删除", icon: "none" });
    await loadGrid();
  } catch (error) {
    const message = error instanceof Error ? error.message : "";
    if (message.includes("已有会员预约")) {
      terminateVisible.value = true;
    } else {
      uni.showToast({ title: message || "删除失败", icon: "none" });
    }
  } finally {
    working.value = false;
  }
}

// —— 课表背景色（原版 select-backgroundcolor） ——
const bgColorVisible = ref(false);
const colorPalette = ref<ScheduleSessionColorItem[]>([]);
const bgActionId = ref<1 | 2>(1);
const changeColor = ref("");

async function openBgColor() {
  if (!session.currentSiteId) return;
  bgActionId.value = 1;
  changeColor.value = "";
  bgColorVisible.value = true;
  if (!colorPalette.value.length) {
    try {
      const response = await fetchScheduleSessionColors(session.currentSiteId);
      colorPalette.value = response.palette;
    } catch {
      colorPalette.value = [];
    }
  }
}

const effectiveColor = computed(() => current.value?.displayColor || "");

async function submitBgColor() {
  const target = current.value;
  if (!target || !session.currentSiteId) {
    bgColorVisible.value = false;
    return;
  }
  if (!changeColor.value || changeColor.value === effectiveColor.value) {
    bgColorVisible.value = false;
    return;
  }
  working.value = true;
  try {
    if (bgActionId.value === 2) {
      // 仅修改当前此排课的颜色
      const updated = await updateStaffScheduleSession(session.currentSiteId, target.id, {
        version: target.version,
        displayColor: changeColor.value,
      });
      await reloadCurrent(updated);
    } else {
      // 以后【该课程】都使用此颜色：写课程级颜色，并清除本节的单独覆盖
      const detail = await fetchStaffCourse(session.currentSiteId, target.courseId);
      await updateStaffCourse(session.currentSiteId, target.courseId, {
        version: detail.version ?? 1,
        courseType: detail.courseType,
        name: detail.name,
        durationMinutes: detail.durationMinutes,
        description: detail.description ?? null,
        difficulty: detail.difficulty ?? null,
        minCapacity: detail.minCapacity ?? null,
        maxCapacity: detail.maxCapacity ?? null,
        defaultRoomId: detail.defaultRoomId ?? null,
        coachStaffId: detail.coachStaffId ?? null,
        tags: detail.tags ?? [],
        sortOrder: detail.sortOrder ?? 0,
        faceStyle: detail.faceStyle ?? null,
        displayColor: changeColor.value,
      });
      if (target.displayColor && target.displayColor !== target.courseDisplayColor) {
        const updated = await updateStaffScheduleSession(session.currentSiteId, target.id, {
          version: target.version,
          displayColor: null,
        });
        await reloadCurrent(updated);
      } else {
        await reloadCurrent();
      }
    }
    bgColorVisible.value = false;
    uni.showToast({ title: "更换成功", icon: "none" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "更换失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// ================= 复制课表（原版 copy-timetable） =================
type CopyQuick = "next-week" | "month-end" | "last-week" | "custom";
const copyVisible = ref(false);
const copyQuick = ref<CopyQuick>("next-week");
const copySrcFrom = ref(thisMonday.value);
const copySrcTo = ref(thisSunday.value);
const copyTargetFrom = ref(addDays(thisMonday.value, 7));
const copyConfirmVisible = ref(false);
const copyTargetExisting = ref(0);
const copyAckDate = ref(false);
const copyClearExisting = ref(false);

const COPY_QUICKS: { key: CopyQuick; label: string }[] = [
  { key: "next-week", label: "本周到下周" },
  { key: "month-end", label: "本周到本月月底" },
  { key: "last-week", label: "上周到本周" },
  { key: "custom", label: "自定义时段" },
];

function applyCopyQuick(key: CopyQuick) {
  copyQuick.value = key;
  if (key === "next-week" || key === "month-end") {
    copySrcFrom.value = thisMonday.value;
    copySrcTo.value = thisSunday.value;
    copyTargetFrom.value = addDays(thisMonday.value, 7);
  } else if (key === "last-week") {
    copySrcFrom.value = addDays(thisMonday.value, -7);
    copySrcTo.value = addDays(thisMonday.value, -1);
    copyTargetFrom.value = thisMonday.value;
  }
}

function openCopy() {
  if (!canCopy.value) {
    uni.showToast({ title: "暂无复制课表权限", icon: "none" });
    return;
  }
  applyCopyQuick("next-week");
  copyVisible.value = true;
}

function copyTargetRange(): { from: string; to: string; rounds: number; spanDays: number } {
  const spanDays = diffDays(copySrcFrom.value, copySrcTo.value) + 1;
  if (copyQuick.value === "month-end") {
    let rounds = 0;
    while (
      diffDays(todayIsoDate(), addDays(copyTargetFrom.value, rounds * spanDays)) >= 0
      && addDays(copyTargetFrom.value, rounds * spanDays) <= monthEnd.value
    ) {
      rounds++;
      if (rounds > 8) break;
    }
    rounds = Math.max(1, rounds);
    return { from: copyTargetFrom.value, to: addDays(copyTargetFrom.value, rounds * spanDays - 1), rounds, spanDays };
  }
  return { from: copyTargetFrom.value, to: addDays(copyTargetFrom.value, spanDays - 1), rounds: 1, spanDays };
}

async function sessionsBetween(fromIso: string, toIso: string): Promise<ScheduleSession[]> {
  if (!session.currentSiteId) return [];
  const response = await fetchStaffScheduleSessions(
    session.currentSiteId,
    `${fromIso}T00:00:00`,
    `${toIso}T23:59:59`,
  );
  return response.items.filter((item) => item.status !== "cancelled");
}

async function precheckCopy() {
  if (copySrcFrom.value > copySrcTo.value) {
    uni.showToast({ title: "来源开始日期不能晚于结束日期", icon: "none" });
    return;
  }
  working.value = true;
  try {
    const target = copyTargetRange();
    const existing = await sessionsBetween(target.from, target.to);
    copyTargetExisting.value = existing.length;
    copyAckDate.value = false;
    copyClearExisting.value = false;
    copyVisible.value = false;
    copyConfirmVisible.value = true;
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "检查失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

const failVisible = ref(false);
const failTitle = ref("");
const failReason = ref("");
const failMethod = ref("");

function showFail(title: string, reason: string, method: string) {
  failTitle.value = title;
  failReason.value = reason;
  failMethod.value = method;
  failVisible.value = true;
}

async function executeCopy() {
  if (!copyAckDate.value || !session.currentSiteId) return;
  copyConfirmVisible.value = false;
  working.value = true;
  try {
    const target = copyTargetRange();
    if (copyClearExisting.value && copyTargetExisting.value > 0) {
      const targetSessions = await sessionsBetween(target.from, target.to);
      if (targetSessions.length) {
        const result = await batchCancelStaffScheduleSessions(session.currentSiteId, {
          commandKey: createCommandKey(),
          sessionIds: targetSessions.map((item) => item.id),
          reason: "复制课表前清除原有排课",
        });
        if (result.failed.length > 0) {
          showFail(
            "注意，未执行复制！",
            `原因：目标时段存在 ${result.failed.length} 节「已有会员预约」的课。`,
            "方法：为避免操作失误造成大量的退课，请您检查该时段的排课，手动取消会员的约课后再进行覆盖。",
          );
          return;
        }
      }
    }
    const baseOffset = diffDays(copySrcFrom.value, copyTargetFrom.value);
    let created = 0;
    for (let round = 0; round < target.rounds; round++) {
      const result = await batchCopyStaffScheduleSessions(session.currentSiteId, {
        commandKey: createCommandKey(),
        sourceFrom: `${copySrcFrom.value}T00:00:00`,
        sourceTo: `${copySrcTo.value}T23:59:59`,
        targetFrom: `${addDays(copyTargetFrom.value, round * target.spanDays)}T00:00:00`,
        dayOffset: baseOffset + round * target.spanDays,
      });
      created += result.createdSessionIds.length;
    }
    uni.showToast({ title: `已复制 ${created} 节课`, icon: "success" });
    await loadGrid();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "复制失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// ================= 批量清除（原版 clear-timetable） =================
type ClearQuick = "this-week" | "this-month" | "custom";
const clearVisible = ref(false);
const clearQuick = ref<ClearQuick>("this-week");
const clearFrom = ref(thisMonday.value);
const clearTo = ref(thisSunday.value);
const clearConfirmVisible = ref(false);
const clearAck = ref(false);

const CLEAR_QUICKS: { key: ClearQuick; label: string }[] = [
  { key: "this-week", label: "清除本周" },
  { key: "this-month", label: "清除本月" },
  { key: "custom", label: "自定义时段" },
];

function applyClearQuick(key: ClearQuick) {
  clearQuick.value = key;
  if (key === "this-week") {
    clearFrom.value = thisMonday.value;
    clearTo.value = thisSunday.value;
  } else if (key === "this-month") {
    clearFrom.value = todayIsoDate().slice(0, 8) + "01";
    clearTo.value = monthEnd.value;
  }
}

function openClear() {
  if (!canCancelBatch.value) {
    uni.showToast({ title: "暂无批量清除权限", icon: "none" });
    return;
  }
  applyClearQuick("this-week");
  clearVisible.value = true;
}

function precheckClear() {
  if (clearFrom.value > clearTo.value) {
    uni.showToast({ title: "开始日期不能晚于结束日期", icon: "none" });
    return;
  }
  clearAck.value = false;
  clearVisible.value = false;
  clearConfirmVisible.value = true;
}

async function executeClear() {
  if (!clearAck.value || !session.currentSiteId) return;
  clearConfirmVisible.value = false;
  working.value = true;
  try {
    const sessions_ = await sessionsBetween(clearFrom.value, clearTo.value);
    if (!sessions_.length) {
      uni.showToast({ title: "该时段没有排课", icon: "none" });
      return;
    }
    const result = await batchCancelStaffScheduleSessions(session.currentSiteId, {
      commandKey: createCommandKey(),
      sessionIds: sessions_.map((item) => item.id),
      reason: "批量清除课表",
    });
    if (result.failed.length > 0) {
      showFail(
        "注意，未全部清除！",
        `已清除 ${result.succeededSessionIds.length} 节；${result.failed.length} 节因存在「已有会员预约」未清除。`,
        "方法：为避免操作失误造成大量的退课，请您检查该时段的排课，手动取消会员的约课后再进行清除。",
      );
      await loadGrid();
      return;
    }
    uni.showToast({ title: `已清除 ${result.succeededSessionIds.length} 节课`, icon: "success" });
    await loadGrid();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "清除失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// ================= 下载课表（原版 download-timetable） =================
type DownloadQuick = "this-week" | "next-week" | "custom";
const downloadVisible = ref(false);
const downloadQuick = ref<DownloadQuick>("this-week");
const downloadFrom = ref(thisMonday.value);
const downloadTo = ref(thisSunday.value);

const DOWNLOAD_QUICKS: { key: DownloadQuick; label: string }[] = [
  { key: "this-week", label: "本周" },
  { key: "next-week", label: "下周" },
  { key: "custom", label: "自定义时段" },
];

function applyDownloadQuick(key: DownloadQuick) {
  downloadQuick.value = key;
  if (key === "this-week") {
    downloadFrom.value = thisMonday.value;
    downloadTo.value = thisSunday.value;
  } else if (key === "next-week") {
    downloadFrom.value = addDays(thisMonday.value, 7);
    downloadTo.value = addDays(thisMonday.value, 13);
  }
}

function openDownload() {
  applyDownloadQuick("this-week");
  downloadVisible.value = true;
}

function openDisplayConfig() {
  downloadVisible.value = false;
  uni.navigateTo({ url: "/subpackages/course/timetable/display-config" });
}

async function executeDownload() {
  if (!session.currentSiteId) return;
  working.value = true;
  try {
    const result = await exportScheduleImage(session.currentSiteId, downloadFrom.value, downloadTo.value);
    downloadVisible.value = false;
    uni.showLoading({ title: "正在生成中，请稍候" });
    uni.downloadFile({
      url: result.imageUrl,
      success: (download) => {
        uni.saveImageToPhotosAlbum({
          filePath: download.tempFilePath,
          success: () => uni.showToast({ title: "已下载到手机相册", icon: "success" }),
          fail: () =>
            uni.showModal({
              title: "无法保存图片",
              content: "打开权限步骤：点击右上角「···」-设置-添加到相册-打开开关！",
              showCancel: false,
            }),
          complete: () => uni.hideLoading(),
        });
      },
      fail: () => {
        uni.hideLoading();
        uni.showToast({ title: "下载失败", icon: "none" });
      },
    });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "生成失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// ================= 其他入口 =================
function openAllCourse() {
  uni.navigateTo({ url: "/subpackages/course/timetable/all-course" });
}

function openBatchTools() {
  uni.navigateTo({ url: "/subpackages/course/batch-tools" });
}

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  const firstLoad = bannerList.value.length === 0;
  await loadGrid();
  if (firstLoad) {
    locateToday();
    measureGridTop();
  }
  void loadCourses();
});
</script>

<template>
  <u-loading-page :loading="checking || pageLoading" />
  <view v-if="!checking" class="page-shell">
    <view class="body-sheet">
      <u-empty v-if="!canView" mode="permission" text="暂无课表查看权限" />

      <template v-else>
        <!-- 工具行（原版 tool-warp 四项） -->
        <view class="tool-warp">
          <view class="tool-item" @tap="openCopy">
            <view class="tool-icon"><u-icon name="file-text" size="26" color="#d9a400" /></view>
            <text class="tool-text">复制课表</text>
          </view>
          <view class="tool-item" @tap="openClear">
            <view class="tool-icon"><u-icon name="trash" size="26" color="#d9a400" /></view>
            <text class="tool-text">批量清除</text>
          </view>
          <view class="tool-item" @tap="openDownload">
            <view class="tool-icon"><u-icon name="download" size="26" color="#d9a400" /></view>
            <text class="tool-text">下载课表</text>
          </view>
          <view class="tool-item" @tap="openAllCourse">
            <view class="tool-icon"><u-icon name="calendar" size="26" color="#d9a400" /></view>
            <text class="tool-text">全部课表</text>
          </view>
        </view>
        <view class="batch-link-row">
          <text class="batch-link" @tap="openBatchTools">批量停课 / 整体换课 →</text>
        </view>

        <!-- 吸顶日期行 -->
        <view v-if="showFixedHead" class="fixed-head">
          <scroll-view class="head-scroll" scroll-x :scroll-left="headScrollLeft" @scroll="onHeadScroll">
            <view class="head-row">
              <view class="grid-gap" />
              <view v-for="(day, index) in bannerList" :key="day.value" class="grid-col-head">
                <view class="date-chip2" :class="{ active: day.weekName === '今天' }" @tap="handleShowTips(index)">
                  <text class="date-top">{{ day.dayName }}</text>
                  <text class="date-bottom">{{ day.weekName }}</text>
                </view>
              </view>
              <view class="grid-gap" />
            </view>
          </scroll-view>
        </view>

        <!-- 返回今天（原版 back-today 绿标签） -->
        <view v-if="showBackToday" class="back-today" :class="{ fixed: showFixedHead }" @tap="locateToday">
          <text class="back-text">返回</text>
        </view>

        <!-- 课表网格（原版 course-scroll） -->
        <view id="courseGrid" class="course-grid">
          <scroll-view class="grid-scroll" scroll-x :scroll-left="mainScrollLeft" @scroll="onMainScroll">
            <view class="grid-inner">
              <view class="head-row">
                <view class="grid-gap" />
                <view v-for="(day, index) in bannerList" :key="day.value" class="grid-col-head">
                  <view class="date-chip2" :class="{ active: day.weekName === '今天' }" @tap="handleShowTips(index)">
                    <text class="date-top">{{ day.dayName }}</text>
                    <text class="date-bottom">{{ day.weekName }}</text>
                  </view>
                </view>
                <view class="grid-gap" />
              </view>
              <view class="content-row">
                <view class="grid-gap" />
                <view v-for="(day, index) in bannerList" :key="day.value" class="grid-col">
                  <view
                    v-for="item in day.list"
                    :key="item.id"
                    class="course-box"
                    :class="{ grey: item.status === 'suspended' }"
                    :style="{ background: sessionFace(item) }"
                    @tap="openManagement(item)"
                  >
                    <view class="box-time">
                      <text class="box-time-big">{{ hhmm(item.startsAt) }}</text>
                      <text class="box-time-small">~{{ hhmm(item.endsAt) }}</text>
                    </view>
                    <view class="box-title">{{ item.courseName || "未命名课程" }}</view>
                    <view class="box-user">
                      <view class="box-avatar">{{ (item.coachName || "教")[0] }}</view>
                      <text class="box-coach">{{ item.coachName || "" }}</text>
                    </view>
                    <view v-if="item.status === 'suspended'" class="box-stop">停</view>
                  </view>
                  <view class="add-btn" @tap="openAddCourse(day)">
                    <u-icon name="plus" size="15" color="#e98933" />
                    <text>添加课程</text>
                  </view>
                  <view v-if="showTipsIndex === index" class="tips-box">
                    <view class="tips">
                      <view>点击此按钮</view>
                      <view>即是排{{ day.weekName }}的课</view>
                    </view>
                  </view>
                </view>
                <view class="grid-gap" />
              </view>
            </view>
          </scroll-view>
        </view>

        <view class="brand-footer">觅境约课</view>
      </template>
    </view>

    <!-- ============ 选择课程弹窗（原版 select-courses）：可叠加在课程管理弹窗之上 ============ -->
    <u-popup :show="coursePickVisible" mode="bottom" round="20" :z-index="10080" @close="coursePickVisible = false">
      <view class="panel tall">
        <text class="panel-title">选择课程</text>
        <view class="search-box">
          <u-icon name="search" size="20" color="#FBD128" />
          <input v-model="courseKeyword" class="search-input" placeholder="团课名称" />
        </view>
        <text class="count-tip">共{{ filteredCourses.length }}个课程</text>
        <scroll-view scroll-y class="course-list">
          <view
            v-for="course in filteredCourses"
            :key="course.id"
            class="subject-card"
            :style="{ background: cardFace(course) }"
            @tap="openTimeFor(course)"
          >
            <view class="sc-left">
              <text class="sc-name">{{ course.name }}</text>
              <view class="sc-meta">
                <text v-if="course.defaultRoomName">{{ course.defaultRoomName }} | </text>
                <text>难度</text>
                <text class="sc-stars">{{ "★".repeat(course.difficulty || 1) }}</text>
              </view>
              <view class="sc-bottom">
                <view class="sc-avatar">{{ (course.coachName || "教")[0] }}</view>
                <text class="sc-coach">{{ course.coachName || "未绑定教练" }}</text>
                <text class="sc-rule">{{ courseRuleLabel(course) }}</text>
              </view>
            </view>
            <text class="sc-duration">{{ course.durationMinutes }}分钟</text>
          </view>
          <view v-if="!filteredCourses.length" class="empty-tip">没有找到相关课目</view>
        </scroll-view>
      </view>
    </u-popup>

    <!-- ============ 选择上课时间弹窗（原版 select-time）：可叠加在课程管理弹窗之上 ============ -->
    <u-popup :show="timePickVisible" mode="bottom" round="20" :z-index="10080" @close="timePickVisible = false">
      <view class="panel">
        <text class="panel-title">选择上课时间</text>
        <picker-view class="time-picker" indicator-class="time-indicator" :value="pickerValue" @change="onPickerChange">
          <picker-view-column>
            <view v-for="hour in HOURS" :key="hour" class="picker-item">{{ hour < 10 ? `0${hour}` : hour }}</view>
          </picker-view-column>
          <picker-view-column>
            <view class="picker-item">:</view>
          </picker-view-column>
          <picker-view-column>
            <view v-for="minute in MINUTES" :key="minute" class="picker-item">{{ minute < 10 ? `0${minute}` : minute }}</view>
          </picker-view-column>
        </picker-view>
        <button class="panel-confirm" @tap="submitTime">确　定</button>
      </view>
    </u-popup>

    <!-- ============ 课程管理弹窗（原版 course-management） ============ -->
    <u-popup :show="manageVisible" mode="bottom" round="20" @close="manageVisible = false">
      <view v-if="current" class="panel">
        <text class="panel-title">课程管理</text>
        <view class="subject-card" :style="{ background: sessionFace(current) }">
          <view class="sc-left">
            <text class="sc-name">{{ current.courseName || "未命名课程" }}</text>
            <view class="sc-meta">
              <text v-if="current.roomName">{{ current.roomName }} | </text>
              <text>预约 {{ current.bookedCount }}/{{ current.capacity }}</text>
            </view>
            <view class="sc-bottom">
              <view class="sc-avatar">{{ (current.coachName || "教")[0] }}</view>
              <text class="sc-coach">{{ current.coachName || "" }}</text>
              <text v-if="current.status === 'suspended'" class="sc-rule stopped">停课中</text>
            </view>
          </view>
          <view class="sc-time">
            <text class="sc-time-start">{{ hhmm(current.startsAt) }}</text>
            <text class="sc-time-end">{{ hhmm(current.endsAt) }}结束</text>
          </view>
        </view>
        <view v-if="effectiveColor" class="bg-bar" :style="{ backgroundColor: effectiveColor }" @tap="openBgColor" />

        <view class="manage-grid">
          <view class="manage-item" @tap="editSession">
            <view class="manage-icon"><u-icon name="edit-pen" size="24" color="#505050" /></view>
            <text class="manage-text">编辑</text>
          </view>
          <view class="manage-item" @tap="openChangeCourse">
            <view class="manage-icon"><u-icon name="reload" size="24" color="#505050" /></view>
            <text class="manage-text">换课</text>
          </view>
          <view class="manage-item" @tap="openReplaceCoach">
            <view class="manage-icon"><u-icon name="account" size="24" color="#505050" /></view>
            <text class="manage-text">换老师</text>
          </view>
          <view class="manage-item" @tap="openRetime">
            <view class="manage-icon"><u-icon name="clock" size="24" color="#505050" /></view>
            <text class="manage-text">修改时间</text>
          </view>
          <view class="manage-item" @tap="openBgColor">
            <view class="manage-icon"><u-icon name="photo" size="24" color="#505050" /></view>
            <text class="manage-text">课表背景色</text>
          </view>
          <view v-if="current.status === 'scheduled'" class="manage-item" @tap="stopConfirmVisible = true">
            <view class="manage-icon"><u-icon name="pause-circle" size="24" color="#505050" /></view>
            <text class="manage-text">停课</text>
          </view>
          <view v-else-if="current.status === 'suspended'" class="manage-item" @tap="startSession">
            <view class="manage-icon"><u-icon name="play-circle" size="24" color="#505050" /></view>
            <view class="manage-text">解除停课<view class="text-tip">停课中</view></view>
          </view>
          <view v-else class="manage-item">
            <view class="manage-icon"><u-icon name="pause-circle" size="24" color="#c8c8c8" /></view>
            <text class="manage-text disabled">停课</text>
          </view>
          <view class="manage-item" @tap="openAllArrange">
            <view class="manage-icon"><u-icon name="list" size="24" color="#505050" /></view>
            <text class="manage-text">全部排课</text>
          </view>
          <view class="manage-item" @tap="delConfirmVisible = true">
            <view class="manage-icon"><u-icon name="trash" size="24" color="#505050" /></view>
            <text class="manage-text">删除</text>
          </view>
        </view>
      </view>
    </u-popup>

    <!-- ============ 换老师弹窗 ============ -->
    <u-popup :show="coachVisible" mode="bottom" round="20" :z-index="10080" @close="coachVisible = false">
      <view class="panel tall">
        <text class="panel-title">更换老师</text>
        <scroll-view scroll-y class="course-list">
          <view v-for="coach in coaches" :key="coach.id" class="coach-row" @tap="pickCoach(coach)">
            <view class="coach-avatar">{{ (coach.displayName || "教")[0] }}</view>
            <text class="coach-name">{{ coach.displayName }}</text>
            <u-icon
              v-if="coach.id === current?.coachStaffId"
              name="checkmark-circle-fill"
              size="20"
              color="#ed920f"
            />
          </view>
          <view v-if="!coaches.length" class="empty-tip">未找到教练，请先在「员工管理」中添加教练</view>
        </scroll-view>
      </view>
    </u-popup>

    <!-- ============ 课表背景色弹窗（原版 select-backgroundcolor） ============ -->
    <u-popup :show="bgColorVisible" mode="bottom" round="20" :z-index="10080" @close="bgColorVisible = false">
      <view class="panel">
        <text class="panel-title">课表背景色</text>
        <view class="bg-tips">
          <u-icon name="bell" size="16" color="#989898" />
          <text class="bg-tips-text">
            仅用于生成【课程表】，一般同类型的课选用同一颜色，如普拉提类用红色、维密类用桔色，或要重点推荐的课用较突出的颜色
          </text>
        </view>
        <view class="bg-radio" @tap="bgActionId = 1">
          <u-icon
            :name="bgActionId === 1 ? 'checkmark-circle-fill' : 'checkmark-circle'"
            :color="bgActionId === 1 ? '#ed920f' : '#bfbfbf'"
            size="20"
          />
          <text class="bg-radio-text" :class="{ active: bgActionId === 1 }">
            以后【{{ current?.courseName || "该课程" }}】都使用此颜色
          </text>
        </view>
        <view class="bg-radio" @tap="bgActionId = 2">
          <u-icon
            :name="bgActionId === 2 ? 'checkmark-circle-fill' : 'checkmark-circle'"
            :color="bgActionId === 2 ? '#ed920f' : '#bfbfbf'"
            size="20"
          />
          <text class="bg-radio-text" :class="{ active: bgActionId === 2 }">仅修改当前此排课的颜色</text>
        </view>
        <view class="color-grid">
          <view
            v-for="color in colorPalette"
            :key="color.key"
            class="color-item"
            :style="{ backgroundColor: color.color }"
            @tap="changeColor = color.color"
          >
            <view v-if="color.color === effectiveColor" class="color-state" :class="{ dim: changeColor && changeColor !== color.color }">
              <u-icon name="checkmark-circle-fill" size="18" color="#fff" />
              <text class="color-state-text">使用中</text>
            </view>
            <view v-else-if="changeColor === color.color" class="color-state">
              <u-icon name="checkmark-circle-fill" size="18" color="#fff" />
              <text class="color-state-text">改用此颜色</text>
            </view>
          </view>
        </view>
        <button class="panel-confirm" @tap="submitBgColor">确　定</button>
      </view>
    </u-popup>

    <!-- ============ 确认弹窗组（原版 confirm-modal） ============ -->
    <!-- 删除确认 -->
    <u-popup :show="delConfirmVisible" mode="center" round="16" :z-index="10090" @close="delConfirmVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">注意，要删除这个课吗？</text>
        <view class="confirm-body">
          <text class="confirm-text">确定后将立即删除</text>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="delConfirmVisible = false">取消</button>
          <button class="btn-ok" @tap="confirmDelete">确定</button>
        </view>
      </view>
    </u-popup>

    <!-- 未删除（已有预约） -->
    <u-popup :show="terminateVisible" mode="center" round="16" :z-index="10090" @close="terminateVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">注意，未删除此课！</text>
        <view class="confirm-body">
          <view class="confirm-text">原因：该课<text class="inline-danger">已有一名或多名会员预约</text></view>
          <text class="confirm-text">方法：为避免操作失误造成退课，需手动取消会员的约课后再进行删除</text>
        </view>
        <view class="confirm-btns center">
          <button class="btn-ok" @tap="terminateVisible = false">知道了</button>
        </view>
      </view>
    </u-popup>

    <!-- 停课确认 -->
    <u-popup :show="stopConfirmVisible" mode="center" round="16" :z-index="10090" @close="stopConfirmVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">确认是否停课？</text>
        <view class="confirm-body">
          <text class="confirm-text">a、改为停课状态后，会员将不可预约</text>
          <view class="confirm-text">b、若<text class="inline-danger">已有会员预约将强制取消</text>，会员将收到停课通知</view>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="stopConfirmVisible = false">取消</button>
          <button class="btn-ok" @tap="confirmStop">确定</button>
        </view>
      </view>
    </u-popup>

    <!-- 换课有约确认 -->
    <u-popup :show="changeConfirmVisible" mode="center" round="16" :z-index="10090" @close="changeConfirmVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">已有会员约课，是否仍然换课？</text>
        <view class="confirm-body">
          <text class="confirm-text">提示：确保这两个课的（会员约课）课时费一致</text>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="changeConfirmVisible = false">取消</button>
          <button class="btn-ok" @tap="confirmChangeNow">确定</button>
        </view>
      </view>
    </u-popup>

    <!-- ============ 复制课表弹窗 ============ -->
    <u-popup :show="copyVisible" mode="bottom" round="20" @close="copyVisible = false">
      <view class="panel">
        <text class="panel-title">复制课表</text>
        <view class="quick-chips">
          <text
            v-for="quick in COPY_QUICKS"
            :key="quick.key"
            class="quick-chip"
            :class="{ active: copyQuick === quick.key }"
            @tap="applyCopyQuick(quick.key)"
          >
            {{ quick.label }}
          </text>
        </view>
        <view class="panel-inline">
          <text class="panel-text">将</text>
          <picker mode="date" :value="copySrcFrom" :disabled="copyQuick !== 'custom'" @change="copySrcFrom = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: copyQuick !== 'custom' }">{{ copySrcFrom }}</text>
          </picker>
          <text class="panel-text">至</text>
          <picker mode="date" :value="copySrcTo" :disabled="copyQuick !== 'custom'" @change="copySrcTo = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: copyQuick !== 'custom' }">{{ copySrcTo }}</text>
          </picker>
        </view>
        <view class="panel-inline">
          <text class="panel-text">粘贴到</text>
          <picker mode="date" :value="copyTargetFrom" :disabled="copyQuick !== 'custom'" @change="copyTargetFrom = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: copyQuick !== 'custom' }">{{ copyTargetFrom }}</text>
          </picker>
          <text class="panel-text">起</text>
        </view>
        <text class="panel-desc">将「{{ copySrcFrom }}」与「{{ copyTargetFrom }}」为对应起点，进行{{ copyQuick === "month-end" ? "循环" : "" }}复制</text>
        <button class="panel-confirm" @tap="precheckCopy">确 定</button>
      </view>
    </u-popup>

    <!-- 复制二次确认 -->
    <u-popup :show="copyConfirmVisible" mode="center" round="16" :z-index="10090" @close="copyConfirmVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">注意，确认日期是否正确？</text>
        <view class="confirm-body">
          <text class="confirm-text">将课表「{{ copySrcFrom }} ~ {{ copySrcTo }}」复制到「{{ copyTargetRange().from }} ~ {{ copyTargetRange().to }}」</text>
          <text v-if="copyTargetExisting > 0" class="confirm-text danger">
            严重警告！该时段内已有 {{ copyTargetExisting }} 节排课
          </text>
          <view class="ack-row" @tap="copyAckDate = !copyAckDate">
            <u-icon :name="copyAckDate ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="copyAckDate ? '#ed920f' : '#bfbfbf'" size="20" />
            <text class="ack-text">日期是正确的</text>
          </view>
          <view v-if="copyTargetExisting > 0" class="ack-row" @tap="copyClearExisting = !copyClearExisting">
            <u-icon :name="copyClearExisting ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="copyClearExisting ? '#ed920f' : '#bfbfbf'" size="20" />
            <text class="ack-text">清除该时段的原有排课</text>
          </view>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="copyConfirmVisible = false">取消</button>
          <button class="btn-ok" :class="{ grey: !copyAckDate }" @tap="executeCopy">确定</button>
        </view>
      </view>
    </u-popup>

    <!-- ============ 批量清除弹窗 ============ -->
    <u-popup :show="clearVisible" mode="bottom" round="20" @close="clearVisible = false">
      <view class="panel">
        <text class="panel-title">选择清除时段</text>
        <view class="quick-chips">
          <text
            v-for="quick in CLEAR_QUICKS"
            :key="quick.key"
            class="quick-chip"
            :class="{ active: clearQuick === quick.key }"
            @tap="applyClearQuick(quick.key)"
          >
            {{ quick.label }}
          </text>
        </view>
        <view class="panel-inline">
          <text class="panel-text">清除时段</text>
          <picker mode="date" :value="clearFrom" :disabled="clearQuick !== 'custom'" @change="clearFrom = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: clearQuick !== 'custom' }">{{ clearFrom }}</text>
          </picker>
          <text class="panel-text">至</text>
          <picker mode="date" :value="clearTo" :disabled="clearQuick !== 'custom'" @change="clearTo = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: clearQuick !== 'custom' }">{{ clearTo }}</text>
          </picker>
        </view>
        <button class="panel-confirm" @tap="precheckClear">确 定</button>
      </view>
    </u-popup>

    <!-- 清除警告确认 -->
    <u-popup :show="clearConfirmVisible" mode="center" round="16" :z-index="10090" @close="clearConfirmVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">警告，确认清除时段？</text>
        <view class="confirm-body">
          <text class="confirm-text danger">清除时段：{{ clearFrom }} 至 {{ clearTo }}</text>
          <view class="ack-row" @tap="clearAck = !clearAck">
            <u-icon :name="clearAck ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="clearAck ? '#ed920f' : '#bfbfbf'" size="20" />
            <text class="ack-text">我确认时段是对的</text>
          </view>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="clearConfirmVisible = false">取消</button>
          <button class="btn-ok" :class="{ grey: !clearAck }" @tap="executeClear">确定</button>
        </view>
      </view>
    </u-popup>

    <!-- ============ 下载课表弹窗 ============ -->
    <u-popup :show="downloadVisible" mode="bottom" round="20" @close="downloadVisible = false">
      <view class="panel">
        <text class="panel-title">下载课表</text>
        <view class="quick-chips">
          <text
            v-for="quick in DOWNLOAD_QUICKS"
            :key="quick.key"
            class="quick-chip"
            :class="{ active: downloadQuick === quick.key }"
            @tap="applyDownloadQuick(quick.key)"
          >
            {{ quick.label }}
          </text>
        </view>
        <view class="panel-inline">
          <text class="panel-text">下载时段</text>
          <picker mode="date" :value="downloadFrom" :disabled="downloadQuick !== 'custom'" @change="downloadFrom = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: downloadQuick !== 'custom' }">{{ downloadFrom }}</text>
          </picker>
          <text class="panel-text">至</text>
          <picker mode="date" :value="downloadTo" :disabled="downloadQuick !== 'custom'" @change="downloadTo = String($event.detail.value)">
            <text class="date-chip" :class="{ dim: downloadQuick !== 'custom' }">{{ downloadTo }}</text>
          </picker>
        </view>
        <view class="panel-links">
          <text class="panel-link" @tap="openDisplayConfig">修改标题</text>
          <text class="panel-link" @tap="openDisplayConfig">修改注意事项</text>
          <text class="panel-link" @tap="openDisplayConfig">高级设置</text>
        </view>
        <button class="panel-confirm" @tap="executeDownload">确 定</button>
      </view>
    </u-popup>

    <!-- 失败弹窗 -->
    <u-popup :show="failVisible" mode="center" round="16" :z-index="10090" @close="failVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">{{ failTitle }}</text>
        <view class="confirm-body">
          <text class="confirm-text danger">{{ failReason }}</text>
          <text class="confirm-text">{{ failMethod }}</text>
        </view>
        <view class="confirm-btns center">
          <button class="btn-ok" @tap="failVisible = false">知道了</button>
        </view>
      </view>
    </u-popup>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 0 60rpx;
  background: #fff;
  border-radius: 24rpx 24rpx 0 0;
  box-sizing: border-box;
}

// —— 工具行（原版 tool-warp） ——
.tool-warp {
  display: flex;
  justify-content: space-between;
  padding: 10rpx 36rpx 20rpx;
}

.tool-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14rpx;
}

.tool-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 96rpx;
  height: 96rpx;
  border-radius: 28rpx;
  background: rgba(251, 209, 40, 0.16);
}

.tool-text {
  color: $color-text;
  font-size: 26rpx;
}

.batch-link-row {
  padding: 0 36rpx 16rpx;
  text-align: right;
}

.batch-link {
  color: $color-text-tertiary;
  font-size: 22rpx;
}

// —— 课表网格（原版 course-scroll） ——
.course-grid {
  background: #fafafa;
}

.grid-scroll,
.head-scroll {
  width: 100%;
  white-space: nowrap;
}

.grid-inner {
  display: inline-block;
}

.head-row,
.content-row {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
}

.grid-gap {
  flex-shrink: 0;
  width: 28rpx;
  background: transparent;
}

.grid-col-head {
  flex-shrink: 0;
  width: 222rpx;
  background: #fff;
  padding-bottom: 8rpx;
}

.grid-col {
  position: relative;
  flex-shrink: 0;
  width: 222rpx;
  min-height: 900rpx;
  padding-bottom: 40rpx;
  background: #fafafa;
}

.date-chip2 {
  width: 90rpx;
  height: 120rpx;
  margin: 0 auto;
  padding-top: 25rpx;
  border-radius: 45rpx;
  box-sizing: border-box;
  color: #7e7e7e;
  text-align: center;

  &.active {
    background-color: #22c788;
    color: #fff;
  }
}

.date-top {
  display: block;
  font-size: 26rpx;
  line-height: 36rpx;
}

.date-bottom {
  display: block;
  font-size: 28rpx;
  line-height: 38rpx;
}

.fixed-head {
  position: fixed;
  top: 0;
  right: 0;
  left: 0;
  z-index: 77;
  padding: 16rpx 0 8rpx;
  background: #fff;
  box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
}

.back-today {
  position: absolute;
  left: 0;
  z-index: 88;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 50rpx;
  height: 79rpx;
  margin-top: 140rpx;
  background: #22c788;
  border-radius: 0 7rpx 7rpx 0;

  &.fixed {
    position: fixed;
    top: 160rpx;
    margin-top: 0;
  }
}

.back-text {
  width: 22rpx;
  color: #fff;
  font-size: 22rpx;
  line-height: 26rpx;
}

// —— 课卡（原版 course-box 200×205） ——
.course-box {
  position: relative;
  width: 200rpx;
  height: 205rpx;
  margin: 0 auto 15rpx;
  padding: 12rpx 0 0 14rpx;
  border-radius: 14rpx;
  box-sizing: border-box;
  overflow: hidden;

  &.grey {
    filter: grayscale(100%);
  }
}

.box-time {
  color: #fff;
  font-size: 21rpx;
}

.box-time-big {
  font-size: 42rpx;
  line-height: 50rpx;
}

.box-title {
  display: flex;
  align-items: center;
  height: 66rpx;
  color: #fff;
  font-size: 28rpx;
  font-weight: 500;
  line-height: 32rpx;
  overflow: hidden;
}

.box-user {
  display: flex;
  align-items: center;
}

.box-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 42rpx;
  height: 42rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.28);
  color: #fff;
  font-size: 22rpx;
}

.box-coach {
  margin-left: 9rpx;
  color: #fff;
  font-size: 21rpx;
}

.box-stop {
  position: absolute;
  top: 0;
  right: 11rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48rpx;
  height: 52rpx;
  background: $color-danger;
  border-radius: 0 0 10rpx 10rpx;
  color: #fff;
  font-size: 24rpx;
}

.add-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6rpx;
  width: 153rpx;
  height: 43rpx;
  margin: 20rpx auto 0;
  background: #fdf6de;
  border-radius: 21rpx;
  color: #e98933;
  font-size: 21rpx;
}

.tips-box {
  margin-top: 15rpx;
  padding: 0 8rpx;
}

.tips {
  padding: 15rpx;
  background: hsla(0, 0%, 9%, 0.85);
  border-radius: 80rpx;
  color: #f5f5f5;
  font-size: 22rpx;
  line-height: 36rpx;
  text-align: center;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 底部弹窗通用 ——
.panel {
  display: flex;
  flex-direction: column;
  gap: 26rpx;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));

  &.tall {
    max-height: 75vh;
  }
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 12rpx;
  height: 69rpx;
  padding: 0 24rpx;
  background: $color-page;
  border-radius: 35rpx;
}

.search-input {
  flex: 1;
  font-size: 26rpx;
}

.count-tip {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.course-list {
  max-height: 52vh;
}

// —— 弹窗内课程卡（原版 subject-card） ——
.subject-card {
  display: flex;
  justify-content: space-between;
  margin-bottom: 20rpx;
  padding: 24rpx;
  border-radius: 16rpx;
  color: #fff;
}

.sc-left {
  flex: 1;
  min-width: 0;
}

.sc-name {
  display: block;
  font-size: 32rpx;
  font-weight: 600;
}

.sc-meta {
  margin-top: 10rpx;
  font-size: 22rpx;
  opacity: 0.9;
}

.sc-stars {
  margin-left: 6rpx;
  color: #ffa800;
}

.sc-bottom {
  display: flex;
  align-items: center;
  gap: 12rpx;
  margin-top: 18rpx;
}

.sc-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52rpx;
  height: 52rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.28);
  font-size: 24rpx;
}

.sc-coach {
  font-size: 24rpx;
}

.sc-rule {
  padding: 6rpx 14rpx;
  background: rgba(255, 255, 255, 0.22);
  border-radius: 999rpx;
  font-size: 20rpx;

  &.stopped {
    background: $color-danger;
  }
}

.sc-duration {
  flex-shrink: 0;
  padding: 8rpx 0 0 18rpx;
  font-size: 28rpx;
  white-space: nowrap;
}

.sc-time {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  padding: 8rpx 0 0 18rpx;
}

.sc-time-start {
  font-size: 40rpx;
  font-weight: 600;
}

.sc-time-end {
  margin-top: 6rpx;
  font-size: 22rpx;
  opacity: 0.85;
}

.empty-tip {
  padding: 60rpx 0;
  color: $color-text-disabled;
  font-size: 26rpx;
  text-align: center;
}

// —— 时间 picker ——
.time-picker {
  height: 500rpx;
}

.picker-item {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 88rpx;
  font-size: 32rpx;
  color: $color-text;
}

// —— 课程管理 2×4 ——
.bg-bar {
  height: 14rpx;
  margin: -10rpx 0 6rpx;
  border-radius: 7rpx;
}

.manage-grid {
  display: flex;
  flex-wrap: wrap;
  padding-bottom: 10rpx;
}

.manage-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10rpx;
  width: 25%;
  margin-top: 30rpx;
}

.manage-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 88rpx;
  height: 88rpx;
  border-radius: 24rpx;
  background: $color-page;
}

.manage-text {
  position: relative;
  color: $color-text;
  font-size: 24rpx;
  text-align: center;

  &.disabled {
    color: #9a9a9a;
  }
}

.text-tip {
  position: absolute;
  top: -64rpx;
  right: -40rpx;
  padding: 4rpx 10rpx;
  background: $color-danger;
  border-radius: 8rpx 8rpx 8rpx 0;
  color: #fff;
  font-size: 18rpx;
  white-space: nowrap;
}

// —— 换老师 ——
.coach-row {
  display: flex;
  align-items: center;
  gap: 20rpx;
  padding: 24rpx 8rpx;
  border-bottom: 1rpx solid #f5f5f5;
}

.coach-avatar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 72rpx;
  height: 72rpx;
  border-radius: 20rpx;
  background: $color-page;
  color: $color-text;
  font-size: 28rpx;
}

.coach-name {
  flex: 1;
  color: $color-text;
  font-size: 28rpx;
}

// —— 背景色弹窗 ——
.bg-tips {
  display: flex;
  align-items: flex-start;
  gap: 8rpx;
}

.bg-tips-text {
  flex: 1;
  color: #989898;
  font-size: 22rpx;
  line-height: 34rpx;
}

.bg-radio {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.bg-radio-text {
  color: $color-text-secondary;
  font-size: 26rpx;

  &.active {
    color: $color-text;
    font-weight: 500;
  }
}

.color-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 18rpx;
}

.color-item {
  position: relative;
  width: 120rpx;
  height: 120rpx;
  border-radius: 14rpx;
  overflow: hidden;
}

.color-state {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4rpx;
  background: rgba(0, 0, 0, 0.25);

  &.dim {
    opacity: 0.45;
  }
}

.color-state-text {
  color: #fff;
  font-size: 18rpx;
}

// —— 快捷 chips / 行内 / 确认弹窗（同批量操作页风格） ——
.quick-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}

.quick-chip {
  padding: 14rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  color: $color-text-secondary;
  font-size: 26rpx;

  &.active {
    border-color: $color-brand-yellow;
    background: rgba(251, 209, 40, 0.14);
    color: #d9a400;
  }
}

.panel-inline {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14rpx;
}

.panel-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.date-chip {
  display: inline-block;
  padding: 14rpx 24rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 26rpx;

  &.dim {
    color: $color-text-tertiary;
  }
}

.panel-desc {
  color: $color-text-tertiary;
  font-size: 24rpx;
  line-height: 1.6;
}

.panel-links {
  display: flex;
  gap: 32rpx;
}

.panel-link {
  color: $color-info;
  font-size: 26rpx;
}

.panel-confirm {
  height: 83rpx;
  margin-top: 6rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.panel-confirm::after {
  border: 0;
}

.confirm-modal {
  display: flex;
  flex-direction: column;
  width: 640rpx;
  padding: 48rpx 40rpx 40rpx;
  box-sizing: border-box;
}

.confirm-title {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 500;
}

.confirm-body {
  margin-top: 18rpx;
}

.confirm-text {
  display: block;
  margin-top: 8rpx;
  color: #989898;
  font-size: 26rpx;
  line-height: 38rpx;

  &.danger {
    color: $color-danger;
    font-weight: 500;
  }
}

.inline-danger {
  color: $color-danger;
  font-weight: 500;
}

.ack-row {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-top: 20rpx;
}

.ack-text {
  color: $color-text;
  font-size: 28rpx;
}

.confirm-btns {
  display: flex;
  justify-content: flex-end;
  gap: 20rpx;
  margin-top: 30rpx;

  &.center {
    justify-content: center;
  }
}

.btn-cancel,
.btn-ok {
  width: 180rpx;
  height: 70rpx;
  margin: 0;
  line-height: 70rpx;
  border-radius: 35rpx;
  font-size: 28rpx;
}

.btn-cancel {
  background: $color-surface;
  border: 1rpx solid $color-border;
  color: $color-text-secondary;
}

.btn-ok {
  background: $color-brand-yellow;
  color: $color-text;

  &.grey {
    opacity: 0.4;
  }
}

.btn-cancel::after,
.btn-ok::after {
  border: 0;
}
</style>
