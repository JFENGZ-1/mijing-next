<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onPullDownRefresh, onShow } from "@dcloudio/uni-app";
import {
  fetchAllCompensationRoles,
  fetchStaffCompensationRoleAssignmentSets,
} from "@/api/compensation";
import {
  fetchMemberCardShareAssignments,
  replaceMemberCardShareAssignments,
} from "@/api/member-cards";
import { fetchStaffDirectory } from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import ShareAssignmentEditor from "@/components/share-assignment-editor/share-assignment-editor.vue";
import { useSessionStore } from "@/stores/session";
import type { CompensationRole } from "@/types/compensation";
import type {
  StaffMemberCardIssueShareAssignment,
  StaffMemberCardShareAssignment,
} from "@/types/member-cards";
import type { StaffDirectoryListItem } from "@/types/staff-directory";
import { createCommandKey } from "@/utils/command-key";

interface RoleDateDraft {
  effectiveFrom: string;
  effectiveUntil: string;
}

const session = useSessionStore();
const memberCardId = ref(0);
const cardName = ref("会员卡");
const loading = ref(true);
const saving = ref(false);
const version = ref(0);
const roles = ref<CompensationRole[]>([]);
const staff = ref<StaffDirectoryListItem[]>([]);
const staffRoleIds = ref<Record<number, number[]>>({});
const records = ref<StaffMemberCardShareAssignment[]>([]);
const assignments = ref<StaffMemberCardIssueShareAssignment[]>([]);
const roleDates = ref<Record<number, RoleDateDraft>>({});
const reason = ref("");
const editingScheduledPlan = ref(false);
const editOptionsError = ref("");

const canRead = computed(() => session.can("compensation.rule.read"));
const canWrite = computed(() => session.can("compensation.rule.write"));
const canEdit = computed(() => canWrite.value && !editOptionsError.value);
const selectedRoleIds = computed(() => [...new Set(assignments.value.map((item) => item.roleId))]);

function localToday() {
  const now = new Date();
  const offset = now.getTimezoneOffset() * 60_000;
  return new Date(now.getTime() - offset).toISOString().slice(0, 10);
}

function ensureRoleDate(roleId: number, from = localToday(), until = "") {
  roleDates.value[roleId] ??= { effectiveFrom: from, effectiveUntil: until };
  return roleDates.value[roleId];
}

function initializeDraft(items: StaffMemberCardShareAssignment[]) {
  const scheduled = items.filter((item) => item.effectiveState === "scheduled" && item.status === "active");
  const current = items.filter((item) => item.effectiveState === "current");
  const source = scheduled.length ? scheduled : current;
  editingScheduledPlan.value = scheduled.length > 0;
  assignments.value = source.map((item) => ({
    roleId: item.roleId,
    staffId: item.staffId,
    allocationBps: item.allocationBps,
  }));
  roleDates.value = {};
  for (const item of source) {
    ensureRoleDate(
      item.roleId,
      scheduled.length ? (item.effectiveFrom || localToday()) : localToday(),
      item.effectiveUntil || "",
    );
  }
}

async function load() {
  if (!session.currentSiteId || !memberCardId.value || !canRead.value) {
    loading.value = false;
    return;
  }
  loading.value = true;
  editOptionsError.value = "";
  try {
    const assignmentSet = await fetchMemberCardShareAssignments(session.currentSiteId, memberCardId.value);
    version.value = assignmentSet.version;
    records.value = assignmentSet.items;
    initializeDraft(assignmentSet.items);
    if (canWrite.value) {
      try {
        const [roleList, staffResponse] = await Promise.all([
          fetchAllCompensationRoles(session.currentSiteId),
          fetchStaffDirectory(session.currentSiteId),
        ]);
        roles.value = roleList.filter((role) => role.type === "share" && role.status === "active");
        staff.value = staffResponse.items.filter((item) => item.status === "active");
        const roleSets = await fetchStaffCompensationRoleAssignmentSets(
          session.currentSiteId,
          staff.value.map((item) => item.id),
        );
        if (staff.value.some((item) => !roleSets.get(item.id))) {
          throw new Error("部分员工的 B 角色加载失败，请下拉刷新后重试");
        }
        staffRoleIds.value = Object.fromEntries(staff.value.map((item) => [
          item.id,
          (roleSets.get(item.id)?.items ?? [])
            .filter((assignment) => assignment.roleType === "share" && assignment.state !== "ended")
            .map((assignment) => assignment.roleId),
        ]));
      } catch (error) {
        editOptionsError.value = error instanceof Error ? error.message : "员工与 B 角色选项加载失败";
        roles.value = [];
        staff.value = [];
        staffRoleIds.value = {};
      }
    }
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "耗卡分成归属加载失败", icon: "none" });
  } finally {
    loading.value = false;
  }
}

function onAssignmentsChange(value: StaffMemberCardIssueShareAssignment[]) {
  assignments.value = value;
  for (const roleId of selectedRoleIds.value) ensureRoleDate(roleId);
}

function setRoleDate(roleId: number, field: keyof RoleDateDraft, value: string) {
  ensureRoleDate(roleId)[field] = value;
}

function stateLabel(state: string) {
  return ({ current: "当前", scheduled: "待生效", expired: "已结束" } as Record<string, string>)[state] ?? state;
}

function stateClass(item: StaffMemberCardShareAssignment) {
  return item.effectiveState === "scheduled" ? "scheduled" : item.effectiveState === "expired" ? "expired" : "current";
}

function dateRange(item: StaffMemberCardShareAssignment) {
  return `${item.effectiveFrom || "不限"} 至 ${item.effectiveUntil || "不限"}`;
}

function validate() {
  const seen = new Set<string>();
  const totals = new Map<number, number>();
  for (const assignment of assignments.value) {
    const key = `${assignment.roleId}:${assignment.staffId}`;
    if (seen.has(key)) return "同一 B 角色下不能重复选择员工";
    seen.add(key);
    if (!(staffRoleIds.value[assignment.staffId] ?? []).includes(assignment.roleId)) {
      return "所选员工未拥有对应 B 角色或其角色已结束";
    }
    if (!Number.isInteger(assignment.allocationBps) || assignment.allocationBps < 1 || assignment.allocationBps > 10000) {
      return "每项归属比例需大于 0% 且不超过 100%";
    }
    totals.set(assignment.roleId, (totals.get(assignment.roleId) ?? 0) + assignment.allocationBps);
  }
  const invalidRoleId = [...totals].find(([, total]) => total !== 10000)?.[0];
  if (invalidRoleId) return `${roles.value.find((role) => role.id === invalidRoleId)?.name || "B 角色"} 组内比例必须合计 100%`;
  for (const roleId of selectedRoleIds.value) {
    const dates = ensureRoleDate(roleId);
    if (!dates.effectiveFrom) return "请选择归属生效日期";
    if (dates.effectiveUntil && dates.effectiveUntil < dates.effectiveFrom) return "归属结束日期不能早于生效日期";
  }
  if (reason.value.trim().length < 4) return "变更原因至少 4 个字符";
  return "";
}

async function save() {
  if (!session.currentSiteId || saving.value || !canEdit.value) return;
  const message = validate();
  if (message) {
    uni.showToast({ title: message, icon: "none" });
    return;
  }
  const isClear = assignments.value.length === 0;
  const confirmed = await uni.showModal({
    title: isClear ? "确认清空耗卡分成归属" : "确认替换耗卡分成归属",
    content: isClear
      ? "将立即结束这张会员卡的全部 B 角色归属。历史耗卡结算不会改写，操作会记录当前员工与原因。"
      : `将以 ${assignments.value.length} 条新归属整体替换当前/排期方案。历史结算不变，未来耗卡按新方案计算。`,
    confirmText: isClear ? "确认清空" : "确认替换",
    confirmColor: isClear ? "#d14343" : "#181818",
  });
  if (!confirmed.confirm) return;
  saving.value = true;
  try {
    await replaceMemberCardShareAssignments(session.currentSiteId, memberCardId.value, {
      assignments: assignments.value.map((assignment) => ({
        ...assignment,
        effectiveFrom: ensureRoleDate(assignment.roleId).effectiveFrom,
        effectiveUntil: ensureRoleDate(assignment.roleId).effectiveUntil || null,
      })),
      expectedVersion: version.value,
      reason: reason.value.trim(),
      commandKey: createCommandKey(),
    });
    reason.value = "";
    uni.showToast({ title: "耗卡分成归属已替换", icon: "success" });
    await load();
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "归属替换失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

onLoad((options) => {
  memberCardId.value = Number(options?.memberCardId || 0);
  cardName.value = decodeURIComponent(String(options?.name || "会员卡"));
});
onShow(async () => { if (await requireStaffAuth()) await load(); });
onPullDownRefresh(async () => { await load(); uni.stopPullDownRefresh(); });
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading && canRead" class="page-container share-page">
    <view class="summary-card">
      <text class="card-name">{{ cardName }}</text>
      <text class="summary-text">B 角色按归属的耗卡价值累计提成；这里只管理归属，不在前端计算最终金额。</text>
    </view>

    <view class="history-card">
      <text class="section-title">当前与排期记录</text>
      <view v-if="records.length" class="record-list">
        <view v-for="item in records" :key="item.id" class="record-row">
          <view class="record-main">
            <text>{{ item.roleName || `B 角色 #${item.roleId}` }} · {{ item.staffName || `员工 #${item.staffId}` }}</text>
            <text>{{ item.allocationBps / 100 }}% · {{ dateRange(item) }}</text>
          </view>
          <text class="state" :class="stateClass(item)">{{ stateLabel(item.effectiveState) }}</text>
        </view>
      </view>
      <u-empty v-else mode="data" text="当前没有 B 角色归属" />
    </view>

    <view v-if="canEdit" class="editor-card">
      <text class="section-title">{{ editingScheduledPlan ? "编辑待生效方案" : "设置新归属方案" }}</text>
      <text class="editor-hint">保存采用整组替换；每个 B 角色可多人分配，但角色组内必须合计 100%。</text>
      <ShareAssignmentEditor
        :model-value="assignments"
        :roles="roles"
        :staff="staff"
        :staff-role-ids="staffRoleIds"
        @update:model-value="onAssignmentsChange"
      />

      <view v-for="roleId in selectedRoleIds" :key="roleId" class="date-card">
        <text class="date-title">{{ roles.find((role) => role.id === roleId)?.name || `B 角色 #${roleId}` }} 生效区间</text>
        <picker mode="date" :value="ensureRoleDate(roleId).effectiveFrom" @change="setRoleDate(roleId, 'effectiveFrom', $event.detail.value)">
          <view class="date-row"><text>生效日期</text><text>{{ ensureRoleDate(roleId).effectiveFrom }}</text></view>
        </picker>
        <picker mode="date" :value="ensureRoleDate(roleId).effectiveUntil || ensureRoleDate(roleId).effectiveFrom" @change="setRoleDate(roleId, 'effectiveUntil', $event.detail.value)">
          <view class="date-row"><text>结束日期（可选）</text><text>{{ ensureRoleDate(roleId).effectiveUntil || "长期" }}</text></view>
        </picker>
        <text v-if="ensureRoleDate(roleId).effectiveUntil" class="clear-date" @tap="setRoleDate(roleId, 'effectiveUntil', '')">清除结束日期</text>
      </view>

      <textarea v-model="reason" class="reason" maxlength="200" placeholder="必填：本次归属变更原因" />
      <button class="save-btn" :disabled="saving" @tap="save">{{ assignments.length ? "确认整组替换" : "确认清空归属" }}</button>
    </view>
    <u-alert v-else-if="canWrite" type="error" :description="editOptionsError" />
    <u-alert v-else type="info" description="你可以查看耗卡分成归属，但没有修改权限。" />
  </view>
  <u-empty v-else-if="!loading" mode="permission" text="暂无耗卡分成归属查看权限" />
</template>

<style scoped lang="scss">
.share-page { padding-bottom: 60rpx; }
.summary-card, .history-card, .editor-card { margin-bottom: 18rpx; padding: 24rpx; background: #fff; border-radius: $radius-lg; }
.card-name, .summary-text, .section-title, .editor-hint { display: block; }
.card-name { font-size: 31rpx; font-weight: 600; }
.summary-text, .editor-hint { margin-top: 8rpx; color: $color-text-tertiary; font-size: 21rpx; line-height: 32rpx; }
.section-title { font-size: 28rpx; font-weight: 600; }
.record-list { margin-top: 14rpx; }
.record-row { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; padding: 18rpx 0; border-bottom: 1rpx solid #f1f1f1; }
.record-row:last-child { border-bottom: 0; }
.record-main { flex: 1; min-width: 0; }
.record-main text { display: block; font-size: 23rpx; }
.record-main text:last-child { margin-top: 5rpx; color: $color-text-tertiary; font-size: 20rpx; }
.state { flex-shrink: 0; padding: 4rpx 10rpx; color: #16734d; background: #e7f7ef; border-radius: 999rpx; font-size: 19rpx; }
.state.scheduled { color: #2c63a6; background: #eaf3ff; }
.state.expired { color: $color-text-tertiary; background: #f0f0f0; }
.date-card { margin-top: 16rpx; padding: 18rpx; background: $color-page; border-radius: 14rpx; }
.date-title { display: block; margin-bottom: 8rpx; font-size: 23rpx; font-weight: 600; }
.date-row { display: flex; align-items: center; justify-content: space-between; min-height: 64rpx; color: $color-text-secondary; font-size: 22rpx; border-bottom: 1rpx solid #e8e8e8; }
.clear-date { display: block; margin-top: 12rpx; color: $color-danger; font-size: 20rpx; text-align: right; }
.reason { box-sizing: border-box; width: 100%; min-height: 120rpx; margin-top: 20rpx; padding: 18rpx; background: $color-page; border-radius: 14rpx; font-size: 23rpx; }
.save-btn { height: 76rpx; margin-top: 20rpx; color: $color-text; background: $color-brand-yellow; border-radius: 38rpx; font-size: 26rpx; line-height: 76rpx; }
.save-btn::after { border: 0; }
</style>
