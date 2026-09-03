<script setup lang="ts">
// 课程管理弹窗组（对标原版 course-management）：
// 编辑/换课/换老师/修改时间/课表背景色/停课·解除停课/全部排课/删除 + 全套子弹窗与确认弹窗
import { computed, ref, watch } from "vue";
import {
  cancelStaffScheduleSession,
  fetchScheduleSessionColors,
  suspendStaffScheduleSession,
  unsuspendStaffScheduleSession,
  updateStaffScheduleSession,
} from "@/api/scheduling";
import { fetchStaffCourse, fetchStaffCourseCatalog, updateStaffCourse } from "@/api/catalog";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { useSessionStore } from "@/stores/session";
import type { CourseCatalogItem } from "@/types/catalog";
import type { ScheduleSession, ScheduleSessionColorItem } from "@/types/scheduling";
import type { StaffDirectoryListItem } from "@/types/staff-directory";

const props = defineProps<{
  show: boolean;
  session: ScheduleSession | null;
}>();

const emit = defineEmits<{
  (event: "update:show", value: boolean): void;
  (event: "success", updated: ScheduleSession): void;
  (event: "deleted"): void;
}>();

const store = useSessionStore();
const working = ref(false);
const current = computed(() => props.session);

const FACE_FALLBACK = "linear-gradient(135deg, #3f4756 0%, #23272f 100%)";

function sessionFace(item?: ScheduleSession | null) {
  return item?.courseFaceGradient || FACE_FALLBACK;
}

function hhmm(iso: string) {
  const idx = iso.indexOf("T");
  return idx > 0 ? iso.slice(idx + 1, idx + 6) : iso.slice(11, 16);
}

function fmtDate(date: Date) {
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function close() {
  emit("update:show", false);
}

function done(updated: ScheduleSession) {
  emit("success", updated);
}

// —— 编辑：跳排课表单 ——
function editSession() {
  if (!current.value) return;
  close();
  uni.navigateTo({ url: `/pages/course/session-form?id=${current.value.id}` });
}

function openAllArrange() {
  if (!current.value) return;
  close();
  uni.navigateTo({
    url: `/pages/course/timetable/all-course?courseId=${current.value.courseId}&name=${encodeURIComponent(current.value.courseName || "")}`,
  });
}

// —— 换课（选课弹窗 + 有约确认） ——
const coursePickVisible = ref(false);
const courseKeyword = ref("");
const courses = ref<CourseCatalogItem[]>([]);
const changeConfirmVisible = ref(false);
const pendingChangeCourse = ref<CourseCatalogItem | null>(null);

const filteredCourses = computed(() => {
  const keyword = courseKeyword.value.trim();
  if (!keyword) return courses.value;
  return courses.value.filter((item) => item.name.includes(keyword));
});

function cardFace(course: CourseCatalogItem) {
  return course.faceGradient || FACE_FALLBACK;
}

async function openChangeCourse() {
  courseKeyword.value = "";
  coursePickVisible.value = true;
  if (!courses.value.length && store.currentSiteId) {
    try {
      const catalog = await fetchStaffCourseCatalog(store.currentSiteId, 1, 200, undefined, "group");
      courses.value = catalog.items;
    } catch {
      courses.value = [];
    }
  }
}

async function pickChangeCourse(course: CourseCatalogItem) {
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
  if (!current.value || !store.currentSiteId) return;
  changeConfirmVisible.value = false;
  working.value = true;
  try {
    const updated = await updateStaffScheduleSession(store.currentSiteId, current.value.id, {
      version: current.value.version,
      courseId: course.id,
    });
    coursePickVisible.value = false;
    uni.showToast({ title: "换课成功", icon: "none" });
    done(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "换课失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 换老师 ——
const coachVisible = ref(false);
const coaches = ref<StaffDirectoryListItem[]>([]);

async function openReplaceCoach() {
  coachVisible.value = true;
  if (!coaches.value.length && store.currentSiteId && store.can("staff.directory.read")) {
    try {
      const response = await fetchStaffDirectory(store.currentSiteId);
      coaches.value = response.items.filter((item) => {
        const capabilities = item.capabilities ?? [];
        return item.status === "active" && (capabilities.length === 0 || capabilities.includes("coach"));
      });
    } catch {
      coaches.value = [];
    }
  }
}

async function pickCoach(coach: StaffDirectoryListItem) {
  if (!current.value || !store.currentSiteId) return;
  if (coach.id === current.value.coachStaffId) {
    coachVisible.value = false;
    return;
  }
  working.value = true;
  try {
    const updated = await updateStaffScheduleSession(store.currentSiteId, current.value.id, {
      version: current.value.version,
      coachStaffId: coach.id,
    });
    coachVisible.value = false;
    uni.showToast({ title: "更换成功", icon: "none" });
    done(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "更换失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 修改时间（时:分 picker） ——
const timeVisible = ref(false);
const pickerValue = ref<number[]>([10, 0, 0]);
const HOURS = Array.from({ length: 24 }, (_, index) => index);
const MINUTES = Array.from({ length: 60 }, (_, index) => index);

function openRetime() {
  if (!current.value) return;
  const [hour, minute] = hhmm(current.value.startsAt).split(":").map((part) => Number.parseInt(part, 10));
  pickerValue.value = [Number.isFinite(hour) ? hour : 10, 0, Number.isFinite(minute) ? minute : 0];
  timeVisible.value = true;
}

function onPickerChange(event: { detail: { value: number[] } }) {
  pickerValue.value = event.detail.value;
}

async function submitRetime() {
  if (!current.value || !store.currentSiteId) return;
  const hour = String(HOURS[pickerValue.value[0]] ?? 10).padStart(2, "0");
  const minute = String(MINUTES[pickerValue.value[2]] ?? 0).padStart(2, "0");
  working.value = true;
  try {
    const date = current.value.startsAt.slice(0, 10);
    const startDate = new Date(`${date}T${hour}:${minute}:00`);
    const duration = new Date(current.value.endsAt).getTime() - new Date(current.value.startsAt).getTime();
    const endDate = new Date(startDate.getTime() + duration);
    const endsAt = `${fmtDate(endDate)}T${String(endDate.getHours()).padStart(2, "0")}:${String(endDate.getMinutes()).padStart(2, "0")}:00`;
    const updated = await updateStaffScheduleSession(store.currentSiteId, current.value.id, {
      version: current.value.version,
      startsAt: `${date}T${hour}:${minute}:00`,
      endsAt,
    });
    timeVisible.value = false;
    uni.showToast({ title: "已修改", icon: "none" });
    done(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "修改失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 停课 / 解除停课 ——
const stopConfirmVisible = ref(false);

async function confirmStop() {
  if (!current.value || !store.currentSiteId) return;
  stopConfirmVisible.value = false;
  working.value = true;
  try {
    const updated = await suspendStaffScheduleSession(store.currentSiteId, current.value.id);
    uni.showToast({ title: "已停课", icon: "none" });
    done(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "停课失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

async function startSession() {
  if (!current.value || !store.currentSiteId) return;
  working.value = true;
  try {
    const updated = await unsuspendStaffScheduleSession(store.currentSiteId, current.value.id);
    uni.showToast({ title: "已解除停课", icon: "none" });
    done(updated);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "操作失败", icon: "none" });
  } finally {
    working.value = false;
  }
}

// —— 删除（有预约拦截 → 未删除弹窗） ——
const delConfirmVisible = ref(false);
const terminateVisible = ref(false);

async function confirmDelete() {
  if (!current.value || !store.currentSiteId) return;
  delConfirmVisible.value = false;
  working.value = true;
  try {
    await cancelStaffScheduleSession(store.currentSiteId, current.value.id);
    close();
    uni.showToast({ title: "已删除", icon: "none" });
    emit("deleted");
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

// —— 课表背景色 ——
const bgColorVisible = ref(false);
const colorPalette = ref<ScheduleSessionColorItem[]>([]);
const bgActionId = ref<1 | 2>(1);
const changeColor = ref("");
const effectiveColor = computed(() => current.value?.displayColor || "");

async function openBgColor() {
  if (!store.currentSiteId) return;
  bgActionId.value = 1;
  changeColor.value = "";
  bgColorVisible.value = true;
  if (!colorPalette.value.length) {
    try {
      const response = await fetchScheduleSessionColors(store.currentSiteId);
      colorPalette.value = response.palette;
    } catch {
      colorPalette.value = [];
    }
  }
}

async function submitBgColor() {
  const target = current.value;
  if (!target || !store.currentSiteId || !changeColor.value || changeColor.value === effectiveColor.value) {
    bgColorVisible.value = false;
    return;
  }
  working.value = true;
  try {
    if (bgActionId.value === 2) {
      const updated = await updateStaffScheduleSession(store.currentSiteId, target.id, {
        version: target.version,
        displayColor: changeColor.value,
      });
      done(updated);
    } else {
      const detail = await fetchStaffCourse(store.currentSiteId, target.courseId);
      await updateStaffCourse(store.currentSiteId, target.courseId, {
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
        const updated = await updateStaffScheduleSession(store.currentSiteId, target.id, {
          version: target.version,
          displayColor: null,
        });
        done(updated);
      } else {
        done({ ...target, displayColor: changeColor.value, courseDisplayColor: changeColor.value });
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

watch(
  () => props.show,
  (value) => {
    if (!value) {
      coursePickVisible.value = false;
      coachVisible.value = false;
      timeVisible.value = false;
      bgColorVisible.value = false;
    }
  },
);
</script>

<template>
  <!-- ============ 课程管理弹窗（原版 course-management 2×4） ============ -->
  <u-popup :show="show" mode="bottom" round="20" @close="close">
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

  <!-- 换课：选择课程 -->
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
          @tap="pickChangeCourse(course)"
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
            </view>
          </view>
          <text class="sc-duration">{{ course.durationMinutes }}分钟</text>
        </view>
        <view v-if="!filteredCourses.length" class="empty-tip">没有找到相关课目</view>
      </scroll-view>
    </view>
  </u-popup>

  <!-- 换老师 -->
  <u-popup :show="coachVisible" mode="bottom" round="20" :z-index="10080" @close="coachVisible = false">
    <view class="panel tall">
      <text class="panel-title">更换老师</text>
      <scroll-view scroll-y class="course-list">
        <view v-for="coach in coaches" :key="coach.id" class="coach-row" @tap="pickCoach(coach)">
          <view class="coach-avatar">{{ (coach.displayName || "教")[0] }}</view>
          <text class="coach-name">{{ coach.displayName }}</text>
          <u-icon v-if="coach.id === current?.coachStaffId" name="checkmark-circle-fill" size="20" color="#ed920f" />
        </view>
        <view v-if="!coaches.length" class="empty-tip">未找到教练，请先在「员工管理」中添加教练</view>
      </scroll-view>
    </view>
  </u-popup>

  <!-- 修改时间 -->
  <u-popup :show="timeVisible" mode="bottom" round="20" :z-index="10080" @close="timeVisible = false">
    <view class="panel">
      <text class="panel-title">选择上课时间</text>
      <picker-view class="time-picker" :value="pickerValue" @change="onPickerChange">
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
      <button class="panel-confirm" @tap="submitRetime">确　定</button>
    </view>
  </u-popup>

  <!-- 课表背景色 -->
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
      <button class="panel-confirm" :disabled="working" @tap="submitBgColor">确　定</button>
    </view>
  </u-popup>

  <!-- 删除确认 -->
  <u-popup :show="delConfirmVisible" mode="center" round="16" :z-index="10090" @close="delConfirmVisible = false">
    <view class="confirm-modal">
      <text class="confirm-title">注意，要删除这个课吗？</text>
      <view class="confirm-body"><text class="confirm-text">确定后将立即删除</text></view>
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
</template>

<style scoped lang="scss">
.panel {
  display: flex;
  flex-direction: column;
  gap: 26rpx;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));

  &.tall { max-height: 75vh; }
}
.panel-title { font-size: 32rpx; font-weight: 600; text-align: center; color: $color-text; }
.search-box { display: flex; align-items: center; gap: 12rpx; height: 69rpx; padding: 0 24rpx; background: $color-page; border-radius: 35rpx; }
.search-input { flex: 1; font-size: 26rpx; }
.count-tip { color: $color-text-tertiary; font-size: 24rpx; }
.course-list { max-height: 52vh; }

.subject-card { display: flex; justify-content: space-between; margin-bottom: 20rpx; padding: 24rpx; border-radius: 16rpx; color: #fff; }
.sc-left { flex: 1; min-width: 0; }
.sc-name { display: block; font-size: 32rpx; font-weight: 600; }
.sc-meta { margin-top: 10rpx; font-size: 22rpx; opacity: .9; }
.sc-stars { margin-left: 6rpx; color: #ffa800; }
.sc-bottom { display: flex; align-items: center; gap: 12rpx; margin-top: 18rpx; }
.sc-avatar { display: flex; align-items: center; justify-content: center; width: 52rpx; height: 52rpx; border-radius: 50%; background: rgba(255,255,255,.28); font-size: 24rpx; }
.sc-coach { font-size: 24rpx; }
.sc-rule { padding: 6rpx 14rpx; background: rgba(255,255,255,.22); border-radius: 999rpx; font-size: 20rpx; &.stopped { background: $color-danger; } }
.sc-duration { flex-shrink: 0; padding: 8rpx 0 0 18rpx; font-size: 28rpx; white-space: nowrap; }
.sc-time { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; padding: 8rpx 0 0 18rpx; }
.sc-time-start { font-size: 40rpx; font-weight: 600; }
.sc-time-end { margin-top: 6rpx; font-size: 22rpx; opacity: .85; }
.empty-tip { padding: 60rpx 0; color: $color-text-disabled; font-size: 26rpx; text-align: center; }

.time-picker { height: 500rpx; }
.picker-item { display: flex; align-items: center; justify-content: center; height: 88rpx; font-size: 32rpx; color: $color-text; }

.bg-bar { height: 14rpx; margin: -10rpx 0 6rpx; border-radius: 7rpx; }
.manage-grid { display: flex; flex-wrap: wrap; padding-bottom: 10rpx; }
.manage-item { display: flex; flex-direction: column; align-items: center; gap: 10rpx; width: 25%; margin-top: 30rpx; }
.manage-icon { display: flex; align-items: center; justify-content: center; width: 88rpx; height: 88rpx; border-radius: 24rpx; background: $color-page; }
.manage-text { position: relative; color: $color-text; font-size: 24rpx; text-align: center; &.disabled { color: #9a9a9a; } }
.text-tip { position: absolute; top: -64rpx; right: -40rpx; padding: 4rpx 10rpx; background: $color-danger; border-radius: 8rpx 8rpx 8rpx 0; color: #fff; font-size: 18rpx; white-space: nowrap; }

.coach-row { display: flex; align-items: center; gap: 20rpx; padding: 24rpx 8rpx; border-bottom: 1rpx solid #f5f5f5; }
.coach-avatar { display: flex; align-items: center; justify-content: center; width: 72rpx; height: 72rpx; border-radius: 20rpx; background: $color-page; color: $color-text; font-size: 28rpx; }
.coach-name { flex: 1; color: $color-text; font-size: 28rpx; }

.bg-tips { display: flex; align-items: flex-start; gap: 8rpx; }
.bg-tips-text { flex: 1; color: #989898; font-size: 22rpx; line-height: 34rpx; }
.bg-radio { display: flex; align-items: center; gap: 12rpx; }
.bg-radio-text { color: $color-text-secondary; font-size: 26rpx; &.active { color: $color-text; font-weight: 500; } }
.color-grid { display: flex; flex-wrap: wrap; gap: 18rpx; }
.color-item { position: relative; width: 120rpx; height: 120rpx; border-radius: 14rpx; overflow: hidden; }
.color-state { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4rpx; background: rgba(0,0,0,.25); &.dim { opacity: .45; } }
.color-state-text { color: #fff; font-size: 18rpx; }

.panel-confirm { height: 83rpx; margin-top: 6rpx; line-height: 83rpx; background: $color-brand-yellow; border-radius: 42rpx; color: $color-text; font-size: 32rpx; font-weight: 500; }
.panel-confirm::after { border: 0; }

.confirm-modal { display: flex; flex-direction: column; width: 640rpx; padding: 48rpx 40rpx 40rpx; box-sizing: border-box; }
.confirm-title { color: $color-text; font-size: 34rpx; font-weight: 500; }
.confirm-body { margin-top: 18rpx; }
.confirm-text { display: block; margin-top: 8rpx; color: #989898; font-size: 26rpx; line-height: 38rpx; }
.inline-danger { color: $color-danger; font-weight: 500; }
.confirm-btns { display: flex; justify-content: flex-end; gap: 20rpx; margin-top: 30rpx; &.center { justify-content: center; } }
.btn-cancel, .btn-ok { width: 180rpx; height: 70rpx; margin: 0; line-height: 70rpx; border-radius: 35rpx; font-size: 28rpx; }
.btn-cancel { background: $color-surface; border: 1rpx solid $color-border; color: $color-text-secondary; }
.btn-ok { background: $color-brand-yellow; color: $color-text; }
.btn-cancel::after, .btn-ok::after { border: 0; }
</style>
