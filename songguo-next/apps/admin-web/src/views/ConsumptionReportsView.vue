<script setup lang="ts">
import { DataAnalysis, Refresh, RefreshLeft, Search, View } from "@element-plus/icons-vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { computed, ref, watch } from "vue";

import { ApiError } from "@/api/client";
import {
  commandKey,
  formatBasisPoints,
  formatCents,
  formatDateTime,
  localDateString,
  scopedList,
  scopedCommand,
  scopeRef,
  type ConsumptionEventRow,
  type ConsumptionReportRow,
} from "@/api/cardConsumption";
import BusinessScopeGuard from "@/components/BusinessScopeGuard.vue";
import PageHeading from "@/components/PageHeading.vue";
import StatusPill from "@/components/StatusPill.vue";
import { useBusinessScopeStore } from "@/stores/businessScope";

const scope = useBusinessScopeStore();
const tab = ref<"summary" | "events">("summary");
const dimension = ref<"delivery" | "share" | "member" | "course" | "card">("delivery");
const reportStatus = ref<"" | "provisional" | "final" | "adjusted">("");
const eventStatus = ref<"" | "provisional" | "final" | "adjusted" | "reversed">("");
const query = ref("");
const today = new Date();
const from = ref(localDateString(new Date(today.getFullYear(), today.getMonth(), 1)));
const to = ref(localDateString(today));
const page = ref(1);
const perPage = 20;
const total = ref(0);
const loading = ref(false);
const reportRows = ref<ConsumptionReportRow[]>([]);
const eventRows = ref<ConsumptionEventRow[]>([]);
const selectedEvent = ref<ConsumptionEventRow | null>(null);
const eventDrawer = ref(false);
let loadRequestId = 0;

const currentScope = computed(() => scope.ready ? scopeRef(scope.tenantId, scope.siteId) : null);
const dimensionLabel = computed(() => ({
  delivery: "实际上课者",
  share: "卡归属分成人员",
  member: "学员",
  course: "课程",
  card: "会员卡",
})[dimension.value]);
const showSessionFee = computed(() => dimension.value === "delivery" || dimension.value === "course");
const showCompensationTotal = computed(() => dimension.value !== "member" && dimension.value !== "card");

function errorMessage(error: unknown) {
  return error instanceof ApiError ? error.payload.message : error instanceof Error ? error.message : "操作失败";
}

function componentLabel(value: string) {
  return value === "session_fee" ? "课时费" : value === "consumption_commission" ? "耗卡提成" : value;
}

function lineTypeLabel(value: string) {
  return ({ accrual: "计提", adjustment: "调整", reversal: "冲正" } as Record<string, string>)[value] ?? value;
}

interface ConsumptionLoadSnapshot {
  requestId: number;
  tenantId: number;
  siteId: number;
  tab: typeof tab.value;
  dimension: typeof dimension.value;
  reportStatus: typeof reportStatus.value;
  eventStatus: typeof eventStatus.value;
  query: string;
  from: string;
  to: string;
  page: number;
}

function isCurrentLoad(snapshot: ConsumptionLoadSnapshot) {
  const activeScope = currentScope.value;
  return snapshot.requestId === loadRequestId
    && activeScope?.tenantId === snapshot.tenantId
    && activeScope.siteId === snapshot.siteId
    && tab.value === snapshot.tab
    && dimension.value === snapshot.dimension
    && reportStatus.value === snapshot.reportStatus
    && eventStatus.value === snapshot.eventStatus
    && query.value.trim() === snapshot.query
    && from.value === snapshot.from
    && to.value === snapshot.to
    && page.value === snapshot.page;
}

async function load() {
  const requestId = ++loadRequestId;
  const activeScope = currentScope.value;
  if (!activeScope) {
    reportRows.value = [];
    eventRows.value = [];
    total.value = 0;
    loading.value = false;
    return;
  }

  const snapshot: ConsumptionLoadSnapshot = {
    requestId,
    tenantId: activeScope.tenantId,
    siteId: activeScope.siteId,
    tab: tab.value,
    dimension: dimension.value,
    reportStatus: reportStatus.value,
    eventStatus: eventStatus.value,
    query: query.value.trim(),
    from: from.value,
    to: to.value,
    page: page.value,
  };

  loading.value = true;
  try {
    if (snapshot.tab === "summary") {
      const data = await scopedList<ConsumptionReportRow>(activeScope, "/consumption-reports", {
        dimension: snapshot.dimension,
        status: snapshot.reportStatus,
        query: snapshot.query,
        from: snapshot.from,
        to: snapshot.to,
        page: snapshot.page,
        perPage,
      });
      if (!isCurrentLoad(snapshot)) return;
      reportRows.value = data.items;
      total.value = data.pagination.total;
    } else {
      const data = await scopedList<ConsumptionEventRow>(activeScope, "/consumption-events", {
        query: snapshot.query,
        status: snapshot.eventStatus,
        from: snapshot.from,
        to: snapshot.to,
        page: snapshot.page,
        perPage,
      });
      if (!isCurrentLoad(snapshot)) return;
      eventRows.value = data.items;
      total.value = data.pagination.total;
    }
  } catch (error) {
    if (isCurrentLoad(snapshot)) ElMessage.error(errorMessage(error));
  } finally {
    if (requestId === loadRequestId) loading.value = false;
  }
}

function inspectEvent(row: ConsumptionEventRow) {
  selectedEvent.value = row;
  eventDrawer.value = true;
}

async function reverseEvent(row: ConsumptionEventRow) {
  if (!currentScope.value || row.status === "reversed") return;
  try {
    const prompt = await ElMessageBox.prompt(
      "冲正会恢复会员卡权益并追加反向提成行，不会删除原耗卡事实。",
      `冲正耗卡 #${row.id}`,
      {
        type: "warning",
        inputPlaceholder: "必填：审批单号与冲正原因",
        inputValidator: (value) => value.trim().length >= 4 || "原因至少 4 个字",
        confirmButtonText: "确认冲正",
        cancelButtonText: "取消",
      },
    );
    await scopedCommand(currentScope.value, `/consumption-events/${row.id}/reverse`, "POST", {
      reason: prompt.value.trim(),
      commandKey: commandKey(),
    });
    ElMessage.success("耗卡已冲正，原事实与反向行均已保留");
    await load();
  } catch (error) {
    if (error === "cancel" || error === "close") return;
    ElMessage.error(errorMessage(error));
  }
}

watch([() => scope.tenantId, () => scope.siteId, () => scope.ready, tab, dimension], () => {
  page.value = 1;
  void load();
}, { immediate: true });
</script>

<template>
  <section>
    <PageHeading
      eyebrow="CARD CONSUMPTION / REPORTING"
      title="耗卡与提成报表"
      description="以同一组已履约耗卡事件和结算行，从实际上课者、卡归属分成人员、学员、课程和会员卡五个维度查看。页面不会用当前规则重算历史。"
    ><el-button :icon="Refresh" @click="load">刷新</el-button></PageHeading>

    <BusinessScopeGuard write-hint="报表以场馆业务日期为准；只有选定租户/场馆后才能提交审计冲正。" />

    <div class="security-banner report-readonly-banner">
      <el-icon><DataAnalysis /></el-icon>
      <div><strong>事实不可改写，异常使用冲正</strong><span>冲正由服务端恢复权益并追加反向结算行；Web 不提供删除、改金额或未审计重算。</span></div>
    </div>

    <div class="governance-tabs">
      <button :class="{ active: tab === 'summary' }" @click="tab = 'summary'">五维汇总</button>
      <button :class="{ active: tab === 'events' }" @click="tab = 'events'">耗卡事件明细</button>
    </div>

    <div class="data-panel">
      <div class="data-toolbar report-toolbar">
        <el-segmented v-if="tab === 'summary'" v-model="dimension" :options="[
          { label: '实际上课者', value: 'delivery' },
          { label: '卡归属分成人员', value: 'share' },
          { label: '学员', value: 'member' },
          { label: '课程', value: 'course' },
          { label: '会员卡', value: 'card' },
        ]" />
        <el-select v-if="tab === 'summary'" v-model="reportStatus" clearable placeholder="全部状态" style="width: 130px" @change="page = 1; load()">
          <el-option label="暂计" value="provisional" />
          <el-option label="已结算" value="final" />
          <el-option label="已调整" value="adjusted" />
        </el-select>
        <el-select v-else v-model="eventStatus" clearable placeholder="全部状态" style="width: 130px" @change="page = 1; load()">
          <el-option label="暂计" value="provisional" />
          <el-option label="已结算" value="final" />
          <el-option label="已调整" value="adjusted" />
          <el-option label="已冲正" value="reversed" />
        </el-select>
        <label class="search-box"><el-icon><Search /></el-icon><input v-model="query" :placeholder="tab === 'summary' ? `搜索${dimensionLabel}` : '搜索会员、卡号或课程'" @keyup.enter="load" /></label>
        <el-date-picker v-model="from" type="date" value-format="YYYY-MM-DD" placeholder="开始日期" />
        <span class="date-separator">至</span>
        <el-date-picker v-model="to" type="date" value-format="YYYY-MM-DD" placeholder="结束日期" />
        <el-button @click="page = 1; load()">查询</el-button>
      </div>
      <div v-if="tab === 'summary' && dimension === 'share'" class="immutable-note">B 类耗卡归属按每个业务角色独立计算，每个角色内 allocationBps 合计 100%。同一员工可承担多个 B 角色，各角色按自身费率分别产生提成，按人汇总会包含多个角色的归属价值。</div>

      <el-table v-if="tab === 'summary'" v-loading="loading" :data="reportRows" class="resource-table">
        <el-table-column prop="subjectName" :label="dimensionLabel" min-width="180" />
        <el-table-column prop="consumptionCount" label="已履约耗卡" width="120"><template #default="{ row }">{{ row.consumptionCount }} 次</template></el-table-column>
        <el-table-column label="耗卡价值" width="130"><template #default="{ row }">{{ formatCents(row.consumedAmountCents) }}</template></el-table-column>
        <el-table-column v-if="showSessionFee" label="课时费" width="120"><template #default="{ row }">{{ formatCents(row.sessionFeeCents) }}</template></el-table-column>
        <el-table-column label="耗卡提成" width="130"><template #default="{ row }">{{ formatCents(row.commissionCents) }}</template></el-table-column>
        <el-table-column v-if="showCompensationTotal" label="合计薪酬" width="130"><template #default="{ row }"><strong class="money-cell">{{ formatCents(row.totalCompensationCents) }}</strong></template></el-table-column>
        <el-table-column label="公式版本" min-width="120"><template #default="{ row }"><code>{{ row.formulaVersion ?? "多版本" }}</code></template></el-table-column>
      </el-table>

      <el-table v-else v-loading="loading" :data="eventRows" class="resource-table">
        <el-table-column label="业务日" width="110"><template #default="{ row }">{{ row.businessDate }}</template></el-table-column>
        <el-table-column prop="memberName" label="会员" min-width="130" />
        <el-table-column prop="memberCardNo" label="会员卡" min-width="145" />
        <el-table-column prop="courseName" label="课程" min-width="140" />
        <el-table-column label="上课时间" min-width="160"><template #default="{ row }">{{ formatDateTime(row.sessionStartsAt) }}</template></el-table-column>
        <el-table-column prop="cardType" label="卡类型" width="105" />
        <el-table-column label="实际耗卡" width="120"><template #default="{ row }">{{ row.consumedAmountCents !== null ? formatCents(row.consumedAmountCents) : row.consumedCount !== null ? `${row.consumedCount} 次` : '按日' }}</template></el-table-column>
        <el-table-column label="计算版本" width="100"><template #default="{ row }">v{{ row.calculationVersion }}</template></el-table-column>
        <el-table-column label="状态" width="95"><template #default="{ row }"><StatusPill :value="row.status" /></template></el-table-column>
        <el-table-column label="操作" width="150" align="right">
          <template #default="{ row }">
            <el-button link :icon="View" @click="inspectEvent(row)">快照</el-button>
            <el-button v-if="row.status !== 'reversed'" link type="danger" :icon="RefreshLeft" :disabled="!scope.ready" @click="reverseEvent(row)">冲正</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="table-footer"><span>共 {{ total }} 条</span><el-pagination v-model:current-page="page" layout="prev, pager, next" :page-size="perPage" :total="total" @current-change="load" /></div>
    </div>

    <el-drawer v-model="eventDrawer" size="760px" title="耗卡公式与结算快照">
      <template v-if="selectedEvent">
        <div class="event-fact-grid">
          <div><span>会员</span><strong>{{ selectedEvent.memberName }}</strong></div>
          <div><span>卡号</span><strong>{{ selectedEvent.memberCardNo }}</strong></div>
          <div><span>课程</span><strong>{{ selectedEvent.courseName }}</strong></div>
          <div><span>业务日</span><strong>{{ selectedEvent.businessDate }}</strong></div>
          <div><span>计算版本</span><strong>v{{ selectedEvent.calculationVersion }}</strong></div>
          <div><span>状态</span><StatusPill :value="selectedEvent.status" /></div>
        </div>
        <h3 class="drawer-section-title">服务端保存的公式输入</h3>
        <dl class="formula-inputs"><template v-for="(value, key) in selectedEvent.formulaInputs" :key="key"><dt>{{ key }}</dt><dd>{{ value ?? "—" }}</dd></template></dl>
        <h3 class="drawer-section-title">A 类实际履约快照</h3>
        <el-table v-if="selectedEvent.deliveryRecipients.length" :data="selectedEvent.deliveryRecipients" size="small">
          <el-table-column prop="staffName" label="员工" min-width="120" />
          <el-table-column prop="roleName" label="业务角色" min-width="120" />
          <el-table-column label="分配"><template #default="{ row }">{{ formatBasisPoints(row.allocationBps) }}</template></el-table-column>
        </el-table>
        <div v-else class="immutable-note">该历史事件没有 A 类快照。</div>
        <h3 class="drawer-section-title">B 类卡归属快照</h3>
        <el-table v-if="selectedEvent.shareRecipients.length" :data="selectedEvent.shareRecipients" size="small">
          <el-table-column prop="staffName" label="员工" min-width="120" />
          <el-table-column prop="roleName" label="业务角色" min-width="120" />
          <el-table-column label="分配"><template #default="{ row }">{{ formatBasisPoints(row.allocationBps) }}</template></el-table-column>
        </el-table>
        <div v-else class="immutable-note">该事件没有 B 类卡归属快照。</div>
        <h3 class="drawer-section-title">次卡价值批次分配</h3>
        <el-table v-if="selectedEvent.valueLotAllocations.length" :data="selectedEvent.valueLotAllocations" size="small">
          <el-table-column label="价值批次"><template #default="{ row }">{{ row.valueLotId ? `#${row.valueLotId}` : "历史未知批次" }}</template></el-table-column>
          <el-table-column prop="count" label="扣除次数" />
          <el-table-column label="批次价值"><template #default="{ row }">{{ formatCents(row.valueCents) }}</template></el-table-column>
        </el-table>
        <div v-else class="immutable-note">非次卡或本次没有价值批次分配。</div>
        <h3 class="drawer-section-title">完整提成结算行</h3>
        <el-table v-if="selectedEvent.commissionLines.length" :data="selectedEvent.commissionLines" size="small">
          <el-table-column prop="staffName" label="收款人" min-width="110" />
          <el-table-column label="角色" min-width="120"><template #default="{ row }">{{ row.roleName ?? (row.roleType === "delivery" ? "A 类" : row.roleType === "share" ? "B 类" : "未绑定角色") }}</template></el-table-column>
          <el-table-column label="项目" min-width="105"><template #default="{ row }">{{ componentLabel(row.component) }}</template></el-table-column>
          <el-table-column label="公式输入" min-width="190"><template #default="{ row }"><span>{{ formatCents(row.baseValueCents) }} × {{ formatBasisPoints(row.rateBps) }} × {{ formatBasisPoints(row.allocationBps) }}</span></template></el-table-column>
          <el-table-column label="本行增量" min-width="105"><template #default="{ row }">{{ formatCents(row.deltaCents) }}</template></el-table-column>
          <el-table-column label="行后净额" min-width="105"><template #default="{ row }"><strong>{{ formatCents(row.netCents) }}</strong></template></el-table-column>
          <el-table-column label="类型" min-width="105"><template #default="{ row }"><span>{{ lineTypeLabel(row.lineType) }}</span><el-tag v-if="row.postCloseAdjustment" size="small" type="warning">关账后</el-tag></template></el-table-column>
        </el-table>
        <div v-else class="immutable-note">本次耗卡没有产生提成结算行。</div>
        <div class="immutable-note">这些数据是耗卡发生时的快照，课程、卡项或分成规则之后改动不会覆盖它。</div>
      </template>
    </el-drawer>
  </section>
</template>
