<script setup lang="ts">
import type { CompensationRole } from "@/types/compensation";
import type { StaffMemberCardIssueShareAssignment } from "@/types/member-cards";
import type { StaffDirectoryListItem } from "@/types/staff-directory";

const props = defineProps<{
  roles: CompensationRole[];
  staff: StaffDirectoryListItem[];
  staffRoleIds: Record<number, number[]>;
  modelValue: StaffMemberCardIssueShareAssignment[];
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: StaffMemberCardIssueShareAssignment[]): void;
}>();

function rowsForRole(roleId: number) {
  return props.modelValue.flatMap((assignment, index) =>
    assignment.roleId === roleId ? [{ assignment, index }] : [],
  );
}

function roleTotal(roleId: number) {
  return rowsForRole(roleId).reduce((total, row) => total + row.assignment.allocationBps, 0);
}

function staffName(staffId: number) {
  return props.staff.find((item) => item.id === staffId)?.displayName || `员工 #${staffId}`;
}

function candidates(roleId: number, currentStaffId?: number) {
  const assigned = new Set(rowsForRole(roleId).map((row) => row.assignment.staffId));
  return props.staff.filter((item) =>
    props.staffRoleIds[item.id]?.includes(roleId)
    && (item.id === currentStaffId || !assigned.has(item.id)),
  );
}

function emitAssignments(next: StaffMemberCardIssueShareAssignment[]) {
  emit("update:modelValue", next.map((item) => ({ ...item })));
}

function splitEven(assignments: StaffMemberCardIssueShareAssignment[]) {
  if (!assignments.length) return assignments;
  const base = Math.floor(10000 / assignments.length);
  let remainder = 10000 - base * assignments.length;
  return assignments.map((assignment) => {
    const allocationBps = base + (remainder > 0 ? 1 : 0);
    remainder = Math.max(0, remainder - 1);
    return { ...assignment, allocationBps };
  });
}

function add(role: CompensationRole) {
  const options = candidates(role.id);
  if (!options.length) {
    uni.showToast({ title: `没有更多已分配「${role.name}」的员工`, icon: "none" });
    return;
  }
  uni.showActionSheet({
    itemList: options.map((item) => `${item.displayName} · ${item.employeeNo}`),
    success: ({ tapIndex }) => {
      const picked = options[tapIndex];
      const existing = props.modelValue.filter((item) => item.roleId === role.id);
      const currentTotal = existing.reduce((total, item) => total + item.allocationBps, 0);
      let roleAssignments = [...existing, {
        roleId: role.id,
        staffId: picked.id,
        allocationBps: Math.max(1, 10000 - currentTotal),
      }];
      if (currentTotal >= 10000) roleAssignments = splitEven(roleAssignments);
      emitAssignments([
        ...props.modelValue.filter((item) => item.roleId !== role.id),
        ...roleAssignments,
      ]);
    },
  });
}

function chooseStaff(index: number) {
  const current = props.modelValue[index];
  if (!current) return;
  const options = candidates(current.roleId, current.staffId);
  if (!options.length) return;
  uni.showActionSheet({
    itemList: options.map((item) => `${item.displayName} · ${item.employeeNo}`),
    success: ({ tapIndex }) => {
      const next = [...props.modelValue];
      next[index] = { ...current, staffId: options[tapIndex].id };
      emitAssignments(next);
    },
  });
}

function updateAllocation(index: number, event: { detail: { value: string } }) {
  const current = props.modelValue[index];
  if (!current) return;
  const percent = Number(event.detail.value);
  const allocationBps = Number.isFinite(percent) ? Math.round(percent * 100) : 0;
  const next = [...props.modelValue];
  next[index] = { ...current, allocationBps };
  emitAssignments(next);
}

function remove(index: number) {
  const current = props.modelValue[index];
  if (!current) return;
  const remainingForRole = props.modelValue.filter((item, itemIndex) =>
    itemIndex !== index && item.roleId === current.roleId,
  );
  const normalized = remainingForRole.length ? splitEven(remainingForRole) : [];
  emitAssignments([
    ...props.modelValue.filter((item) => item.roleId !== current.roleId),
    ...normalized,
  ]);
}
</script>

<template>
  <view class="share-editor">
    <view v-for="role in roles" :key="role.id" class="role-group">
      <view class="role-head">
        <view>
          <text class="role-name">B · {{ role.name }}</text>
          <text class="role-hint">同一角色可归属多人，组内比例必须合计 100%</text>
        </view>
        <text v-if="rowsForRole(role.id).length" class="role-total" :class="{ invalid: roleTotal(role.id) !== 10000 }">
          {{ roleTotal(role.id) / 100 }}%
        </text>
      </view>

      <view v-for="row in rowsForRole(role.id)" :key="`${role.id}:${row.assignment.staffId}`" class="assignment-row">
        <view class="staff-picker" @tap="chooseStaff(row.index)">
          <text>{{ staffName(row.assignment.staffId) }}</text>
          <u-icon name="arrow-right" size="14" color="#989898" />
        </view>
        <view class="allocation-input">
          <input :value="row.assignment.allocationBps / 100" type="digit" @input="updateAllocation(row.index, $event)" />
          <text>%</text>
        </view>
        <text class="remove" @tap="remove(row.index)">移除</text>
      </view>

      <button class="add-btn" @tap="add(role)">+ 添加{{ role.name }}归属</button>
    </view>
  </view>
</template>

<style scoped lang="scss">
.role-group { margin-top: 18rpx; padding: 18rpx 20rpx; background: $color-page; border-radius: 14rpx; }
.role-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16rpx; }
.role-name, .role-hint { display: block; }
.role-name { font-size: 25rpx; font-weight: 600; }
.role-hint { margin-top: 5rpx; color: $color-text-tertiary; font-size: 19rpx; line-height: 29rpx; }
.role-total { flex-shrink: 0; color: $color-success; font-size: 22rpx; }
.role-total.invalid { color: $color-danger; }
.assignment-row { display: flex; align-items: center; gap: 12rpx; min-height: 70rpx; margin-top: 12rpx; border-top: 1rpx solid #e9e9e9; }
.staff-picker { display: flex; flex: 1; min-width: 0; align-items: center; gap: 6rpx; color: $color-text-secondary; font-size: 23rpx; }
.staff-picker text { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.allocation-input { display: flex; align-items: center; gap: 4rpx; color: $color-text-secondary; font-size: 22rpx; }
.allocation-input input { width: 94rpx; height: 52rpx; text-align: right; background: $color-surface; border-radius: 9rpx; }
.remove { color: $color-danger; font-size: 20rpx; }
.add-btn { height: 58rpx; margin-top: 12rpx; color: $color-text-secondary; background: $color-surface; border-radius: 29rpx; font-size: 22rpx; line-height: 58rpx; }
.add-btn::after { border: 0; }
</style>
