<script setup lang="ts">
import { Plus, Refresh, Search } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { computed, reactive, ref, watch } from "vue";

import { ApiError } from "@/api/client";
import {
  commandKey,
  formatBasisPoints,
  formatDateTime,
  governanceOptions,
  localDateString,
  scopedCommand,
  scopedList,
  scopeRef,
  type CompensationAssignmentRow,
  type CompensationRoleRow,
  type GovernanceOptions,
  type MemberCardShareAssignmentRow,
  type SessionDeliveryAssignmentRow,
} from "@/api/cardConsumption";
import BusinessScopeGuard from "@/components/BusinessScopeGuard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";
import { useBusinessScopeStore } from "@/stores/businessScope";

const scope = useBusinessScopeStore();
const tab = ref<"roles" | "assignments" | "delivery_assignments" | "card_assignments">("roles");
const query = ref("");
const page = ref(1);
const perPage = 20;
const total = ref(0);
const rows = ref<CompensationRoleRow[]>([]);
const assignments = ref<CompensationAssignmentRow[]>([]);
const cardAssignments = ref<MemberCardShareAssignmentRow[]>([]);
const deliveryAssignments = ref<SessionDeliveryAssignmentRow[]>([]);
const options = ref<GovernanceOptions>({ cardProducts: [], courses: [], compensationRoles: [], staff: [], members: [], memberCards: [], sessions: [] });
const loading = ref(false);
const saving = ref(false);
const roleDialog = ref(false);
const assignmentDialog = ref(false);
const endAssignmentDialog = ref(false);
const cardAssignmentDialog = ref(false);
const deliveryAssignmentDialog = ref(false);
const loadedCardAssignmentId = ref<number | null>(null);
const loadedDeliverySessionId = ref<number | null>(null);
let loadRequestId = 0;

const roleForm = reactive({
  id: null as number | null,
  name: "",
  roleType: "delivery" as "delivery" | "share",
  version: 0,
});
const assignmentForm = reactive({
  roleId: null as number | null,
  staffId: null as number | null,
  effectiveFrom: localDateString(),
  effectiveUntil: "",
});
const endAssignmentForm = reactive({
  assignmentId: null as number | null,
  version: 0,
  staffName: "",
  roleName: "",
  effectiveUntil: localDateString(),
  reason: "",
});
const cardAssignmentForm = reactive({
  memberCardId: null as number | null,
  expectedVersion: 0,
  reason: "",
  assignments: [] as Array<{
    roleId: number | null;
    staffId: number | null;
    allocationPercent: number;
    effectiveFrom: string;
    effectiveUntil: string;
  }>,
});
const deliveryAssignmentForm = reactive({
  sessionId: null as number | null,
  expectedVersion: null as number | null,
  reason: "",
  assignments: [] as Array<{
    staffId: number | null;
    roleId: number | null;
    allocationPercent: number;
    isPrimary: boolean;
  }>,
});

const currentScope = computed(() => scopeRef(scope.tenantId, scope.siteId));
const staffOptions = computed(() => options.value.staff.map((item) => ({
  id: item.id,
  name: item.name,
  employeeNo: item.code ?? "未设置工号",
})));
const deliveryRoles = computed(() => options.value.compensationRoles.filter((item) => item.type === "delivery" && item.status === "active"));
const deliveryAllocationSummary = computed(() => {
  const byRole = new Map<number, number>();
  deliveryAssignmentForm.assignments.forEach((item) => {
    if (item.roleId) byRole.set(item.roleId, (byRole.get(item.roleId) ?? 0) + item.allocationPercent);
  });
  return [...byRole.entries()].map(([roleId, total]) => {
    const roleName = deliveryRoles.value.find((role) => role.id === roleId)?.name ?? `角色 #${roleId}`;
    return `${roleName} ${total.toFixed(2)}%`;
  }).join(" · ") || "待分配";
});
const deliveryAssignmentValid = computed(() => {
  if (!deliveryAssignmentForm.sessionId
    || loadedDeliverySessionId.value !== deliveryAssignmentForm.sessionId
    || deliveryAssignmentForm.expectedVersion === null) return false;
  if (deliveryAssignmentForm.assignments.some((item) => !item.staffId || !item.roleId || item.allocationPercent <= 0)) return false;
  if (deliveryAssignmentForm.assignments.filter((item) => item.isPrimary).length > 1) return false;
  const byRole = new Map<number, number>();
  deliveryAssignmentForm.assignments.forEach((item) => {
    if (item.roleId) byRole.set(item.roleId, (byRole.get(item.roleId) ?? 0) + Math.round(item.allocationPercent * 100));
  });
  return [...byRole.values()].every((total) => total === 10000);
});
const cardAssignmentSummary = computed(() => {
  const byRole = new Map<number, number>();
  cardAssignmentForm.assignments.forEach((item) => {
    if (item.roleId) byRole.set(item.roleId, (byRole.get(item.roleId) ?? 0) + Math.round(item.allocationPercent * 100));
  });
  return [...byRole.entries()].map(([roleId, bps]) => {
    const roleName = options.value.compensationRoles.find((role) => role.id === roleId)?.name ?? `角色 #${roleId}`;
    return `${roleName} ${(bps / 100).toFixed(2).replace(/\.00$/, "")}%`;
  }).join(" · ") || "尚未配置 B 类归属";
});
const cardAssignmentValid = computed(() => {
  if (!cardAssignmentForm.memberCardId || loadedCardAssignmentId.value !== cardAssignmentForm.memberCardId) return false;
  if (cardAssignmentForm.assignments.some((item) => !item.roleId
    || !item.staffId
    || item.allocationPercent <= 0
    || !item.effectiveFrom
    || (item.effectiveUntil !== "" && item.effectiveUntil < item.effectiveFrom))) return false;
  const unique = new Set(cardAssignmentForm.assignments.map((item) => `${item.roleId}:${item.staffId}`));
  if (unique.size !== cardAssignmentForm.assignments.length) return false;
  const byRole = new Map<number, number>();
  const windowByRole = new Map<number, string>();
  cardAssignmentForm.assignments.forEach((item) => {
    if (item.roleId) {
      byRole.set(item.roleId, (byRole.get(item.roleId) ?? 0) + Math.round(item.allocationPercent * 100));
      const window = `${item.effectiveFrom}:${item.effectiveUntil}`;
      const existingWindow = windowByRole.get(item.roleId);
      if (existingWindow === undefined) windowByRole.set(item.roleId, window);
      else if (existingWindow !== window) windowByRole.set(item.roleId, "__conflict__");
    }
  });
  return [...byRole.values()].every((total) => total === 10000)
    && [...windowByRole.values()].every((window) => window !== "__conflict__");
});

function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.payload.message : error instanceof Error ? error.message : "操作失败";
}

async function load() {
  const requestId = ++loadRequestId;
  const requestedScope = currentScope.value;
  const requestedTab = tab.value;
  if (!requestedScope) {
    rows.value = [];
    assignments.value = [];
    deliveryAssignments.value = [];
    total.value = 0;
    return;
  }
  loading.value = true;
  try {
    const data = requestedTab === "roles"
      ? await scopedList<CompensationRoleRow>(requestedScope, "/compensation-roles", {
        query: query.value.trim(), page: page.value, perPage,
      })
      : requestedTab === "assignments"
        ? await scopedList<CompensationAssignmentRow>(requestedScope, "/compensation-role-assignments", {
          query: query.value.trim(), page: page.value, perPage,
        })
        : requestedTab === "delivery_assignments"
          ? await scopedList<SessionDeliveryAssignmentRow>(requestedScope, "/delivery-assignments", {
            query: query.value.trim(), page: page.value, perPage,
          })
          : await scopedList<MemberCardShareAssignmentRow>(requestedScope, "/member-card-share-assignments", {
            query: query.value.trim(), page: page.value, perPage,
          });
    if (requestId !== loadRequestId) return;
    if (requestedTab === "roles") rows.value = data.items as CompensationRoleRow[];
    else if (requestedTab === "assignments") assignments.value = data.items as CompensationAssignmentRow[];
    else if (requestedTab === "delivery_assignments") deliveryAssignments.value = data.items as SessionDeliveryAssignmentRow[];
    else cardAssignments.value = data.items as MemberCardShareAssignmentRow[];
    total.value = data.pagination.total;
  } catch (error) {
    if (requestId !== loadRequestId) return;
    ElMessage.error(errorMessage(error));
  } finally {
    if (requestId === loadRequestId) loading.value = false;
  }
}

async function loadGovernanceOptions() {
  if (!currentScope.value) return;
  options.value = await governanceOptions(currentScope.value);
}

function openRole(row?: CompensationRoleRow) {
  roleForm.id = row?.id ?? null;
  roleForm.name = row?.name ?? "";
  roleForm.roleType = row?.roleType ?? "delivery";
  roleForm.version = row?.version ?? 0;
  roleDialog.value = true;
}

async function saveRole() {
  if (!currentScope.value || !roleForm.name.trim()) return;
  saving.value = true;
  try {
    const body = {
      name: roleForm.name.trim(),
      roleType: roleForm.roleType,
      version: roleForm.version,
      commandKey: commandKey(),
    };
    await scopedCommand(
      currentScope.value,
      roleForm.id ? `/compensation-roles/${roleForm.id}` : "/compensation-roles",
      roleForm.id ? "PUT" : "POST",
      body,
    );
    ElMessage.success(roleForm.id ? "业务角色已更新" : "业务角色已创建");
    roleDialog.value = false;
    await load();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

async function archiveRole(row: CompensationRoleRow) {
  if (!currentScope.value) return;
  try {
    const reason = await ElMessageBox.prompt("归档后不能再用于新的课程或人员分配，历史结算保留。", "归档业务角色", {
      inputPlaceholder: "请填写归档原因",
      inputValidator: (value) => value.trim().length >= 2 || "原因至少 2 个字",
      confirmButtonText: "确认归档",
      cancelButtonText: "取消",
    });
    await scopedCommand(currentScope.value, `/compensation-roles/${row.id}/archive`, "POST", {
      version: row.version,
      reason: reason.value.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("业务角色已归档");
    await load();
  } catch (error) {
    if (error === "cancel" || error === "close") return;
    ElMessage.error(errorMessage(error));
  }
}

async function openAssignment() {
  assignmentForm.roleId = null;
  assignmentForm.staffId = null;
  assignmentForm.effectiveFrom = localDateString();
  assignmentForm.effectiveUntil = "";
  await loadGovernanceOptions();
  assignmentDialog.value = true;
}

function addCardShareRecipient() {
  cardAssignmentForm.assignments.push({
    roleId: options.value.compensationRoles.find((role) => role.type === "share" && role.status === "active")?.id ?? null,
    staffId: null,
    allocationPercent: cardAssignmentForm.assignments.length === 0 ? 100 : 0,
    effectiveFrom: localDateString(),
    effectiveUntil: "",
  });
}

async function loadCardShareAssignments() {
  if (!currentScope.value || !cardAssignmentForm.memberCardId) return;
  const requestedCardId = cardAssignmentForm.memberCardId;
  loadedCardAssignmentId.value = null;
  cardAssignmentForm.expectedVersion = -1;
  cardAssignmentForm.assignments = [];
  try {
    const data = await scopedList<MemberCardShareAssignmentRow>(currentScope.value, "/member-card-share-assignments", {
      memberCardId: requestedCardId,
      status: "active",
      page: 1,
      perPage: 100,
    });
    if (cardAssignmentForm.memberCardId !== requestedCardId) return;
    cardAssignmentForm.expectedVersion = data.scopeVersion ?? data.items[0]?.scopeVersion ?? 0;
    cardAssignmentForm.assignments = data.items.map((item) => ({
      roleId: item.roleId,
      staffId: item.staffId,
      allocationPercent: item.allocationBps / 100,
      effectiveFrom: item.effectiveFrom ?? localDateString(),
      effectiveUntil: item.effectiveUntil ?? "",
    }));
    if (cardAssignmentForm.assignments.length === 0) addCardShareRecipient();
    loadedCardAssignmentId.value = requestedCardId;
  } catch (error) {
    if (cardAssignmentForm.memberCardId === requestedCardId) {
      cardAssignmentForm.expectedVersion = -1;
      cardAssignmentForm.assignments = [];
    }
    ElMessage.error(errorMessage(error));
  }
}

async function openCardAssignment(row?: MemberCardShareAssignmentRow) {
  try {
    await loadGovernanceOptions();
    Object.assign(cardAssignmentForm, {
      memberCardId: row?.memberCardId ?? null,
      expectedVersion: -1,
      reason: "",
      assignments: [],
    });
    loadedCardAssignmentId.value = null;
    if (row) await loadCardShareAssignments();
    else addCardShareRecipient();
    cardAssignmentDialog.value = true;
  } catch (error) {
    ElMessage.error(errorMessage(error));
  }
}

function addDeliveryRecipient() {
  deliveryAssignmentForm.assignments.push({
    staffId: null,
    roleId: deliveryRoles.value[0]?.id ?? null,
    allocationPercent: deliveryAssignmentForm.assignments.length === 0 ? 100 : 0,
    isPrimary: deliveryAssignmentForm.assignments.length === 0,
  });
}

async function loadSessionDeliveryAssignments() {
  if (!currentScope.value || !deliveryAssignmentForm.sessionId) return;
  const requestedSessionId = deliveryAssignmentForm.sessionId;
  loadedDeliverySessionId.value = null;
  deliveryAssignmentForm.expectedVersion = null;
  deliveryAssignmentForm.assignments = [];
  try {
    const data = await scopedList<SessionDeliveryAssignmentRow>(currentScope.value, "/delivery-assignments", {
      sessionId: requestedSessionId,
      page: 1,
      perPage: 100,
    });
    if (deliveryAssignmentForm.sessionId !== requestedSessionId) return;
    deliveryAssignmentForm.assignments = data.items.map((item) => ({
      staffId: item.staffId,
      roleId: item.roleId,
      allocationPercent: item.allocationBps / 100,
      isPrimary: item.isPrimary,
    }));
    deliveryAssignmentForm.expectedVersion = data.items[0]?.sessionVersion
      ?? options.value.sessions.find((item) => item.id === requestedSessionId)?.version
      ?? null;
    if (deliveryAssignmentForm.assignments.length === 0) addDeliveryRecipient();
    loadedDeliverySessionId.value = requestedSessionId;
  } catch (error) {
    if (deliveryAssignmentForm.sessionId === requestedSessionId) {
      deliveryAssignmentForm.expectedVersion = null;
      deliveryAssignmentForm.assignments = [];
    }
    ElMessage.error(errorMessage(error));
  }
}

async function openDeliveryAssignment(row?: SessionDeliveryAssignmentRow) {
  try {
    await loadGovernanceOptions();
    deliveryAssignmentForm.sessionId = row?.sessionId ?? null;
    deliveryAssignmentForm.expectedVersion = row?.sessionVersion ?? null;
    deliveryAssignmentForm.reason = "";
    deliveryAssignmentForm.assignments = [];
    loadedDeliverySessionId.value = null;
    if (row) await loadSessionDeliveryAssignments();
    else addDeliveryRecipient();
    deliveryAssignmentDialog.value = true;
  } catch (error) {
    ElMessage.error(errorMessage(error));
  }
}

async function saveDeliveryAssignments() {
  if (!currentScope.value || !deliveryAssignmentForm.sessionId) return;
  if (!deliveryAssignmentValid.value) {
    ElMessage.warning("请完整选择履约人与 A 类角色；每个角色的分配不能超过 100%，且最多一位主履约人");
    return;
  }
  saving.value = true;
  try {
    await scopedCommand(currentScope.value, `/sessions/${deliveryAssignmentForm.sessionId}/delivery-assignments`, "PUT", {
      assignments: deliveryAssignmentForm.assignments.map((item) => ({
        staffId: item.staffId,
        compensationRoleId: item.roleId,
        allocationBps: Math.round(item.allocationPercent * 100),
        isPrimary: item.isPrimary,
      })),
      expectedVersion: deliveryAssignmentForm.expectedVersion,
      reason: deliveryAssignmentForm.reason.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("场次实际履约人已更新");
    deliveryAssignmentDialog.value = false;
    await load();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

async function saveAssignment() {
  if (!currentScope.value || !assignmentForm.roleId || !assignmentForm.staffId) return;
  saving.value = true;
  try {
    await scopedCommand(currentScope.value, "/compensation-role-assignments", "POST", {
      roleId: assignmentForm.roleId,
      staffId: assignmentForm.staffId,
      effectiveFrom: assignmentForm.effectiveFrom,
      effectiveUntil: assignmentForm.effectiveUntil || null,
      commandKey: commandKey(),
    });
    ElMessage.success("员工业务角色已分配");
    assignmentDialog.value = false;
    await load();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

function openEndAssignment(row: CompensationAssignmentRow) {
  Object.assign(endAssignmentForm, {
    assignmentId: row.id,
    version: row.version,
    staffName: row.staffName,
    roleName: row.roleName,
    effectiveUntil: localDateString(),
    reason: "",
  });
  endAssignmentDialog.value = true;
}

async function endAssignment() {
  if (!currentScope.value || !endAssignmentForm.assignmentId) return;
  saving.value = true;
  try {
    await scopedCommand(currentScope.value, `/compensation-role-assignments/${endAssignmentForm.assignmentId}/end`, "POST", {
      version: endAssignmentForm.version,
      effectiveUntil: endAssignmentForm.effectiveUntil,
      reason: endAssignmentForm.reason.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("分配已结束");
    endAssignmentDialog.value = false;
    await load();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

async function saveCardAssignment() {
  if (!currentScope.value || !cardAssignmentForm.memberCardId || !cardAssignmentValid.value) return;
  if (cardAssignmentForm.assignments.length === 0) {
    try {
      await ElMessageBox.confirm(
        "这会结束该会员卡当前全部 B 类归属。历史耗卡快照与结算不会删除。",
        "确认清空当前归属",
        { type: "warning", confirmButtonText: "确认结束全部归属", cancelButtonText: "取消" },
      );
    } catch (error) {
      if (error === "cancel" || error === "close") return;
      throw error;
    }
  }
  saving.value = true;
  try {
    await scopedCommand(currentScope.value, `/member-cards/${cardAssignmentForm.memberCardId}/share-assignments`, "PUT", {
      assignments: cardAssignmentForm.assignments.map((item) => ({
        compensationRoleId: item.roleId,
        staffId: item.staffId,
        allocationBps: Math.round(item.allocationPercent * 100),
        effectiveFrom: item.effectiveFrom,
        effectiveUntil: item.effectiveUntil || null,
      })),
      expectedVersion: cardAssignmentForm.expectedVersion,
      reason: cardAssignmentForm.reason.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("会员卡 B 类归属已按整卡配置保存");
    cardAssignmentDialog.value = false;
    await load();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

watch([() => scope.tenantId, () => scope.siteId, tab], () => {
  page.value = 1;
  void load();
}, { immediate: true });
</script>

<template>
  <section>
    <PageHeading
      eyebrow="CARD CONSUMPTION / PEOPLE"
      title="业务角色与人员"
      description="A 类角色用于记录实际上课者，B 类角色用于按归属卡参与耗卡分成。业务角色与平台权限角色完全分离。"
    >
      <el-button :icon="Refresh" @click="load">刷新</el-button>
      <el-button
        type="primary"
        :icon="Plus"
        :disabled="!scope.ready"
        @click="tab === 'roles' ? openRole() : tab === 'assignments' ? openAssignment() : tab === 'delivery_assignments' ? openDeliveryAssignment() : openCardAssignment()"
      >{{ tab === "roles" ? "新建业务角色" : tab === "assignments" ? "分配员工角色" : tab === "delivery_assignments" ? "设置场次履约人" : "设置会员卡归属" }}</el-button>
    </PageHeading>

    <BusinessScopeGuard write-hint="业务角色、员工任职、场次实际履约人与会员卡归属均限定在当前场馆。" />

    <div class="governance-tabs">
      <button :class="{ active: tab === 'roles' }" @click="tab = 'roles'">业务角色</button>
      <button :class="{ active: tab === 'assignments' }" @click="tab = 'assignments'">员工任职分配</button>
      <button :class="{ active: tab === 'delivery_assignments' }" @click="tab = 'delivery_assignments'">场次多 A 履约人</button>
      <button :class="{ active: tab === 'card_assignments' }" @click="tab = 'card_assignments'">会员卡 B 类归属</button>
    </div>

    <div class="data-panel">
      <div class="data-toolbar">
        <label class="search-box"><el-icon><Search /></el-icon><input v-model="query" placeholder="搜索角色或员工" @keyup.enter="load" /></label>
        <el-button @click="page = 1; load()">查询</el-button>
        <span class="result-count">共 {{ total }} 条</span>
      </div>

      <el-table v-if="tab === 'roles'" v-loading="loading" :data="rows" class="resource-table">
        <el-table-column prop="name" label="业务角色" min-width="180" />
        <el-table-column label="类型" width="150">
          <template #default="{ row }">{{ row.roleType === "delivery" ? "A · 实际上课者" : "B · 卡归属分成" }}</template>
        </el-table-column>
        <el-table-column prop="assignedStaffCount" label="在职人数" width="100" />
        <el-table-column label="规则版本" width="100"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="最后更新" min-width="160"><template #default="{ row }">{{ formatDateTime(row.updatedAt) }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="150" align="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openRole(row)">编辑</el-button>
            <el-button v-if="row.status === 'active'" link type="danger" @click="archiveRole(row)">归档</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-table v-else-if="tab === 'assignments'" v-loading="loading" :data="assignments" class="resource-table">
        <el-table-column prop="staffName" label="员工" min-width="160" />
        <el-table-column prop="employeeNo" label="工号" width="120" />
        <el-table-column prop="roleName" label="业务角色" min-width="160" />
        <el-table-column label="类型" width="130"><template #default="{ row }">{{ row.roleType === "delivery" ? "A · 上课" : "B · 分成" }}</template></el-table-column>
        <el-table-column prop="effectiveFrom" label="生效日" width="120" />
        <el-table-column label="结束日" width="120"><template #default="{ row }">{{ row.effectiveUntil ?? "长期" }}</template></el-table-column>
        <el-table-column label="版本" width="80"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="120" align="right"><template #default="{ row }"><el-button v-if="row.status === 'active'" link type="danger" @click="openEndAssignment(row)">结束分配</el-button></template></el-table-column>
      </el-table>

      <el-table v-else-if="tab === 'delivery_assignments'" v-loading="loading" :data="deliveryAssignments" class="resource-table">
        <el-table-column prop="courseName" label="课程" min-width="150" />
        <el-table-column label="场次时间" min-width="175"><template #default="{ row }">{{ formatDateTime(row.startsAt) }}</template></el-table-column>
        <el-table-column prop="staffName" label="实际履约人" min-width="145" />
        <el-table-column prop="roleName" label="A 类角色" min-width="135" />
        <el-table-column label="履约分配" width="110"><template #default="{ row }">{{ formatBasisPoints(row.allocationBps) }}</template></el-table-column>
        <el-table-column label="主履约人" width="100"><template #default="{ row }"><el-tag v-if="row.isPrimary" type="success">是</el-tag><span v-else>否</span></template></el-table-column>
        <el-table-column label="更新时间" min-width="160"><template #default="{ row }">{{ formatDateTime(row.updatedAt) }}</template></el-table-column>
        <el-table-column label="操作" width="100" align="right"><template #default="{ row }"><el-button link type="primary" @click="openDeliveryAssignment(row)">设置本场</el-button></template></el-table-column>
      </el-table>

      <el-table v-else v-loading="loading" :data="cardAssignments" class="resource-table">
        <el-table-column prop="memberCardNo" label="会员卡" min-width="160" />
        <el-table-column prop="memberName" label="会员" min-width="130" />
        <el-table-column prop="staffName" label="分成人员" min-width="140" />
        <el-table-column prop="roleName" label="B 类业务角色" min-width="150" />
        <el-table-column label="分配比例" width="110"><template #default="{ row }">{{ formatBasisPoints(row.allocationBps) }}</template></el-table-column>
        <el-table-column label="生效期" min-width="180"><template #default="{ row }">{{ row.effectiveFrom ?? "立即" }} — {{ row.effectiveUntil ?? "长期" }}</template></el-table-column>
        <el-table-column label="版本" width="80"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="状态" width="90"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="100" align="right"><template #default="{ row }"><el-button link type="primary" @click="openCardAssignment(row)">整卡管理</el-button></template></el-table-column>
      </el-table>

      <div class="table-footer">
        <span>每页 {{ perPage }} 条</span>
        <el-pagination v-model:current-page="page" layout="prev, pager, next" :page-size="perPage" :total="total" @current-change="load" />
      </div>
    </div>

    <el-dialog v-model="roleDialog" :title="roleForm.id ? '编辑业务角色' : '新建业务角色'" width="480px">
      <el-form label-position="top">
        <el-form-item label="角色名称"><el-input v-model="roleForm.name" maxlength="80" show-word-limit /></el-form-item>
        <el-form-item label="业务类型">
          <el-radio-group v-model="roleForm.roleType" :disabled="Boolean(roleForm.id)">
            <el-radio-button value="delivery">A · 实际上课者</el-radio-button>
            <el-radio-button value="share">B · 卡归属分成</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <p class="form-help">角色类型创建后不可修改，避免历史结算语义改变。</p>
      </el-form>
      <template #footer><el-button @click="roleDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!roleForm.name.trim()" @click="saveRole">保存</el-button></template>
    </el-dialog>

    <el-dialog v-model="assignmentDialog" title="分配员工业务角色" width="520px">
      <el-form label-position="top">
        <el-form-item label="员工"><el-select v-model="assignmentForm.staffId" filterable style="width: 100%"><el-option v-for="item in staffOptions" :key="item.id" :label="`${item.name} · ${item.employeeNo}`" :value="item.id" /></el-select></el-form-item>
        <el-form-item label="业务角色"><el-select v-model="assignmentForm.roleId" style="width: 100%"><el-option v-for="item in options.compensationRoles.filter((role) => role.status === 'active')" :key="item.id" :label="item.name" :value="item.id" /></el-select></el-form-item>
        <div class="form-grid two-columns">
          <el-form-item label="生效日"><el-date-picker v-model="assignmentForm.effectiveFrom" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
          <el-form-item label="结束日（可选）"><el-date-picker v-model="assignmentForm.effectiveUntil" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
        </div>
      </el-form>
      <template #footer><el-button @click="assignmentDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!assignmentForm.roleId || !assignmentForm.staffId" @click="saveAssignment">确认分配</el-button></template>
    </el-dialog>

    <el-dialog v-model="endAssignmentDialog" title="结束员工业务角色" width="520px">
      <el-alert type="warning" :closable="false" show-icon>
        {{ endAssignmentForm.staffName }} · {{ endAssignmentForm.roleName }}。仅结束有效期，历史任职与结算记录不会删除。
      </el-alert>
      <el-form label-position="top" class="command-form">
        <el-form-item label="最后有效日">
          <el-date-picker v-model="endAssignmentForm.effectiveUntil" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
        </el-form-item>
        <el-form-item label="结束原因">
          <el-input v-model="endAssignmentForm.reason" type="textarea" :rows="3" maxlength="500" show-word-limit placeholder="请说明人员任职结束或计划调整原因" />
        </el-form-item>
        <p class="form-help">可选择未来日期。命令生效后，该日期之后不再作为该业务角色参与新耗卡；已产生的快照保持不变。</p>
      </el-form>
      <template #footer>
        <el-button @click="endAssignmentDialog = false">取消</el-button>
        <el-button type="danger" :loading="saving" :disabled="!endAssignmentForm.effectiveUntil || endAssignmentForm.reason.trim().length < 2" @click="endAssignment">确认结束</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="deliveryAssignmentDialog" title="设置场次实际履约人" width="720px">
      <el-form label-position="top">
        <el-form-item label="场次">
          <el-select v-model="deliveryAssignmentForm.sessionId" filterable :disabled="deliveryAssignmentForm.assignments.some((item) => item.staffId !== null)" style="width: 100%" @change="loadSessionDeliveryAssignments">
            <el-option v-for="item in options.sessions" :key="item.id" :label="item.name" :value="item.id" />
          </el-select>
        </el-form-item>
        <div class="delivery-assignment-head"><strong>实际履约人</strong><span :class="{ invalid: !deliveryAssignmentValid }">{{ deliveryAllocationSummary }}</span></div>
        <div v-for="(item, index) in deliveryAssignmentForm.assignments" :key="index" class="delivery-recipient-row">
          <el-select v-model="item.staffId" filterable placeholder="选员工"><el-option v-for="staff in staffOptions" :key="staff.id" :label="`${staff.name} · ${staff.employeeNo}`" :value="staff.id" /></el-select>
          <el-select v-model="item.roleId" placeholder="选 A 类角色"><el-option v-for="role in deliveryRoles" :key="role.id" :label="role.name" :value="role.id" /></el-select>
          <el-input-number v-model="item.allocationPercent" :min="0.01" :max="100" :precision="2" controls-position="right" />
          <el-checkbox v-model="item.isPrimary">主履约</el-checkbox>
          <el-button type="danger" link :disabled="deliveryAssignmentForm.assignments.length === 1" @click="deliveryAssignmentForm.assignments.splice(index, 1)">移除</el-button>
        </div>
        <el-button plain :icon="Plus" @click="addDeliveryRecipient">添加履约人</el-button>
        <el-form-item label="调整原因" class="delivery-reason"><el-input v-model="deliveryAssignmentForm.reason" type="textarea" :rows="2" maxlength="300" show-word-limit placeholder="例：双教练协同上课，按实际工作量分配" /></el-form-item>
        <p class="form-help">同一 A 类角色下的人员分配必须合计 100%，最多一位主履约人；后端保存场次快照，Web 不重算提成。</p>
      </el-form>
      <template #footer><el-button @click="deliveryAssignmentDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!deliveryAssignmentForm.sessionId || deliveryAssignmentForm.reason.trim().length < 4 || !deliveryAssignmentValid" @click="saveDeliveryAssignments">保存本场分配</el-button></template>
    </el-dialog>

    <el-dialog v-model="cardAssignmentDialog" title="设置会员卡 B 类归属" width="760px">
      <el-form label-position="top">
        <el-form-item label="会员卡"><el-select v-model="cardAssignmentForm.memberCardId" filterable style="width: 100%" @change="loadCardShareAssignments"><el-option v-for="item in options.memberCards" :key="item.id" :label="item.name" :value="item.id" /></el-select></el-form-item>
        <div class="delivery-assignment-head"><strong>分成人员与 B 类角色</strong><span :class="{ invalid: !cardAssignmentValid }">{{ cardAssignmentSummary }}</span></div>
        <div v-for="(item, index) in cardAssignmentForm.assignments" :key="index" class="card-share-assignment-block">
          <div class="delivery-recipient-row card-share-recipient-row">
            <el-select v-model="item.roleId" placeholder="选 B 类角色"><el-option v-for="role in options.compensationRoles.filter((candidate) => candidate.type === 'share' && candidate.status === 'active')" :key="role.id" :label="role.name" :value="role.id" /></el-select>
            <el-select v-model="item.staffId" filterable placeholder="选分成人员"><el-option v-for="staff in options.staff" :key="staff.id" :label="staff.name" :value="staff.id" /></el-select>
            <el-input-number v-model="item.allocationPercent" :min="0.01" :max="100" :precision="2" controls-position="right" />
            <span class="allocation-unit">%</span>
            <el-button type="danger" link @click="cardAssignmentForm.assignments.splice(index, 1)">移除</el-button>
          </div>
          <div class="card-share-date-row">
            <el-form-item label="生效日"><el-date-picker v-model="item.effectiveFrom" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
            <el-form-item label="结束日（可选）"><el-date-picker v-model="item.effectiveUntil" type="date" value-format="YYYY-MM-DD" style="width: 100%" /></el-form-item>
          </div>
        </div>
        <el-button plain :icon="Plus" @click="addCardShareRecipient">添加分成人员</el-button>
        <el-form-item label="调整原因"><el-input v-model="cardAssignmentForm.reason" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="说明整卡 B 类归属或分配比例调整原因" /></el-form-item>
        <p class="form-help">同一 B 类角色下的人员比例必须合计 100%，且使用相同生效/结束日；不同 B 类角色可使用不同时间窗。保存时按整张卡一次性替换，历史事件继续使用发生时快照。</p>
      </el-form>
      <template #footer><el-button @click="cardAssignmentDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!cardAssignmentForm.memberCardId || !cardAssignmentValid || cardAssignmentForm.reason.trim().length < 2" @click="saveCardAssignment">保存整卡新版本</el-button></template>
    </el-dialog>
  </section>
</template>
