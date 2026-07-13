<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { ApiError } from "@songguo/api-client";
import { fetchStaffCourseCatalog, fetchStaffRoomCatalog } from "@/api/catalog";
import {
  createStaffScheduleSession,
  fetchStaffScheduleSession,
  updateStaffScheduleSession,
} from "@/api/scheduling";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem, RoomCatalogItem } from "@/types/catalog";
import type { ScheduleSession, ScheduleSessionKind } from "@/types/scheduling";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import { combineLocalDateTime, splitLocalDateTime, todayIsoDate } from "@/utils/format";

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
const pageTitle = computed(() => (isEdit.value ? "编辑排课" : "新建排课"));
const canWrite = computed(() => session.can("schedule.session.write"));
const canLoadCourses = computed(() => session.can("course-catalog.read"));
const canLoadRooms = computed(() => session.can("site.rooms.read"));
const canLoadCoaches = computed(() => session.can("staff.directory.read"));
const editBlocked = computed(() => isEdit.value && bookedCount.value > 0);
const canSave = computed(() => canWrite.value && !editBlocked.value);

const courseIndex = computed(() => courses.value.findIndex((item) => item.id === form.courseId));
const coachIndex = computed(() => coaches.value.findIndex((item) => item.id === form.coachStaffId));
const roomIndex = computed(() => rooms.value.findIndex((item) => item.id === form.roomId));
const selectedCourse = computed(() => courses.value.find((item) => item.id === form.courseId) || null);
const isPrivateSession = computed(() => form.sessionKind === "private");

const courseLabels = computed(() => courses.value.map((item) => `${item.name}（${item.courseType === "private" ? "私教" : "团课"}）`));
const coachLabels = computed(() => coaches.value.map((item) => item.displayName));
const roomLabels = computed(() => ["不指定教室", ...rooms.value.map((item) => item.name)]);

function courseLabel() {
  if (!form.courseId) return "请选择课程";
  return courseLabels.value[courseIndex.value] || "请选择课程";
}

function coachLabel() {
  if (!form.coachStaffId) return "请选择教练";
  return coachLabels.value[coachIndex.value] || "请选择教练";
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
  if (!canLoadCoaches.value || editBlocked.value) return;
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
}

function onCoachChange(event: { detail: { value: string | number } }) {
  const coach = coaches.value[Number(event.detail.value)];
  form.coachStaffId = coach?.id || 0;
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
      fetchStaffCourseCatalog(session.currentSiteId, 1, 50)
        .then((response) => {
          courses.value = response.items;
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
          coaches.value = response.items.filter(isCoachCandidate);
        })
        .catch(() => {
          coaches.value = [];
        }),
    );
  }

  await Promise.all(tasks);
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
}

async function load() {
  if (!canWrite.value || !session.currentSiteId) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    await loadOptions();
    if (isEdit.value) {
      await loadSession();
    } else if (!form.courseId && courses.value.length) {
      form.courseId = courses.value[0].id;
      applyCourseDefaults(courses.value[0]);
    }
    loaded.value = true;
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "排课表单加载失败";
  } finally {
    loading.value = false;
  }
}

function validateForm() {
  if (!form.courseId) return "请选择课程";
  if (!form.coachStaffId) return "请选择教练";
  if (!form.date) return "请选择日期";
  if (!form.startTime || !form.endTime) return "请选择开始和结束时间";
  const capacity = Number(form.capacity);
  if (!Number.isFinite(capacity) || capacity < 1) return "请填写有效容量";
  if (form.startTime >= form.endTime) return "结束时间必须晚于开始时间";
  if (!isPrivateSession.value && !form.roomId && rooms.value.length) return "团课请选择教室";
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

  try {
    if (isEdit.value) {
      await updateStaffScheduleSession(session.currentSiteId, sessionId.value, {
        version: version.value,
        courseId: form.courseId,
        coachStaffId: form.coachStaffId,
        roomId,
        startsAt,
        endsAt,
        capacity,
        sessionKind: form.sessionKind,
      });
      uni.showToast({ title: "已保存", icon: "success" });
    } else {
      await createStaffScheduleSession(session.currentSiteId, {
        courseId: form.courseId,
        coachStaffId: form.coachStaffId,
        roomId,
        startsAt,
        endsAt,
        capacity,
        sessionKind: form.sessionKind,
      });
      uni.showToast({ title: "排课已创建", icon: "success" });
    }
    setTimeout(() => uni.navigateBack(), 300);
  } catch (error) {
    if (error instanceof ApiError && error.payload.code === "SCHEDULE_SESSION_UPDATE_BLOCKED") {
      errorMessage.value = "已有会员预约，无法修改本节课程";
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
  <view v-if="!checking && canWrite" class="page-container form-page">
    <view class="page-title">{{ pageTitle }}</view>
    <view class="page-hint">填写课程、教练、教室与时间。团课需指定教室，私教教室可选。</view>

    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-alert
      v-if="editBlocked"
      type="warning"
      description="已有会员预约，无法修改本节课程。"
    />
    <u-alert
      v-if="isEdit && sessionStatus !== 'scheduled'"
      type="warning"
      :description="`当前状态为「${sessionStatus === 'suspended' ? '已停课' : sessionStatus === 'cancelled' ? '已取消' : '已结束'}」，仅可查看。`"
    />

    <view v-if="!canLoadCourses" class="field-help">缺少 course-catalog.read 权限，无法加载课程列表</view>
    <view class="field-label">课程</view>
    <picker
      :disabled="!canLoadCourses || !courses.length || editBlocked"
      :range="courseLabels"
      :value="courseIndex >= 0 ? courseIndex : 0"
      @change="onCourseChange"
    >
      <view class="picker-field">{{ courseLabel() }}</view>
    </picker>

    <view v-if="!canLoadCoaches" class="field-help">缺少 staff.directory.read 权限，无法加载教练列表</view>
    <view class="field-label">教练</view>
    <picker
      v-if="coachLabels.length"
      :disabled="!canLoadCoaches || editBlocked"
      :range="coachLabels"
      :value="coachIndex >= 0 ? coachIndex : 0"
      @change="onCoachChange"
    >
      <view class="picker-field">{{ coachLabel() }}</view>
    </picker>
    <view
      v-else
      class="picker-field"
      :class="{ 'picker-field--disabled': !canLoadCoaches || editBlocked }"
      @tap="onCoachPickerUnavailable"
    >
      {{ coachLabel() }}
    </view>

    <view v-if="!canLoadRooms" class="field-help">缺少 site.rooms.read 权限，无法加载教室列表</view>
    <view class="field-label">{{ isPrivateSession ? "教室（选填）" : "教室" }}</view>
    <picker
      :disabled="!canLoadRooms || editBlocked"
      :range="roomLabels"
      :value="form.roomId ? roomIndex + 1 : 0"
      @change="onRoomChange"
    >
      <view class="picker-field">{{ roomLabel() }}</view>
    </picker>

    <view class="field-label">日期</view>
    <picker
      mode="date"
      :value="form.date"
      :disabled="editBlocked"
      @change="onDateChange"
    >
      <view class="picker-field">{{ form.date || "请选择" }}</view>
    </picker>

    <view class="field-label">开始时间</view>
    <picker
      mode="time"
      :value="form.startTime"
      :disabled="editBlocked"
      @change="onStartTimeChange"
    >
      <view class="picker-field">{{ form.startTime || "请选择" }}</view>
    </picker>

    <view class="field-label">结束时间</view>
    <picker
      mode="time"
      :value="form.endTime"
      :disabled="editBlocked"
      @change="onEndTimeChange"
    >
      <view class="picker-field">{{ form.endTime || "请选择" }}</view>
    </picker>

    <view class="field-label">容量</view>
    <u-input
      v-model="form.capacity"
      type="number"
      :disabled="editBlocked"
      placeholder="请输入人数上限"
    />

    <view class="field-label">课程类型</view>
    <view class="picker-field readonly">{{ isPrivateSession ? "私教" : "团课" }}</view>
    <view class="field-help">由所选课程模板自动确定</view>

    <u-button
      v-if="canSave && (!isEdit || sessionStatus === 'scheduled')"
      type="primary"
      :loading="saving"
      @click="save"
    >
      保存
    </u-button>
  </view>
  <u-empty v-else-if="!checking && !canWrite" mode="permission" text="需要 schedule.session.write 权限" />
</template>

<style scoped lang="scss">
.form-page {
  padding-bottom: 48rpx;
}

.page-title {
  font-size: 36rpx;
  font-weight: 600;
}

.page-hint,
.field-help {
  margin-top: 10rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
  line-height: 1.5;
}

.field-label {
  margin: 28rpx 0 12rpx;
  color: $color-text-secondary;
  font-size: 24rpx;
}

.picker-field {
  min-height: 80rpx;
  box-sizing: border-box;
  padding: 20rpx;
  background: $color-surface;
  border: 1rpx solid $color-border;
  border-radius: $radius-sm;
}

.picker-field.readonly {
  color: $color-text-secondary;
}

.picker-field--disabled {
  color: $color-text-secondary;
}
</style>
