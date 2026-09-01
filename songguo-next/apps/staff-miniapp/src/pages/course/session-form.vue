<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { fetchAllStaffCourseCatalog, fetchStaffRoomCatalog } from "@/api/catalog";
import {
  fetchAllCompensationRoles,
  fetchStaffCompensationRoleAssignmentSets,
} from "@/api/compensation";
import {
  createStaffScheduleSession,
  fetchStaffScheduleSession,
  updateStaffScheduleSession,
} from "@/api/scheduling";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem, RoomCatalogItem } from "@/types/catalog";
import type { CompensationRole, StaffCompensationRoleAssignmentItem } from "@/types/compensation";
import type { ScheduleSession, ScheduleSessionKind } from "@/types/scheduling";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import { combineLocalDateTime, splitLocalDateTime, todayIsoDate } from "@/utils/format";
import { createCommandKey } from "@/utils/command-key";

const session = useSessionStore();
const sessionId = ref(0);
const checking = ref(true);
const loading = ref(false);
const saving = ref(false);
const loaded = ref(false);
const errorMessage = ref("");
const version = ref(1);
const bookedCount = ref(0);
const sessionStatus = ref<ScheduleSession["status"]>("scheduled");

const courses = ref<CourseCatalogItem[]>([]);
const rooms = ref<RoomCatalogItem[]>([]);
const coaches = ref<StaffDirectoryListItem[]>([]);
const staffMembers = ref<StaffDirectoryListItem[]>([]);
const deliveryRoles = ref<CompensationRole[]>([]);
const staffDeliveryRoleIds = ref<Record<number, number[]>>({});
const staffDeliveryRoleAssignments = ref<Record<number, StaffCompensationRoleAssignmentItem[]>>({});

interface DeliveryAssignmentDraft {
  staffId: number;
  compensationRoleId: number;
  allocationPercent: string;
}

const deliveryAssignments = ref<DeliveryAssignmentDraft[]>([]);

const form = reactive({
  courseId: 0,
  coachStaffId: 0,
  roomId: 0,
  date: todayIsoDate(),
  startTime: "10:00",
  endTime: "11:00",
  capacity: "12",
  sessionKind: "group" as ScheduleSessionKind,
});

const isEdit = computed(() => sessionId.value > 0);
const canWrite = computed(() => session.can("schedule.session.write"));
const canLoadCourses = computed(() => session.can("course-catalog.read"));
const canLoadRooms = computed(() => session.can("site.rooms.read"));
const canLoadCoaches = computed(() => session.can("staff.directory.read"));
const canReadDeliveryRoles = computed(() => session.can("compensation.role.read"));
// 有预约的排课允许编辑（员工排错课可修正），仅做细粒度限制：
// 课程不可在表单内更换（换课请走详情页「课程管理 → 换课」，带二次确认）；
// 容量不可小于已约人数（validateForm 校验 + 后端 SCHEDULE_SESSION_UPDATE_BLOCKED 兜底）。
const hasBookings = computed(() => isEdit.value && bookedCount.value > 0);
const canSave = computed(() => canWrite.value && (!isEdit.value || sessionStatus.value === "scheduled"));

const courseIndex = computed(() => courses.value.findIndex((item) => item.id === form.courseId));
const coachIndex = computed(() => coaches.value.findIndex((item) => item.id === form.coachStaffId));
const roomIndex = computed(() => rooms.value.findIndex((item) => item.id === form.roomId));
const selectedCourse = computed(() => courses.value.find((item) => item.id === form.courseId) || null);
const isPrivateSession = computed(() => form.sessionKind === "private");

const courseLabels = computed(() => courses.value.map((item) => `${item.name}（${item.courseType === "private" ? "私教" : "团课"}）`));
const coachLabels = computed(() => coaches.value.map((item) => item.displayName));
const roomLabels = computed(() => ["不指定教室", ...rooms.value.map((item) => item.name)]);
const deliveryAllocationTotals = computed(() => deliveryAssignments.value.reduce<Record<number, number>>(
  (totals, assignment) => {
    totals[assignment.compensationRoleId] = (totals[assignment.compensationRoleId] ?? 0)
      + (Number(assignment.allocationPercent) || 0);
    return totals;
  },
  {},
));
const deliveryAllocationValid = computed(() => deliveryAssignments.value.length > 0
  && Object.values(deliveryAllocationTotals.value).every((total) => Math.round(total * 100) === 10000));
const deliveryAllocationSummary = computed(() => Object.entries(deliveryAllocationTotals.value)
  .map(([roleId, total]) => `${deliveryRoleName(Number(roleId))} ${total}%`)
  .join(" · "));
const deliveryStaffCandidates = computed(() => staffMembers.value.filter(
  (staff) => deliveryRoleIdsForDate(staff.id).some((roleId) =>
    deliveryRoles.value.some((role) => role.id === roleId),
  ),
));

function deliveryRoleIdsForDate(staffId: number) {
  const assignments = staffDeliveryRoleAssignments.value[staffId];
  if (!assignments) return staffDeliveryRoleIds.value[staffId] ?? [];
  return assignments
    .filter((assignment) => assignment.roleType === "delivery"
      && ["active", "archived"].includes(assignment.status ?? "active")
      && (!assignment.effectiveFrom || assignment.effectiveFrom <= form.date)
      && (!assignment.effectiveUntil || assignment.effectiveUntil >= form.date))
    .map((assignment) => assignment.roleId);
}

function courseLabel() {
  if (!form.courseId) return "请选择课程";
  return courseLabels.value[courseIndex.value] || "请选择课程";
}

function coachLabel() {
  if (!form.coachStaffId) return "请选择教练";
  return coachLabels.value[coachIndex.value] || "请选择教练";
}

function deliveryStaffName(staffId: number) {
  return staffMembers.value.find((staff) => staff.id === staffId)?.displayName || "请选择授课员工";
}

function availableDeliveryRoles(staffId: number) {
  const roleIds = deliveryRoleIdsForDate(staffId);
  return deliveryRoles.value.filter((role) => roleIds.includes(role.id));
}

function deliveryRoleName(roleId: number) {
  return deliveryRoles.value.find((role) => role.id === roleId)?.name || "请选择 A 角色";
}

function syncPrimaryCoach() {
  if (deliveryAssignments.value[0]?.staffId) {
    form.coachStaffId = deliveryAssignments.value[0].staffId;
  }
}

function ensureLegacyDeliveryAssignment() {
  if (!form.coachStaffId || deliveryAssignments.value.length) return;
  const role = availableDeliveryRoles(form.coachStaffId)[0];
  if (!role) return;
  deliveryAssignments.value = [{
    staffId: form.coachStaffId,
    compensationRoleId: role.id,
    allocationPercent: "100",
  }];
}

function addDeliveryAssignment() {
  const candidates = deliveryStaffCandidates.value;
  if (!deliveryRoles.value.length) {
    uni.showToast({ title: "请先在设置中心创建 A 类型业务角色", icon: "none" });
    return;
  }
  if (!candidates.length) {
    uni.showToast({ title: "请先为授课员工分配 A 类型业务角色", icon: "none" });
    return;
  }
  uni.showActionSheet({
    itemList: candidates.map((staff) => staff.displayName),
    success: ({ tapIndex }) => {
      const staff = candidates[tapIndex];
      const role = availableDeliveryRoles(staff.id)[0];
      const remaining = Math.max(0, 100 - (deliveryAllocationTotals.value[role.id] ?? 0));
      deliveryAssignments.value.push({
        staffId: staff.id,
        compensationRoleId: role.id,
        allocationPercent: String(deliveryAssignments.value.length ? remaining : 100),
      });
      syncPrimaryCoach();
    },
  });
}

function chooseDeliveryStaff(index: number) {
  const candidates = deliveryStaffCandidates.value;
  if (!candidates.length) return addDeliveryAssignment();
  uni.showActionSheet({
    itemList: candidates.map((staff) => staff.displayName),
    success: ({ tapIndex }) => {
      const staff = candidates[tapIndex];
      const current = deliveryAssignments.value[index];
      if (!current) return;
      current.staffId = staff.id;
      const validRoles = availableDeliveryRoles(staff.id);
      if (!validRoles.some((role) => role.id === current.compensationRoleId)) {
        current.compensationRoleId = validRoles[0]?.id ?? 0;
      }
      if (index === 0) syncPrimaryCoach();
    },
  });
}

function chooseDeliveryRole(index: number) {
  const assignment = deliveryAssignments.value[index];
  if (!assignment) return;
  const roles = availableDeliveryRoles(assignment.staffId);
  if (!roles.length) {
    uni.showToast({ title: "该员工尚未分配 A 类型业务角色", icon: "none" });
    return;
  }
  uni.showActionSheet({
    itemList: roles.map((role) => role.name),
    success: ({ tapIndex }) => { assignment.compensationRoleId = roles[tapIndex].id; },
  });
}

function removeDeliveryAssignment(index: number) {
  if (deliveryAssignments.value.length <= 1) {
    uni.showToast({ title: "排课必须保留至少一名实际授课人员", icon: "none" });
    return;
  }
  deliveryAssignments.value.splice(index, 1);
  syncPrimaryCoach();
}

function roomLabel() {
  if (!form.roomId) return "不指定教室";
  return rooms.value.find((item) => item.id === form.roomId)?.name || "不指定教室";
}

function isCoachCandidate(item: StaffDirectoryListItem) {
  if (item.status !== "active") return false;
  const capabilities = item.capabilities ?? [];
  return capabilities.length === 0 || capabilities.includes("coach");
}

function onCoachPickerUnavailable() {
  if (!canLoadCoaches.value) return;
  if (loading.value) {
    uni.showToast({ title: "教练列表加载中", icon: "none" });
    return;
  }
  uni.showToast({ title: "暂无可用教练，请先在员工设置中标记教练", icon: "none" });
}

function applyCourseDefaults(course: CourseCatalogItem) {
  form.sessionKind = course.courseType;
  if (course.maxCapacity) {
    form.capacity = String(course.maxCapacity);
  }
  if (course.coachStaffId) {
    form.coachStaffId = course.coachStaffId;
  }
  if (course.defaultRoomId) {
    form.roomId = course.defaultRoomId;
  } else if (course.courseType === "private") {
    form.roomId = 0;
  }
  if (course.durationMinutes > 0) {
    const [hour, minute] = form.startTime.split(":").map(Number);
    const end = new Date(2000, 0, 1, hour, minute, 0);
    end.setMinutes(end.getMinutes() + course.durationMinutes);
    form.endTime = `${String(end.getHours()).padStart(2, "0")}:${String(end.getMinutes()).padStart(2, "0")}`;
  }
}

function onCourseChange(event: { detail: { value: string | number } }) {
  const index = Number(event.detail.value);
  const course = courses.value[index];
  if (!course) return;
  form.courseId = course.id;
  applyCourseDefaults(course);
  if (deliveryAssignments.value.length) syncPrimaryCoach();
  else ensureLegacyDeliveryAssignment();
}

function onCoachChange(event: { detail: { value: string | number } }) {
  const coach = coaches.value[Number(event.detail.value)];
  if (!coach) return;
  const roles = availableDeliveryRoles(coach.id);
  if (deliveryAssignments.value.length && !roles.length) {
    uni.showToast({ title: "该教练尚未分配 A 类型业务角色", icon: "none" });
    return;
  }
  form.coachStaffId = coach?.id || 0;
  if (deliveryAssignments.value.length) {
    deliveryAssignments.value[0].staffId = coach.id;
    if (!roles.some((role) => role.id === deliveryAssignments.value[0].compensationRoleId)) {
      deliveryAssignments.value[0].compensationRoleId = roles[0]?.id ?? 0;
    }
  } else {
    ensureLegacyDeliveryAssignment();
  }
}

function onRoomChange(event: { detail: { value: string | number } }) {
  const index = Number(event.detail.value);
  form.roomId = index === 0 ? 0 : rooms.value[index - 1]?.id || 0;
}

function onDateChange(event: { detail: { value: string } }) {
  form.date = event.detail.value;
}

function onStartTimeChange(event: { detail: { value: string } }) {
  form.startTime = event.detail.value;
  const course = selectedCourse.value;
  if (!course?.durationMinutes) return;
  const [hour, minute] = form.startTime.split(":").map(Number);
  const end = new Date(2000, 0, 1, hour, minute, 0);
  end.setMinutes(end.getMinutes() + course.durationMinutes);
  form.endTime = `${String(end.getHours()).padStart(2, "0")}:${String(end.getMinutes()).padStart(2, "0")}`;
}

function onEndTimeChange(event: { detail: { value: string } }) {
  form.endTime = event.detail.value;
}

async function loadOptions() {
  if (!session.currentSiteId) return;
  const tasks: Promise<void>[] = [];

  if (canLoadCourses.value) {
    tasks.push(
      fetchAllStaffCourseCatalog(session.currentSiteId)
        .then((items) => {
          courses.value = items;
        })
        .catch(() => {
          courses.value = [];
        }),
    );
  }

  if (canLoadRooms.value) {
    tasks.push(
      fetchStaffRoomCatalog(session.currentSiteId)
        .then((response) => {
          rooms.value = response.items;
        })
        .catch(() => {
          rooms.value = [];
        }),
    );
  }

  if (canLoadCoaches.value) {
    tasks.push(
      fetchStaffDirectory(session.currentSiteId)
        .then((response) => {
          staffMembers.value = response.items.filter((item) => item.status === "active");
          coaches.value = staffMembers.value.filter(isCoachCandidate);
        })
        .catch(() => {
          staffMembers.value = [];
          coaches.value = [];
        }),
    );
  }

  if (canReadDeliveryRoles.value) tasks.push(
    fetchAllCompensationRoles(session.currentSiteId)
      .then((roles) => {
        deliveryRoles.value = roles.filter((role) => role.type === "delivery" && role.status === "active");
      })
      .catch(() => {
        deliveryRoles.value = [];
      }),
  );

  await Promise.all(tasks);

  if (staffMembers.value.length && deliveryRoles.value.length) {
    const assignments = await fetchStaffCompensationRoleAssignmentSets(
      session.currentSiteId,
      staffMembers.value.map((staff) => staff.id),
    );
    staffDeliveryRoleIds.value = Object.fromEntries(
      staffMembers.value.map((staff) => [staff.id, assignments.get(staff.id)?.roleIds ?? []]),
    );
    staffDeliveryRoleAssignments.value = Object.fromEntries(
      staffMembers.value.map((staff) => [staff.id, assignments.get(staff.id)?.items ?? []]),
    );
  } else {
    staffDeliveryRoleIds.value = {};
    staffDeliveryRoleAssignments.value = {};
  }
}

async function loadSession() {
  if (!session.currentSiteId || !sessionId.value) return;
  const detail = await fetchStaffScheduleSession(session.currentSiteId, sessionId.value);
  version.value = detail.version;
  bookedCount.value = detail.bookedCount;
  sessionStatus.value = detail.status;
  form.courseId = detail.courseId;
  form.coachStaffId = detail.coachStaffId;
  form.roomId = detail.roomId || 0;
  form.sessionKind = detail.sessionKind;
  form.capacity = String(detail.capacity);
  const start = splitLocalDateTime(detail.startsAt);
  const end = splitLocalDateTime(detail.endsAt);
  form.date = start.date;
  form.startTime = start.time;
  form.endTime = end.time;
  deliveryAssignments.value = (detail.deliveryAssignments ?? []).map((assignment) => ({
    staffId: assignment.staffId,
    compensationRoleId: assignment.compensationRoleId,
    allocationPercent: String(assignment.allocationBps / 100),
  }));
  ensureLegacyDeliveryAssignment();
}

async function load() {
  if (!canWrite.value || !session.currentSiteId) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    await loadOptions();
    if (isEdit.value) {
      await loadSession();
    } else if (courses.value.length) {
      const course = courses.value.find((item) => item.id === form.courseId) ?? courses.value[0];
      form.courseId = course.id;
      applyCourseDefaults(course);
      ensureLegacyDeliveryAssignment();
    }
    loaded.value = true;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "排课表单加载失败";
  } finally {
    loading.value = false;
  }
}

function deliveryPayload() {
  return deliveryAssignments.value.map((assignment, index) => ({
    staffId: assignment.staffId,
    compensationRoleId: assignment.compensationRoleId,
    allocationBps: Math.round(Number(assignment.allocationPercent) * 100),
    isPrimary: index === 0,
  }));
}

function validateForm() {
  if (!form.courseId) return "请选择课程";
  if (!form.coachStaffId) return "请选择教练";
  if (!form.date) return "请选择日期";
  if (!form.startTime || !form.endTime) return "请选择开始和结束时间";
  const capacity = Number(form.capacity);
  if (!Number.isFinite(capacity) || capacity < 1) return "请填写有效容量";
  if (hasBookings.value && capacity < bookedCount.value) {
    return `容量不能小于已预约人数（当前已约 ${bookedCount.value} 人）`;
  }
  if (form.startTime >= form.endTime) return "结束时间必须晚于开始时间";
  if (!isPrivateSession.value && !form.roomId && rooms.value.length) return "团课请选择教室";
  if (!deliveryAssignments.value.length) return "请至少配置一名实际授课人员及其 A 类型角色";
  const seen = new Set<string>();
  for (const assignment of deliveryAssignments.value) {
    if (!assignment.staffId || !assignment.compensationRoleId) return "请完整选择授课员工和 A 类型角色";
    if (!deliveryRoleIdsForDate(assignment.staffId).includes(assignment.compensationRoleId)) {
      return `${deliveryStaffName(assignment.staffId)} 在 ${form.date} 未处于所选 A 类型角色的有效任期`;
    }
    const percent = Number(assignment.allocationPercent);
    if (!Number.isFinite(percent) || percent <= 0 || percent > 100) return "授课分配比例需大于 0% 且不超过 100%";
    const key = `${assignment.staffId}:${assignment.compensationRoleId}`;
    if (seen.has(key)) return "同一授课员工与 A 角色不能重复";
    seen.add(key);
  }
  const payload = deliveryPayload();
  if (payload.filter((assignment) => assignment.isPrimary).length !== 1) return "实际授课人员必须且只能有一名主授课";
  const totals = payload.reduce<Record<number, number>>((result, assignment) => {
    result[assignment.compensationRoleId] = (result[assignment.compensationRoleId] ?? 0) + assignment.allocationBps;
    return result;
  }, {});
  const invalidRoleId = Object.keys(totals).find((roleId) => totals[Number(roleId)] !== 10000);
  if (invalidRoleId) return `${deliveryRoleName(Number(invalidRoleId))} 的分配比例合计必须为 100%`;
  syncPrimaryCoach();
  return "";
}

async function save() {
  if (!session.currentSiteId || !canSave.value) return;
  const validationError = validateForm();
  if (validationError) {
    errorMessage.value = validationError;
    return;
  }

  saving.value = true;
  errorMessage.value = "";
  const startsAt = combineLocalDateTime(form.date, form.startTime);
  const endsAt = combineLocalDateTime(form.date, form.endTime);
  const capacity = Number(form.capacity);
  const roomId = form.roomId > 0 ? form.roomId : null;
  const assignments = deliveryPayload();

  try {
    const wasEdit = isEdit.value;
    const saved = wasEdit
      ? await updateStaffScheduleSession(session.currentSiteId, sessionId.value, {
        version: version.value,
        courseId: form.courseId,
        coachStaffId: form.coachStaffId,
        roomId,
        startsAt,
        endsAt,
        capacity,
        sessionKind: form.sessionKind,
        deliveryAssignments: assignments,
        assignmentCommandKey: createCommandKey(),
      })
      : await createStaffScheduleSession(session.currentSiteId, {
        courseId: form.courseId,
        coachStaffId: form.coachStaffId,
        roomId,
        startsAt,
        endsAt,
        capacity,
        sessionKind: form.sessionKind,
        deliveryAssignments: assignments,
        assignmentCommandKey: createCommandKey(),
      });

    sessionId.value = saved.id;
    version.value = saved.version;
    sessionStatus.value = saved.status;
    uni.setNavigationBarTitle({ title: "编辑排课" });

    uni.showToast({ title: wasEdit ? "已保存" : "排课已创建", icon: "success" });
    setTimeout(() => uni.navigateBack(), 300);
  } catch (error) {
    if (error instanceof ApiError && error.payload.code === "SCHEDULE_SESSION_UPDATE_BLOCKED") {
      errorMessage.value = error.payload.message || "已有会员预约，部分修改被限制";
      return;
    }
    if (error instanceof ApiError && error.payload.code === "SCHEDULE_SESSION_ROOM_CONFLICT") {
      errorMessage.value = "教室时间冲突，请调整时间或教室";
      return;
    }
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

onLoad((options) => {
  sessionId.value = Number(options?.id || 0);
  if (options?.date) {
    form.date = String(options.date);
  }
  if (!sessionId.value && options?.courseId) {
    form.courseId = Number(options.courseId) || 0;
  }
  if (!sessionId.value && options?.startTime) {
    form.startTime = String(options.startTime);
  }
  uni.setNavigationBarTitle({ title: sessionId.value > 0 ? "编辑排课" : "新建排课" });
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  if (!loaded.value) {
    await load();
  }
});
</script>

<template>
  <u-loading-page :loading="checking || loading || saving" />
  <view v-if="!checking && canWrite" class="form-page">
    <view class="alert-area">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-alert
        v-if="hasBookings"
        type="warning"
        :description="`已有 ${bookedCount} 位会员预约：教练/教室/时间可改，容量不可小于已约人数；换课请用详情页「课程管理 → 换课」。`"
      />
      <u-alert
        v-if="isEdit && sessionStatus !== 'scheduled'"
        type="warning"
        :description="`当前状态为「${sessionStatus === 'suspended' ? '已停课' : sessionStatus === 'cancelled' ? '已取消' : '已结束'}」，仅可查看。`"
      />
      <view v-if="!canLoadCourses" class="field-help">缺少课程列表权限，请联系管理员开通</view>
      <view v-if="!canLoadCoaches" class="field-help">缺少教练列表权限，请联系管理员开通</view>
      <view v-if="!canLoadRooms" class="field-help">缺少教室列表权限，请联系管理员开通</view>
    </view>

    <!-- 行式表单（对标原版 subject-edit：左label+右值+箭头） -->
    <view class="form-card">
      <picker
        :disabled="!canLoadCourses || !courses.length || hasBookings"
        :range="courseLabels"
        :value="courseIndex >= 0 ? courseIndex : 0"
        @change="onCourseChange"
      >
        <view class="form-row">
          <text class="row-label required">课程</text>
          <text class="row-value" :class="{ placeholder: !form.courseId }">{{ courseLabel() }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </picker>

      <picker
        v-if="coachLabels.length"
        :disabled="!canLoadCoaches"
        :range="coachLabels"
        :value="coachIndex >= 0 ? coachIndex : 0"
        @change="onCoachChange"
      >
        <view class="form-row">
          <text class="row-label required">教练</text>
          <text class="row-value" :class="{ placeholder: !form.coachStaffId }">{{ coachLabel() }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </picker>
      <view v-else class="form-row" @tap="onCoachPickerUnavailable">
        <text class="row-label required">教练</text>
        <text class="row-value placeholder">{{ coachLabel() }}</text>
        <u-icon name="arrow-right" size="15" color="#bfbfbf" />
      </view>

      <picker
        :disabled="!canLoadRooms"
        :range="roomLabels"
        :value="form.roomId ? roomIndex + 1 : 0"
        @change="onRoomChange"
      >
        <view class="form-row">
          <text class="row-label" :class="{ required: !isPrivateSession }">选择教室</text>
          <text class="row-value" :class="{ placeholder: !form.roomId }">{{ roomLabel() }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </picker>

      <picker mode="date" :value="form.date" @change="onDateChange">
        <view class="form-row">
          <text class="row-label required">日期</text>
          <text class="row-value" :class="{ placeholder: !form.date }">{{ form.date || "请选择" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </picker>

      <picker mode="time" :value="form.startTime" @change="onStartTimeChange">
        <view class="form-row">
          <text class="row-label required">开始时间</text>
          <text class="row-value" :class="{ placeholder: !form.startTime }">{{ form.startTime || "请选择" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </picker>

      <picker mode="time" :value="form.endTime" @change="onEndTimeChange">
        <view class="form-row">
          <text class="row-label required">结束时间</text>
          <text class="row-value" :class="{ placeholder: !form.endTime }">{{ form.endTime || "请选择" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </picker>

      <view class="form-row">
        <text class="row-label required">开课规则</text>
        <view class="capacity-wrap">
          <text class="capacity-prefix">限</text>
          <input
            v-model="form.capacity"
            class="capacity-input"
            type="number"
            placeholder="人数"
          />
          <text class="capacity-prefix">人</text>
        </view>
      </view>

      <view class="form-row last">
        <text class="row-label">课程类型</text>
        <text class="kind-tag" :class="{ private: isPrivateSession }">{{ isPrivateSession ? "私教" : "团课" }}</text>
      </view>
      <view class="row-hint">类型与时长由所选课程模板自动确定</view>
    </view>

    <view class="delivery-card">
      <view class="delivery-head">
        <view>
          <text class="delivery-title">实际授课人员（A）</text>
          <text class="delivery-hint">多人授课时按比例拆分课时费与 A 耗卡提成；最终金额由后端结算。</text>
        </view>
        <text class="delivery-total" :class="{ invalid: deliveryAssignments.length && !deliveryAllocationValid }">
          {{ deliveryAssignments.length ? deliveryAllocationSummary : "未配置" }}
        </text>
      </view>

      <view v-for="(assignment, index) in deliveryAssignments" :key="`${assignment.staffId}-${assignment.compensationRoleId}-${index}`" class="delivery-item">
        <view class="delivery-item-head">
          <text>{{ index === 0 ? "主授课" : `协同授课 ${index + 1}` }}</text>
          <text class="delivery-remove" @tap="removeDeliveryAssignment(index)">移除</text>
        </view>
        <view class="delivery-field" @tap="chooseDeliveryStaff(index)">
          <text>授课员工</text>
          <view><text>{{ deliveryStaffName(assignment.staffId) }}</text><u-icon name="arrow-right" size="14" color="#bfbfbf" /></view>
        </view>
        <view class="delivery-field" @tap="chooseDeliveryRole(index)">
          <text>A 类型角色</text>
          <view><text>{{ deliveryRoleName(assignment.compensationRoleId) }}</text><u-icon name="arrow-right" size="14" color="#bfbfbf" /></view>
        </view>
        <view class="delivery-field">
          <text>分配比例</text>
          <view class="allocation-input"><input v-model="assignment.allocationPercent" type="digit" /><text>%（角色组内）</text></view>
        </view>
      </view>

      <view v-if="!deliveryAssignments.length" class="delivery-empty">
        排课必须明确配置至少一名实际授课人员及其 A 类型角色，不能仅按主教练静默结算。
      </view>
      <button class="delivery-add" @tap="addDeliveryAssignment">+ 添加实际授课人员</button>
    </view>

    <!-- 保存按钮（对标原版：黄底黑字大胶囊居中） -->
    <view v-if="canSave && (!isEdit || sessionStatus === 'scheduled')" class="btn-box">
      <button class="save-btn" :disabled="saving" @click="save">
        {{ saving ? "保存中..." : "保存" }}
      </button>
    </view>
  </view>
  <u-empty v-else-if="!checking && !canWrite" mode="permission" text="需要排课编辑权限" />
</template>

<style scoped lang="scss">
.form-page {
  min-height: 100vh;
  padding: $spacing-md;
  box-sizing: border-box;
}

.alert-area:not(:empty) {
  margin-bottom: $spacing-sm;
}

.field-help {
  margin-top: 10rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
  line-height: 1.5;
}

// —— 白卡行式表单（原版 u-cell 风格） ——
.form-card {
  padding: 4rpx 34rpx 24rpx;
  background: $color-surface;
  border-radius: $radius-lg;
}

.form-row {
  display: flex;
  align-items: center;
  gap: 16rpx;
  min-height: 104rpx;
  padding: 26rpx 0;
  box-sizing: border-box;
  border-bottom: 1rpx solid $color-page;

  &.last {
    border-bottom: none;
  }
}

.row-label {
  flex-shrink: 0;
  width: 176rpx;
  color: $color-text;
  font-size: 30rpx;

  // 原版必填红星
  &.required::after {
    content: "*";
    margin-left: 2rpx;
    color: $color-danger;
    font-size: 30rpx;
  }
}

.row-value {
  overflow: hidden;
  flex: 1;
  color: $color-text-secondary;
  font-size: 28rpx;
  text-align: right;
  white-space: nowrap;
  text-overflow: ellipsis;

  &.placeholder {
    color: $color-text-disabled;
  }
}

.capacity-wrap {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: flex-end;
  gap: 12rpx;
}

.capacity-prefix {
  color: $color-text-secondary;
  font-size: 28rpx;
}

.capacity-input {
  width: 140rpx;
  height: 64rpx;
  padding: 0 16rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 28rpx;
  text-align: center;
}

.kind-tag {
  margin-left: auto;
  padding: 4rpx 20rpx;
  border: 1rpx solid $color-info;
  border-radius: 8rpx;
  color: $color-info;
  font-size: 24rpx;

  &.private {
    border-color: $color-primary;
    color: $color-primary;
  }
}

.row-hint {
  padding: 12rpx 0 8rpx;
  color: $color-text-disabled;
  font-size: 22rpx;
}

.delivery-card {
  margin-top: $spacing-md;
  padding: 28rpx 28rpx 24rpx;
  background: $color-surface;
  border-radius: $radius-lg;
}

.delivery-head,
.delivery-item-head,
.delivery-field,
.delivery-field > view,
.allocation-input {
  display: flex;
  align-items: center;
}

.delivery-head,
.delivery-item-head,
.delivery-field {
  justify-content: space-between;
}

.delivery-head {
  gap: 20rpx;
}

.delivery-title,
.delivery-hint {
  display: block;
}

.delivery-title {
  font-size: 29rpx;
  font-weight: 600;
}

.delivery-hint {
  max-width: 470rpx;
  margin-top: 6rpx;
  color: $color-text-tertiary;
  font-size: 21rpx;
  line-height: 32rpx;
}

.delivery-total {
  flex-shrink: 0;
  max-width: 270rpx;
  color: $color-success;
  font-size: 23rpx;
  line-height: 32rpx;
  text-align: right;

  &.invalid {
    color: $color-danger;
  }
}

.delivery-item {
  margin-top: 20rpx;
  padding: 18rpx 20rpx;
  background: $color-page;
  border-radius: 14rpx;
}

.delivery-item-head {
  padding-bottom: 12rpx;
  font-size: 24rpx;
  font-weight: 600;
}

.delivery-remove {
  color: $color-danger;
  font-size: 21rpx;
  font-weight: 400;
}

.delivery-field {
  min-height: 72rpx;
  color: $color-text-secondary;
  border-top: 1rpx solid #ececec;
  font-size: 23rpx;
}

.delivery-field > view {
  gap: 7rpx;
  max-width: 430rpx;
  color: $color-text;
}

.allocation-input {
  gap: 6rpx;
}

.allocation-input input {
  width: 120rpx;
  height: 56rpx;
  padding: 0 12rpx;
  text-align: right;
  background: $color-surface;
  border-radius: 10rpx;
  box-sizing: border-box;
}

.delivery-empty {
  margin-top: 20rpx;
  padding: 18rpx;
  color: $color-text-tertiary;
  background: $color-page;
  border-radius: 12rpx;
  font-size: 22rpx;
  line-height: 34rpx;
}

.delivery-add {
  height: 68rpx;
  margin-top: 20rpx;
  color: $color-text-secondary;
  background: $color-page;
  border-radius: 34rpx;
  font-size: 24rpx;
  line-height: 68rpx;
}

.delivery-add::after {
  border: 0;
}

// —— 原版保存按钮：黄底黑字大胶囊 ——
.btn-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 80rpx;
  padding-bottom: 60rpx;
}

.save-btn {
  width: 458rpx;
  height: 83rpx;
  line-height: 83rpx;
  background: $color-brand-yellow;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;

  &[disabled] {
    opacity: 0.6;
    color: $color-text;
    background: $color-brand-yellow;
  }
}

.save-btn::after {
  border: 0;
}
</style>
