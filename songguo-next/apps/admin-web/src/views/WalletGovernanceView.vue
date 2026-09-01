<script setup lang="ts">
import { Coin, Refresh, Search } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { computed, reactive, ref, watch } from "vue";

import { ApiError } from "@/api/client";
import {
  commandKey,
  formatCents,
  formatDateTime,
  scopedCommand,
  scopedList,
  scopeRef,
  type MemberWalletLedgerRow,
  type MemberWalletRow,
} from "@/api/cardConsumption";
import BusinessScopeGuard from "@/components/BusinessScopeGuard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";
import { useBusinessScopeStore } from "@/stores/businessScope";

const scope = useBusinessScopeStore();
const query = ref("");
const page = ref(1);
const perPage = 20;
const total = ref(0);
const loading = ref(false);
const rows = ref<MemberWalletRow[]>([]);
const selected = ref<MemberWalletRow | null>(null);
const ledgerRows = ref<MemberWalletLedgerRow[]>([]);
const ledgerTotal = ref(0);
const ledgerPage = ref(1);
const ledgerLoading = ref(false);
const drawer = ref(false);
const adjustmentDialog = ref(false);
const saving = ref(false);
const adjustment = reactive({ amountYuan: 0, reason: "" });
let loadRequestId = 0;
let ledgerRequestId = 0;

const currentScope = computed(() => scope.ready ? scopeRef(scope.tenantId, scope.siteId) : null);

function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.payload.message : error instanceof Error ? error.message : "操作失败";
}

interface WalletLoadSnapshot {
  requestId: number;
  tenantId: number;
  siteId: number;
  query: string;
  page: number;
}

interface LedgerLoadSnapshot {
  requestId: number;
  tenantId: number;
  siteId: number;
  memberId: number;
  page: number;
}

function isCurrentLoad(snapshot: WalletLoadSnapshot) {
  const activeScope = currentScope.value;
  return snapshot.requestId === loadRequestId
    && activeScope?.tenantId === snapshot.tenantId
    && activeScope.siteId === snapshot.siteId
    && query.value.trim() === snapshot.query
    && page.value === snapshot.page;
}

function isCurrentLedgerLoad(snapshot: LedgerLoadSnapshot) {
  const activeScope = currentScope.value;
  return snapshot.requestId === ledgerRequestId
    && activeScope?.tenantId === snapshot.tenantId
    && activeScope.siteId === snapshot.siteId
    && drawer.value
    && selected.value?.memberId === snapshot.memberId
    && ledgerPage.value === snapshot.page;
}

function invalidateLedger() {
  ledgerRequestId += 1;
  ledgerLoading.value = false;
  ledgerRows.value = [];
  ledgerTotal.value = 0;
}

async function load() {
  const requestId = ++loadRequestId;
  const activeScope = currentScope.value;
  if (!activeScope) {
    rows.value = [];
    total.value = 0;
    loading.value = false;
    return;
  }

  const snapshot: WalletLoadSnapshot = {
    requestId,
    tenantId: activeScope.tenantId,
    siteId: activeScope.siteId,
    query: query.value.trim(),
    page: page.value,
  };

  loading.value = true;
  try {
    const data = await scopedList<MemberWalletRow>(activeScope, "/member-wallets", {
      query: snapshot.query,
      page: snapshot.page,
      perPage,
    });
    if (!isCurrentLoad(snapshot)) return;
    rows.value = data.items;
    total.value = data.pagination.total;
  } catch (error) {
    if (isCurrentLoad(snapshot)) ElMessage.error(errorMessage(error));
  } finally {
    if (requestId === loadRequestId) loading.value = false;
  }
}

async function loadLedger() {
  const requestId = ++ledgerRequestId;
  const activeScope = currentScope.value;
  const memberId = selected.value?.memberId;
  if (!activeScope || !memberId || !drawer.value) {
    ledgerLoading.value = false;
    ledgerRows.value = [];
    ledgerTotal.value = 0;
    return;
  }

  const snapshot: LedgerLoadSnapshot = {
    requestId,
    tenantId: activeScope.tenantId,
    siteId: activeScope.siteId,
    memberId,
    page: ledgerPage.value,
  };

  ledgerLoading.value = true;
  try {
    const data = await scopedList<MemberWalletLedgerRow>(
      activeScope,
      `/member-wallets/${snapshot.memberId}/ledger`,
      { page: snapshot.page, perPage: 20 },
    );
    if (!isCurrentLedgerLoad(snapshot)) return;
    ledgerRows.value = data.items;
    ledgerTotal.value = data.pagination.total;
  } catch (error) {
    if (isCurrentLedgerLoad(snapshot)) ElMessage.error(errorMessage(error));
  } finally {
    if (requestId === ledgerRequestId) ledgerLoading.value = false;
  }
}

async function inspect(row: MemberWalletRow) {
  invalidateLedger();
  selected.value = row;
  ledgerPage.value = 1;
  drawer.value = true;
  await loadLedger();
}

function openAdjustment(row: MemberWalletRow) {
  selected.value = row;
  adjustment.amountYuan = 0;
  adjustment.reason = "";
  adjustmentDialog.value = true;
}

async function submitAdjustment() {
  if (!currentScope.value || !selected.value || adjustment.amountYuan === 0 || adjustment.reason.trim().length < 4) return;
  const deltaCents = Math.round(adjustment.amountYuan * 100);
  const direction = deltaCents > 0 ? "增加" : "减少";
  try {
    await ElMessageBox.confirm(
      `将${direction}会员 ${selected.value.memberName} 的钱包余额 ${formatCents(Math.abs(deltaCents))}。该动作会生成不可篡改的调整流水。`,
      "确认钱包调整",
      { type: "warning", confirmButtonText: "确认提交", cancelButtonText: "取消" },
    );
  } catch {
    return;
  }
  saving.value = true;
  try {
    await scopedCommand(currentScope.value, `/member-wallets/${selected.value.memberId}/adjustments`, "POST", {
      amountCents: deltaCents,
      reason: adjustment.reason.trim(),
      version: selected.value.version,
      commandKey: commandKey(),
    });
    ElMessage.success("钱包调整流水已入账");
    adjustmentDialog.value = false;
    await load();
    if (drawer.value) await loadLedger();
  } catch (error) {
    ElMessage.error(errorMessage(error));
  } finally {
    saving.value = false;
  }
}

watch([() => scope.tenantId, () => scope.siteId, () => scope.ready], () => {
  page.value = 1;
  invalidateLedger();
  drawer.value = false;
  selected.value = null;
  adjustmentDialog.value = false;
  void load();
}, { immediate: true });

watch(drawer, (isOpen) => {
  if (!isOpen) invalidateLedger();
});
</script>

<template>
  <section>
    <PageHeading
      eyebrow="CARD CONSUMPTION / MEMBER WALLET"
      title="会员钱包与审计调整"
      description="会员钱包是独立的支付账户，不等同于储值会员卡。超管仅能查看余额与流水，或提交有原因、有命令号的增减调整。"
    ><el-button :icon="Refresh" @click="load">刷新</el-button></PageHeading>

    <BusinessScopeGuard write-hint="调整命令会携带期望版本、唯一 commandKey 和必填原因，并由超管审计中间件记录。" />

    <div class="security-banner wallet-banner">
      <el-icon><Coin /></el-icon>
      <div><strong>不允许直接覆盖余额</strong><span>页面没有“设置余额”或“删除流水”入口；所有变动都以 append-only 调整命令进入后端。</span></div>
    </div>

    <div class="data-panel">
      <div class="data-toolbar">
        <label class="search-box"><el-icon><Search /></el-icon><input v-model="query" placeholder="搜索会员姓名或编号" @keyup.enter="load" /></label>
        <el-button @click="page = 1; load()">查询</el-button>
        <span class="result-count">共 {{ total }} 个钱包</span>
      </div>
      <el-table v-loading="loading" :data="rows" class="resource-table">
        <el-table-column prop="memberName" label="会员" min-width="170"><template #default="{ row }"><div class="primary-cell"><b>{{ row.memberName }}</b><small>{{ row.memberNo }}</small></div></template></el-table-column>
        <el-table-column label="可用余额" width="150"><template #default="{ row }"><strong class="money-cell">{{ formatCents(row.balanceCents, row.currency) }}</strong></template></el-table-column>
        <el-table-column prop="currency" label="币种" width="90" />
        <el-table-column label="账户版本" width="100"><template #default="{ row }">v{{ row.version }}</template></el-table-column>
        <el-table-column label="最后流水" min-width="170"><template #default="{ row }">{{ formatDateTime(row.lastEntryAt) }}</template></el-table-column>
        <el-table-column label="状态" width="100"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="170" align="right"><template #default="{ row }"><el-button link @click="inspect(row)">查看流水</el-button><el-button link type="primary" :disabled="!scope.ready" @click="openAdjustment(row)">审计调整</el-button></template></el-table-column>
      </el-table>
      <div class="table-footer"><span>每页 {{ perPage }} 条</span><el-pagination v-model:current-page="page" layout="prev, pager, next" :page-size="perPage" :total="total" @current-change="load" /></div>
    </div>

    <el-drawer v-model="drawer" size="720px" :title="selected ? `${selected.memberName} · 钱包流水` : '钱包流水'">
      <div v-if="selected" class="wallet-summary-strip"><span>当前可用余额</span><strong>{{ formatCents(selected.balanceCents, selected.currency) }}</strong><small>账户版本 v{{ selected.version }}</small></div>
      <el-table v-loading="ledgerLoading" :data="ledgerRows" class="resource-table">
        <el-table-column label="时间" width="160"><template #default="{ row }">{{ formatDateTime(row.occurredAt) }}</template></el-table-column>
        <el-table-column prop="entryType" label="类型" width="110" />
        <el-table-column label="变动" width="120"><template #default="{ row }"><b :class="row.deltaCents >= 0 ? 'amount-positive' : 'amount-negative'">{{ row.deltaCents >= 0 ? "+" : "" }}{{ formatCents(row.deltaCents) }}</b></template></el-table-column>
        <el-table-column label="变动后" width="120"><template #default="{ row }">{{ formatCents(row.balanceAfterCents) }}</template></el-table-column>
        <el-table-column prop="reason" label="原因" min-width="170" />
        <el-table-column prop="actorName" label="操作人" width="110" />
      </el-table>
      <div class="table-footer"><span>共 {{ ledgerTotal }} 条不可篡改流水</span><el-pagination v-model:current-page="ledgerPage" layout="prev, pager, next" :page-size="20" :total="ledgerTotal" @current-change="loadLedger" /></div>
    </el-drawer>

    <el-dialog v-model="adjustmentDialog" title="提交钱包审计调整" width="520px">
      <div v-if="selected" class="dialog-subject"><span>调整对象</span><strong>{{ selected.memberName }} · {{ selected.memberNo }}</strong><small>当前 {{ formatCents(selected.balanceCents, selected.currency) }}</small></div>
      <el-form label-position="top">
        <el-form-item label="变动金额（元）"><el-input-number v-model="adjustment.amountYuan" :precision="2" :step="100" /><p class="form-help">正数增加余额，负数减少余额；后端会拒绝导致余额为负数的命令。</p></el-form-item>
        <el-form-item label="调整原因"><el-input v-model="adjustment.reason" type="textarea" :rows="3" maxlength="300" show-word-limit placeholder="必填：由谁批准、为何调整" /></el-form-item>
      </el-form>
      <template #footer><el-button @click="adjustmentDialog = false">取消</el-button><el-button type="primary" :loading="saving" :disabled="adjustment.amountYuan === 0 || adjustment.reason.trim().length < 4" @click="submitAdjustment">提交审计命令</el-button></template>
    </el-dialog>
  </section>
</template>
