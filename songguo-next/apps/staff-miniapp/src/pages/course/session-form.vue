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
const canWrite = computed(() => session.can("schedule.session.write"));
const canLoadCourses = computed(() => session.can("course-catalog.read"));
const canLoadRooms = computed(() => session.can("site.rooms.read"));
const canLoadCoaches = computed(() => session.can("staff.directory.read"));
// 有预约的排课允许编辑（员工排错课可修正），仅做细粒度限制：
// 课程不可在表单内更换（换课请走详情页「课程管理 → 换课」，带二次确认）；
// 容量不可小于已约人数（validateForm 校验 + 后端 SCHEDULE_SESSION_UPDATE_BLOCKED 兜底）。
const hasBookings = computed(() => isEdit.value && bookedCount.value > 0);
const canSave = computed(() => canWrite.value);

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
      fetchStaffCourseCatalog(session.currentSiteId, 1, 50, undefined, "group")
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
  if (hasBookings.value && capacity < bookedCount.value) {
    return `容量不能小于已预约人数（当前已约 ${bookedCount.value} 人）`;
  }
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
