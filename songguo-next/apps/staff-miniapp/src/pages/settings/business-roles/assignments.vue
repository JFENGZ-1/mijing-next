<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import {
  fetchAllCompensationRoles,
  fetchStaffCompensationRoleAssignments,
  fetchStaffCompensationRoleAssignmentSets,
  updateStaffCompensationRoleAssignments,
} from "@/api/compensation";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import FfBottomSheet from "@/components/ff-bottom-sheet/ff-bottom-sheet.vue";
import { useSessionStore } from "@/stores/session";
import type { CompensationRole, StaffCompensationRoleAssignmentItem } from "@/types/compensation";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import { createCommandKey } from "@/utils/command-key";

interface AssignmentRow extends StaffDirectoryListItem {
  compensationRoles: CompensationRole[];
  assignmentItems: StaffCompensationRoleAssignmentItem[];
}

interface AssignmentSafety {
  blockedReason: string;
  protectedRoleIds: number[];
  scheduleNotice: string;
}

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const keyword = ref("");
const roles = ref<CompensationRole[]>([]);
const staff = ref<AssignmentRow[]>([]);
const selectedStaff = ref<AssignmentRow | null>(null);
const selectedRoleIds = ref<number[]>([]);
const showSheet = ref(false);
const pendingStaffId = ref(0);
const canRead = computed(() => session.can("compensation.role.read"));
const canWrite = computed(() => session.can("compensation.role.write"));
const assignmentSafety = computed(() => analyzeAssignments(selectedStaff.value?.assignmentItems ?? []));

const filteredStaff = computed(() => {
  const query = keyword.value.trim().toLowerCase();
  if (!query) return staff.value;
  return staff.value.filter((item) =>
    `${item.displayName}${item.employeeNo}`.toLowerCase().includes(query),
  );
});

async function load() {
  if (!session.currentSiteId || !canRead.value) { loading.value = false; return; }
  loading.value = true;
  try {
    const [roleResponse, staffResponse] = await Promise.all([
      fetchAllCompensationRoles(session.currentSiteId),
      fetchStaffDirectory(session.currentSiteId),
    ]);
    roles.value = roleResponse.filter((item) => item.status === "active");
    const activeStaff = staffResponse.items.filter((item) => item.status === "active");
    const assignments = await fetchStaffCompensationRoleAssignmentSets(
      session.currentSiteId,
      activeStaff.map((item) => item.id),
    );
    if (activeStaff.some((item) => !assignments.get(item.id))) {
      throw new Error("部分员工角色加载失败，请下拉刷新后重试");
    }
    staff.value = activeStaff.map((item) => ({
      ...item,
      compensationRoles: assignments.get(item.id)?.roles ?? [],
      assignmentItems: assignments.get(item.id)?.items ?? [],
    }));
    if (pendingStaffId.value) {
      const target = staff.value.find((item) => item.id === pendingStaffId.value);
      pendingStaffId.value = 0;
      if (target) openAssignment(target);
    }
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "分配信息加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function isReplaceableAssignment(item: StaffCompensationRoleAssignmentItem) {
  return item.state !== "ended"
    && !["archived", "cancelled", "inactive", "ended"].includes(item.status ?? "active");
}

function analyzeAssignments(items: StaffCompensationRoleAssignmentItem[]): AssignmentSafety {
  const nonEndedByRole = new Map<number, StaffCompensationRoleAssignmentItem[]>();
  for (const item of items.filter((assignment) => assignment.state !== "ended")) {
    nonEndedByRole.set(item.roleId, [...(nonEndedByRole.get(item.roleId) ?? []), item]);
  }

  const protectedRoleIds: number[] = [];
  const blockedRoleNames: string[] = [];
  const scheduledRoleNames: string[] = [];
  for (const [roleId, windows] of nonEndedByRole) {
    if (windows.length < 2) continue;
    const replaceable = windows.filter(isReplaceableAssignment);
    const roleName = windows[0]?.roleName || `角色 #${roleId}`;
    if (replaceable.length !== 1) {
      blockedRoleNames.push(roleName);
      continue;
    }
    // The Staff replace endpoint accepts only one writable row per role. When a
    // current archived window and one active scheduled window coexist, resending
    // the active row byte-for-byte leaves every historical/scheduled row intact.
    protectedRoleIds.push(roleId);
    scheduledRoleNames.push(roleName);
  }

  return {
    blockedReason: blockedRoleNames.length
      ? `检测到「${blockedRoleNames.join("、")}」存在多个可写排期，当前界面无法无损表达，已禁止保存。请先在 Web 管理端逐条处理。`
      : "",
    protectedRoleIds,
    scheduleNotice: scheduledRoleNames.length
      ? `「${scheduledRoleNames.join("、")}」同时存在当前与待生效时间窗，将原样保留；为避免误删，不能在此取消这些角色。`
      : "",
  };
}

function editableRoleIds(item: AssignmentRow) {
  const activeIds = item.assignmentItems
    .filter(isReplaceableAssignment)
    .map((assignment) => assignment.roleId);
  return [...new Set(activeIds.length || item.assignmentItems.length
    ? activeIds
    : item.compensationRoles.map((role) => role.id))];
}

function assignmentFingerprint(items: StaffCompensationRoleAssignmentItem[]) {
  return JSON.stringify(items.map((item) => ({
    id: item.id ?? null,
    roleId: item.roleId,
    effectiveFrom: item.effectiveFrom ?? null,
    effectiveUntil: item.effectiveUntil ?? null,
    status: item.status ?? null,
    state: item.state,
    version: item.version ?? null,
  })).sort((left, right) =>
    left.roleId - right.roleId
    || String(left.effectiveFrom).localeCompare(String(right.effectiveFrom))
    || String(left.effectiveUntil).localeCompare(String(right.effectiveUntil))
    || Number(left.id ?? 0) - Number(right.id ?? 0)));
}

function applyAssignmentSet(
  target: AssignmentRow,
  assignmentSet: Awaited<ReturnType<typeof fetchStaffCompensationRoleAssignments>>,
) {
  target.compensationRoles = assignmentSet.roles ?? [];
  target.assignmentItems = assignmentSet.items ?? [];
}

function openAssignment(item: AssignmentRow) {
  if (!canWrite.value) {
    uni.showToast({ title: "暂无员工业务角色分配权限", icon: "none" });
    return;
  }
  selectedStaff.value = item;
  selectedRoleIds.value = editableRoleIds(item);
  showSheet.value = true;
}

function toggleRole(roleId: number) {
  if (assignmentSafety.value.blockedReason) {
    uni.showToast({ title: "存在无法无损表达的排期，当前仅可查看", icon: "none" });
    return;
  }
  if (assignmentSafety.value.protectedRoleIds.includes(roleId)) {
    uni.showToast({ title: "该角色含待生效排期，请在 Web 管理端调整", icon: "none" });
    return;
  }
  selectedRoleIds.value = selectedRoleIds.value.includes(roleId)
    ? selectedRoleIds.value.filter((id) => id !== roleId)
    : [...selectedRoleIds.value, roleId];
}

async function saveAssignment() {
  if (!session.currentSiteId || !selectedStaff.value || saving.value || !canWrite.value) return;
  if (assignmentSafety.value.blockedReason) {
    uni.showToast({ title: "存在冲突排期，已禁止保存", icon: "none" });
    return;
  }
  saving.value = true;
  try {
    const siteId = session.currentSiteId;
    const target = selectedStaff.value;
    // The backend endpoint has no collection-level expectedVersion contract.
    // Re-read immediately before replacement and fail closed if anything changed,
    // instead of presenting the highest row version as an optimistic lock.
    const latest = await fetchStaffCompensationRoleAssignments(siteId, target.id);
    if (assignmentFingerprint(latest.items ?? []) !== assignmentFingerprint(target.assignmentItems)) {
      applyAssignmentSet(target, latest);
      selectedRoleIds.value = editableRoleIds(target);
      uni.showModal({
        title: "排期已更新",
        content: "保存前检测到该员工的业务角色排期已发生变化，已载入最新内容。本次没有写入，请确认后重新操作。",
        showCancel: false,
      });
      return;
    }
    const latestSafety = analyzeAssignments(latest.items ?? []);
    if (latestSafety.blockedReason) {
      applyAssignmentSet(target, latest);
      uni.showModal({ title: "无法安全保存", content: latestSafety.blockedReason, showCancel: false });
      return;
    }

    const assignments = selectedRoleIds.value.map((roleId) => {
      const replaceable = (latest.items ?? []).filter((assignment) =>
        assignment.roleId === roleId && isReplaceableAssignment(assignment),
      );
      if (replaceable.length > 1) throw new Error("检测到重复可写排期，本次未保存");
      const existing = replaceable[0];
      return {
        roleId,
        activeFrom: existing?.effectiveFrom ?? null,
        activeUntil: existing?.effectiveUntil ?? null,
      };
    });
    const response = await updateStaffCompensationRoleAssignments(
      siteId,
      target.id,
      {
        roleIds: selectedRoleIds.value,
        assignments,
        commandKey: createCommandKey(),
        reason: "员工端更新员工业务角色",
      },
    );
    let persisted = response;
    try {
      persisted = await fetchStaffCompensationRoleAssignments(siteId, target.id);
    } catch {
      // The write succeeded; retain the write response and let pull-to-refresh
      // recover the historical rows if this best-effort follow-up read fails.
    }
    applyAssignmentSet(target, persisted);
    showSheet.value = false;
    uni.showToast({ title: "分配已保存", icon: "success" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "保存失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

function typeLabel(role: CompensationRole) {
  return role.type === "delivery" ? "A" : "B";
}

function assignmentStateLabel(item: StaffCompensationRoleAssignmentItem) {
  if (item.state === "scheduled") return "待生效";
  if (item.state === "ended") return "已结束";
  return "当前";
}

function assignmentDateLabel(item: StaffCompensationRoleAssignmentItem) {
  if (!item.effectiveFrom && !item.effectiveUntil) return "长期有效";
  return `${item.effectiveFrom || "不限"} 至 ${item.effectiveUntil || "不限"}`;
}

onShow(async () => {
  if (await requireStaffAuth()) await load();
});

onLoad((options) => { pendingStaffId.value = Number(options?.staffId || 0); });

onPullDownRefresh(async () => {
  await load();
  uni.stopPullDownRefresh();
});
</script>

<template>
  <u-loading-page :loading="loading" />
  <view v-if="!loading && canRead" class="page-container assignment-page">
    <u-search v-model="keyword" placeholder="搜索员工姓名或工号" :show-action="false" bg-color="#ffffff" />
    <u-alert class="hint" type="info" description="A/B 是业务计算角色，可多选；员工原有的系统权限角色不会改变。" />
    <view v-if="filteredStaff.length" class="staff-list">
      <view v-for="item in filteredStaff" :key="item.id" class="staff-row" @tap="openAssignment(item)">
        <view class="avatar">{{ item.displayName.slice(0, 1) }}</view>
        <view class="staff-main">
          <text class="staff-name">{{ item.displayName }}</text>
          <text class="staff-no">{{ item.employeeNo }}</text>
          <view v-if="item.compensationRoles.length" class="role-chips">
            <text v-for="role in item.compensationRoles" :key="role.id" class="chip">
              {{ typeLabel(role) }} · {{ role.name }}
            </text>
          </view>
          <text v-else class="unassigned">未分配业务角色</text>
        </view>
        <u-icon name="arrow-right" size="16" color="#bfbfbf" />
      </view>
    </view>
    <u-empty v-else mode="list" text="暂无可分配员工" />
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无业务角色查看权限" />

  <FfBottomSheet
    v-model:show="showSheet"
    :title="`分配角色 · ${selectedStaff?.displayName || ''}`"
    tips="同一个员工可以同时是多个 A/B 角色；实际结算以课程规则和耗卡归属快照为准。"
    :confirm-disabled="saving || Boolean(assignmentSafety.blockedReason)"
    @confirm="saveAssignment"
  >
    <u-alert
      v-if="assignmentSafety.blockedReason"
      class="safety-alert"
      type="error"
      :description="assignmentSafety.blockedReason"
    />
    <u-alert
      v-else-if="assignmentSafety.scheduleNotice"
      class="safety-alert"
      type="warning"
      :description="assignmentSafety.scheduleNotice"
    />
    <view v-if="roles.length" class="sheet-list">
      <view
        v-for="role in roles"
        :key="role.id"
        class="sheet-role"
        :class="{ blocked: Boolean(assignmentSafety.blockedReason), locked: assignmentSafety.protectedRoleIds.includes(role.id) }"
        @tap="toggleRole(role.id)"
      >
        <view>
          <text class="sheet-role-name">{{ typeLabel(role) }} · {{ role.name }}</text>
          <text class="sheet-role-desc">{{ role.type === "delivery" ? "实际上课者" : "分成归属角色" }}</text>
        </view>
        <view class="role-state">
          <text v-if="assignmentSafety.protectedRoleIds.includes(role.id)" class="schedule-lock">排期锁定</text>
          <u-icon :name="selectedRoleIds.includes(role.id) ? 'checkmark-circle-fill' : 'checkmark-circle'" :color="selectedRoleIds.includes(role.id) ? '#22c788' : '#dadada'" size="24" />
        </view>
      </view>
    </view>
    <u-empty v-else mode="list" text="请先创建业务角色" />
    <view v-if="selectedStaff?.assignmentItems.length" class="assignment-history">
      <text class="history-title">当前与排期记录</text>
      <text class="history-hint">保存前会重新读取最新排期；当前与待生效时间窗会保留原日期，已结束记录仅展示。</text>
      <view v-for="item in selectedStaff.assignmentItems" :key="item.id ?? `${item.roleId}-${item.effectiveFrom}-${item.effectiveUntil}`" class="history-row">
        <view><text>{{ item.roleType === "delivery" ? "A" : "B" }} · {{ item.roleName }}</text><text>{{ assignmentDateLabel(item) }}</text></view>
        <text class="history-state" :class="item.state">{{ assignmentStateLabel(item) }}</text>
      </view>
    </view>
  </FfBottomSheet>
</template>

<style scoped lang="scss">
.assignment-page { padding-bottom: 60rpx; }
.hint { margin-top: 18rpx; }
.staff-list { margin-top: 20rpx; overflow: hidden; background: #fff; border-radius: $radius-lg; }
.staff-row { display: flex; align-items: center; gap: 18rpx; padding: 26rpx 22rpx; border-bottom: 1rpx solid #f1f1f1; }
.staff-row:last-child { border-bottom: 0; }
.avatar { display: flex; flex-shrink: 0; align-items: center; justify-content: center; width: 76rpx; height: 76rpx; color: #fff; background: #696b99; border-radius: 50%; font-size: 28rpx; }
.staff-main { flex: 1; min-width: 0; }
.staff-name, .staff-no { display: block; }
.staff-name { font-size: 28rpx; font-weight: 500; }
.staff-no { margin-top: 4rpx; color: $color-text-tertiary; font-size: 21rpx; }
.role-chips { display: flex; flex-wrap: wrap; gap: 8rpx; margin-top: 10rpx; }
.chip { padding: 4rpx 10rpx; color: #7c6400; background: #fff6c7; border-radius: 999rpx; font-size: 20rpx; }
.unassigned { display: block; margin-top: 8rpx; color: $color-danger-soft; font-size: 21rpx; }
.sheet-role { display: flex; align-items: center; justify-content: space-between; padding: 25rpx 6rpx; border-bottom: 1rpx solid #f1f1f1; }
.sheet-role.blocked { opacity: .58; }
.sheet-role.locked { background: #fffaf0; }
.sheet-role-name, .sheet-role-desc { display: block; }
.sheet-role-name { font-size: 28rpx; }
.sheet-role-desc { margin-top: 6rpx; color: $color-text-tertiary; font-size: 22rpx; }
.safety-alert { margin-bottom: 18rpx; }
.role-state { display: flex; flex-shrink: 0; align-items: center; gap: 12rpx; }
.schedule-lock { padding: 4rpx 10rpx; color: #8a6300; background: #fff0bd; border-radius: 999rpx; font-size: 19rpx; }
.assignment-history { margin-top: 28rpx; padding-top: 22rpx; border-top: 1rpx solid #ededed; }
.history-title, .history-hint { display: block; }
.history-title { font-size: 26rpx; font-weight: 600; }
.history-hint { margin-top: 7rpx; color: $color-text-tertiary; font-size: 20rpx; line-height: 30rpx; }
.history-row { display: flex; align-items: center; justify-content: space-between; padding: 18rpx 0; border-bottom: 1rpx solid #f3f3f3; }
.history-row view text { display: block; font-size: 23rpx; }
.history-row view text:last-child { margin-top: 4rpx; color: $color-text-tertiary; font-size: 19rpx; }
.history-state { padding: 4rpx 10rpx; color: #786100; background: #fff5c2; border-radius: 999rpx; font-size: 19rpx; }
.history-state.scheduled { color: #2c63a6; background: #eaf3ff; }
.history-state.ended { color: $color-text-tertiary; background: #f0f0f0; }
</style>
