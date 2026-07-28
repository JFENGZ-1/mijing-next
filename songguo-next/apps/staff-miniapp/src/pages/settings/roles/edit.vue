<script setup lang="ts">
import { computed, ref } from "vue";
import { onLoad, onShow } from "@dcloudio/uni-app";
import {
  fetchPermissionCatalog,
  fetchStaffRoleDetail,
  upsertStaffRole,
  type StaffPermissionCatalogModule,
} from "@/api/staff-roles";
import { requireStaffAuth } from "@/auth/guard";
import { useSessionStore } from "@/stores/session";

const session = useSessionStore();
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref("");
const roleId = ref<number | null>(null);
const roleName = ref("自定义角色");
const modules = ref<StaffPermissionCatalogModule[]>([]);
const selectedPermissionIds = ref<number[]>([]);

const canWrite = computed(() => session.can("staff.directory.write"));
const pageTitle = computed(() => (roleId.value ? "编辑角色" : "新建角色"));

function togglePermission(permissionId: number, checked: boolean) {
  if (checked) {
    selectedPermissionIds.value = [...new Set([...selectedPermissionIds.value, permissionId])];
    return;
  }
  selectedPermissionIds.value = selectedPermissionIds.value.filter((id) => id !== permissionId);
}

function isSelected(permissionId: number) {
  return selectedPermissionIds.value.includes(permissionId);
}

async function load() {
  if (!canWrite.value) return;
  loading.value = true;
  errorMessage.value = "";
  try {
    modules.value = await fetchPermissionCatalog();
    if (roleId.value) {
      const role = await fetchStaffRoleDetail(roleId.value);
      roleName.value = role.name;
      selectedPermissionIds.value = role.permissions.map((permission) => permission.id);
    }
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "角色资料加载失败";
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (!roleName.value.trim() || selectedPermissionIds.value.length === 0) {
    uni.showToast({ title: "请填写角色名并选择权限", icon: "none" });
    return;
  }
  saving.value = true;
  errorMessage.value = "";
  try {
    await upsertStaffRole({
      id: roleId.value ?? undefined,
      name: roleName.value.trim(),
      permissionIds: selectedPermissionIds.value,
    });
    uni.showToast({ title: "保存成功", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

onLoad((query) => {
  if (query?.id) roleId.value = Number(query.id);
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
        <u-form-item label="角色名称">
          <u-input v-model="roleName" placeholder="请输入角色名称" />
        </u-form-item>
      </u-form>

      <view v-for="module in modules" :key="module.module" class="module-block">
        <text class="module-title">{{ module.module }}</text>
        <view v-for="permission in module.permissions" :key="permission.id" class="permission-item">
          <u-checkbox
            :checked="isSelected(permission.id)"
            :label="permission.name"
            @change="(checked: boolean) => togglePermission(permission.id, checked)"
          />
          <text class="permission-code">{{ permission.code }}</text>
        </view>
      </view>

      <u-button type="primary" text="保存角色" @click="save" />
    </template>
  </view>
</template>

<style scoped lang="scss">
.page-container {
  padding: 24rpx;
}

.module-block {
  margin-bottom: 24rpx;
  padding: 20rpx;
  border-radius: 12rpx;
  background: #fff;
}

.module-title {
  display: block;
  margin-bottom: 12rpx;
  font-size: 28rpx;
  font-weight: 600;
}

.permission-item {
  padding: 12rpx 0;
  border-top: 1rpx solid #f1f3f4;
}

.permission-code {
  display: block;
  margin-top: 4rpx;
  color: #989898;
  font-size: 22rpx;
}
</style>
