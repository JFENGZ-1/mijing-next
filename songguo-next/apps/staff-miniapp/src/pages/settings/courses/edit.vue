<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  archiveStaffCourse,
  createStaffCourse,
  fetchStaffCourse,
  fetchStaffRoomCatalog,
  restoreStaffCourse,
  updateStaffCourse,
} from "@/api/catalog";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { CourseDetail, RoomCatalogItem } from "@/types/catalog";
import type { CourseType } from "@/types/scheduling";
import type { StaffDirectoryListItem } from "@/types/staff-directory";

const COURSE_TYPES: CourseType[] = ["group", "private"];

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const courseId = ref<number | null>(null);
const course = ref<CourseDetail | null>(null);
const rooms = ref<RoomCatalogItem[]>([]);
const coaches = ref<StaffDirectoryListItem[]>([]);

const courseType = ref<CourseType>("group");
const name = ref("");
const description = ref("");
const durationMinutes = ref("60");
const difficulty = ref("");
const minCapacity = ref("");
const maxCapacity = ref("");
const defaultRoomId = ref(0);
const coachStaffId = ref(0);
const tagsText = ref("");
const sortOrder = ref("0");

const isEdit = computed(() => courseId.value !== null);
const canRead = computed(() => session.can("course-catalog.read"));
const canWrite = computed(() => session.can("course-catalog.write"));
const isArchived = computed(() => course.value?.catalogStatus === "archived");
const isGroup = computed(() => courseType.value === "group");
const isPrivate = computed(() => courseType.value === "private");
const canLoadRooms = computed(() => session.can("site.rooms.read"));
const canLoadCoaches = computed(() => session.can("staff.directory.read"));

const courseTypeLabels = computed(() =>
  COURSE_TYPES.map((type) => ({ group: "团课", private: "私教" } as const)[type]),
);
const courseTypeIndex = computed(() => COURSE_TYPES.indexOf(courseType.value));
const roomLabels = computed(() => ["不指定教室", ...rooms.value.map((item) => item.name)]);
const roomIndex = computed(() => {
  if (!defaultRoomId.value) return 0;
  const index = rooms.value.findIndex((item) => item.id === defaultRoomId.value);
  return index >= 0 ? index + 1 : 0;
});
const coachLabels = computed(() => coaches.value.map((item) => item.displayName));
const coachIndex = computed(() => coaches.value.findIndex((item) => item.id === coachStaffId.value));

function courseTypeLabel() {
  return ({ group: "团课", private: "私教" } as const)[courseType.value];
}

function roomLabel() {
  if (!defaultRoomId.value) return "不指定教室";
  return rooms.value.find((item) => item.id === defaultRoomId.value)?.name || "不指定教室";
}

function coachLabel() {
  if (!coachStaffId.value) return "请选择教练";
  return coaches.value.find((item) => item.id === coachStaffId.value)?.displayName || "请选择教练";
}

function isCoachCandidate(item: StaffDirectoryListItem) {
  if (item.status !== "active") return false;
  return item.capabilities.includes("coach");
}

function onCourseTypePickerChange(event: { detail: { value: string | number } }) {
  onCourseTypeChange(Number(event.detail.value));
}

function onRoomPickerChange(event: { detail: { value: string | number } }) {
  onRoomChange(Number(event.detail.value));
}

function onCoachPickerChange(event: { detail: { value: string | number } }) {
  onCoachChange(Number(event.detail.value));
}

function onCourseTypeChange(index: number) {
  const next = COURSE_TYPES[index];
  if (!next || next === courseType.value || isEdit.value) return;
  courseType.value = next;
}

function onRoomChange(index: number) {
  defaultRoomId.value = index === 0 ? 0 : rooms.value[index - 1]?.id || 0;
}

function onCoachChange(index: number) {
  coachStaffId.value = coaches.value[index]?.id || 0;
}

function parsePositiveInt(value: string, label: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const amount = Number.parseInt(trimmed, 10);
  if (!Number.isFinite(amount) || amount < 1) {
    uni.showToast({ title: `请输入有效${label}`, icon: "none" });
    return null;
  }
  return amount;
}

function parseTags(): string[] {
  return tagsText.value
    .split(/[,，]/)
    .map((tag) => tag.trim())
    .filter(Boolean);
}

function buildCreatePayload() {
  if (!name.value.trim()) {
    uni.showToast({ title: "请填写课程名称", icon: "none" });
    return null;
  }
  const duration = parsePositiveInt(durationMinutes.value, "课时时长");
  if (duration == null) return null;

  const payload = {
    courseType: courseType.value,
    name: name.value.trim(),
    durationMinutes: duration,
    sortOrder: Number.parseInt(sortOrder.value, 10) || 0,
  } as Parameters<typeof createStaffCourse>[1];

  if (description.value.trim()) payload.description = description.value.trim();
  if (difficulty.value.trim()) {
    const parsed = parsePositiveInt(difficulty.value, "难度");
    if (parsed == null || parsed > 5) {
      uni.showToast({ title: "难度为 1-5", icon: "none" });
      return null;
    }
    payload.difficulty = parsed;
  }
  const tags = parseTags();
  if (tags.length) payload.tags = tags;

  if (isGroup.value) {
    const max = parsePositiveInt(maxCapacity.value, "最大人数");
    if (max == null) return null;
    payload.maxCapacity = max;
    if (minCapacity.value.trim()) {
      const min = parsePositiveInt(minCapacity.value, "最少开课人数");
      if (min == null) return null;
      payload.minCapacity = min;
    }
    if (defaultRoomId.value) payload.defaultRoomId = defaultRoomId.value;
  }

  if (isPrivate.value) {
    if (!coachStaffId.value) {
      uni.showToast({ title: "请选择教练", icon: "none" });
      return null;
    }
    payload.coachStaffId = coachStaffId.value;
  }

  return payload;
}

function buildUpdatePayload() {
  if (!course.value) return null;
  const base = buildCreatePayload();
  if (!base) return null;
  return { ...base, version: course.value.version ?? 1 };
}

function fillForm(detail: CourseDetail) {
  courseType.value = detail.courseType;
  name.value = detail.name;
  description.value = detail.description || "";
  durationMinutes.value = String(detail.durationMinutes);
  difficulty.value = detail.difficulty != null ? String(detail.difficulty) : "";
  minCapacity.value = detail.minCapacity != null ? String(detail.minCapacity) : "";
  maxCapacity.value = detail.maxCapacity != null ? String(detail.maxCapacity) : "";
  defaultRoomId.value = detail.defaultRoomId || 0;
  coachStaffId.value = detail.coachStaffId || 0;
  tagsText.value = (detail.tags || []).join("，");
  sortOrder.value = String(detail.sortOrder ?? 0);
}

async function loadReferenceData() {
  if (!session.currentSiteId) return;
  const tasks: Promise<void>[] = [];
  if (canLoadRooms.value) {
    tasks.push(
      fetchStaffRoomCatalog(session.currentSiteId).then((response) => {
        rooms.value = response.items;
      }),
    );
  }
  if (canLoadCoaches.value) {
    tasks.push(
      fetchStaffDirectory(session.currentSiteId).then((response) => {
        coaches.value = response.items.filter(isCoachCandidate);
      }),
    );
  }
  await Promise.all(tasks);
}

async function load() {
  if (!session.currentSiteId || !canRead.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    await loadReferenceData();
    if (courseId.value) {
      course.value = await fetchStaffCourse(session.currentSiteId, courseId.value);
      fillForm(course.value);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "课程资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !canWrite.value || isArchived.value) return;
  const siteId = session.currentSiteId;
  saving.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && courseId.value) {
      const payload = buildUpdatePayload();
      if (!payload) {
        saving.value = false;
        return;
      }
      course.value = await updateStaffCourse(siteId, courseId.value, payload);
      fillForm(course.value);
      uni.showToast({ title: "已保存", icon: "none" });
      return;
    }

    const payload = buildCreatePayload();
    if (!payload) {
      saving.value = false;
      return;
    }
    const created = await createStaffCourse(siteId, payload);
    uni.showToast({ title: "已创建", icon: "none" });
    uni.redirectTo({ url: `/pages/settings/courses/edit?id=${created.id}` });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function archive() {
  if (!session.currentSiteId || !courseId.value || !canWrite.value) return;
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认归档",
      content: "归档后该课程将不再出现在排课与卡种关联列表中，可稍后恢复。",
      success: (result) => resolve(Boolean(result.confirm)),
    });
  });
  if (!confirmed) return;

  saving.value = true;
  errorMessage.value = "";
  try {
    course.value = await archiveStaffCourse(session.currentSiteId, courseId.value);
    fillForm(course.value);
    uni.showToast({ title: "已归档", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "归档失败";
  } finally {
    saving.value = false;
  }
}

async function restore() {
  if (!session.currentSiteId || !courseId.value || !canWrite.value) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    course.value = await restoreStaffCourse(session.currentSiteId, courseId.value);
    fillForm(course.value);
    uni.showToast({ title: "已恢复", icon: "none" });
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "恢复失败";
  } finally {
    saving.value = false;
  }
}

onLoad((options) => {
  const id = Number(options?.id);
  courseId.value = Number.isFinite(id) && id > 0 ? id : null;
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看课程库权限" />

    <template v-else>
      <u-alert
        v-if="isArchived"
        type="warning"
        description="该课程已归档，恢复后可重新用于排课与售卡关联。"
      />

      <view class="field-block">
        <view class="field-label">课程类型</view>
        <picker
          v-if="!isEdit"
          mode="selector"
          :range="courseTypeLabels"
          :value="courseTypeIndex"
          @change="onCourseTypePickerChange"
        >
          <view class="picker-value">{{ courseTypeLabel() }}</view>
        </picker>
        <text v-else class="field-hint">{{ courseTypeLabel() }}</text>
      </view>

      <view class="field-block">
        <view class="field-label">课程名称</view>
        <u-input v-model="name" placeholder="如：阿斯汤伽、垫上普拉提" :disabled="!canWrite || isArchived" />
      </view>

      <view class="field-block">
        <view class="field-label">课时时长（分钟）</view>
        <u-input v-model="durationMinutes" type="number" placeholder="60" :disabled="!canWrite || isArchived" />
      </view>

      <template v-if="isGroup">
        <view class="field-block">
          <view class="field-label">最大人数</view>
          <u-input v-model="maxCapacity" type="number" placeholder="12" :disabled="!canWrite || isArchived" />
        </view>
        <view class="field-block">
          <view class="field-label">最少开课人数（可选）</view>
          <u-input v-model="minCapacity" type="number" placeholder="3" :disabled="!canWrite || isArchived" />
        </view>
        <view class="field-block">
          <view class="field-label">难度 1-5（可选）</view>
          <u-input v-model="difficulty" type="number" placeholder="1" :disabled="!canWrite || isArchived" />
        </view>
        <view v-if="canLoadRooms" class="field-block">
          <view class="field-label">默认教室</view>
          <picker
            mode="selector"
            :range="roomLabels"
            :value="roomIndex"
            :disabled="!canWrite || isArchived"
            @change="onRoomPickerChange"
          >
            <view class="picker-value">{{ roomLabel() }}</view>
          </picker>
        </view>
        <view class="field-block">
          <view class="field-label">标签（可选，逗号分隔）</view>
          <u-input v-model="tagsText" placeholder="瑜伽，塑形" :disabled="!canWrite || isArchived" />
        </view>
      </template>

      <template v-if="isPrivate">
        <view v-if="canLoadCoaches" class="field-block">
          <view class="field-label">授课教练</view>
          <picker
            mode="selector"
            :range="coachLabels"
            :value="coachIndex >= 0 ? coachIndex : 0"
            :disabled="!canWrite || isArchived || coaches.length === 0"
            @change="onCoachPickerChange"
          >
            <view class="picker-value">{{ coachLabel() }}</view>
          </picker>
          <text v-if="coaches.length === 0" class="field-hint">请先在员工管理中添加教练</text>
        </view>
      </template>

      <view class="field-block">
        <view class="field-label">课程说明（可选）</view>
        <u-input v-model="description" placeholder="课程简介" :disabled="!canWrite || isArchived" />
      </view>

      <view class="field-block">
        <view class="field-label">排序（可选）</view>
        <u-input v-model="sortOrder" type="number" placeholder="数字越小越靠前" :disabled="!canWrite || isArchived" />
      </view>

      <view v-if="canWrite" class="actions">
        <u-button
          v-if="!isArchived"
          type="primary"
          :loading="saving"
          :text="isEdit ? '保存修改' : '创建课程'"
          @click="save"
        />
        <u-button
          v-if="isEdit && !isArchived"
          type="error"
          plain
          :loading="saving"
          text="归档课程"
          @click="archive"
        />
        <u-button
          v-if="isEdit && isArchived"
          type="primary"
          :loading="saving"
          text="恢复课程"
          @click="restore"
        />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
  padding-bottom: 48rpx;
}

.field-block {
  margin-bottom: 28rpx;
}

.field-label {
  margin-bottom: 12rpx;
  font-size: 28rpx;
  font-weight: 600;
  color: #202124;
}

.field-hint {
  display: block;
  color: #5f6368;
  font-size: 24rpx;
}

.picker-value {
  padding: 20rpx 24rpx;
  border-radius: 12rpx;
  background: #f5f6f8;
  color: #202124;
  font-size: 28rpx;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
  margin-top: 32rpx;
}
</style>
