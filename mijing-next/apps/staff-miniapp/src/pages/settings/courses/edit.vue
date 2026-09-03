<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  archiveStaffCourse,
  createStaffCourse,
  createStaffRoom,
  fetchCourseDeletePreflight,
  fetchCourseTags,
  fetchStaffCourse,
  fetchStaffRoomCatalog,
  restoreStaffCourse,
  updateCourseTags,
  updateStaffCourse,
} from "@/api/catalog";
import type { CourseTagItem } from "@/api/catalog";
import { fetchCardFaceLibrary, fetchAllCardProducts } from "@/api/card-products";
import { fetchCardProductCourseRuleSets } from "@/api/compensation";
import type { CardFaceLibraryItem } from "@/api/card-products";
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

// —— 原版 subject-edit 增强 ——
type PanelKey = "openRule" | "description" | "face" | "tags" | "trainer" | "classroom";
const activePanel = ref<PanelKey | null>(null);
// 默认第一款图案「青瓷」（id=0）
const faceStyle = ref<number | null>(0);
const savedFaceGradient = ref<string | null>(null);
const faceLibrary = ref<CardFaceLibraryItem[]>([]);
const supportCardCount = ref<number | null>(null); // 会员卡扣费：已设置X张卡
const deletePreflightCount = ref(0);
const deleteAcknowledged = ref(false);

const FACE_FALLBACK = "linear-gradient(135deg, #5f9ea8 0%, #3c7a86 100%)";
const faceGradient = computed(() => {
  const picked = faceLibrary.value.find((item) => item.id === faceStyle.value);
  return picked?.gradient || savedFaceGradient.value || FACE_FALLBACK;
});

const difficultyNum = computed(() => Number.parseInt(difficulty.value || "0", 10) || 0);

// 原版开课规则摘要：不限制 / 限X人 / 满X人开课
const openRuleSummary = computed(() => {
  const max = maxCapacity.value.trim();
  const min = minCapacity.value.trim();
  if (!max && !min) return isGroup.value ? "" : "不限制";
  const parts: string[] = [];
  if (max) parts.push(`限${max}人`);
  if (min) parts.push(`满${min}人开课`);
  return parts.join("，");
});

function openPanel(panel: PanelKey) {
  activePanel.value = panel;
}

function closePanel() {
  activePanel.value = null;
}

function setDifficulty(star: number) {
  difficulty.value = String(star);
}

async function openFacePicker() {
  if (!faceLibrary.value.length && session.currentSiteId) {
    try {
      const library = await fetchCardFaceLibrary(session.currentSiteId);
      faceLibrary.value = library.items;
    } catch {
      uni.showToast({ title: "图案库加载失败", icon: "none" });
      return;
    }
  }
  openPanel("face");
}

function pickFace(id: number) {
  faceStyle.value = id;
  closePanel();
}

// 会员卡扣费入口（原版 sujectChoiceCard：已设置X张卡 → 卡·课关联）
async function loadSupportCardCount() {
  if (!courseId.value || !session.currentSiteId || !session.can("card-product.catalog.read")) return;
  try {
    const products = await fetchAllCardProducts(session.currentSiteId, undefined, "active");
    const selectedCourseId = courseId.value;
    const rulesByProduct = await fetchCardProductCourseRuleSets(
      session.currentSiteId,
      products.map((product) => product.id),
    );
    supportCardCount.value = products.filter((product) => {
      const rules = rulesByProduct.get(product.id);
      if (rules?.items.length) {
        return rules.items.some((rule) => rule.courseId === selectedCourseId);
      }
      return (product.courseScopeKeys ?? []).includes(selectedCourseId);
    }).length;
  } catch {
    supportCardCount.value = null;
  }
}

// —— 教练弹窗（原版 subject-trainer） ——
function pickCoach(id: number) {
  coachStaffId.value = id;
  closePanel();
}

// —— 教室弹窗（原版 suject-classroom：不指定 + 添加新的） ——
const newRoomName = ref("");
const roomCreating = ref(false);

function pickRoom(id: number) {
  defaultRoomId.value = id;
  closePanel();
}

async function addRoom() {
  const roomName = newRoomName.value.trim();
  if (!roomName) {
    uni.showToast({ title: "请填写教室名称", icon: "none" });
    return;
  }
  if (!session.currentSiteId) return;
  roomCreating.value = true;
  try {
    const created = await createStaffRoom(session.currentSiteId, { name: roomName });
    rooms.value = [...rooms.value, created];
    defaultRoomId.value = created.id;
    newRoomName.value = "";
    closePanel();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "教室创建失败", icon: "none" });
  } finally {
    roomCreating.value = false;
  }
}

// —— 标签库弹窗（原版 tag-popup：选择 + 添加新的） ——
const tagLibrary = ref<CourseTagItem[]>([]);
const newTagName = ref("");
const selectedTags = computed(() => parseTags());

async function openTagPanel() {
  if (session.currentSiteId && session.can("course-catalog.read")) {
    try {
      const response = await fetchCourseTags(session.currentSiteId);
      tagLibrary.value = response.tags;
    } catch {
      tagLibrary.value = [];
    }
  }
  openPanel("tags");
}

function toggleTag(label: string) {
  const current = parseTags();
  const next = current.includes(label) ? current.filter((tag) => tag !== label) : [...current, label];
  tagsText.value = next.join("，");
}

async function addTag() {
  const label = newTagName.value.trim();
  if (!label) {
    uni.showToast({ title: "请填写标签名称", icon: "none" });
    return;
  }
  if (tagLibrary.value.some((tag) => tag.label === label)) {
    uni.showToast({ title: "标签已存在", icon: "none" });
    return;
  }
  const next = [...tagLibrary.value, { key: label, label }];
  if (session.currentSiteId && session.can("course-catalog.write")) {
    try {
      const response = await updateCourseTags(session.currentSiteId, next);
      tagLibrary.value = response.tags;
    } catch (error) {
      uni.showToast({ title: error instanceof Error ? error.message : "标签保存失败", icon: "none" });
      return;
    }
  } else {
    tagLibrary.value = next;
  }
  toggleTag(label);
  newTagName.value = "";
}

// —— 会员卡扣费：跳转独立的卡课扣费规则页 ——
function goCardFee() {
  if (!courseId.value) return;
  if (!session.can("compensation.rule.write")) {
    uni.showToast({ title: "暂无卡课规则编辑权限", icon: "none" });
    return;
  }
  uni.navigateTo({
    url: `/pages/settings/courses/card-fee?courseId=${courseId.value}&name=${encodeURIComponent(name.value.trim())}`,
  });
}

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
  // 必填校验（对标原版：每项缺失都有明确提示，不静默失败）
  if (!name.value.trim()) {
    uni.showToast({ title: "请填写课程名称", icon: "none" });
    return null;
  }
  if (!coachStaffId.value) {
    uni.showToast({ title: "请选择教练", icon: "none" });
    return null;
  }
  if (!durationMinutes.value.trim()) {
    uni.showToast({ title: "请填写课程时长", icon: "none" });
    return null;
  }
  const duration = parsePositiveInt(durationMinutes.value, "课时时长");
  if (duration == null) return null;
  if (!difficulty.value.trim()) {
    uni.showToast({ title: "请选择课程难度", icon: "none" });
    return null;
  }
  if (isGroup.value && !maxCapacity.value.trim()) {
    uni.showToast({ title: "请设置开课规则（最多预约人数）", icon: "none" });
    return null;
  }

  const payload = {
    courseType: courseType.value,
    name: name.value.trim(),
    durationMinutes: duration,
    coachStaffId: coachStaffId.value,
    // 未选择时默认第一款图案「青瓷」
    faceStyle: faceStyle.value ?? 0,
    sortOrder: Number.parseInt(sortOrder.value, 10) || 0,
  } as Parameters<typeof createStaffCourse>[1];

  if (description.value.trim()) payload.description = description.value.trim();
  const parsedDifficulty = parsePositiveInt(difficulty.value, "难度");
  if (parsedDifficulty == null || parsedDifficulty > 5) {
    uni.showToast({ title: "难度为 1-5", icon: "none" });
    return null;
  }
  payload.difficulty = parsedDifficulty;
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
  // 历史课程未设置图案时按默认「青瓷」处理
  faceStyle.value = detail.faceStyle ?? 0;
  savedFaceGradient.value = detail.faceGradient ?? null;
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
      loadSupportCardCount();
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

  // 编辑模式：先校验（缺项直接 toast），通过后再进入保存状态
  if (isEdit.value && courseId.value) {
    const payload = buildUpdatePayload();
    if (!payload) return;
    saving.value = true;
    errorMessage.value = "";
    try {
      course.value = await updateStaffCourse(siteId, courseId.value, payload);
      fillForm(course.value);
      uni.showToast({ title: "已保存", icon: "success" });
      setTimeout(() => uni.navigateBack(), 600);
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : "保存失败";
    } finally {
      saving.value = false;
    }
    return;
  }

  const payload = buildCreatePayload();
  if (!payload) return;
  saving.value = true;
  errorMessage.value = "";
  try {
    await createStaffCourse(siteId, payload);
    uni.showToast({ title: "已创建", icon: "success" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

// 删除弹窗（原版：有未来排课时红字警示 + 勾选「我已清楚，仍要删除」）
const deleteModalVisible = ref(false);

async function openDeleteModal() {
  if (!session.currentSiteId || !courseId.value || !canWrite.value) return;
  deleteAcknowledged.value = false;
  try {
    const preflight = await fetchCourseDeletePreflight(session.currentSiteId, courseId.value);
    deletePreflightCount.value = preflight.futureSessionCount;
  } catch {
    deletePreflightCount.value = 0;
  }
  deleteModalVisible.value = true;
}

async function confirmDelete() {
  if (deletePreflightCount.value > 0 && !deleteAcknowledged.value) return;
  deleteModalVisible.value = false;
  if (!session.currentSiteId || !courseId.value) return;

  saving.value = true;
  errorMessage.value = "";
  try {
    course.value = await archiveStaffCourse(session.currentSiteId, courseId.value);
    fillForm(course.value);
    uni.showToast({ title: "已删除，可稍后恢复", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "删除失败";
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
  // 课程库「创建 团课/私教课」浮动按钮带入类型
  if (!courseId.value && (options?.type === "group" || options?.type === "private")) {
    courseType.value = options.type;
  }
  uni.setNavigationBarTitle({
    title: courseId.value ? "编辑课程" : options?.type === "private" ? "创建私教课" : "创建团课",
  });
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="edit-page">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canRead" mode="permission" text="暂无查看课程库权限" />

    <template v-else>
      <u-alert v-if="isArchived" type="warning" description="该课程已删除，恢复后才可编辑与排课。" />

      <!-- 课程卡预览（原版 subject-card：背景图案 + 名称 + 难度 + 时长/规则） -->
      <view class="course-face" :style="{ background: faceGradient }">
        <view class="face-top-row">
          <text class="face-name">{{ name || "课程名称" }}</text>
          <text v-if="difficultyNum" class="face-stars">{{ "★".repeat(difficultyNum) }}</text>
        </view>
        <view class="face-bottom-row">
          <text class="face-meta">{{ durationMinutes || "60" }}分钟</text>
          <text class="face-meta">{{ isGroup ? (openRuleSummary || "开课规则待设置") : "私教课" }}</text>
        </view>
      </view>
      <view class="change-face-wrap">
        <view class="change-face-btn" @tap="openFacePicker">更换背景</view>
      </view>

      <!-- 行式表单（原版 u-form） -->
      <view class="plain-form">
        <view class="p-row">
          <text class="p-label required">课程名称</text>
          <input v-model="name" class="p-input" :disabled="isArchived" placeholder="如：阿斯汤伽" maxlength="120" />
        </view>

        <view class="p-row" @tap="!isArchived && openPanel('trainer')">
          <text class="p-label required">教练</text>
          <text class="p-value" :class="{ placeholder: !coachStaffId }">{{ coachStaffId ? coachLabel() : "请选择" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>

        <view class="p-row">
          <text class="p-label required">课程时长</text>
          <view class="p-input-wrap">
            <input v-model="durationMinutes" class="p-input right" type="number" :disabled="isArchived" placeholder="如60" />
            <text class="p-unit">分钟</text>
          </view>
        </view>

        <!-- 课程难度：原版 u-rate 星级（#FFA800） -->
        <view class="p-row">
          <text class="p-label required">课程难度</text>
          <view class="star-row">
            <text
              v-for="star in 5"
              :key="star"
              class="star"
              :class="{ active: star <= difficultyNum }"
              @tap="!isArchived && setDifficulty(star)"
            >
              ★
            </text>
          </view>
        </view>

        <view v-if="isGroup" class="p-row" @tap="!isArchived && openPanel('openRule')">
          <text class="p-label required">开课规则</text>
          <text class="p-value" :class="{ placeholder: !openRuleSummary }">
            <template v-if="!openRuleSummary">请选择</template>
            <template v-else>{{ openRuleSummary }}</template>
          </text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>

        <view class="p-row" @tap="!isArchived && openTagPanel()">
          <text class="p-label">分类标签</text>
          <text class="p-value" :class="{ placeholder: !tagsText }">{{ tagsText || "可不填写" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>

        <view v-if="isGroup" class="p-row" @tap="!isArchived && openPanel('classroom')">
          <text class="p-label">选择教室</text>
          <text class="p-value" :class="{ placeholder: !defaultRoomId }">{{ defaultRoomId ? roomLabel() : "可不填写" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>

        <!-- 会员卡扣费：按卡类型配置金额、次数或按日自动分摊 -->
        <view v-if="isEdit" class="p-row" @tap="goCardFee">
          <text class="p-label">会员卡扣费</text>
          <text class="p-value" :class="{ placeholder: !supportCardCount }">
            {{ supportCardCount ? `已设置${supportCardCount}张卡` : "请设置会员卡扣费" }}
          </text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>

        <view class="p-row last" @tap="!isArchived && openPanel('description')">
          <text class="p-label">课程简介</text>
          <text class="p-value" :class="{ placeholder: !description.trim() }">
            {{ description.trim() ? description.trim().slice(0, 16) + (description.trim().length > 16 ? "…" : "") : "无" }}
          </text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
      </view>

      <!-- 保存 / 删除（原版黄底黑字大胶囊） -->
      <view class="btn-box">
        <button v-if="canWrite && !isArchived" class="save-btn" :disabled="saving" @click="save">保存</button>
        <button v-if="canWrite && isEdit && !isArchived" class="delete-btn" :disabled="saving" @click="openDeleteModal">
          删除
        </button>
        <button v-if="canWrite && isArchived" class="save-btn" :disabled="saving" @click="restore">恢复课程</button>
      </view>

      <view class="brand-footer">觅境约课</view>

      <!-- 编辑弹窗 -->
      <u-popup :show="activePanel !== null" mode="bottom" round="20" @close="closePanel">
        <view class="panel">
          <!-- 更换背景（平台图案库） -->
          <template v-if="activePanel === 'face'">
            <text class="panel-title">更换背景</text>
            <view class="face-grid">
              <view
                v-for="item in faceLibrary"
                :key="item.id"
                class="face-cell"
                :class="{ active: faceStyle === item.id }"
                :style="{ background: item.gradient }"
                @tap="pickFace(item.id)"
              >
                <text class="face-cell-name">{{ item.name }}</text>
                <u-icon v-if="faceStyle === item.id" name="checkmark-circle-fill" size="20" color="#ffffff" />
              </view>
            </view>
          </template>

          <!-- 开课规则（原版：限制最多预约人数 / 满(最低)几人开课） -->
          <template v-else-if="activePanel === 'openRule'">
            <text class="panel-title">开课规则</text>
            <view class="panel-inline">
              <text class="panel-text">限制最多预约人数：最高</text>
              <input v-model="maxCapacity" class="panel-input small" type="number" placeholder="人数" />
              <text class="panel-text">人</text>
            </view>
            <view class="panel-inline">
              <text class="panel-text">满(最低)几人开课：最低</text>
              <input v-model="minCapacity" class="panel-input small" type="number" placeholder="不限" />
              <text class="panel-text">人</text>
            </view>
            <text class="panel-desc">人数不足最低时，则自动取消课程</text>
          </template>

          <!-- 分类标签（原版 tag-popup：标签库选择 + 添加新的） -->
          <template v-else-if="activePanel === 'tags'">
            <text class="panel-title">选择标签</text>
            <text class="panel-desc">显示在课程标题后面，通常用来标注重点/推荐，也可用来分类</text>
            <view v-if="tagLibrary.length" class="tag-chips">
              <text
                v-for="tag in tagLibrary"
                :key="tag.key"
                class="tag-chip"
                :class="{ active: selectedTags.includes(tag.label) }"
                @tap="toggleTag(tag.label)"
              >
                {{ tag.label }}
              </text>
            </view>
            <view class="panel-inline">
              <input v-model="newTagName" class="panel-input" placeholder="添加新的标签" maxlength="12" />
              <view class="panel-add" @tap="addTag">
                <u-icon name="plus" size="14" color="#22c788" />
                <text class="panel-add-text">添加新的</text>
              </view>
            </view>
          </template>

          <!-- 教练（原版 subject-trainer：选择老师） -->
          <template v-else-if="activePanel === 'trainer'">
            <text class="panel-title">选择老师</text>
            <text class="panel-desc">选择主要负责此课的教练即可，在「排课」时可以任意更换教练</text>
            <scroll-view v-if="coaches.length" scroll-y class="panel-scroll">
              <view
                v-for="coach in coaches"
                :key="coach.id"
                class="panel-option"
                :class="{ active: coachStaffId === coach.id }"
                @tap="pickCoach(coach.id)"
              >
                <text>{{ coach.displayName }}</text>
                <u-icon v-if="coachStaffId === coach.id" name="checkmark" size="16" color="#22c788" />
              </view>
            </scroll-view>
            <text v-else class="panel-desc danger-text">
              提示：没有可选择的教练，请先在「教练/员工」中添加新的教练
            </text>
          </template>

          <!-- 选择教室（原版 suject-classroom：不指定 + 添加新的） -->
          <template v-else-if="activePanel === 'classroom'">
            <text class="panel-title">选择教室</text>
            <text class="panel-desc">如果有多个教室，则可在此填写</text>
            <scroll-view scroll-y class="panel-scroll">
              <view class="panel-option" :class="{ active: !defaultRoomId }" @tap="pickRoom(0)">
                <text>不指定</text>
                <u-icon v-if="!defaultRoomId" name="checkmark" size="16" color="#22c788" />
              </view>
              <view
                v-for="room in rooms"
                :key="room.id"
                class="panel-option"
                :class="{ active: defaultRoomId === room.id }"
                @tap="pickRoom(room.id)"
              >
                <text>{{ room.name }}</text>
                <u-icon v-if="defaultRoomId === room.id" name="checkmark" size="16" color="#22c788" />
              </view>
            </scroll-view>
            <view class="panel-inline">
              <input v-model="newRoomName" class="panel-input" placeholder="添加新的教室" maxlength="40" />
              <view class="panel-add" @tap="!roomCreating && addRoom()">
                <u-icon name="plus" size="14" color="#22c788" />
                <text class="panel-add-text">{{ roomCreating ? "创建中..." : "添加新的" }}</text>
              </view>
            </view>
          </template>

          <!-- 课程简介 -->
          <template v-else-if="activePanel === 'description'">
            <text class="panel-title">课程简介</text>
            <u-textarea v-model="description" maxlength="2500" height="240" placeholder="介绍课程内容、适合人群等（可不填写）" />
          </template>

          <button class="panel-confirm" @tap="closePanel">确 定</button>
        </view>
      </u-popup>

      <!-- 删除警示弹窗（原版：有排课红字 + 我已清楚勾选） -->
      <u-popup :show="deleteModalVisible" mode="center" round="16" @close="deleteModalVisible = false">
        <view class="del-modal">
          <text class="del-title">警示!确定删除吗？</text>
          <view class="del-content">
            <text v-if="deletePreflightCount > 0" class="del-text danger">
              该课程还有 {{ deletePreflightCount }} 节未开始的排课。删除后，这些排课与会员预约将无法正常进行！
            </text>
            <text v-else class="del-text">删除后，该课程不再出现在排课与卡·课关联中，可稍后恢复。</text>
            <view v-if="deletePreflightCount > 0" class="del-agree" @tap="deleteAcknowledged = !deleteAcknowledged">
              <u-icon
                :name="deleteAcknowledged ? 'checkmark-circle-fill' : 'checkmark-circle'"
                :color="deleteAcknowledged ? '#dc3c5c' : '#bfbfbf'"
                size="20"
              />
              <text class="del-agree-text">我已清楚，仍要删除</text>
            </view>
          </view>
          <view class="del-btns">
            <button class="del-cancel" @tap="deleteModalVisible = false">取消</button>
            <button
              class="del-confirm"
              :class="{ grey: deletePreflightCount > 0 && !deleteAcknowledged }"
              @tap="confirmDelete"
            >
              确定
            </button>
          </view>
        </view>
      </u-popup>
    </template>
  </view>
</template>

<style scoped lang="scss">
.edit-page {
  min-height: 100vh;
  padding: $spacing-md $spacing-md 60rpx;
  box-sizing: border-box;
}

.course-face {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 300rpx;
  padding: 32rpx;
  border-radius: 21rpx;
  box-sizing: border-box;
}

.face-top-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.face-name {
  overflow: hidden;
  color: #fff;
  font-size: 42rpx;
  font-weight: 600;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.face-stars {
  flex-shrink: 0;
  color: #ffa800;
  font-size: 26rpx;
  letter-spacing: 4rpx;
}

.face-bottom-row {
  display: flex;
  gap: 24rpx;
}

.face-meta {
  color: rgba(255, 255, 255, 0.85);
  font-size: 24rpx;
}

.change-face-wrap {
  display: flex;
  justify-content: center;
  margin: 24rpx 0 8rpx;
}

.change-face-btn {
  padding: 14rpx 48rpx;
  border: 2rpx solid $color-brand-yellow;
  border-radius: 40rpx;
  background: $color-surface;
  color: #d9a400;
  font-size: 26rpx;
}

.plain-form {
  margin-top: 8rpx;
  padding: 0 8rpx;
}

.p-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
  min-height: 104rpx;
  padding: 26rpx 4rpx;
  box-sizing: border-box;
  border-bottom: 1rpx solid #efefef;

  &.last {
    border-bottom: none;
  }
}

.p-label {
  flex-shrink: 0;
  width: 170rpx;
  color: $color-text;
  font-size: 30rpx;

  &.required::after {
    content: "*";
    margin-left: 2rpx;
    color: $color-danger;
    font-size: 30rpx;
  }
}

.p-input {
  flex: 1;
  color: $color-text;
  font-size: 28rpx;

  &.right {
    text-align: right;
  }
}

.p-input-wrap {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 12rpx;
}

.p-unit {
  flex-shrink: 0;
  color: $color-text-secondary;
  font-size: 28rpx;
}

.p-value {
  overflow: hidden;
  flex: 1;
  color: $color-text;
  font-size: 28rpx;
  text-align: right;
  white-space: nowrap;
  text-overflow: ellipsis;

  &.placeholder {
    color: #dadada;
  }
}

.star-row {
  display: flex;
  flex: 1;
  justify-content: flex-end;
  gap: 10rpx;
}

.star {
  color: #e8e8eb;
  font-size: 40rpx;

  &.active {
    color: #ffa800;
  }
}

.btn-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 70rpx;
}

.save-btn,
.delete-btn {
  width: 458rpx;
  height: 83rpx;
  line-height: 83rpx;
  border-radius: 42rpx;
  color: $color-text;
  font-size: 32rpx;
  font-weight: 500;
}

.save-btn {
  background: $color-brand-yellow;

  &[disabled] {
    opacity: 0.6;
    color: $color-text;
    background: $color-brand-yellow;
  }
}

.delete-btn {
  margin-top: 39rpx;
  background: $color-surface;
  border: 1rpx solid $color-brand-yellow;
}

.save-btn::after,
.delete-btn::after {
  border: 0;
}

.brand-footer {
  margin: 80rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

.panel {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
  max-height: 70vh;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.panel-desc {
  color: $color-text-tertiary;
  font-size: 24rpx;
  line-height: 1.6;
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

.panel-input {
  flex: 1;
  height: 72rpx;
  padding: 0 20rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 28rpx;

  &.small {
    flex: none;
    width: 130rpx;
    text-align: center;
  }
}

.panel-confirm {
  height: 83rpx;
  margin-top: 8rpx;
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

.face-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20rpx;
}

.face-cell {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  height: 140rpx;
  padding: 16rpx 20rpx;
  border: 3rpx solid transparent;
  border-radius: 16rpx;
  box-sizing: border-box;

  &.active {
    border-color: $color-brand-yellow;
  }
}

.face-cell-name {
  color: #fff;
  font-size: 24rpx;
}

.panel-scroll {
  max-height: 40vh;

  &.tall {
    max-height: 50vh;
  }
}

.panel-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 26rpx 8rpx;
  border-bottom: 1rpx solid #f5f5f5;
  color: #181818;
  font-size: 28rpx;

  &.active {
    color: #22c788;
  }
}

.panel-add {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  gap: 8rpx;
  padding: 12rpx 0;
}

.panel-add-text {
  color: #22c788;
  font-size: 26rpx;
}

.tag-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}

.tag-chip {
  padding: 12rpx 28rpx;
  border: 1rpx solid #e5e5e5;
  border-radius: 999rpx;
  color: #505050;
  font-size: 26rpx;

  &.active {
    border-color: #fbd128;
    background: rgba(251, 209, 40, 0.12);
    color: #d9a400;
  }
}

.danger-text {
  color: #dc3c5c;
}

.del-modal {
  display: flex;
  flex-direction: column;
  width: 620rpx;
  padding: 48rpx 40rpx 40rpx;
  box-sizing: border-box;
}

.del-title {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 500;
}

.del-content {
  margin-top: 18rpx;
}

.del-text {
  color: #989898;
  font-size: 26rpx;
  line-height: 38rpx;

  &.danger {
    color: $color-danger;
  }
}

.del-agree {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-top: 20rpx;
}

.del-agree-text {
  color: $color-text;
  font-size: 28rpx;
}

.del-btns {
  display: flex;
  justify-content: flex-end;
  gap: 20rpx;
  margin-top: 30rpx;
}

.del-cancel,
.del-confirm {
  width: 180rpx;
  height: 70rpx;
  margin: 0;
  line-height: 70rpx;
  border-radius: 35rpx;
  font-size: 28rpx;
}

.del-cancel {
  background: $color-surface;
  border: 1rpx solid $color-border;
  color: $color-text-secondary;
}

.del-confirm {
  background: $color-brand-yellow;
  color: $color-text;

  &.grey {
    opacity: 0.4;
  }
}

.del-cancel::after,
.del-confirm::after {
  border: 0;
}
</style>
