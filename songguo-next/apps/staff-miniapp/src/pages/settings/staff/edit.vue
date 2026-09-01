<script setup lang="ts">
// 添加/编辑员工 —— 对标原版 pagesImp/shop/staff/staff-edit
// 含：在职/离职切换、身份弹窗、权限（角色）弹窗、删除警示、转让店长、邀请绑定微信
import { computed, ref } from "vue";
import { onLoad, onShareAppMessage, onShow } from "@dcloudio/uni-app";
import {
  createStaffDirectoryMember,
  departStaffDirectoryMember,
  fetchStaffDirectoryMember,
  fetchStaffRoleOptions,
  transferStaffOwnership,
  updateStaffDirectoryMember,
} from "@/api/staff-directory";
import { fetchPermissionCatalog, fetchStaffRoleDetail, upsertStaffRole } from "@/api/staff-roles";
import type { StaffPermissionCatalogModule } from "@/api/staff-roles";
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
const inviteMode = ref(false);

const displayName = ref("");
const mobile = ref("");
const gender = ref<"male" | "female" | null>(null);
const roleId = ref<number | null>(null);
const coachChecked = ref(true);
const salesChecked = ref(false);

const isEdit = computed(() => staffId.value !== null);
const canWrite = computed(() => session.can("staff.directory.write"));
const canDepart = computed(() => session.can("staff.departure.soft"));
const canTransfer = computed(() => session.can("staff.directory.transfer-ownership"));
const canWriteBusinessRoles = computed(() => session.can("compensation.role.write"));
const isSiteOwner = computed(() => member.value?.isSiteOwner ?? false);
const isDeparted = computed(() => member.value?.status === "departed");

const genderLabel = computed(() => (gender.value === "male" ? "男" : gender.value === "female" ? "女" : ""));
const identName = computed(() => {
  const parts: string[] = [];
  if (coachChecked.value) parts.push("教练");
  if (salesChecked.value) parts.push("会籍顾问");
  return parts.join(" | ");
});
const selectedRoleName = computed(() => roles.value.find((role) => role.id === roleId.value)?.name || "");

// 在职/离职分段（原版 subsection：在职绿 / 离职红）
const sectionIndex = computed(() => (isDeparted.value ? 1 : 0));

function capabilities(): StaffCapability[] {
  const values: StaffCapability[] = [];
  if (coachChecked.value) values.push("coach");
  if (salesChecked.value) values.push("sales");
  return values;
}

async function loadRoles() {
  try {
    roles.value = await fetchStaffRoleOptions();
  } catch {
    roles.value = [];
  }
  if (!roleId.value && roles.value.length) {
    roleId.value = roles.value[0].id;
  }
}

async function loadMember() {
  if (!session.currentSiteId || !staffId.value) return;
  member.value = await fetchStaffDirectoryMember(session.currentSiteId, staffId.value);
  displayName.value = member.value.displayName;
  mobile.value = member.value.mobile || "";
  gender.value = member.value.gender;
  roleId.value = member.value.role?.id || roleId.value;
  coachChecked.value = member.value.capabilities.includes("coach");
  salesChecked.value = member.value.capabilities.includes("sales");
}

async function load() {
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

// —— 性别（原版 action-sheet：请选择性别） ——
function pickGender() {
  if (isDeparted.value) return;
  uni.showActionSheet({
    itemList: ["男", "女"],
    success: (result) => {
      gender.value = result.tapIndex === 0 ? "male" : "female";
    },
  });
}

// —— 身份弹窗（原版 identity-popup：兼教练 / 兼会籍顾问） ——
const identityVisible = ref(false);
const draftCoach = ref(true);
const draftSales = ref(false);

function openIdentity() {
  if (isDeparted.value) return;
  draftCoach.value = coachChecked.value;
  draftSales.value = salesChecked.value;
  identityVisible.value = true;
}

function submitIdentity() {
  coachChecked.value = draftCoach.value;
  salesChecked.value = draftSales.value;
  identityVisible.value = false;
}

function openBusinessRoles() {
  if (!canWriteBusinessRoles.value) {
    uni.showToast({ title: "暂无员工业务角色分配权限", icon: "none" });
    return;
  }
  if (!staffId.value) {
    uni.showToast({ title: "请先保存员工，再分配业务角色", icon: "none" });
    return;
  }
  uni.navigateTo({ url: `/pages/settings/business-roles/assignments?staffId=${staffId.value}` });
}

function onCoachSwitch(event: { detail: { value: boolean } }) {
  draftCoach.value = event.detail.value;
}

function onSalesSwitch(event: { detail: { value: boolean } }) {
  draftSales.value = event.detail.value;
}

// —— 权限弹窗（对标原版 permission-popup：预设角色 + 模块 tab + 权限开关矩阵） ——
const MODULE_LABELS: Record<string, string> = {
  schedule: "排课",
  booking: "约课",
  crm: "会员",
  card: "会员卡",
  catalog: "课程库",
  order: "订单",
  reporting: "报表",
  payroll: "课时费",
  points: "积分",
  export: "导出",
  notification: "通知",
  org: "员工",
  organization: "组织",
  "tenant-config": "场馆设置",
  access: "门店访问",
  platform: "平台",
  identity: "登录",
};
const MODULE_ORDER = Object.keys(MODULE_LABELS);

const permissionVisible = ref(false);
const draftRoleId = ref<number | null>(null);
const permModules = ref<StaffPermissionCatalogModule[]>([]);
const activeModule = ref("");
const checkedPermissionIds = ref<Set<number>>(new Set());
const permDirty = ref(false);
const permLoading = ref(false);
const permSaving = ref(false);

const sortedModules = computed(() =>
  [...permModules.value].sort(
    (a, b) =>
      (MODULE_ORDER.indexOf(a.module) + 1 || 99) - (MODULE_ORDER.indexOf(b.module) + 1 || 99),
  ),
);
const activeModulePermissions = computed(
  () => permModules.value.find((item) => item.module === activeModule.value)?.permissions ?? [],
);
const checkedCount = computed(() => checkedPermissionIds.value.size);

function moduleLabel(module: string) {
  return MODULE_LABELS[module] || module;
}

async function loadRolePermissions(id: number) {
  permLoading.value = true;
  try {
    const detail = await fetchStaffRoleDetail(id);
    checkedPermissionIds.value = new Set(detail.permissions.map((item) => item.id));
    permDirty.value = false;
  } catch {
    checkedPermissionIds.value = new Set();
  } finally {
    permLoading.value = false;
  }
}

async function openPermission() {
  if (isDeparted.value) return;
  if (isSiteOwner.value) return;
  draftRoleId.value = roleId.value;
  permissionVisible.value = true;
  if (!permModules.value.length) {
    try {
      permModules.value = await fetchPermissionCatalog();
      activeModule.value = sortedModules.value[0]?.module || "";
    } catch {
      permModules.value = [];
    }
  }
  if (draftRoleId.value) await loadRolePermissions(draftRoleId.value);
}

async function pickPresetRole(id: number) {
  draftRoleId.value = id;
  await loadRolePermissions(id);
}

function togglePermission(id: number) {
  const next = new Set(checkedPermissionIds.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  checkedPermissionIds.value = next;
  permDirty.value = true;
}

async function submitPermission() {
  // 未调整开关：直接使用所选预设角色（原版：预设即用）
  if (!permDirty.value) {
    roleId.value = draftRoleId.value;
    permissionVisible.value = false;
    return;
  }
  // 调整过开关：保存为该员工的自定义权限角色（原版 isCustom 语义）
  if (!checkedPermissionIds.value.size) {
    uni.showToast({ title: "请至少开启一项权限", icon: "none" });
    return;
  }
  permSaving.value = true;
  try {
    const customName = `自定义-${displayName.value.trim() || member.value?.displayName || "员工"}`;
    const existing = roles.value.find((role) => !role.isSystem && role.name === customName);
    const saved = await upsertStaffRole({
      id: existing?.id,
      name: customName,
      permissionIds: [...checkedPermissionIds.value],
    });
    roles.value = await fetchStaffRoleOptions();
    roleId.value = saved.id;
    permissionVisible.value = false;
    uni.showToast({ title: "已保存为自定义权限", icon: "none" });
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "权限保存失败", icon: "none" });
  } finally {
    permSaving.value = false;
  }
}

function openRoleEditor() {
  permissionVisible.value = false;
  uni.navigateTo({ url: "/pages/settings/roles/edit" });
}

// —— 保存（原版必填校验链） ——
async function save() {
  if (!session.currentSiteId || !canWrite.value) return;
  if (!displayName.value.trim()) {
    uni.showToast({ title: "请输入姓名", icon: "none" });
    return;
  }
  if (!gender.value) {
    uni.showToast({ title: "请选择性别", icon: "none" });
    return;
  }
  if (!mobile.value.trim()) {
    uni.showToast({ title: "请输入手机", icon: "none" });
    return;
  }
  if (!/^1\d{10}$/.test(mobile.value.trim())) {
    uni.showToast({ title: "手机号格式不正确", icon: "none" });
    return;
  }
  if (!identName.value) {
    uni.showToast({ title: "请选择身份", icon: "none" });
    return;
  }
  if (!roleId.value) {
    uni.showToast({ title: "请选择权限", icon: "none" });
    return;
  }

  saving.value = true;
  errorMessage.value = "";
  try {
    if (isEdit.value && member.value) {
      await updateStaffDirectoryMember(session.currentSiteId, member.value.id, {
        displayName: displayName.value.trim(),
        mobile: mobile.value.trim(),
        gender: gender.value,
        roleId: roleId.value,
        capabilities: capabilities(),
        version: member.value.version,
      });
    } else {
      await createStaffDirectoryMember(session.currentSiteId, {
        displayName: displayName.value.trim(),
        mobile: mobile.value.trim(),
        gender: gender.value,
        roleId: roleId.value,
        capabilities: capabilities(),
      });
    }
    uni.showToast({ title: "已保存", icon: "success" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    errorMessage.value = error instanceof Error ? error.message : "保存失败";
  } finally {
    saving.value = false;
  }
}

// —— 删除 / 离职弹窗（原版双模式） ——
const delVisible = ref(false);
const delIsDepart = ref(false); // true=subsection 切离职触发；false=删除按钮触发
const delAcknowledged = ref(false);
const existPlan = computed(() => member.value?.hasFutureBookings === true);

function openDelete(asDepart: boolean) {
  if (!member.value || isSiteOwner.value) return;
  delIsDepart.value = asDepart;
  delAcknowledged.value = false;
  delVisible.value = true;
}

function onSectionTap(index: number) {
  if (!isEdit.value || isSiteOwner.value || isDeparted.value) return;
  if (index === 1) openDelete(true);
}

async function confirmDelete() {
  if (existPlan.value && !delIsDepart.value && !delAcknowledged.value) return;
  if (!session.currentSiteId || !member.value) return;
  delVisible.value = false;
  saving.value = true;
  try {
    await departStaffDirectoryMember(
      session.currentSiteId,
      member.value.id,
      existPlan.value && delAcknowledged.value,
    );
    uni.showToast({ title: delIsDepart.value ? "已设为离职" : "已删除", icon: "none" });
    setTimeout(() => uni.navigateBack(), 600);
  } catch (error) {
    uni.showToast({ title: error instanceof Error ? error.message : "操作失败", icon: "none" });
  } finally {
    saving.value = false;
  }
}

// —— 转让店长（原版：确认弹窗 + 失败弹窗） ——
const transferVisible = ref(false);
const transferChecked = ref(false);
const transferFailVisible = ref(false);
const transferFailReason = ref("");

const showTransferBtn = computed(
  () =>
    isEdit.value
    && canTransfer.value
    && !isSiteOwner.value
    && member.value?.status === "active"
    && member.value?.hasWechatBinding === true,
);

function openTransfer() {
  transferChecked.value = false;
  transferVisible.value = true;
}

async function confirmTransfer() {
  if (!transferChecked.value) {
    uni.showToast({ title: "请点击「我已阅读」", icon: "none" });
    return;
  }
  if (!session.currentSiteId || !member.value) return;
  transferVisible.value = false;
  saving.value = true;
  try {
    await transferStaffOwnership(session.currentSiteId, member.value.id);
    uni.showToast({ title: "转让成功", icon: "none", mask: true });
    setTimeout(() => uni.navigateBack(), 800);
  } catch (error) {
    transferFailReason.value = error instanceof Error ? error.message : "转让失败";
    transferFailVisible.value = true;
  } finally {
    saving.value = false;
  }
}

// —— 邀请绑定微信（原版 invited-share：分享邀请卡片） ——
const showInvite = computed(
  () => isEdit.value && member.value?.status === "active" && member.value?.hasWechatBinding === false,
);

onShareAppMessage(() => ({
  title: `邀请你加入「${session.sites.find((site) => site.id === session.currentSiteId)?.name || "场馆"}」员工端`,
  path: `/pages/invite/accept?sign=${member.value?.inviteSign || ""}`,
}));

function avatarText() {
  return (displayName.value || member.value?.displayName || "员")[0];
}

function tapAvatar() {
  uni.showToast({ title: "头像同步自员工微信，绑定后自动更新", icon: "none" });
}

onLoad((query) => {
  const id = Number(query?.id);
  staffId.value = Number.isFinite(id) && id > 0 ? id : null;
  inviteMode.value = query?.invite === "1";
  uni.setNavigationBarTitle({ title: staffId.value ? "编辑教练" : "添加员工/教练" });
});

onShow(async () => {
  if (await requireStaffAuth()) {
    await load();
    if (inviteMode.value && member.value && !member.value.hasWechatBinding) {
      inviteMode.value = false;
      uni.showToast({ title: "请点击「邀请绑定」转发给该员工", icon: "none" });
    }
  }
});
</script>

<template>
  <u-loading-page :loading="loading || saving" />
  <view v-if="!loading" class="page-shell">
    <view class="body-sheet">
      <u-alert v-if="errorMessage" type="error" :description="errorMessage" />
      <u-empty v-if="!canWrite" mode="permission" text="暂无员工管理权限" />

      <template v-else>
        <!-- 在职/离职分段（原版 u-subsection：在职绿 / 离职红） -->
        <view v-if="isEdit && !isSiteOwner" class="subsection">
          <view
            class="section-item"
            :class="{ 'active-green': sectionIndex === 0 }"
            @tap="onSectionTap(0)"
          >
            在职
          </view>
          <view
            class="section-item"
            :class="{ 'active-red': sectionIndex === 1 }"
            @tap="onSectionTap(1)"
          >
            {{ isDeparted ? "已离职" : "离职" }}
          </view>
        </view>

        <!-- 头像（原版 imghead） -->
        <view class="imghead" @tap="tapAvatar">
          <image v-if="member?.avatarUrl" class="avatar" :src="member.avatarUrl" mode="aspectFill" />
          <view v-else class="avatar placeholder">{{ avatarText() }}</view>
          <view v-if="isEdit" class="avatar-tip">头像同步自微信</view>
        </view>

        <!-- 邀请绑定微信（未绑定时；原版「去邀请」invited-share） -->
        <view v-if="showInvite" class="invite-row">
          <view class="invite-info">
            <text class="invite-title">该员工尚未绑定微信</text>
            <text class="invite-desc">转发邀请卡片给TA，登录后即可使用员工端</text>
          </view>
          <button class="invite-share-btn" open-type="share">邀请绑定</button>
        </view>

        <!-- 行式表单 -->
        <view class="plain-form">
          <view class="p-row">
            <text class="p-label required">姓名</text>
            <input v-model="displayName" class="p-input" :disabled="isDeparted" placeholder="请输入姓名" maxlength="30" />
          </view>
          <view class="p-row" @tap="pickGender">
            <text class="p-label required">性别</text>
            <text class="p-value" :class="{ placeholder: !gender }">{{ genderLabel || "请选择" }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row">
            <text class="p-label required">手机</text>
            <input v-model="mobile" class="p-input" :disabled="isDeparted" type="number" placeholder="请输入手机" maxlength="11" />
          </view>
          <view class="p-row" @tap="openIdentity">
            <text class="p-label required">身份</text>
            <text class="p-value" :class="{ placeholder: !identName }">{{ identName || "请选择" }}</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view v-if="canWriteBusinessRoles" class="p-row" @tap="openBusinessRoles">
            <text class="p-label">业务角色</text>
            <text class="p-value">A / B 多角色分配</text>
            <u-icon name="arrow-right" size="15" color="#bfbfbf" />
          </view>
          <view class="p-row last" @tap="openPermission">
            <text class="p-label required">权限</text>
            <text class="p-value" :class="{ placeholder: !selectedRoleName && !isSiteOwner }">
              <template v-if="isSiteOwner">{{ selectedRoleName || "店长" }}（拥有全部权限）</template>
              <template v-else>{{ selectedRoleName || "请选择" }}</template>
            </text>
            <u-icon v-if="!isSiteOwner" name="arrow-right" size="15" color="#bfbfbf" />
          </view>
        </view>

        <!-- 保存 / 删除 / 转让店长（原版 btn-box） -->
        <view class="btn-box">
          <button v-if="!isDeparted" class="save-btn" :disabled="saving" @tap="save">保存</button>
          <button
            v-if="isEdit && !isSiteOwner && !isDeparted && canDepart"
            class="delete-btn"
            :disabled="saving"
            @tap="openDelete(false)"
          >
            删除
          </button>
          <view v-if="showTransferBtn" class="transfer-btn" @tap="openTransfer">转让店长</view>
        </view>

        <view class="brand-footer">觅境约课</view>
      </template>
    </view>

    <!-- ===== 身份弹窗（原版 identity-popup） ===== -->
    <u-popup :show="identityVisible" mode="bottom" round="20" @close="identityVisible = false">
      <view class="panel">
        <text class="panel-title">选择身份</text>
        <view class="identity-row">
          <view class="identity-info">
            <text class="identity-name">兼教练</text>
            <text class="identity-desc">若是上课的老师，请一定打开；若不是，请关闭</text>
          </view>
          <switch class="identity-switch" :checked="draftCoach" color="#FBD128" @change="onCoachSwitch($event)" />
        </view>
        <view class="identity-line" />
        <view class="identity-row">
          <view class="identity-info">
            <text class="identity-name">兼会籍顾问</text>
            <text class="identity-desc">若是会籍顾问并需统计各自销售额，请打开</text>
          </view>
          <switch class="identity-switch" :checked="draftSales" color="#FBD128" @change="onSalesSwitch($event)" />
        </view>
        <button class="panel-confirm" @tap="submitIdentity">确 定</button>
      </view>
    </u-popup>

    <!-- ===== 权限弹窗（原版 permission-popup：预设角色 + 模块 tab + 开关矩阵） ===== -->
    <u-popup :show="permissionVisible" mode="bottom" round="20" @close="permissionVisible = false">
      <view class="panel perm-panel">
        <text class="panel-title">权限设置</text>
        <view class="perm-tips">
          <u-icon name="bell" size="16" color="#C96B30" />
          <text class="perm-tips-text">
            先选择预设角色，再按需调整下方开关；调整后将保存为该员工的自定义权限
          </text>
        </view>

        <!-- 预设角色 chips（原版 headbut button-list） -->
        <view class="role-chips">
          <view
            v-for="role in roles"
            :key="role.id"
            class="role-chip"
            :class="{ active: draftRoleId === role.id }"
            @tap="pickPresetRole(role.id)"
          >
            <text>{{ role.name }}</text>
            <u-icon v-if="draftRoleId === role.id" name="checkmark-circle-fill" size="18" color="#d9a400" />
          </view>
        </view>

        <!-- 模块 tab（左） + 权限开关（右）（原版 content：title 竖列 + text 开关区） -->
        <view class="perm-body">
          <scroll-view scroll-y class="perm-nav">
            <view
              v-for="moduleItem in sortedModules"
              :key="moduleItem.module"
              class="perm-nav-item"
              :class="{ checked: activeModule === moduleItem.module }"
              @tap="activeModule = moduleItem.module"
            >
              {{ moduleLabel(moduleItem.module) }}
            </view>
          </scroll-view>
          <scroll-view scroll-y class="perm-list">
            <view v-if="permLoading" class="perm-loading">加载中...</view>
            <template v-else>
              <view v-for="perm in activeModulePermissions" :key="perm.id" class="perm-row">
                <view class="perm-info">
                  <text class="perm-name">{{ perm.name }}</text>
                  <text class="perm-code">{{ perm.code }}</text>
                </view>
                <switch
                  class="perm-switch"
                  :checked="checkedPermissionIds.has(perm.id)"
                  color="#FBD128"
                  @change="togglePermission(perm.id)"
                />
              </view>
              <view v-if="!activeModulePermissions.length" class="perm-loading">该模块暂无权限项</view>
            </template>
          </scroll-view>
        </view>

        <view class="perm-footer-row">
          <text class="role-meta">已开启 {{ checkedCount }} 项权限</text>
          <view class="role-manage" @tap="openRoleEditor">管理自定义角色 →</view>
        </view>
        <button class="panel-confirm" :disabled="permSaving" @tap="submitPermission">
          {{ permSaving ? "保存中..." : "确 定" }}
        </button>
      </view>
    </u-popup>

    <!-- ===== 删除/离职弹窗（原版 u-modal 双模式 + existPlan 警示勾选） ===== -->
    <u-popup :show="delVisible" mode="center" round="16" :z-index="10090" @close="delVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">{{ delIsDepart ? "确认该员工离职吗？" : "警示！确认删除该员工吗？" }}</text>
        <view class="confirm-body">
          <template v-if="existPlan && !delIsDepart">
            <text class="confirm-text danger">
              该员工正在任职团课或私教，删除后会致使其任课的数据无法统计且无法找回，建议改为离职状态！
            </text>
            <view class="ack-row" @tap="delAcknowledged = !delAcknowledged">
              <u-icon
                :name="delAcknowledged ? 'checkmark-circle-fill' : 'checkmark-circle'"
                :color="delAcknowledged ? '#dc3c5c' : '#bfbfbf'"
                size="20"
              />
              <text class="ack-text">我已阅读上面信息，仍要删除</text>
            </view>
          </template>
          <text v-else-if="delIsDepart" class="confirm-text">离职后，该员工将被清退出本馆，使其不能再访问!</text>
          <text v-else class="confirm-text">点击确定后将删除，您也可以改为离职状态</text>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="delVisible = false">取消</button>
          <button
            class="btn-ok"
            :class="{ grey: existPlan && !delIsDepart && !delAcknowledged }"
            @tap="confirmDelete"
          >
            确定
          </button>
        </view>
      </view>
    </u-popup>

    <!-- ===== 转让店长确认（原版 confirm-modal） ===== -->
    <u-popup :show="transferVisible" mode="center" round="16" :z-index="10090" @close="transferVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">确认转让店长吗？</text>
        <view class="confirm-body">
          <view class="confirm-text">
            转让后您将失去<text class="inline-danger">本系统权属人身份及店长权限</text>，并成为本店普通员工
          </view>
          <view class="ack-row" @tap="transferChecked = !transferChecked">
            <u-icon
              :name="transferChecked ? 'checkmark-circle-fill' : 'checkmark-circle'"
              :color="transferChecked ? '#ed920f' : '#bfbfbf'"
              size="20"
            />
            <text class="ack-text">我已阅读并知晓，确认转让</text>
          </view>
        </view>
        <view class="confirm-btns">
          <button class="btn-cancel" @tap="transferVisible = false">取消</button>
          <button class="btn-ok" :class="{ grey: !transferChecked }" @tap="confirmTransfer">确定</button>
        </view>
      </view>
    </u-popup>

    <!-- ===== 转让失败（原版 confirmModalFail） ===== -->
    <u-popup :show="transferFailVisible" mode="center" round="16" :z-index="10090" @close="transferFailVisible = false">
      <view class="confirm-modal">
        <text class="confirm-title">转让失败！</text>
        <view class="confirm-body">
          <text class="confirm-text">「{{ displayName }}」{{ transferFailReason }}</text>
          <text class="confirm-text">如无法解决，请联系觅境客服协助您处理</text>
        </view>
        <view class="confirm-btns center">
          <button class="btn-ok" @tap="transferFailVisible = false">知道了</button>
        </view>
      </view>
    </u-popup>
  </view>
</template>

<style scoped lang="scss">
.page-shell {
  min-height: 100vh;
  background: $color-brand-yellow;
}

.body-sheet {
  min-height: 100vh;
  padding: 30rpx 28rpx 60rpx;
  background: #fff;
  border-radius: 20rpx 20rpx 0 0;
  box-sizing: border-box;
}

// —— 在职/离职分段 ——
.subsection {
  display: flex;
  margin: 0 auto 10rpx;
  width: 400rpx;
  border: 1rpx solid #e5e5e5;
  border-radius: 12rpx;
  overflow: hidden;
}

.section-item {
  flex: 1;
  padding: 14rpx 0;
  color: #7e7e7e;
  font-size: 26rpx;
  text-align: center;

  &.active-green {
    background: #22c788;
    color: #fff;
  }

  &.active-red {
    background: #dc3c5c;
    color: #fff;
  }
}

// —— 头像 ——
.imghead {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 30rpx 0 10rpx;
}

.avatar {
  width: 150rpx;
  height: 150rpx;
  border-radius: 20rpx;

  &.placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: $color-page;
    color: $color-text-secondary;
    font-size: 52rpx;
  }
}

.avatar-tip {
  margin-top: 14rpx;
  color: $color-text-tertiary;
  font-size: 22rpx;
}

// —— 邀请 ——
.invite-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 16rpx 0;
  padding: 22rpx 24rpx;
  background: #fdf6ec;
  border-radius: 14rpx;
}

.invite-info {
  flex: 1;
  min-width: 0;
}

.invite-title {
  display: block;
  color: #c96b30;
  font-size: 26rpx;
  font-weight: 500;
}

.invite-desc {
  display: block;
  margin-top: 6rpx;
  color: #c96b30;
  font-size: 22rpx;
  opacity: 0.8;
}

.invite-share-btn {
  flex-shrink: 0;
  height: 56rpx;
  margin: 0 0 0 16rpx;
  padding: 0 26rpx;
  line-height: 56rpx;
  background: #fff;
  border: 1rpx solid #e98932;
  border-radius: 50rpx;
  color: #e98933;
  font-size: 24rpx;
}

.invite-share-btn::after {
  border: 0;
}

// —— 行式表单 ——
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
  width: 130rpx;
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

// —— 按钮区 ——
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

.transfer-btn {
  margin-top: 46rpx;
  color: $color-info;
  font-size: 28rpx;
}

.brand-footer {
  margin: 80rpx 0 20rpx;
  color: #d8d8d8;
  font-size: 26rpx;
  letter-spacing: 6rpx;
  text-align: center;
}

// —— 底部弹窗 ——
.panel {
  display: flex;
  flex-direction: column;
  gap: 26rpx;
  padding: 40rpx 32rpx calc(40rpx + env(safe-area-inset-bottom));
}

.panel-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  color: $color-text;
}

.identity-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.identity-info {
  flex: 1;
  min-width: 0;
  padding-right: 16rpx;
}

.identity-name {
  display: block;
  color: $color-text;
  font-size: 30rpx;
  font-weight: 500;
}

.identity-desc {
  display: block;
  margin-top: 8rpx;
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.identity-switch {
  flex-shrink: 0;
  transform: scale(0.85);
}

.identity-line {
  height: 1rpx;
  background: #f0f0f0;
}

.perm-tips {
  display: flex;
  align-items: flex-start;
  gap: 8rpx;
  padding: 18rpx 20rpx;
  background: #fdf6ec;
  border-radius: 12rpx;
}

.perm-tips-text {
  flex: 1;
  color: #c96b30;
  font-size: 22rpx;
  line-height: 34rpx;
}

.role-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 16rpx;
}

.role-chip {
  display: flex;
  align-items: center;
  gap: 8rpx;
  padding: 14rpx 28rpx;
  border: 1rpx solid $color-border;
  border-radius: 999rpx;
  color: $color-text-secondary;
  font-size: 26rpx;

  &.active {
    border-color: $color-brand-yellow;
    background: rgba(251, 209, 40, 0.14);
    color: #d9a400;
  }
}

.role-meta {
  color: $color-text-tertiary;
  font-size: 24rpx;
}

.role-manage {
  color: $color-info;
  font-size: 26rpx;
}

// —— 权限开关矩阵（原版 permission-popup content） ——
.perm-panel {
  max-height: 88vh;
}

.perm-body {
  display: flex;
  height: 46vh;
  border: 1rpx solid #f0f0f0;
  border-radius: 14rpx;
  overflow: hidden;
}

.perm-nav {
  flex-shrink: 0;
  width: 170rpx;
  height: 100%;
  background: #f7f7f7;
}

.perm-nav-item {
  position: relative;
  padding: 26rpx 16rpx;
  color: #7e7e7e;
  font-size: 24rpx;
  text-align: center;

  &.checked {
    background: #fff;
    color: #181818;
    font-weight: 600;
  }

  &.checked::before {
    content: "";
    position: absolute;
    top: 26rpx;
    bottom: 26rpx;
    left: 0;
    width: 6rpx;
    background: $color-brand-yellow;
    border-radius: 0 4rpx 4rpx 0;
  }
}

.perm-list {
  flex: 1;
  height: 100%;
  padding: 0 20rpx;
  box-sizing: border-box;
}

.perm-loading {
  padding: 40rpx 0;
  color: $color-text-disabled;
  font-size: 24rpx;
  text-align: center;
}

.perm-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 22rpx 0;
  border-bottom: 1rpx solid #f5f5f5;
}

.perm-info {
  flex: 1;
  min-width: 0;
  padding-right: 12rpx;
}

.perm-name {
  display: block;
  color: $color-text;
  font-size: 26rpx;
}

.perm-code {
  display: block;
  overflow: hidden;
  margin-top: 4rpx;
  color: #c8c8c8;
  font-size: 18rpx;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.perm-switch {
  flex-shrink: 0;
  transform: scale(0.75);
}

.perm-footer-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
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

// —— 居中确认弹窗 ——
.confirm-modal {
  display: flex;
  flex-direction: column;
  width: 640rpx;
  padding: 48rpx 40rpx 40rpx;
  box-sizing: border-box;
}

.confirm-title {
  color: $color-text;
  font-size: 34rpx;
  font-weight: 500;
}

.confirm-body {
  margin-top: 18rpx;
}

.confirm-text {
  display: block;
  margin-top: 8rpx;
  color: #989898;
  font-size: 26rpx;
  line-height: 38rpx;

  &.danger {
    color: $color-danger;
    font-weight: 500;
  }
}

.inline-danger {
  color: $color-danger;
  font-weight: 500;
}

.ack-row {
  display: flex;
  align-items: center;
  gap: 10rpx;
  margin-top: 20rpx;
}

.ack-text {
  color: $color-text;
  font-size: 28rpx;
}

.confirm-btns {
  display: flex;
  justify-content: flex-end;
  gap: 20rpx;
  margin-top: 30rpx;

  &.center {
    justify-content: center;
  }
}

.btn-cancel,
.btn-ok {
  width: 180rpx;
  height: 70rpx;
  margin: 0;
  line-height: 70rpx;
  border-radius: 35rpx;
  font-size: 28rpx;
}

.btn-cancel {
  background: $color-surface;
  border: 1rpx solid $color-border;
  color: $color-text-secondary;
}

.btn-ok {
  background: $color-brand-yellow;
  color: $color-text;

  &.grey {
    opacity: 0.4;
  }
}

.btn-cancel::after,
.btn-ok::after {
  border: 0;
}
</style>
