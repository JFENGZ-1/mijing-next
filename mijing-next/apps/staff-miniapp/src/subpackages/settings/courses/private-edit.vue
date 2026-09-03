<script setup lang="ts">
// 添加/编辑私教课 —— 对标原版 pagesImp/subject/subject-personal-edit
// 原版机制：整页大表单，课目/时长/卡扣费全部本地暂存（临时 pcourseId + storage 回传），
// 点「保存」一次性提交 savePrivateCourse。
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import { deletePrivateCoach, fetchPrivateCoaches, savePrivateCoachFull } from "@/api/catalog";
import type { CoachBookingWindow, CoachPrivateFee, CoachPrivateProfile } from "@/api/catalog";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffDirectoryListItem } from "@/types/staff-directory";

const session = useSessionStore();
const checking = ref(true);
const loading = ref(true);
const saving = ref(false);
const profileId = ref(0);
const profile = ref<CoachPrivateProfile | null>(null);

const coachStaffId = ref(0);
const coachName = ref("");
const tagText = ref("");
const experience = ref("");
const specialty = ref("");
const windows = ref<CoachBookingWindow[]>([{ days: [1, 2, 3, 4, 5, 6, 7], start: "08:00", end: "21:00" }]);
const subjectOn = ref(false); // 私教课目开关（关=统一时长统一定价）
const uniformDuration = ref(60);
const uniformFeeList = ref<CoachPrivateFee[]>([]);

// 课目本地草稿（对标原版 courseList：临时 pcourseId）
interface CourseDraft {
  key: string; // 真实 id 字符串或 tmp-xxx
  id?: number;
  name: string;
  durationMinutes: number;
  feeList: CoachPrivateFee[];
}

const courseDrafts = ref<CourseDraft[]>([]);

const isEdit = computed(() => profileId.value > 0);
const canWrite = computed(() => session.can("course-catalog.write"));

const WEEK_NAMES = ["", "周一", "周二", "周三", "周四", "周五", "周六", "周日"];
const TAG_PRESETS = ["金牌", "明星", "资深", "冠军", "新锐"];

function windowWeekLabel(window: CoachBookingWindow) {
  const days = [...window.days].sort((a, b) => a - b);
  if (!days.length) return "";
  const continuous = days.length > 2 && days[days.length - 1] - days[0] === days.length - 1;
  if (days.length === 7) return "周一至周日";
  if (continuous) return `${WEEK_NAMES[days[0]]}至${WEEK_NAMES[days[days.length - 1]]}`;
  return days.map((day) => WEEK_NAMES[day]).join("、");
}

// —— 教练选择弹窗（编辑模式不可换，对标原版 arrow=!isEdit） ——
const coachVisible = ref(false);
const coaches = ref<StaffDirectoryListItem[]>([]);

async function openCoachPicker() {
  if (isEdit.value) return;
  coachVisible.value = true;
  if (!coaches.value.length && session.currentSiteId && session.can("staff.directory.read")) {
    try {
      const response = await fetchStaffDirectory(session.currentSiteId);
      coaches.value = response.items.filter((item) => {
        if (item.status !== "active") return false;
        return item.capabilities.length === 0 || item.capabilities.includes("coach");
      });
    } catch {
      coaches.value = [];
    }
  }
}

function pickCoach(coach: StaffDirectoryListItem) {
  coachStaffId.value = coach.id;
  coachName.value = coach.displayName;
  coachVisible.value = false;
}

// —— 标签弹窗（原版 tag-popup） ——
const tagVisible = ref(false);
const tagDraft = ref("");

function openTagPanel() {
  tagDraft.value = tagText.value;
  tagVisible.value = true;
}

function submitTag() {
  tagText.value = tagDraft.value.trim();
  tagVisible.value = false;
}

// —— 经历/擅长长文本弹窗（原版 subject-desc-popup） ——
const textVisible = ref(false);
const textField = ref<"experience" | "specialty">("experience");
const textDraft = ref("");

function openTextPanel(field: "experience" | "specialty") {
  textField.value = field;
  textDraft.value = field === "experience" ? experience.value : specialty.value;
  textVisible.value = true;
}

function submitText() {
  if (textField.value === "experience") experience.value = textDraft.value.trim();
  else specialty.value = textDraft.value.trim();
  textVisible.value = false;
}

// —— 预约时间弹窗（原版 time-popup1：周圆点 + 起止时间；已被其他时段占用的星期禁选） ——
const timeVisible = ref(false);
const editingWindowIndex = ref(-1);
const draftDays = ref<number[]>([]);
const draftStart = ref("08:00");
const draftEnd = ref("21:00");

// 其他时段占用的星期（原版 disDay：discheck 不可选）
const occupiedDays = computed(() => {
  const occupied = new Set<number>();
  windows.value.forEach((window, index) => {
    if (index === editingWindowIndex.value) return;
    for (const day of window.days) occupied.add(day);
  });
  return occupied;
});

function openTimePanel(index = -1) {
  editingWindowIndex.value = index;
  if (index >= 0) {
    const window = windows.value[index];
    draftDays.value = [...window.days];
    draftStart.value = window.start;
    draftEnd.value = window.end;
  } else {
    draftDays.value = [];
    draftStart.value = "08:00";
    draftEnd.value = "21:00";
  }
  timeVisible.value = true;
}

function toggleDraftDay(day: number) {
  if (occupiedDays.value.has(day)) return;
  draftDays.value = draftDays.value.includes(day)
    ? draftDays.value.filter((item) => item !== day)
    : [...draftDays.value, day];
}

function removeWindow(index: number) {
  windows.value = windows.value.filter((_, itemIndex) => itemIndex !== index);
}

function submitTimePanel() {
  if (!draftDays.value.length) {
    uni.showToast({ title: "请选择星期", icon: "none" });
    return;
  }
  if (draftStart.value >= draftEnd.value) {
    uni.showToast({ title: "结束时间需晚于开始时间", icon: "none" });
    return;
  }
  const window: CoachBookingWindow = {
    days: [...draftDays.value].sort((a, b) => a - b),
    start: draftStart.value,
    end: draftEnd.value,
  };
  if (editingWindowIndex.value >= 0) {
    windows.value = windows.value.map((item, index) => (index === editingWindowIndex.value ? window : item));
  } else {
    windows.value = [...windows.value, window];
  }
  timeVisible.value = false;
}

// —— 课目弹窗（原版 suject-creatcourse-popup：名称 + 时长；统一模式仅时长） ——
const subjectPopupVisible = ref(false);
const subjectPopupNameShow = ref(true);
const editingCourseKey = ref<string | null>(null); // null=新建
const subjectNameDraft = ref("");
const subjectMinuteDraft = ref("60");

function openCreateCourse() {
  editingCourseKey.value = null;
  subjectPopupNameShow.value = true;
  subjectNameDraft.value = "";
  subjectMinuteDraft.value = "60";
  subjectPopupVisible.value = true;
}

function openEditCourse(draft: CourseDraft) {
  editingCourseKey.value = draft.key;
  subjectPopupNameShow.value = true;
  subjectNameDraft.value = draft.name;
  subjectMinuteDraft.value = String(draft.durationMinutes);
  subjectPopupVisible.value = true;
}

function openUniformDuration() {
  editingCourseKey.value = null;
  subjectPopupNameShow.value = false;
  subjectMinuteDraft.value = String(uniformDuration.value);
  subjectPopupVisible.value = true;
}

function submitSubjectPopup() {
  const minutes = Number.parseInt(subjectMinuteDraft.value.trim() || "0", 10);
  if (!Number.isFinite(minutes) || minutes < 1) {
    uni.showToast({ title: "请输入时长", icon: "none" });
    return;
  }
  // 统一模式：仅时长
  if (!subjectPopupNameShow.value) {
    uniformDuration.value = minutes;
    subjectPopupVisible.value = false;
    return;
  }
  const name = subjectNameDraft.value.trim();
  if (!name) {
    uni.showToast({ title: "请输入名称", icon: "none" });
    return;
  }
  if (editingCourseKey.value) {
    courseDrafts.value = courseDrafts.value.map((draft) =>
      draft.key === editingCourseKey.value ? { ...draft, name, durationMinutes: minutes } : draft,
    );
  } else {
    // 对标原版：pcourseId = Date.now() 临时 id，本地暂存
    courseDrafts.value = [
      ...courseDrafts.value,
      { key: `tmp-${Date.now()}`, name, durationMinutes: minutes, feeList: [] },
    ];
  }
  subjectPopupVisible.value = false;
}

// —— 删除课目（本地移除，原版「是否删除这个课程？」） ——
async function removeCourse(draft: CourseDraft) {
  const confirmation = await uni.showModal({
    title: "是否删除这个课程？",
    content: "点击确定后将删除",
  });
  if (!confirmation.confirm) return;
  courseDrafts.value = courseDrafts.value.filter((item) => item.key !== draft.key);
}

// —— 卡扣费（对标原版 sujectChoiceCard：跳选卡页，storage 回传，保存时一并提交） ——
function goPickFee(token: string, name: string, feeList: CoachPrivateFee[]) {
  const fees = encodeURIComponent(
    JSON.stringify(feeList.map((fee) => ({ cardProductId: fee.cardProductId, deductAmount: fee.deductAmount ?? null }))),
  );
  uni.navigateTo({
    url: `/subpackages/settings/courses/card-fee?pick=1&token=${encodeURIComponent(token)}&name=${encodeURIComponent(name)}&fees=${fees}`,
  });
}

function applyFeePick() {
  const picked = uni.getStorageSync("private_fee_pick") as
    | { token: string; arr: CoachPrivateFee[]; checknum: number }
    | "";
  if (!picked || typeof picked !== "object") return;
  try {
    uni.removeStorageSync("private_fee_pick");
  } catch {
    // ignore
  }
  if (picked.token === "uniform") {
    uniformFeeList.value = picked.arr;
    return;
  }
  courseDrafts.value = courseDrafts.value.map((draft) =>
    draft.key === picked.token ? { ...draft, feeList: picked.arr } : draft,
  );
}

function feeText(feeList: CoachPrivateFee[]) {
  return feeList.length ? `已设置${feeList.length}张卡` : "设置会员卡扣费";
}

// —— 加载 / 保存 / 删除 ——
function fillForm(data: CoachPrivateProfile) {
  profile.value = data;
  coachStaffId.value = data.coachStaffId;
  coachName.value = data.coachName || "";
  tagText.value = data.tagText || "";
  experience.value = data.experience || "";
  specialty.value = data.specialty || "";
  windows.value = data.bookingWindows.length
    ? data.bookingWindows.map((window) => ({ ...window, days: [...window.days] }))
    : [];
  subjectOn.value = data.subjectMode === "per_course";
  uniformDuration.value = data.uniformDurationMinutes;
  uniformFeeList.value = data.uniformFeeList ?? [];
  courseDrafts.value = data.courses.map((course) => ({
    key: String(course.id),
    id: course.id,
    name: course.name,
    durationMinutes: course.durationMinutes,
    feeList: course.feeList ?? [],
  }));
}

async function load() {
  loading.value = true;
  try {
    if (profileId.value && session.currentSiteId) {
      const items = await fetchPrivateCoaches(session.currentSiteId);
      const found = items.find((item) => item.id === profileId.value);
      if (found) fillForm(found);
    }
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !canWrite.value) return;
  // 原版校验顺序：老师 → 预约时间 → 私教科目
  if (!coachStaffId.value) {
    uni.showToast({ title: "请选择老师！", icon: "none" });
    return;
  }
  if (!windows.value.length) {
    uni.showToast({ title: "请选择预约时间！", icon: "none" });
    return;
  }
  if (subjectOn.value && !courseDrafts.value.length) {
    uni.showToast({ title: "请设置私教科目", icon: "none" });
    return;
  }

  saving.value = true;
  try {
    const normalizeFees = (feeList: CoachPrivateFee[]) =>
      feeList.map((fee) => ({
        cardProductId: fee.cardProductId,
        deductAmount: fee.deductAmount != null && fee.deductAmount !== "" ? Number(fee.deductAmount) : null,
      }));

    await savePrivateCoachFull(session.currentSiteId, {
      profileId: isEdit.value ? profileId.value : undefined,
      version: isEdit.value ? profile.value?.version : undefined,
      coachStaffId: isEdit.value ? undefined : coachStaffId.value,
      tagText: tagText.value || null,
      experience: experience.value || null,
      specialty: specialty.value || null,
      bookingWindows: windows.value,
      subjectMode: subjectOn.value ? "per_course" : "uniform",
      uniformDurationMinutes: uniformDuration.value,
      uniformFeeList: subjectOn.value ? undefined : normalizeFees(uniformFeeList.value),
      courses: subjectOn.value
        ? courseDrafts.value.map((draft) => ({
            id: draft.id,
            name: draft.name,
            durationMinutes: draft.durationMinutes,
            feeList: normalizeFees(draft.feeList),
          }))
        : undefined,
    });
    uni.showToast({ title: "操作成功！", icon: "none", mask: true });
    setTimeout(() => uni.navigateBack(), 800);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (!profile.value || !session.currentSiteId) return;
  const confirmation = await uni.showModal({
    title: "确认删除这个私教教练吗？",
    content: "点击确定后将删除",
  });
  if (!confirmation.confirm) return;
  saving.value = true;
  try {
    await deletePrivateCoach(session.currentSiteId, profile.value.id);
    uni.showToast({ title: "操作成功！", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "删除失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

const loadedOnce = ref(false);

onLoad((query) => {
  profileId.value = Number(query?.id || 0);
  uni.setNavigationBarTitle({ title: profileId.value ? "编辑私教课" : "添加私教课" });
});

onShow(async () => {
  checking.value = true;
  const authenticated = await requireStaffAuth();
  checking.value = false;
  if (!authenticated) return;
  if (!loadedOnce.value) {
    loadedOnce.value = true;
    await load();
  } else {
    loading.value = false;
  }
  // 选卡页回传（对标原版 subjectkey storage）
  applyFeePick();
});
</script>

<template>
  <u-loading-page :loading="checking || loading || saving" />
  <view v-if="!checking" class="edit-page">
    <u-empty v-if="!canWrite" mode="permission" text="暂无课程库管理权限" />

    <template v-else>
      <!-- 行式表单 -->
      <view class="plain-form">
        <view class="p-row" @tap="openCoachPicker">
          <text class="p-label required">教练</text>
          <text class="p-value" :class="{ placeholder: !coachName }">{{ coachName || "请选择" }}</text>
          <u-icon v-if="!isEdit" name="arrow-right" size="15" color="#bfbfbf" />
        </view>
        <view class="p-row" @tap="openTagPanel">
          <text class="p-label">标签</text>
          <text class="p-value" :class="{ placeholder: !tagText }">{{ tagText || "可不填写" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
        <view class="p-row" @tap="openTextPanel('experience')">
          <text class="p-label">经历</text>
          <text class="p-value" :class="{ placeholder: !experience }">{{ experience || "无" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>
        <view class="p-row" @tap="openTextPanel('specialty')">
          <text class="p-label required">擅长</text>
          <text class="p-value" :class="{ placeholder: !specialty }">{{ specialty || "无" }}</text>
          <u-icon name="arrow-right" size="15" color="#bfbfbf" />
        </view>

        <!-- 预约时间（原版 grid-time：多段 + 添加时段） -->
        <view class="time-block">
          <text class="p-label required">预约时间</text>
          <view class="time-list">
            <view v-for="(window, index) in windows" :key="index" class="time-item">
              <view class="time-item-head">
                <u-icon name="minus-circle" size="22" color="#DC3C5C" @tap="removeWindow(index)" />
                <text class="time-week" @tap="openTimePanel(index)">{{ windowWeekLabel(window) }}</text>
                <u-icon name="arrow-right" size="14" color="#bfbfbf" @tap="openTimePanel(index)" />
              </view>
              <text class="time-range" @tap="openTimePanel(index)">{{ window.start }}~{{ window.end }}</text>
            </view>
            <view class="add-window" @tap="openTimePanel(-1)">
              <u-icon name="plus" size="14" color="#E98900" />
              <text class="add-window-text">添加时段</text>
            </view>
          </view>
        </view>
      </view>

      <!-- 私教课目开关 -->
      <view class="switch-row">
        <text class="p-label required">私教课目</text>
        <switch class="subject-switch" :checked="subjectOn" color="#FBD128" @change="subjectOn = !subjectOn" />
      </view>

      <!-- 关：统一时长统一定价 -->
      <template v-if="!subjectOn">
        <text class="mode-desc">* 默认会员在约私教时不需要选择具体上课内容，仅预约时间</text>
        <view class="uniform-card">
          <text class="uniform-title">统一时长统一定价</text>
          <view class="uniform-right">
            <view class="uniform-line" @tap="openUniformDuration">
              <text>{{ uniformDuration }}分钟</text>
              <u-icon name="arrow-right" size="14" color="#bfbfbf" />
            </view>
            <view class="uniform-line" @tap="goPickFee('uniform', `${coachName || '教练'}私教`, uniformFeeList)">
              <text :class="{ 'fee-set': uniformFeeList.length }">{{ feeText(uniformFeeList) }}</text>
              <u-icon name="arrow-right" size="14" color="#bfbfbf" />
            </view>
          </view>
        </view>
      </template>

      <!-- 开：私教课目列表（本地暂存，保存时一并提交） -->
      <template v-else>
        <text class="mode-desc">* 可添加课程(如体型调整、产后恢复等)，会员约课时必须选择课程</text>
        <view v-for="draft in courseDrafts" :key="draft.key" class="uniform-card subject-card-item">
          <view class="close-icon" @tap="removeCourse(draft)">
            <u-icon name="close" size="14" color="#989898" />
          </view>
          <view class="subject-title-wrap" @tap="openEditCourse(draft)">
            <text class="uniform-title">{{ draft.name }}</text>
            <u-icon name="edit-pen" size="14" color="#bfbfbf" />
          </view>
          <view class="uniform-right">
            <view class="uniform-line" @tap="openEditCourse(draft)">
              <text>{{ draft.durationMinutes }}分钟</text>
              <u-icon name="arrow-right" size="14" color="#bfbfbf" />
            </view>
            <view class="uniform-line" @tap="goPickFee(draft.key, draft.name, draft.feeList)">
              <text :class="{ 'fee-set': draft.feeList.length }">{{ feeText(draft.feeList) }}</text>
              <u-icon name="arrow-right" size="14" color="#bfbfbf" />
            </view>
          </view>
        </view>
        <view class="add-window subject-add" @tap="openCreateCourse">
          <u-icon name="plus" size="14" color="#E98900" />
          <text class="add-window-text">添加课程</text>
        </view>
      </template>

      <!-- 保存 / 删除 -->
      <view class="btn-box">
        <button class="save-btn" :disabled="saving" @tap="save">保存</button>
        <button v-if="isEdit" class="delete-btn" :disabled="saving" @tap="remove">删除</button>
      </view>

      <view class="brand-footer">觅境约课</view>
    </template>

    <!-- ===== 教练选择弹窗 ===== -->
    <u-popup :show="coachVisible" mode="bottom" round="20" @close="coachVisible = false">
      <view class="panel">
        <text class="panel-title">选择老师</text>
        <scroll-view scroll-y class="panel-scroll">
          <view
            v-for="coach in coaches"
            :key="coach.id"
            class="panel-option"
            :class="{ active: coachStaffId === coach.id }"
            @tap="pickCoach(coach)"
          >
            <text>{{ coach.displayName }}</text>
            <u-icon v-if="coachStaffId === coach.id" name="checkmark" size="16" color="#22c788" />
          </view>
          <view v-if="!coaches.length" class="panel-desc danger-text">
            提示：没有可选择的教练，请先在「教练/员工」中添加新的教练
          </view>
        </scroll-view>
      </view>
    </u-popup>

    <!-- ===== 标签弹窗（原版 tag-popup） ===== -->
    <u-popup :show="tagVisible" mode="bottom" round="20" @close="tagVisible = false">
      <view class="panel">
        <text class="panel-title">选择标签</text>
        <text class="panel-desc">显示在教练名字后面，如金牌教练、明星教练</text>
        <view class="tag-chips">
          <text
            v-for="preset in TAG_PRESETS"
            :key="preset"
            class="tag-chip"
            :class="{ active: tagDraft === preset }"
            @tap="tagDraft = tagDraft === preset ? '' : preset"
          >
            {{ preset }}
          </text>
        </view>
        <input v-model="tagDraft" class="panel-input" placeholder="或输入自定义标签" maxlength="12" />
        <button class="panel-confirm" @tap="submitTag">确 定</button>
      </view>
    </u-popup>

    <!-- ===== 经历/擅长长文本弹窗 ===== -->
    <u-popup :show="textVisible" mode="bottom" round="20" @close="textVisible = false">
      <view class="panel">
        <text class="panel-title">{{ textField === "experience" ? "教练经历" : "擅长领域" }}</text>
        <u-textarea
          v-model="textDraft"
          maxlength="2500"
          height="220"
          :placeholder="textField === 'experience' ? '如：入行3年，上千节授课经验' : '如：局部塑形，体态调整等'"
        />
        <button class="panel-confirm" @tap="submitText">确 定</button>
      </view>
    </u-popup>

    <!-- ===== 预约时间弹窗（原版 time-popup1：周圆点 + 起止时间；占用星期禁选） ===== -->
    <u-popup :show="timeVisible" mode="bottom" round="20" @close="timeVisible = false">
      <view class="panel">
        <text class="panel-title">预约时间</text>
        <view class="week-dots">
          <view
            v-for="day in 7"
            :key="day"
            class="week-dot"
            :class="{ active: draftDays.includes(day), disabled: occupiedDays.has(day) }"
            @tap="toggleDraftDay(day)"
          >
            {{ WEEK_NAMES[day].slice(1) }}
          </view>
        </view>
        <text v-if="occupiedDays.size" class="panel-desc">灰色星期已被其他时段占用</text>
        <view class="panel-inline">
          <text class="panel-text">时段</text>
          <picker mode="time" :value="draftStart" @change="draftStart = String($event.detail.value)">
            <text class="date-chip">{{ draftStart }}</text>
          </picker>
          <text class="panel-text">~</text>
          <picker mode="time" :value="draftEnd" @change="draftEnd = String($event.detail.value)">
            <text class="date-chip">{{ draftEnd }}</text>
          </picker>
        </view>
        <button class="panel-confirm" @tap="submitTimePanel">确定</button>
      </view>
    </u-popup>

    <!-- ===== 课目弹窗（原版 suject-creatcourse-popup：名称 + 时长；统一模式仅时长） ===== -->
    <u-popup :show="subjectPopupVisible" mode="bottom" round="20" @close="subjectPopupVisible = false">
      <view class="panel">
        <text class="panel-title">创建课程</text>
        <view v-if="subjectPopupNameShow" class="panel-inline">
          <text class="panel-text field-label">课程名称</text>
          <input v-model="subjectNameDraft" class="panel-input flex-input" placeholder="请输入名称" maxlength="30" />
        </view>
        <view class="panel-inline">
          <text class="panel-text field-label">课程时长</text>
          <input v-model="subjectMinuteDraft" class="panel-input flex-input" type="number" maxlength="3" placeholder="请输入时长" />
          <text class="panel-text">分钟</text>
        </view>
        <button class="panel-confirm" @tap="submitSubjectPopup">确 定</button>
      </view>
    </u-popup>
  </view>
</template>

<style scoped lang="scss">
.edit-page {
  min-height: 100vh;
  padding: 20rpx 28rpx 60rpx;
  background: #fff;
  box-sizing: border-box;
}

.plain-form {
  padding: 0 4rpx;
}

.p-row {
  display: flex;
  align-items: center;
  gap: 14rpx;
  min-height: 104rpx;
  padding: 26rpx 4rpx;
  box-sizing: border-box;
  border-bottom: 1rpx solid #efefef;
}

.p-label {
  flex-shrink: 0;
  width: 150rpx;
  color: $color-text;
  font-size: 30rpx;

  &.required::after {
    content: "*";
    margin-left: 2rpx;
    color: $color-danger;
    font-size: 30rpx;
  }
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

// —— 预约时间块 ——
.time-block {
  display: flex;
  gap: 14rpx;
  padding: 30rpx 4rpx 10rpx;
}

.time-list {
  flex: 1;
}

.time-item {
  margin-bottom: 22rpx;
}

.time-item-head {
  display: flex;
  align-items: center;
  gap: 12rpx;
}

.time-week {
  color: $color-text;
  font-size: 30rpx;
}

.time-range {
  display: block;
  margin: 8rpx 0 0 34rpx;
  color: $color-text-secondary;
  font-size: 28rpx;
}

.add-window {
  display: inline-flex;
  align-items: center;
  gap: 8rpx;
  margin-top: 10rpx;
  padding: 14rpx 28rpx;
  background: #fdf6de;
  border-radius: 999rpx;
}

.add-window-text {
  color: #e98900;
  font-size: 24rpx;
}

.subject-add {
  margin: 30rpx 0 0 4rpx;
}

// —— 私教课目 ——
.switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 20rpx;
  padding: 10rpx 4rpx 0;
}

.subject-switch {
  transform: scale(0.9);
}

.mode-desc {
  display: block;
  margin: 8rpx 4rpx 20rpx;
  color: #989898;
  font-size: 24rpx;
}

.uniform-card {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin: 0 4rpx 24rpx;
  padding: 30rpx 28rpx;
  background: #f5f5f5;
  border-radius: 20rpx;
}

.subject-card-item {
  margin-top: 14rpx;
}

.close-icon {
  position: absolute;
  top: -14rpx;
  right: -10rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44rpx;
  height: 44rpx;
  background: #fff;
  border: 1rpx solid #e5e5e5;
  border-radius: 50%;
}

.subject-title-wrap {
  display: flex;
  align-items: center;
  gap: 8rpx;
  min-width: 0;
}

.uniform-title {
  overflow: hidden;
  color: $color-text;
  font-size: 30rpx;
  font-weight: 500;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.uniform-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  gap: 22rpx;
}

.uniform-line {
  display: flex;
  align-items: center;
  gap: 6rpx;
  color: #989898;
  font-size: 26rpx;
}

.fee-set {
  color: #22c788;
}

// —— 按钮区 ——
.btn-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 70rpx;
}

.save-btn,
.delete-btn {
  width: 640rpx;
  height: 96rpx;
  line-height: 96rpx;
  border-radius: 48rpx;
  color: $color-text;
  font-size: 34rpx;
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
  margin-top: 30rpx;
  background: #fff;
  border: 2rpx solid $color-brand-yellow;
}

.save-btn::after,
.delete-btn::after {
  border: 0;
}

.brand-footer {
  margin: 90rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 弹窗 ——
.panel {
  display: flex;
  flex-direction: column;
  gap: 24rpx;
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

.danger-text {
  color: #dc3c5c;
}

.panel-scroll {
  max-height: 46vh;
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

.tag-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}

.tag-chip {
  padding: 14rpx 30rpx;
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

.panel-input {
  height: 76rpx;
  padding: 0 22rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 28rpx;

  &.flex-input {
    flex: 1;
  }
}

.field-label {
  flex-shrink: 0;
  width: 140rpx;
}

.week-dots {
  display: flex;
  justify-content: space-between;
}

.week-dot {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 84rpx;
  height: 84rpx;
  background: #f5f5f5;
  border-radius: 50%;
  color: #7e7e7e;
  font-size: 26rpx;

  &.active {
    background: $color-brand-yellow;
    color: #181818;
    font-weight: 600;
  }

  &.disabled {
    background: #efefef;
    color: #c8c8c8;
  }
}

.panel-inline {
  display: flex;
  align-items: center;
  gap: 14rpx;
}

.panel-text {
  color: $color-text-secondary;
  font-size: 26rpx;
}

.date-chip {
  display: inline-block;
  padding: 14rpx 30rpx;
  background: $color-page;
  border-radius: 12rpx;
  color: $color-text;
  font-size: 28rpx;
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
</style>
