<script setup lang="ts">
import { EditPen, Plus, Refresh, Search } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { computed, reactive, ref, watch } from "vue";

import { ApiError } from "@/api/client";
import {
  commandKey,
  formatBasisPoints,
  formatCents,
  formatDateTime,
  governanceOptions,
  scopedCommand,
  scopedList,
  scopeRef,
  type CardCourseRuleSetRow,
  type CardProductCatalogRow,
  type CardProductPaymentMethodRow,
  type CourseCatalogRow,
  type CourseCompensationRuleRow,
  type GovernanceOptions,
} from "@/api/cardConsumption";
import BusinessScopeGuard from "@/components/BusinessScopeGuard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";
import { useBusinessScopeStore } from "@/stores/businessScope";

const scope = useBusinessScopeStore();
const tab = ref<"products" | "courses" | "payments" | "deduction" | "compensation">("products");
const query = ref("");
const page = ref(1);
const perPage = 20;
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const ruleSets = ref<CardCourseRuleSetRow[]>([]);
const paymentRows = ref<CardProductPaymentMethodRow[]>([]);
const compensationRows = ref<CourseCompensationRuleRow[]>([]);
const productRows = ref<CardProductCatalogRow[]>([]);
const courseRows = ref<CourseCatalogRow[]>([]);
const options = ref<GovernanceOptions>({ cardProducts: [], courses: [], compensationRoles: [], staff: [], members: [], memberCards: [], sessions: [] });
const dialog = ref(false);
let loadRequestId = 0;

const productForm = reactive({
  id: null as number | null,
  name: "",
  description: "",
  cardType: "stored_value" as "stored_value" | "count" | "period",
  priceYuan: 0,
  faceValueYuan: null as number | null,
  initialCount: null as number | null,
  validityDays: 365,
  saleStatus: "on_sale" as "on_sale" | "stopped",
  allowedPaymentMethods: ["online", "balance"] as Array<"online" | "balance">,
  version: 0,
});
const courseForm = reactive({
  id: null as number | null,
  name: "",
  description: "",
  courseType: "group" as "group" | "private",
  durationMinutes: 60,
  difficulty: 1,
  minCapacity: 1,
  maxCapacity: 20,
  coachStaffId: null as number | null,
  version: 0,
});

const paymentForm = reactive({
  id: null as number | null,
  name: "",
  allowedPaymentMethods: ["online"] as Array<"online" | "balance">,
  version: 0,
  reason: "",
});

const deductionForm = reactive({
  cardProductId: null as number | null,
  rulesVersion: 0,
  originalCourseIds: [] as number[],
  rules: [] as Array<{
    courseId: number | null;
    deductionKind: "amount" | "count" | "period_auto";
    amountYuan: number | null;
    deductionCount: number;
    effectiveAt: string | null;
  }>,
  reason: "",
});
const compensationForm = reactive({
  id: null as number | null,
  courseId: null as number | null,
  sessionFeeYuan: 0,
  roleRates: [] as Array<{ compensationRoleId: number | null; ratePercent: number }>,
  effectiveAt: null as string | null,
  version: 0,
  reason: "",
});

const currentScope = computed(() => scopeRef(scope.tenantId, scope.siteId));
const activeRoles = computed(() => options.value.compensationRoles.filter((item) => item.status !== "archived"));
const deductionValid = computed(() => {
  if (!deductionForm.cardProductId || deductionForm.reason.trim().length < 2) return false;
  const courseIds = deductionForm.rules.map((rule) => rule.courseId).filter((id): id is number => id !== null);
  if (courseIds.length !== deductionForm.rules.length || new Set(courseIds).size !== courseIds.length) return false;
  return deductionForm.rules.every((rule) =>
    (rule.deductionKind !== "amount" || (rule.amountYuan ?? 0) > 0)
    && (rule.deductionKind !== "count" || rule.deductionCount > 0));
});
const compensationValid = computed(() => {
  if (!compensationForm.courseId || compensationForm.reason.trim().length < 2) return false;
  const roleIds = compensationForm.roleRates.map((rate) => rate.compensationRoleId).filter((id): id is number => id !== null);
  return roleIds.length === compensationForm.roleRates.length && new Set(roleIds).size === roleIds.length;
});

function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.payload.message : error instanceof Error ? error.message : "操作失败";
}

function cardTypeLabel(value: string) {
  return ({ stored_value: "储值卡", count: "次卡", period: "期限卡" } as Record<string, string>)[value] ?? value;
}

function editableDateTime(value: string | null | undefined) {
  return value ? value.slice(0, 19).replace("T", " ") : null;
}

async function loadOptions() {
  if (!currentScope.value) return;
  try {
    options.value = await governanceOptions(currentScope.value);
  } catch (error) {
    if (error === "cancel" || error === "close") return;
    ElMessage.error(errorMessage(error));
  }
}

async function load() {
  const requestId = ++loadRequestId;
  const requestedScope = currentScope.value;
  const requestedTab = tab.value;
  if (!requestedScope) {
    ruleSets.value = [];
    compensationRows.value = [];
    productRows.value = [];
    courseRows.value = [];
    total.value = 0;
    return;
  }
  loading.value = true;
  try {
    const endpoint = ({
      products: "/card-products",
      courses: "/courses",
      payments: "/card-product-payment-methods",
      deduction: "/card-course-rules",
      compensation: "/course-compensation-rules",
    } as const)[requestedTab];
    const data = await scopedList<
      CardProductCatalogRow | CourseCatalogRow | CardProductPaymentMethodRow | CardCourseRuleSetRow | CourseCompensationRuleRow
    >(requestedScope, endpoint, { query: query.value.trim(), page: page.value, perPage });
    if (requestId !== loadRequestId) return;
    if (requestedTab === "products") productRows.value = data.items as CardProductCatalogRow[];
    else if (requestedTab === "courses") courseRows.value = data.items as CourseCatalogRow[];
    else if (requestedTab === "payments") paymentRows.value = data.items as CardProductPaymentMethodRow[];
    else if (requestedTab === "deduction") ruleSets.value = data.items as CardCourseRuleSetRow[];
    else compensationRows.value = data.items as CourseCompensationRuleRow[];
    total.value = data.pagination.total;
  } catch (error) {
    if (requestId !== loadRequestId) return;
    ElMessage.error(errorMessage(error));
  } finally {
    if (requestId === loadRequestId) loading.value = false;
  }
}

async function openNew() {
  await loadOptions();
  if (tab.value === "products") {
    Object.assign(productForm, {
      id: null, name: "", description: "", cardType: "stored_value", priceYuan: 0,
      faceValueYuan: null, initialCount: null, validityDays: 365, saleStatus: "on_sale",
      allowedPaymentMethods: ["online", "balance"], version: 0,
    });
  } else if (tab.value === "courses") {
    Object.assign(courseForm, {
      id: null, name: "", description: "", courseType: "group", durationMinutes: 60,
      difficulty: 1, minCapacity: 1, maxCapacity: 20, coachStaffId: null, version: 0,
    });
  } else if (tab.value === "payments") {
    return;
  } else if (tab.value === "deduction") {
    Object.assign(deductionForm, { cardProductId: null, rulesVersion: 0, originalCourseIds: [], rules: [], reason: "" });
    addDeductionRule();
  } else {
    Object.assign(compensationForm, { id: null, courseId: null, sessionFeeYuan: 0, roleRates: [], effectiveAt: null, version: 0, reason: "" });
  }
  dialog.value = true;
}

async function openProduct(row: CardProductCatalogRow) {
  await loadOptions();
  Object.assign(productForm, {
    id: row.id,
    name: row.name,
    description: row.description ?? "",
    cardType: row.cardType,
    priceYuan: row.priceCents / 100,
    faceValueYuan: row.faceValueCents === null ? null : row.faceValueCents / 100,
    initialCount: row.initialCount,
    validityDays: row.validityDays ?? 365,
    saleStatus: row.saleStatus,
    allowedPaymentMethods: [...row.allowedPaymentMethods],
    version: row.version,
    reason: "",
  });
  dialog.value = true;
}

async function openCourse(row: CourseCatalogRow) {
  await loadOptions();
  Object.assign(courseForm, {
    id: row.id,
    name: row.name,
    description: row.description ?? "",
    courseType: row.courseType,
    durationMinutes: row.durationMinutes,
    difficulty: row.difficulty ?? 1,
    minCapacity: row.minCapacity ?? 1,
    maxCapacity: row.maxCapacity ?? 20,
    coachStaffId: row.coachStaffId,
    version: row.version,
  });
  dialog.value = true;
}

function openPaymentMethods(row: CardProductPaymentMethodRow) {
  Object.assign(paymentForm, {
    id: row.id,
    name: row.name,
    allowedPaymentMethods: [...row.allowedPaymentMethods],
    version: row.version,
    reason: "",
  });
  dialog.value = true;
}

function deductionKindFor(cardType: string | null | undefined): "amount" | "count" | "period_auto" {
  if (cardType === "count") return "count";
  if (cardType === "period") return "period_auto";
  return "amount";
}

const selectedDeductionCardType = computed(() => {
  const product = options.value.cardProducts.find((item) => item.id === deductionForm.cardProductId);
  return product?.type ?? ruleSets.value.find((item) => item.cardProductId === deductionForm.cardProductId)?.cardType ?? null;
});

function addDeductionRule() {
  deductionForm.rules.push({
    courseId: null,
    deductionKind: deductionKindFor(selectedDeductionCardType.value),
    amountYuan: null,
    deductionCount: 1,
    effectiveAt: null,
  });
}

function removeDeductionRule(index: number) {
  deductionForm.rules.splice(index, 1);
}

function syncDeductionKinds() {
  const expected = deductionKindFor(selectedDeductionCardType.value);
  deductionForm.rules.forEach((rule) => { rule.deductionKind = expected; });
}

async function openDeduction(row: CardCourseRuleSetRow) {
  await loadOptions();
  Object.assign(deductionForm, {
    cardProductId: row.cardProductId,
    rulesVersion: row.rulesVersion,
    originalCourseIds: row.rules.map((rule) => rule.courseId),
    rules: row.rules.map((rule) => ({
      courseId: rule.courseId,
      deductionKind: rule.deductionKind,
      amountYuan: rule.deductionAmountCents === null ? null : rule.deductionAmountCents / 100,
      deductionCount: rule.deductionCount ?? 1,
      effectiveAt: rule.effectiveAt,
    })),
    reason: "",
  });
  dialog.value = true;
}

async function openCompensation(row: CourseCompensationRuleRow) {
  await loadOptions();
  Object.assign(compensationForm, {
    id: row.id,
    courseId: row.courseId,
    sessionFeeYuan: row.sessionFeeCents / 100,
    roleRates: row.roleRates.map((rate) => ({
      compensationRoleId: rate.compensationRoleId,
      ratePercent: rate.rateBps / 100,
    })),
    effectiveAt: editableDateTime(row.effectiveAt),
    version: row.version,
    reason: "",
  });
  dialog.value = true;
}

function addCompensationRate() {
  compensationForm.roleRates.push({ compensationRoleId: null, ratePercent: 0 });
}

function removeCompensationRate(index: number) {
  compensationForm.roleRates.splice(index, 1);
}

function roleAlreadySelected(roleId: number, rowIndex: number) {
  return compensationForm.roleRates.some((rate, index) => index !== rowIndex && rate.compensationRoleId === roleId);
}

async function save() {
  if (!currentScope.value) return;
  saving.value = true;
  try {
    if (tab.value === "products") {
      if (!productForm.name.trim() || productForm.allowedPaymentMethods.length < 1) return;
      await scopedCommand(
        currentScope.value,
        productForm.id ? `/card-products/${productForm.id}` : "/card-products",
        productForm.id ? "PUT" : "POST",
        {
          name: productForm.name.trim(),
          description: productForm.description.trim() || null,
          cardType: productForm.cardType,
          price: productForm.priceYuan.toFixed(2),
          faceValue: productForm.cardType === "stored_value" ? productForm.faceValueYuan : null,
          initialCount: productForm.cardType === "count" ? productForm.initialCount : null,
          validityDays: productForm.validityDays,
          ...(productForm.id ? {} : { validityMode: "days", activationMode: "immediate" }),
          saleStatus: productForm.saleStatus,
          allowedPaymentMethods: productForm.allowedPaymentMethods,
          version: productForm.version,
          commandKey: commandKey(),
        },
      );
    } else if (tab.value === "courses") {
      if (!courseForm.name.trim()) return;
      await scopedCommand(
        currentScope.value,
        courseForm.id ? `/courses/${courseForm.id}` : "/courses",
        courseForm.id ? "PUT" : "POST",
        {
          name: courseForm.name.trim(),
          description: courseForm.description.trim() || null,
          courseType: courseForm.courseType,
          durationMinutes: courseForm.durationMinutes,
          difficulty: courseForm.difficulty,
          minCapacity: courseForm.courseType === "group" ? courseForm.minCapacity : 1,
          maxCapacity: courseForm.courseType === "group" ? courseForm.maxCapacity : 1,
          coachStaffId: courseForm.coachStaffId,
          version: courseForm.version,
          commandKey: commandKey(),
        },
      );
    } else if (tab.value === "payments") {
      if (!paymentForm.id || paymentForm.allowedPaymentMethods.length < 1) return;
      await scopedCommand(currentScope.value, `/card-products/${paymentForm.id}/payment-methods`, "PUT", {
        allowedPaymentMethods: paymentForm.allowedPaymentMethods,
        version: paymentForm.version,
        reason: paymentForm.reason.trim(),
        commandKey: commandKey(),
      });
    } else if (tab.value === "deduction") {
      if (!deductionForm.cardProductId || deductionForm.rules.some((rule) => !rule.courseId)) return;
      const currentCourseIds = deductionForm.rules.map((rule) => Number(rule.courseId));
      const removedCourseIds = deductionForm.originalCourseIds.filter((id) => !currentCourseIds.includes(id));
      if (removedCourseIds.length > 0) {
        await ElMessageBox.confirm(
          `将解除 ${removedCourseIds.length} 个课程关联。历史预约与耗卡快照会保留，新预约将不再使用被解除规则。`,
          "确认解除卡课关联",
          { type: "warning", confirmButtonText: "确认解除", cancelButtonText: "取消" },
        );
      }
      await scopedCommand(currentScope.value, `/card-products/${deductionForm.cardProductId}/course-rules`, "PUT", {
        rules: deductionForm.rules.map((rule) => ({
          courseId: rule.courseId,
          deductionKind: rule.deductionKind,
          deductionAmountCents: rule.deductionKind === "amount" ? Math.round((rule.amountYuan ?? 0) * 100) : null,
          deductionCount: rule.deductionKind === "count" ? rule.deductionCount : null,
          effectiveAt: rule.effectiveAt,
        })),
        expectedVersion: deductionForm.rulesVersion,
        reason: deductionForm.reason.trim(),
        commandKey: commandKey(),
      });
    } else {
      if (!compensationForm.courseId || compensationForm.roleRates.some((rate) => !rate.compensationRoleId)) return;
      await scopedCommand(currentScope.value, "/course-compensation-rules", "PUT", {
        id: compensationForm.id,
        courseId: compensationForm.courseId,
        sessionFeeCents: Math.round(compensationForm.sessionFeeYuan * 100),
        roleRates: compensationForm.roleRates.map((rate) => ({
          compensationRoleId: rate.compensationRoleId,
          rateBps: Math.round(rate.ratePercent * 100),
        })),
        effectiveAt: compensationForm.effectiveAt,
        version: compensationForm.version,
        reason: compensationForm.reason.trim(),
        commandKey: commandKey(),
      });
    }
    ElMessage.success(tab.value === "products" ? "卡项已保存" : tab.value === "courses" ? "课程已保存" : tab.value === "payments" ? "卡项支付方式已更新" : "新版本规则已发布，历史耗卡快照不受影响");
    dialog.value = false;
    await load();
  } catch (error) {
    if (error === "cancel" || error === "close") return;
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

async function changeCatalogStatus(kind: "card-products" | "courses", row: CardProductCatalogRow | CourseCatalogRow, action: "archive" | "restore") {
  if (!currentScope.value) return;
  try {
    const verb = action === "archive" ? "下架" : "恢复上架";
    await ElMessageBox.confirm(`${verb}后历史订单、会员卡和耗卡快照仍会保留。`, `${verb}${kind === "card-products" ? "卡项" : "课程"}`, {
      type: "warning",
      confirmButtonText: verb,
      cancelButtonText: "取消",
    });
    await scopedCommand(currentScope.value, `/${kind}/${row.id}/${action}`, "POST", {
      version: row.version,
      reason: `${verb}治理命令`,
      commandKey: commandKey(),
    });
    ElMessage.success(`${verb}完成`);
    await load();
  } catch (error) {
    if (error === "cancel" || error === "close") return;
    ElMessage.error(errorMessage(error));
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
      eyebrow="CARD CONSUMPTION / RULE VERSIONS"
      title="会员卡、课程与薪酬规则"
      description="在同一场馆作用域内管理三类卡项、课程、支付方式、卡课扣费和课程薪酬版本。"
    >
      <el-button :icon="Refresh" @click="load">刷新</el-button>
      <el-button v-if="tab !== 'payments' && tab !== 'deduction'" type="primary" :icon="Plus" :disabled="!scope.ready" @click="openNew">{{ tab === "products" ? "新建卡项" : tab === "courses" ? "新建课程" : "新建规则版本" }}</el-button>
    </PageHeading>

    <BusinessScopeGuard write-hint="每次保存都产生新版本；报表使用耗卡事件上的服务端公式快照。" />

    <div class="governance-tabs">
      <button :class="{ active: tab === 'products' }" @click="tab = 'products'">卡项管理</button>
      <button :class="{ active: tab === 'courses' }" @click="tab = 'courses'">课程管理</button>
      <button :class="{ active: tab === 'payments' }" @click="tab = 'payments'">卡项支付方式</button>
      <button :class="{ active: tab === 'deduction' }" @click="tab = 'deduction'">卡关联课程扣费</button>
      <button :class="{ active: tab === 'compensation' }" @click="tab = 'compensation'">课程课时费与耗卡分成</button>
    </div>

    <div class="formula-banner">
      <el-icon><EditPen /></el-icon>
      <div><strong>公式在后端统一执行</strong><span>网页只展示服务端返回的公式版本、输入快照和结算金额，不在浏览器中计算提成。</span></div>
    </div>

    <div class="data-panel">
      <div class="data-toolbar">
        <label class="search-box"><el-icon><Search /></el-icon><input v-model="query" placeholder="搜索卡项、课程或角色" @keyup.enter="load" /></label>
        <el-button @click="page = 1; load()">查询</el-button>
        <span class="result-count">共 {{ total }} 条版本规则</span>
      </div>

      <el-table v-if="tab === 'products'" v-loading="loading" :data="productRows" class="resource-table">
        <el-table-column prop="name" label="卡项" min-width="170" />
        <el-table-column label="类型" width="110"><template #default="{ row }">{{ cardTypeLabel(row.cardType) }}</template></el-table-column>
        <el-table-column label="售价" width="125"><template #default="{ row }">{{ formatCents(row.priceCents) }}</template></el-table-column>
        <el-table-column label="权益" min-width="145"><template #default="{ row }"><span v-if="row.cardType === 'stored_value'">面值 {{ formatCents(row.faceValueCents) }}</span><span v-else-if="row.cardType === 'count'">{{ row.initialCount }} 次</span><span v-else>{{ row.validityDays }} 天</span></template></el-table-column>
        <el-table-column label="销售" width="95"><template #default="{ row }"><StatusPill :value="row.saleStatus === 'on_sale' ? '销售中' : '已停售'" /></template></el-table-column>
        <el-table-column label="版本" width="75"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="状态" width="95"><template #default="{ row }"><StatusPill :value="row.catalogStatus" /></template></el-table-column>
        <el-table-column label="操作" width="170" align="right"><template #default="{ row }"><el-button link type="primary" @click="openProduct(row)">编辑</el-button><el-button v-if="row.catalogStatus === 'active'" link type="danger" @click="changeCatalogStatus('card-products', row, 'archive')">下架</el-button><el-button v-else link type="primary" @click="changeCatalogStatus('card-products', row, 'restore')">恢复</el-button></template></el-table-column>
      </el-table>

      <el-table v-else-if="tab === 'courses'" v-loading="loading" :data="courseRows" class="resource-table">
        <el-table-column prop="name" label="课程" min-width="180" />
        <el-table-column label="类型" width="100"><template #default="{ row }">{{ row.courseType === "group" ? "团课" : "私教" }}</template></el-table-column>
        <el-table-column label="时长" width="100"><template #default="{ row }">{{ row.durationMinutes }} 分钟</template></el-table-column>
        <el-table-column label="容量" width="110"><template #default="{ row }">{{ row.courseType === "group" ? `${row.minCapacity ?? 1}–${row.maxCapacity ?? 1} 人` : "1 人" }}</template></el-table-column>
        <el-table-column prop="coachName" label="默认教练" min-width="135"><template #default="{ row }">{{ row.coachName ?? "未指定" }}</template></el-table-column>
        <el-table-column label="版本" width="75"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="状态" width="95"><template #default="{ row }"><StatusPill :value="row.catalogStatus" /></template></el-table-column>
        <el-table-column label="操作" width="170" align="right"><template #default="{ row }"><el-button link type="primary" @click="openCourse(row)">编辑</el-button><el-button v-if="row.catalogStatus === 'active'" link type="danger" @click="changeCatalogStatus('courses', row, 'archive')">下架</el-button><el-button v-else link type="primary" @click="changeCatalogStatus('courses', row, 'restore')">恢复</el-button></template></el-table-column>
      </el-table>

      <el-table v-else-if="tab === 'payments'" v-loading="loading" :data="paymentRows" class="resource-table">
        <el-table-column prop="name" label="卡项" min-width="190" />
        <el-table-column prop="cardType" label="卡类型" width="120" />
        <el-table-column label="售价" width="130"><template #default="{ row }">{{ formatCents(row.priceCents) }}</template></el-table-column>
        <el-table-column label="允许支付方式" min-width="220">
          <template #default="{ row }"><div class="payment-method-tags"><el-tag v-if="row.allowedPaymentMethods.includes('online')" type="success">在线支付</el-tag><el-tag v-if="row.allowedPaymentMethods.includes('balance')" type="warning">会员钱包余额</el-tag></div></template>
        </el-table-column>
        <el-table-column label="版本" width="90"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="更新时间" min-width="170"><template #default="{ row }">{{ formatDateTime(row.updatedAt) }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="110" align="right"><template #default="{ row }"><el-button link type="primary" @click="openPaymentMethods(row)">编辑支付方式</el-button></template></el-table-column>
      </el-table>

      <el-table v-else-if="tab === 'deduction'" v-loading="loading" :data="ruleSets" class="resource-table">
        <el-table-column type="expand">
          <template #default="{ row }">
            <el-table :data="row.rules" size="small" class="nested-rule-table" empty-text="尚未关联课程">
              <el-table-column prop="courseName" label="课程" min-width="180" />
              <el-table-column label="扣费机制" min-width="190"><template #default="{ row: rule }"><span v-if="rule.deductionKind === 'amount'">每次扣 {{ formatCents(rule.deductionAmountCents) }}</span><span v-else-if="rule.deductionKind === 'count'">每次扣 {{ rule.deductionCount }} 次</span><span v-else>期限卡按日自动计算</span></template></el-table-column>
              <el-table-column label="版本" width="80"><template #default="{ row: rule }">v{{ rule.version }}</template></el-table-column>
              <el-table-column label="状态" width="100"><template #default="{ row: rule }"><StatusPill :value="rule.status" /></template></el-table-column>
            </el-table>
          </template>
        </el-table-column>
        <el-table-column prop="cardProductName" label="卡项" min-width="190" />
        <el-table-column label="卡类型" width="110"><template #default="{ row }">{{ cardTypeLabel(row.cardType) }}</template></el-table-column>
        <el-table-column label="已关联课程" width="125"><template #default="{ row }">{{ row.rules.length }} 门</template></el-table-column>
        <el-table-column label="更新时间" min-width="170"><template #default="{ row }">{{ formatDateTime(row.updatedAt) }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.catalogStatus" /></template></el-table-column>
        <el-table-column label="操作" width="110" align="right"><template #default="{ row }"><el-button link type="primary" @click="openDeduction(row)">管理关联</el-button></template></el-table-column>
      </el-table>

      <el-table v-else v-loading="loading" :data="compensationRows" class="resource-table">
        <el-table-column type="expand"><template #default="{ row }"><el-table :data="row.roleRates" size="small" class="nested-rule-table" empty-text="仅配置课时费，未配置角色耗卡分成"><el-table-column prop="roleName" label="业务角色" min-width="170" /><el-table-column label="角色类型" width="120"><template #default="{ row: rate }">{{ rate.roleType === "delivery" ? "A · 上课" : "B · 分成" }}</template></el-table-column><el-table-column label="耗卡分成" min-width="170"><template #default="{ row: rate }">{{ formatBasisPoints(rate.rateBps) }} <small class="unit-detail">{{ rate.rateBps }} bps</small></template></el-table-column></el-table></template></el-table-column>
        <el-table-column prop="courseName" label="课程" min-width="180" />
        <el-table-column label="每节课时费" width="130"><template #default="{ row }">{{ formatCents(row.sessionFeeCents) }}</template></el-table-column>
        <el-table-column label="分成角色" width="110"><template #default="{ row }">{{ row.roleRates.length }} 个</template></el-table-column>
        <el-table-column label="公式版本" width="110"><template #default="{ row }"><code>{{ row.formulaVersion }}</code></template></el-table-column>
        <el-table-column label="生效时间" min-width="170"><template #default="{ row }">{{ formatDateTime(row.effectiveAt) }}</template></el-table-column>
        <el-table-column label="规则版本" width="90"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="状态" width="90"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="110" align="right"><template #default="{ row }"><el-button link type="primary" @click="openCompensation(row)">编辑整条规则</el-button></template></el-table-column>
      </el-table>

      <div class="table-footer"><span>每页 {{ perPage }} 条</span><el-pagination v-model:current-page="page" layout="prev, pager, next" :page-size="perPage" :total="total" @current-change="load" /></div>
    </div>

    <el-dialog v-model="dialog" :title="tab === 'products' ? (productForm.id ? '编辑卡项' : '新建卡项') : tab === 'courses' ? (courseForm.id ? '编辑课程' : '新建课程') : tab === 'payments' ? '卡项支付方式' : tab === 'deduction' ? '卡课扣费规则' : '课程薪酬规则'" width="620px">
      <el-form v-if="tab === 'products'" label-position="top">
        <div class="form-grid two-columns"><el-form-item label="卡项名称"><el-input v-model="productForm.name" maxlength="120" /></el-form-item><el-form-item label="卡项类型"><el-select v-model="productForm.cardType" :disabled="Boolean(productForm.id)" style="width: 100%"><el-option label="储值卡" value="stored_value" /><el-option label="次卡" value="count" /><el-option label="期限卡" value="period" /></el-select></el-form-item></div>
        <div class="form-grid two-columns"><el-form-item label="售价（元）"><el-input-number v-model="productForm.priceYuan" :min="0" :precision="2" /></el-form-item><el-form-item v-if="productForm.cardType === 'stored_value'" label="储值面值（元）"><el-input-number v-model="productForm.faceValueYuan" :min="0" :precision="2" /></el-form-item><el-form-item v-else-if="productForm.cardType === 'count'" label="总次数"><el-input-number v-model="productForm.initialCount" :min="1" :precision="0" /></el-form-item><el-form-item v-else label="有效天数"><el-input-number v-model="productForm.validityDays" :min="1" :precision="0" /></el-form-item></div>
        <el-form-item v-if="productForm.cardType !== 'period'" label="有效天数"><el-input-number v-model="productForm.validityDays" :min="1" :precision="0" /></el-form-item>
        <el-form-item label="允许支付方式"><el-checkbox-group v-model="productForm.allowedPaymentMethods"><el-checkbox value="online">在线支付</el-checkbox><el-checkbox value="balance">会员钱包余额</el-checkbox></el-checkbox-group></el-form-item>
        <el-form-item label="销售状态"><el-radio-group v-model="productForm.saleStatus"><el-radio-button value="on_sale">销售中</el-radio-button><el-radio-button value="stopped">停止销售</el-radio-button></el-radio-group></el-form-item>
        <el-form-item label="说明"><el-input v-model="productForm.description" type="textarea" :rows="3" maxlength="500" show-word-limit /></el-form-item>
      </el-form>

      <el-form v-else-if="tab === 'courses'" label-position="top">
        <div class="form-grid two-columns"><el-form-item label="课程名称"><el-input v-model="courseForm.name" maxlength="120" /></el-form-item><el-form-item label="课程类型"><el-select v-model="courseForm.courseType" style="width: 100%"><el-option label="团课" value="group" /><el-option label="私教" value="private" /></el-select></el-form-item></div>
        <div class="form-grid two-columns"><el-form-item label="时长（分钟）"><el-input-number v-model="courseForm.durationMinutes" :min="1" :max="600" :precision="0" /></el-form-item><el-form-item label="难度"><el-rate v-model="courseForm.difficulty" /></el-form-item></div>
        <div v-if="courseForm.courseType === 'group'" class="form-grid two-columns"><el-form-item label="最小容量"><el-input-number v-model="courseForm.minCapacity" :min="1" :precision="0" /></el-form-item><el-form-item label="最大容量"><el-input-number v-model="courseForm.maxCapacity" :min="1" :precision="0" /></el-form-item></div>
        <el-form-item :label="courseForm.courseType === 'private' ? '私教教练（必填）' : '默认教练（可选）'"><el-select v-model="courseForm.coachStaffId" filterable clearable style="width: 100%"><el-option v-for="item in options.staff" :key="item.id" :label="item.name" :value="item.id" /></el-select></el-form-item>
        <el-form-item label="课程说明"><el-input v-model="courseForm.description" type="textarea" :rows="3" maxlength="500" show-word-limit /></el-form-item>
      </el-form>

      <el-form v-else-if="tab === 'payments'" label-position="top">
        <el-form-item label="卡项"><el-input :model-value="paymentForm.name" disabled /></el-form-item>
        <el-form-item label="允许支付方式">
          <el-checkbox-group v-model="paymentForm.allowedPaymentMethods">
            <el-checkbox value="online">在线支付</el-checkbox>
            <el-checkbox value="balance">会员钱包余额</el-checkbox>
          </el-checkbox-group>
          <p class="form-help">至少选择一种。“余额”指独立会员钱包，不是储值卡内的卡余额。</p>
        </el-form-item>
        <el-form-item label="调整原因"><el-input v-model="paymentForm.reason" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="说明支付方式调整原因" /></el-form-item>
      </el-form>

      <el-form v-else-if="tab === 'deduction'" label-position="top">
        <el-form-item label="卡项"><el-select v-model="deductionForm.cardProductId" filterable :disabled="Boolean(deductionForm.cardProductId)" style="width: 100%" @change="syncDeductionKinds"><el-option v-for="item in options.cardProducts" :key="item.id" :label="item.name" :value="item.id" /></el-select></el-form-item>
        <div class="dynamic-rule-header"><div><strong>关联课程</strong><p class="form-help">提交时整批替换；从列表移除即解除关联，历史快照不变。</p></div><el-button :icon="Plus" @click="addDeductionRule">添加课程</el-button></div>
        <div v-for="(rule, index) in deductionForm.rules" :key="index" class="dynamic-rule-row">
          <el-select v-model="rule.courseId" filterable placeholder="选择课程"><el-option v-for="item in options.courses" :key="item.id" :label="item.name" :value="item.id" :disabled="deductionForm.rules.some((other, otherIndex) => otherIndex !== index && other.courseId === item.id)" /></el-select>
          <span class="deduction-kind-label">{{ rule.deductionKind === "amount" ? "储值卡扣金额" : rule.deductionKind === "count" ? "次卡扣次" : "期限卡自动" }}</span>
          <el-input-number v-if="rule.deductionKind === 'amount'" v-model="rule.amountYuan" :min="0.01" :precision="2" :step="10" controls-position="right" />
          <el-input-number v-else-if="rule.deductionKind === 'count'" v-model="rule.deductionCount" :min="1" :precision="0" controls-position="right" />
          <span v-else class="form-help">按实付价值 / 天 / 当日次数</span>
          <el-button link type="danger" @click="removeDeductionRule(index)">移除</el-button>
        </div>
        <el-empty v-if="deductionForm.rules.length === 0" :image-size="56" description="保存空列表将解除该卡项的全部课程关联" />
        <p v-if="selectedDeductionCardType === 'period'" class="form-help">期限卡不填金额，服务端使用实付价值批次、总天数和当天有效耗卡次数计算。</p>
        <el-form-item label="发布原因"><el-input v-model="deductionForm.reason" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="说明本次扣费规则调整原因" /></el-form-item>
      </el-form>

      <el-form v-else label-position="top">
        <div class="form-grid two-columns"><el-form-item label="课程"><el-select v-model="compensationForm.courseId" filterable :disabled="Boolean(compensationForm.id)" style="width: 100%"><el-option v-for="item in options.courses" :key="item.id" :label="item.name" :value="item.id" /></el-select></el-form-item><el-form-item label="每节课时费（元）"><el-input-number v-model="compensationForm.sessionFeeYuan" :min="0" :precision="2" :step="10" /></el-form-item></div>
        <el-form-item label="生效时间（留空立即生效）"><el-date-picker v-model="compensationForm.effectiveAt" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" placeholder="立即生效" clearable /></el-form-item>
        <div class="dynamic-rule-header"><div><strong>角色耗卡分成</strong><p class="form-help">这是课程规则的完整角色清单，可增加或移除；允许只设课时费。</p></div><el-button :icon="Plus" @click="addCompensationRate">添加角色</el-button></div>
        <div v-for="(rate, index) in compensationForm.roleRates" :key="index" class="dynamic-rule-row compensation-rate-row">
          <el-select v-model="rate.compensationRoleId" filterable placeholder="选择业务角色"><el-option v-for="item in activeRoles" :key="item.id" :label="`${item.name} · ${item.type === 'delivery' ? 'A 上课' : 'B 分成'}`" :value="item.id" :disabled="roleAlreadySelected(item.id, index)" /></el-select>
          <el-input-number v-model="rate.ratePercent" :min="0" :max="100" :precision="2" :step="1" controls-position="right" />
          <span class="unit-detail">% · {{ Math.round(rate.ratePercent * 100) }} bps</span>
          <el-button link type="danger" @click="removeCompensationRate(index)">移除</el-button>
        </div>
        <el-empty v-if="compensationForm.roleRates.length === 0" :image-size="56" description="当前规则仅计课时费，不按业务角色计耗卡分成" />
        <p class="form-help">例：10 点请填 10%，提交时仅换算为 1000 基点；提成金额由后端公式计算。</p>
        <el-form-item label="发布原因"><el-input v-model="compensationForm.reason" type="textarea" :rows="2" maxlength="500" show-word-limit placeholder="说明本次课时费或分成调整原因" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="dialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="(tab === 'products' && (!productForm.name.trim() || productForm.allowedPaymentMethods.length < 1)) || (tab === 'courses' && (!courseForm.name.trim() || (courseForm.courseType === 'private' && !courseForm.coachStaffId))) || (tab === 'payments' && (paymentForm.allowedPaymentMethods.length < 1 || paymentForm.reason.trim().length < 2)) || (tab === 'deduction' && !deductionValid) || (tab === 'compensation' && !compensationValid)" @click="save">{{ tab === "deduction" || tab === "compensation" ? "发布新版本" : "保存" }}</el-button></template>
    </el-dialog>
  </section>
</template>
