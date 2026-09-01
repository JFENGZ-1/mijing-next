import { useApiClient } from "@/api/client";
import type {
  AppointmentConsumptionPreview,
  ConsumptionCommissionLine,
  ConsumptionSettlement,
  ConsumptionSettlementList,
  ConsumptionSettlementListItem,
  ConsumptionSettlementQuery,
  ConsumptionSettlementStatus,
  PayrollPeriod,
  PayrollPeriodList,
} from "@/types/consumption";
import { centsToDecimal } from "@/utils/money";

function sitePath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}${suffix}`;
}

function buildQuery(params: Record<string, string | number | undefined>) {
  const values = Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== "")
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
  return values.length ? `?${values.join("&")}` : "";
}

interface DeductionWire {
  amount?: string | null;
  amountCents?: number | null;
  count?: number | null;
}

interface FormulaInputsWire {
  paidAmount?: string | null;
  paidAmountCents?: number | null;
  deductedAmount?: string | null;
  deductedAmountCents?: number | null;
  totalCount?: number | null;
  entitlementCount?: number | null;
  deductedCount?: number | null;
  totalDays?: number | null;
  entitlementDays?: number | null;
  dayUseCount?: number | null;
  activeDayConsumptionCount?: number | null;
  dailyBaseAmount?: string | null;
  dailyBaseAmountCents?: number | null;
  dayValueCents?: number | null;
  perUseBaseAmount?: string | null;
  perUseBaseAmountCents?: number | null;
  consumedValueCents?: number | null;
  rateBasisPoints?: number | null;
  rateBps?: number | null;
}

interface PreviewWire {
  appointmentId: number;
  memberId?: number;
  memberName?: string | null;
  courseId?: number;
  courseName?: string | null;
  cardProductId?: number | null;
  cardName?: string | null;
  cardType?: string;
  deductionKind?: string;
  deductionType?: string;
  deduction?: DeductionWire;
  reservedAmount?: string | null;
  reservedCount?: number | null;
  consumptionValue?: string | null;
  consumedValueCents?: number | null;
  consumedAmountCents?: number | null;
  estimatedConsumedValueCents?: number | null;
  settlementStatus?: string;
  viewStatus?: string;
  status?: string;
  settlementHint?: string | null;
  valueProvenance?: string | null;
  settleable?: boolean;
  existingSettlementId?: number | null;
  formulaInputs?: FormulaInputsWire | null;
  sessionFee?: string | null;
  sessionFeeCents?: number | null;
  estimatedCommissionAmount?: string | null;
  estimatedCommissionCents?: number | null;
  ruleVersion?: number | null;
  courseCompensationRuleVersion?: number | null;
  formulaVersion?: string | number | null;
  businessDate?: string | null;
}

function mapFormulaInputs(raw?: FormulaInputsWire | null): AppointmentConsumptionPreview["formulaInputs"] {
  if (!raw) return null;
  return {
    paidAmount: raw.paidAmount ?? centsToDecimal(raw.paidAmountCents),
    deductedAmount: raw.deductedAmount ?? centsToDecimal(raw.deductedAmountCents),
    totalCount: raw.totalCount ?? raw.entitlementCount,
    deductedCount: raw.deductedCount,
    totalDays: raw.totalDays ?? raw.entitlementDays,
    dayUseCount: raw.dayUseCount ?? raw.activeDayConsumptionCount,
    dailyBaseAmount: raw.dailyBaseAmount ?? centsToDecimal(raw.dailyBaseAmountCents ?? raw.dayValueCents),
    perUseBaseAmount: raw.perUseBaseAmount ?? centsToDecimal(raw.perUseBaseAmountCents ?? raw.consumedValueCents),
    rateBasisPoints: raw.rateBasisPoints ?? raw.rateBps,
  };
}

function formulaVersionNumber(value?: string | number | null) {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : undefined;
  }
  const match = value?.match(/(\d+)$/);
  return match ? Number(match[1]) : undefined;
}

function deductionKind(raw: PreviewWire): string {
  if (raw.deductionType || raw.deductionKind) return raw.deductionType ?? raw.deductionKind ?? "period_auto";
  if (raw.cardType === "stored_value") return "amount";
  if (raw.cardType === "count") return "count";
  return "period_auto";
}

function mapStatus(rawStatus: string | undefined, cardType?: string, settleable?: boolean): ConsumptionSettlementStatus {
  if (rawStatus === "final") return "settled";
  if (rawStatus === "provisional") return cardType === "period" ? "pending_day_close" : "settled";
  if (rawStatus === "archived") return "reversed";
  if (rawStatus) return rawStatus;
  if (settleable) return cardType === "period" ? "pending_day_close" : "pending";
  return "reserved";
}

function mapPreview(raw: PreviewWire): AppointmentConsumptionPreview {
  const consumedCents = raw.consumedValueCents ?? raw.consumedAmountCents ?? raw.estimatedConsumedValueCents;
  const provenanceHint = raw.valueProvenance === "unknown"
    ? "缺少实付价值快照，暂无法确定耗卡价值"
    : null;
  return {
    appointmentId: Number(raw.appointmentId),
    memberId: raw.memberId,
    memberName: raw.memberName,
    courseId: raw.courseId,
    courseName: raw.courseName,
    cardProductId: raw.cardProductId,
    cardName: raw.cardName,
    cardType: raw.cardType ?? "",
    deductionKind: deductionKind(raw),
    reservedAmount: raw.reservedAmount ?? raw.deduction?.amount ?? centsToDecimal(raw.deduction?.amountCents),
    reservedCount: raw.reservedCount ?? raw.deduction?.count,
    consumptionValue: raw.consumptionValue ?? centsToDecimal(consumedCents),
    settlementStatus: mapStatus(raw.viewStatus ?? raw.settlementStatus ?? raw.status, raw.cardType, raw.settleable),
    settlementHint: raw.settlementHint ?? provenanceHint,
    formulaInputs: mapFormulaInputs(raw.formulaInputs),
    sessionFee: raw.sessionFee ?? centsToDecimal(raw.sessionFeeCents),
    estimatedCommissionAmount: raw.estimatedCommissionAmount ?? centsToDecimal(raw.estimatedCommissionCents),
    ruleVersion: raw.ruleVersion ?? raw.courseCompensationRuleVersion ?? formulaVersionNumber(raw.formulaVersion),
  };
}

interface CommissionLineWire {
  id?: number;
  staffId?: number | null;
  staffName?: string | null;
  roleId?: number;
  compensationRoleId?: number;
  roleName?: string | null;
  roleType?: "delivery" | "share";
  component?: string;
  sessionFee?: string;
  sessionFeeCents?: number;
  commissionAmount?: string;
  amountCents?: number;
  rateBasisPoints?: number;
  rateBps?: number;
  allocationBps?: number;
}

function mapCommissionLine(raw: CommissionLineWire): ConsumptionCommissionLine {
  const amount = raw.commissionAmount ?? centsToDecimal(raw.amountCents) ?? "0.00";
  const isSessionFee = raw.component === "session_fee";
  return {
    id: raw.id,
    staffId: raw.staffId,
    staffName: raw.staffName,
    roleId: Number(raw.compensationRoleId ?? raw.roleId ?? 0),
    roleName: raw.roleName ?? `角色 #${raw.compensationRoleId ?? raw.roleId ?? "—"}`,
    roleType: raw.roleType ?? (isSessionFee ? "delivery" : "share"),
    component: raw.component,
    rateBasisPoints: Number(raw.rateBps ?? raw.rateBasisPoints ?? 0),
    allocationBps: raw.allocationBps != null ? Number(raw.allocationBps) : undefined,
    sessionFee: raw.sessionFee ?? centsToDecimal(raw.sessionFeeCents) ?? (isSessionFee ? amount : "0.00"),
    commissionAmount: isSessionFee ? "0.00" : amount,
  };
}

interface SettlementWire extends PreviewWire {
  id: number;
  settlementNo?: string | null;
  coachStaffId?: number | null;
  coachName?: string | null;
  shareStaffNames?: string[];
  occurredAt?: string | null;
  settledAt?: string | null;
  businessDate?: string | null;
  commissionLines?: CommissionLineWire[];
  commissionAmount?: string | null;
  commissionCents?: number | null;
  commissionTotalCents?: number | null;
  adjustmentReason?: string | null;
  reversalReason?: string | null;
}

function mapSettlement(raw: SettlementWire): ConsumptionSettlement {
  const commissionLines = (raw.commissionLines ?? []).map(mapCommissionLine);
  const sessionFeeCents = raw.sessionFeeCents ?? (raw.commissionLines ?? [])
    .filter((line) => line.component === "session_fee")
    .reduce((total, line) => total + Number(line.amountCents ?? 0), 0);
  const commissionCents = raw.commissionCents ?? ((raw.commissionLines?.length ?? 0) > 0
    ? (raw.commissionLines ?? [])
        .filter((line) => line.component !== "session_fee")
        .reduce((total, line) => total + Number(line.amountCents ?? 0), 0)
    : raw.commissionTotalCents);
  return {
    ...mapPreview(raw),
    id: Number(raw.id),
    settlementNo: raw.settlementNo,
    coachStaffId: raw.coachStaffId,
    coachName: raw.coachName,
    shareStaffNames: raw.shareStaffNames,
    occurredAt: raw.occurredAt,
    settledAt: raw.settledAt,
    businessDate: raw.businessDate,
    sessionFee: raw.sessionFee ?? centsToDecimal(sessionFeeCents),
    commissionLines,
    commissionAmount: raw.commissionAmount ?? centsToDecimal(commissionCents),
    adjustmentReason: raw.adjustmentReason ?? raw.reversalReason,
  };
}

export async function fetchAppointmentConsumptionPreview(siteId: number, appointmentId: number) {
  const response = await useApiClient().request<PreviewWire>(
    sitePath(siteId, `/appointments/${appointmentId}/consumption-preview`),
  );
  return mapPreview(response.data);
}

export async function fetchAppointmentConsumptionSettlement(siteId: number, appointmentId: number) {
  const response = await useApiClient().request<SettlementWire | null>(
    sitePath(siteId, `/appointments/${appointmentId}/consumption-settlement`),
  );
  return response.data ? mapSettlement(response.data) : null;
}

interface SettlementListWire {
  items: Array<Partial<SettlementWire> & {
    key?: string | number | null;
    name?: string | null;
    dimensionKey?: string | number | null;
    dimensionName?: string | null;
    subjectId?: string | number | null;
    subjectName?: string | null;
    consumptionCount?: number;
    consumedValueCents?: number;
    consumedAmountCents?: number;
    sessionFeeCents?: number;
    commissionCents?: number;
  }>;
  summary?: {
    consumptionCount?: number;
    consumptionValue?: string;
    consumedValueCents?: number;
    consumedAmountCents?: number;
    sessionFee?: string;
    sessionFeeCents?: number;
    commissionAmount?: string;
    commissionCents?: number;
    pendingCount?: number;
  };
  pagination?: ConsumptionSettlementList["pagination"];
}

function mapListItem(raw: SettlementListWire["items"][number]): ConsumptionSettlementListItem {
  const dimensionKey = raw.dimensionKey ?? raw.key ?? raw.subjectId;
  // Aggregate rows may carry the conventional sentinel id=0. They must not be
  // mapped as settlement details or become tappable just because id is present.
  const isAggregate = dimensionKey != null && (raw.id == null || Number(raw.id) <= 0);
  if (isAggregate) {
    return {
      id: 0,
      isAggregate: true,
      dimensionKey,
      dimensionName: raw.dimensionName ?? raw.name ?? raw.subjectName ?? `#${dimensionKey}`,
      consumptionCount: Number(raw.consumptionCount ?? 0),
      consumptionValue: raw.consumptionValue ?? centsToDecimal(raw.consumedValueCents ?? raw.consumedAmountCents),
      sessionFee: raw.sessionFee ?? centsToDecimal(raw.sessionFeeCents),
      commissionAmount: raw.commissionAmount ?? centsToDecimal(raw.commissionCents),
    };
  }
  const base = mapSettlement({
    ...raw,
    id: raw.id ?? 0,
    appointmentId: raw.appointmentId ?? 0,
    cardType: raw.cardType ?? "",
    deductionType: raw.deductionType ?? raw.deductionKind ?? "period_auto",
    status: raw.status ?? "final",
    commissionLines: raw.commissionLines ?? [],
  });
  return {
    ...base,
    isAggregate: false,
    dimensionKey,
    dimensionName: raw.dimensionName ?? raw.name ?? raw.subjectName,
    consumptionCount: raw.consumptionCount,
    consumptionValue: raw.consumptionValue ?? centsToDecimal(raw.consumedValueCents ?? raw.consumedAmountCents) ?? base.consumptionValue,
    commissionAmount: raw.commissionAmount ?? centsToDecimal(raw.commissionCents) ?? base.commissionAmount,
  };
}

export async function fetchConsumptionSettlements(siteId: number, query: ConsumptionSettlementQuery) {
  const wireStatus = query.status === "settled" ? "final"
    : query.status === "pending_day_close" ? "provisional" : query.status;
  const response = await useApiClient().request<SettlementListWire>(
    `${sitePath(siteId, "/consumption-settlements")}${buildQuery({
      dimension: query.dimension,
      from: query.from,
      to: query.to,
      query: query.query,
      status: wireStatus,
      page: query.page,
      perPage: query.perPage,
    })}`,
  );
  const summary = response.data.summary;
  return {
    items: (response.data.items ?? []).map(mapListItem),
    summary: summary ? {
      consumptionCount: Number(summary.consumptionCount ?? 0),
      consumptionValue: summary.consumptionValue ?? centsToDecimal(summary.consumedValueCents ?? summary.consumedAmountCents) ?? "0.00",
      sessionFee: summary.sessionFee ?? centsToDecimal(summary.sessionFeeCents) ?? "0.00",
      commissionAmount: summary.commissionAmount ?? centsToDecimal(summary.commissionCents) ?? "0.00",
      pendingCount: summary.pendingCount,
    } : undefined,
    pagination: response.data.pagination ?? { page: 1, perPage: 20, total: response.data.items?.length ?? 0, lastPage: 1 },
  };
}

export async function fetchConsumptionSettlement(siteId: number, settlementId: number) {
  const response = await useApiClient().request<SettlementWire>(
    sitePath(siteId, `/consumption-settlements/${settlementId}`),
  );
  return mapSettlement(response.data);
}

export async function reverseConsumptionSettlement(
  siteId: number,
  settlementId: number,
  payload: { reason: string; commandKey: string },
) {
  const response = await useApiClient().request<SettlementWire>(
    sitePath(siteId, `/consumption-settlements/${settlementId}/reverse`),
    { method: "POST", data: payload },
  );
  return mapSettlement(response.data);
}

interface PayrollPeriodWire {
  id: number;
  year?: number;
  month?: number;
  startsOn?: string;
  endsOn?: string;
  status?: string;
  version?: number;
  settlementCount?: number;
  settlementLineCount?: number;
  pendingCount?: number;
  consumptionValue?: string;
  consumedValueCents?: number;
  sessionFee?: string;
  sessionFeeCents?: number;
  commissionAmount?: string;
  commissionCents?: number;
  closedAt?: string | null;
  closedByStaffName?: string | null;
  canClose?: boolean;
  closeBlockedReason?: string | null;
  blockedReason?: string | null;
  closeBlockedReasons?: string[];
}

function mapPayrollPeriod(raw: PayrollPeriodWire): PayrollPeriod {
  const date = raw.startsOn ? new Date(`${raw.startsOn}T00:00:00`) : null;
  return {
    id: Number(raw.id),
    year: Number(raw.year ?? date?.getFullYear() ?? 0),
    month: Number(raw.month ?? (date ? date.getMonth() + 1 : 0)),
    startsOn: raw.startsOn,
    endsOn: raw.endsOn,
    status: raw.status ?? "open",
    version: Number(raw.version ?? 1),
    settlementCount: raw.settlementCount ?? raw.settlementLineCount,
    pendingCount: raw.pendingCount,
    consumptionValue: raw.consumptionValue ?? centsToDecimal(raw.consumedValueCents) ?? undefined,
    sessionFee: raw.sessionFee ?? centsToDecimal(raw.sessionFeeCents) ?? undefined,
    commissionAmount: raw.commissionAmount ?? centsToDecimal(raw.commissionCents) ?? undefined,
    closedAt: raw.closedAt,
    closedByStaffName: raw.closedByStaffName,
    canClose: raw.canClose ?? false,
    closeBlockedReason: payrollBlockedReason(
      raw.closeBlockedReason ?? raw.blockedReason ?? raw.closeBlockedReasons?.join("；"),
    ),
  };
}

function payrollBlockedReason(reason?: string | null) {
  if (!reason) return reason;
  return ({
    PAYROLL_PERIOD_NOT_ENDED: "期间尚未结束",
    PAYROLL_PERIOD_BUCKETS_OPEN: "仍有期限卡待日结，暂不能关账",
  } as Record<string, string>)[reason] ?? reason;
}

export async function fetchPayrollPeriods(
  siteId: number,
  query: { year?: number; month?: number; page?: number; perPage?: number } = {},
) {
  const response = await useApiClient().request<{
    items: PayrollPeriodWire[];
    pagination?: PayrollPeriodList["pagination"];
  }>(`${sitePath(siteId, "/payroll-periods")}${buildQuery(query)}`);
  return {
    items: (response.data.items ?? []).map(mapPayrollPeriod),
    pagination: response.data.pagination,
  };
}

export async function fetchAllPayrollPeriods(siteId: number) {
  const items: PayrollPeriod[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchPayrollPeriods(siteId, { page, perPage: 100 });
    items.push(...response.items);
    lastPage = response.pagination?.lastPage ?? page;
    page += 1;
  } while (page <= lastPage);
  return items;
}

export async function closePayrollPeriod(
  siteId: number,
  periodId: number,
  payload: { version: number; commandKey: string; reason: string },
) {
  const response = await useApiClient().request<PayrollPeriodWire>(
    sitePath(siteId, `/payroll-periods/${periodId}/close`),
    {
      method: "POST",
      data: { ...payload, expectedVersion: payload.version },
    },
  );
  return mapPayrollPeriod(response.data);
}

export async function createPayrollPeriod(
  siteId: number,
  payload: { startsOn: string; endsOn: string; reason: string; commandKey: string },
) {
  const [year, month] = payload.startsOn.split("-").map(Number);
  const response = await useApiClient().request<PayrollPeriodWire>(
    sitePath(siteId, "/payroll-periods"),
    { method: "POST", data: { ...payload, year, month } },
  );
  return mapPayrollPeriod(response.data);
}
