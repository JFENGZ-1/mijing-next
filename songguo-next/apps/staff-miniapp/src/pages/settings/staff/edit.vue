<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  createStaffDirectoryMember,
  departStaffDirectoryMember,
  fetchStaffDirectoryMember,
  fetchStaffRoleOptions,
  updateStaffDirectoryMember,
} from "@/api/staff-directory";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";
import type { StaffCapability, StaffDirectoryMember, StaffRoleOption } from "@/types/staff-directory";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const staffId = ref<number | null>(null);
const member = ref<StaffDirectoryMember | null>(null);
const roles = ref<StaffRoleOption[]>([]);
const displayName = ref("");
const mobile = ref("");
const gender = ref<"male" | "female">("female");
const roleId = ref<number | null>(null);
const coachChecked = ref(true);
const salesChecked = ref(false);

const isEdit = computed(() => staffId.value !== null);
const pageTitle = computed(() => (isEdit.value ? "编辑员工" : "添加员工"));
const canWrite = computed(() => session.can("staff.directory.write"));
const canDepart = computed(() => session.can("staff.departure.soft"));
const selectedRoleName = computed(() => roles.value.find((role) => role.id === roleId.value)?.name || "请选择角色");

function capabilities(): StaffCapability[] {
  const values: StaffCapability[] = [];
  if (coachChecked.value) values.push("coach");
  if (salesChecked.value) values.push("sales");
  return values;
}

async function loadRoles() {
  roles.value = await fetchStaffRoleOptions();
  if (!roleId.value && roles.value.length) {
    roleId.value = roles.value[0].id;
  }
}

async function loadMember() {
  if (!session.currentSiteId || !staffId.value) return;
  member.value = await fetchStaffDirectoryMember(session.currentSiteId, staffId.value);
  displayName.value = member.value.displayName;
  mobile.value = member.value.mobile || "";
  gender.value = member.value.gender || "female";
  roleId.value = member.value.role?.id || null;
  coachChecked.value = member.value.capabilities.includes("coach");
  salesChecked.value = member.value.capabilities.includes("sales");
}

async function load() {
  if (!canWrite.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    await loadRoles();
    if (isEdit.value) await loadMember();
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "员工资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!session.currentSiteId || !roleId.value || !displayName.value.trim()) {
    uni.showToast({ title: "请填写姓名并选择角色", icon: "none" });
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && member.value) {
      member.value = await updateStaffDirectoryMember(session.currentSiteId, member.value.id, {
        displayName: displayName.value.trim(),
        mobile: mobile.value.trim() || null,
        gender: gender.value,
        roleId: roleId.value,
        capabilities: capabilities(),
        version: member.value.version,
      });
    } else {
      member.value = await createStaffDirectoryMember(session.currentSiteId, {
        displayName: displayName.value.trim(),
        mobile: mobile.value.trim() || null,
        gender: gender.value,
        roleId: roleId.value,
        capabilities: capabilities(),
      });
      staffId.value = member.value.id;
    }
    uni.showToast({ title: "保存成功", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

async function depart() {
  if (!session.currentSiteId || !member.value) return;
  const confirmed = await new Promise<boolean>((resolve) => {
    uni.showModal({
      title: "确认离职",
      content: member.value?.hasFutureBookings
        ? "该员工仍有未来课程或预约，暂时无法离职。"
        : `确认将「${member.value?.displayName ?? ""}」设为离职吗？`,
      success: (result) => resolve(Boolean(result.confirm)),
    });
  });
  if (!confirmed || member.value.hasFutureBookings) return;
  saving.value = true;
  try {
    await departStaffDirectoryMember(session.currentSiteId, member.value.id);
    uni.showToast({ title: "已设为离职", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "离职操作失败";
  } finally {
    saving.value = false;
  }
}

function selectRole(role: StaffRoleOption) {
  roleId.value = role.id;
}

function openRoleEditor() {
  uni.navigateTo({ url: "/pages/settings/roles/edit" });
}

onLoad((query) => {
  if (query?.id) staffId.value = Number(query.id);
});

onShow(async () => {
  if (await requireStaffAuth()) await load();
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-container">
    <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
    <u-empty v-if="!canWrite" mode="permission" :text="`${pageTitle}需要管理权限`" />

    <template v-else>
      <u-form label-position="left" label-width="160rpx">
        <u-form-item label="姓名">
          <u-input v-model="displayName" placeholder="请输入姓名" />
        </u-form-item>
        <u-form-item label="手机号">
          <u-input v-model="mobile" placeholder="选填" />
        </u-form-item>
        <u-form-item label="性别">
          <u-radio-group v-model="gender">
            <u-radio name="female" label="女" />
            <u-radio name="male" label="男" />
          </u-radio-group>
        </u-form-item>
        <u-form-item label="身份">
          <u-checkbox-group>
            <u-checkbox v-model="coachChecked" label="教练" />
            <u-checkbox v-model="salesChecked" label="会籍顾问" />
          </u-checkbox-group>
        </u-form-item>
        <u-form-item label="角色">
          <text class="role-label">{{ selectedRoleName }}</text>
        </u-form-item>
      </u-form>

      <view class="role-list">
        <view
          v-for="role in roles"
          :key="role.id"
          class="role-item"
          :class="{ active: role.id === roleId }"
          @click="selectRole(role)"
        >
          <text>{{ role.name }}</text>
          <text class="role-meta">{{ role.permissionCount }} 项权限</text>
        </view>
      </view>

      <view class="actions">
        <u-button type="primary" text="保存" @click="save" />
        <u-button
          v-if="canWrite"
          type="info"
          plain
          text="管理自定义角色"
          @click="openRoleEditor"
        />
        <u-button
          v-if="isEdit && canDepart && member?.status === 'active'"
          type="error"
          plain
          text="设为离职"
          @click="depart"
        />
      </view>
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.role-label {
  color: #202124;
}

.role-list {
  margin: 16rpx 0 32rpx;
}

.role-item {
  padding: 20rpx 24rpx;
  margin-bottom: 12rpx;
  border-radius: 12rpx;
  background: #fff;
}

.role-item.active {
  border: 2rpx solid #1a73e8;
}

.role-meta {
  display: block;
  margin-top: 8rpx;
  font-size: 24rpx;
  color: #80868b;
}

.actions {
  display: flex;
  flex-direction: column;
  gap: 16rpx;
}
</style>
