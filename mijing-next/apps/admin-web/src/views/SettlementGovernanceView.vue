<script setup lang="ts">
import { Lock, Plus, Refresh, Search } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { computed, reactive, ref, watch } from "vue";

import { ApiError } from "@/api/client";
import {
  commandKey,
  formatCents,
  formatDateTime,
  localMonthString,
  scopedCommand,
  scopedList,
  scopeRef,
  type PayrollPeriodRow,
  type PeriodSettlementDayRow,
} from "@/api/cardConsumption";
import BusinessScopeGuard from "@/components/BusinessScopeGuard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";
import { useBusinessScopeStore } from "@/stores/businessScope";

const scope = useBusinessScopeStore();
const tab = ref<"days" | "months">("days");
const query = ref("");
const month = ref(localMonthString());
const page = ref(1);
const perPage = 20;
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const dayRows = ref<PeriodSettlementDayRow[]>([]);
const periodRows = ref<PayrollPeriodRow[]>([]);
const createDialog = ref(false);
const createForm = reactive({ month: localMonthString(), reason: "" });
let loadRequestId = 0;

const currentScope = computed(() => scope.ready ? scopeRef(scope.tenantId, scope.siteId) : null);

function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.payload.message : error instanceof Error ? error.message : "操作失败";
}

function periodCloseBlockedReason(row: PayrollPeriodRow) {
  if (row.status !== "open") return "已关账锁定";
  if (row.canClose) return "";
  if (row.blockedReason === "PAYROLL_PERIOD_NOT_ENDED") return `期间尚未结束（结束日 ${row.endsOn}）`;
  if (row.blockedReason === "PAYROLL_PERIOD_BUCKETS_OPEN") return `仍有 ${row.pendingCount} 个期限卡日结未完结`;
  return "暂不可关账";
}

interface SettlementLoadSnapshot {
  requestId: number;
  tenantId: number;
  siteId: number;
  tab: typeof tab.value;
  query: string;
  month: string;
  page: number;
}

function isCurrentLoad(snapshot: SettlementLoadSnapshot) {
  const activeScope = currentScope.value;
  return snapshot.requestId === loadRequestId
    && activeScope?.tenantId === snapshot.tenantId
    && activeScope.siteId === snapshot.siteId
    && tab.value === snapshot.tab
    && query.value.trim() === snapshot.query
    && month.value === snapshot.month
    && page.value === snapshot.page;
}

async function load() {
  const requestId = ++loadRequestId;
  const activeScope = currentScope.value;
  if (!activeScope) {
    dayRows.value = [];
    periodRows.value = [];
    total.value = 0;
    loading.value = false;
    return;
  }

  const snapshot: SettlementLoadSnapshot = {
    requestId,
    tenantId: activeScope.tenantId,
    siteId: activeScope.siteId,
    tab: tab.value,
    query: query.value.trim(),
    month: month.value,
    page: page.value,
  };

  loading.value = true;
  try {
    const [year, monthNumber] = snapshot.month.split("-").map(Number);
    if (snapshot.tab === "days") {
      const data = await scopedList<PeriodSettlementDayRow>(activeScope, "/period-settlement-days", {
        query: snapshot.query,
        year,
        month: monthNumber,
        page: snapshot.page,
        perPage,
      });
      if (!isCurrentLoad(snapshot)) return;
      dayRows.value = data.items;
      total.value = data.pagination.total;
    } else {
      const data = await scopedList<PayrollPeriodRow>(activeScope, "/payroll-periods", {
        year,
        page: snapshot.page,
        perPage,
      });
      if (!isCurrentLoad(snapshot)) return;
      periodRows.value = data.items;
      total.value = data.pagination.total;
    }
  } catch (error) {
    if (isCurrentLoad(snapshot)) ElMessage.error(errorMessage(error));
  } finally {
    if (requestId === loadRequestId) loading.value = false;
  }
}

function openCreate() {
  createForm.month = month.value;
  createForm.reason = "";
  createDialog.value = true;
}

async function createPeriod() {
  if (!currentScope.value || !createForm.month || createForm.reason.trim().length < 4) return;
  saving.value = true;
  try {
    const [year, monthNumber] = createForm.month.split("-").map(Number);
    await scopedCommand(currentScope.value, "/payroll-periods", "POST", {
      year,
      month: monthNumber,
      reason: createForm.reason.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("月度结算期已创建");
    createDialog.value = false;
    month.value = createForm.month;
    await load();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

async function closePeriod(row: PayrollPeriodRow) {
  if (!currentScope.value) return;
  const blockedReason = periodCloseBlockedReason(row);
  if (blockedReason) {
    ElMessage.warning(blockedReason);
    return;
  }
  try {
    const prompt = await ElMessageBox.prompt(
      "关账后不再允许原地重算该月份；迟到业务只能以后续调整行入账。",
      `关账 ${row.year}-${String(row.month).padStart(2, "0")}`,
      {
        type: "warning",
        inputPlaceholder: "请填写关账原因或审批编号",
        inputValidator: (value) => value.trim().length >= 4 || "原因至少 4 个字",
        confirmButtonText: "确认关账",
        cancelButtonText: "取消",
      },
    );
    saving.value = true;
    await scopedCommand(currentScope.value, `/payroll-periods/${row.id}/close`, "POST", {
      expectedVersion: row.version,
      reason: prompt.value.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("月度结算期已关账");
    await load();
  } catch (error) {
    if (error === "cancel" || error === "close") return;
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

watch([() => scope.tenantId, () => scope.siteId, () => scope.ready, tab], () => {
  page.value = 1;
  void load();
}, { immediate: true });
</script>

<template>
  <section>
    <PageHeading
      eyebrow="CARD CONSUMPTION / SETTLEMENT CONTROL"
      title="期限卡日结与月度关账"
      description="查看期限卡按业务日分摊的计算版本与完结状态，并对月度薪酬结算期执行可审计关账。"
    >
      <el-button :icon="Refresh" @click="load">刷新</el-button>
      <el-button v-if="tab === 'months'" type="primary" :icon="Plus" :disabled="!scope.ready" @click="openCreate">创建月度结算期</el-button>
    </PageHeading>

    <BusinessScopeGuard write-hint="日结只读；月度关账命令带作用域、期望版本、原因和唯一 commandKey。" />

    <div class="governance-tabs">
      <button :class="{ active: tab === 'days' }" @click="tab = 'days'">期限卡日结</button>
      <button :class="{ active: tab === 'months' }" @click="tab = 'months'">月度关账</button>
    </div>

    <div class="data-panel">
      <div class="data-toolbar settlement-toolbar">
        <label v-if="tab === 'days'" class="search-box"><el-icon><Search /></el-icon><input v-model="query" placeholder="搜索卡号或会员" @keyup.enter="load" /></label>
        <el-date-picker v-model="month" type="month" value-format="YYYY-MM" placeholder="选择月份" @change="page = 1; load()" />
        <el-button @click="page = 1; load()">查询</el-button>
        <span class="result-count">共 {{ total }} 条</span>
      </div>

      <el-table v-if="tab === 'days'" v-loading="loading" :data="dayRows" class="resource-table">
        <el-table-column prop="businessDate" label="业务日" width="110" />
        <el-table-column prop="memberName" label="会员" min-width="140" />
        <el-table-column prop="memberCardNo" label="期限卡" min-width="160" />
        <el-table-column prop="activeConsumptionCount" label="当日有效耗卡" width="140"><template #default="{ row }">{{ row.activeConsumptionCount }} 次</template></el-table-column>
        <el-table-column label="当日卡价值" width="130"><template #default="{ row }">{{ formatCents(row.dailyValueCents) }}</template></el-table-column>
        <el-table-column label="当日提成" width="120"><template #default="{ row }">{{ formatCents(row.commissionCents) }}</template></el-table-column>
        <el-table-column label="计算版本" width="100"><template #default="{ row }">v{{ row.calculationVersion }}</template></el-table-column>
        <el-table-column label="完结时间" min-width="165"><template #default="{ row }">{{ formatDateTime(row.finalizedAt) }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
      </el-table>

      <el-table v-else v-loading="loading" :data="periodRows" class="resource-table">
        <el-table-column label="月份" width="120"><template #default="{ row }"><strong>{{ row.year }}-{{ String(row.month).padStart(2, "0") }}</strong></template></el-table-column>
        <el-table-column prop="settlementLineCount" label="有效耗卡" width="100" />
        <el-table-column label="耗卡价值" width="120"><template #default="{ row }">{{ formatCents(row.consumedValueCents) }}</template></el-table-column>
        <el-table-column label="课时费" width="115"><template #default="{ row }">{{ formatCents(row.sessionFeeCents) }}</template></el-table-column>
        <el-table-column label="耗卡提成" width="120"><template #default="{ row }">{{ formatCents(row.commissionCents) }}</template></el-table-column>
        <el-table-column label="调整金额" width="120"><template #default="{ row }">{{ formatCents(row.adjustmentCents) }}</template></el-table-column>
        <el-table-column label="待完结日" width="100"><template #default="{ row }"><span :class="{ 'danger-text': row.pendingCount > 0 }">{{ row.pendingCount }}</span></template></el-table-column>
        <el-table-column label="版本" width="90"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="冻结时间" min-width="170"><template #default="{ row }">{{ formatDateTime(row.metricsSnapshottedAt ?? row.closedAt) }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" min-width="190" align="right"><template #default="{ row }"><el-button v-if="!periodCloseBlockedReason(row)" link type="primary" :icon="Lock" :loading="saving" @click="closePeriod(row)">关账</el-button><span v-else class="immutable-label">{{ periodCloseBlockedReason(row) }}</span></template></el-table-column>
      </el-table>

      <div class="table-footer"><span>每页 {{ perPage }} 条</span><el-pagination v-model:current-page="page" layout="prev, pager, next" :page-size="perPage" :total="total" @current-change="load" /></div>
    </div>

    <el-dialog v-model="createDialog" title="创建月度结算期" width="500px">
      <el-form label-position="top">
        <el-form-item label="结算月份"><el-date-picker v-model="createForm.month" type="month" value-format="YYYY-MM" style="width: 100%" /></el-form-item>
        <el-form-item label="创建原因"><el-input v-model="createForm.reason" type="textarea" :rows="3" maxlength="300" show-word-limit placeholder="例：创建九月度薪酬结算期" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="createDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="!createForm.month || createForm.reason.trim().length < 4" @click="createPeriod">创建</el-button></template>
    </el-dialog>
  </section>
</template>
