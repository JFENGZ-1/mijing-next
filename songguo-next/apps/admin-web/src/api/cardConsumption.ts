import { apiRequest } from "@/api/client";

export interface Pagination {
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export interface Paginated<T> {
  items: T[];
  pagination: Pagination;
  scopeVersion?: number | null;
}

export interface ScopeRef {
  tenantId: number;
  siteId: number;
}

export interface ListFilters {
  page?: number;
  perPage?: number;
  query?: string;
  status?: string;
  from?: string;
  to?: string;
  [key: string]: string | number | undefined;
}

export interface CompensationRoleRow {
  id: number;
  name: string;
  roleType: "delivery" | "share";
  roleTypeLabel?: string;
  assignedStaffCount: number;
  version: number;
  status: string;
  updatedAt: string | null;
}

export interface CompensationAssignmentRow {
  id: number;
  roleId: number;
  roleName: string;
  roleType: string;
  staffId: number;
  staffName: string;
  employeeNo: string | null;
  effectiveFrom: string;
  effectiveUntil: string | null;
  status: string;
  version: number;
}

export interface MemberCardShareAssignmentRow {
  id: number;
  memberCardId: number;
  memberCardNo: string;
  memberName: string;
  staffId: number;
  staffName: string;
  roleId: number;
  roleName: string;
  allocationBps: number;
  effectiveFrom: string | null;
  effectiveUntil: string | null;
  version: number;
  scopeVersion: number;
  status: string;
}

export interface SessionDeliveryAssignmentRow {
  id: number;
  sessionId: number;
  sessionLabel: string;
  courseName: string;
  startsAt: string;
  staffId: number;
  staffName: string;
  roleId: number;
  roleName: string;
  allocationBps: number;
  isPrimary: boolean;
  assignmentVersion: number;
  sessionVersion: number;
  updatedAt: string | null;
}

export interface CardCourseRuleRow {
  id: number;
  cardProductId: number;
  cardProductName: string;
  cardType: string;
  courseId: number;
  courseName: string;
  deductionKind: "amount" | "count" | "period_auto";
  deductionAmountCents: number | null;
  deductionCount: number | null;
  version: number;
  status: string;
  effectiveAt: string | null;
  updatedAt: string | null;
}

export interface CardCourseRuleSetRow {
  cardProductId: number;
  cardProductName: string;
  cardType: string;
  catalogStatus: string;
  rulesVersion: number;
  rules: CardCourseRuleRow[];
  updatedAt: string | null;
}

export interface CardProductPaymentMethodRow {
  id: number;
  name: string;
  cardType: string;
  priceCents: number;
  allowedPaymentMethods: Array<"online" | "balance">;
  version: number;
  status: string;
  updatedAt: string | null;
}

export interface CardProductCatalogRow {
  id: number;
  name: string;
  description: string | null;
  cardType: "stored_value" | "count" | "period";
  priceCents: number;
  faceValueCents: number | null;
  initialCount: number | null;
  validityDays: number | null;
  saleStatus: "on_sale" | "stopped";
  catalogStatus: "active" | "archived";
  allowedPaymentMethods: Array<"online" | "balance">;
  version: number;
  updatedAt: string | null;
}

export interface CourseCatalogRow {
  id: number;
  name: string;
  description: string | null;
  courseType: "group" | "private";
  durationMinutes: number;
  difficulty: number | null;
  minCapacity: number | null;
  maxCapacity: number | null;
  coachStaffId: number | null;
  coachName: string | null;
  catalogStatus: "active" | "archived";
  version: number;
  updatedAt: string | null;
}

export interface CourseCompensationRuleRow {
  id: number;
  courseId: number;
  courseName: string;
  sessionFeeCents: number;
  roleRates: CourseCompensationRoleRateRow[];
  formulaVersion: string;
  version: number;
  status: string;
  effectiveAt: string | null;
  updatedAt: string | null;
}

export interface CourseCompensationRoleRateRow {
  compensationRoleId: number;
  roleName: string;
  roleType: "delivery" | "share";
  rateBps: number;
}

export interface MemberWalletRow {
  id: number;
  memberId: number;
  memberNo: string;
  memberName: string;
  balanceCents: number;
  currency: string;
  version: number;
  lastEntryAt: string | null;
  status: string;
}

export interface MemberWalletLedgerRow {
  id: number;
  entryType: string;
  deltaCents: number;
  balanceAfterCents: number;
  referenceType: string | null;
  referenceId: string | number | null;
  reason: string | null;
  actorName: string | null;
  commandKey: string;
  reversalOfId: number | null;
  occurredAt: string;
}

export interface ConsumptionReportRow {
  key: string;
  subjectId: number | null;
  subjectName: string;
  roleType: string | null;
  consumptionCount: number;
  consumedAmountCents: number;
  sessionFeeCents: number;
  commissionCents: number;
  totalCompensationCents: number;
  formulaVersion: string | null;
}

export interface ConsumptionEventRow {
  id: number;
  memberName: string;
  memberCardNo: string;
  courseName: string;
  sessionStartsAt: string;
  businessDate: string;
  cardType: string;
  consumedAmountCents: number | null;
  consumedCount: number | null;
  formulaInputs: Record<string, string | number | null>;
  deliveryRecipients: ConsumptionRecipientSnapshot[];
  shareRecipients: ConsumptionRecipientSnapshot[];
  valueLotAllocations: ValueLotAllocationSnapshot[];
  commissionLines: CommissionLineSnapshot[];
  calculationVersion: number;
  status: string;
  reversalOfId: number | null;
}

export interface ConsumptionRecipientSnapshot {
  staffId: number;
  staffName: string | null;
  compensationRoleId: number | null;
  roleName: string | null;
  roleType: "delivery" | "share" | null;
  allocationBps: number;
}

export interface ValueLotAllocationSnapshot {
  valueLotId: number | null;
  count: number;
  valueCents: number | null;
}

export interface CommissionLineSnapshot {
  id: number;
  staffId: number;
  staffName: string | null;
  compensationRoleId: number | null;
  roleName: string | null;
  roleType: "delivery" | "share" | null;
  component: string;
  lineType: string;
  baseValueCents: number;
  rateBps: number | null;
  allocationBps: number | null;
  deltaCents: number;
  netCents: number;
  postCloseAdjustment: boolean;
  occurredAt: string | null;
}

export interface PeriodSettlementDayRow {
  id: number;
  businessDate: string;
  memberCardId: number;
  memberCardNo: string;
  memberName: string;
  activeConsumptionCount: number;
  dailyValueCents: number;
  commissionCents: number;
  calculationVersion: number;
  status: string;
  finalizedAt: string | null;
}

export interface PayrollPeriodRow {
  id: number;
  year: number;
  month: number;
  startsOn: string;
  endsOn: string;
  status: string;
  settlementLineCount: number;
  consumedValueCents: number;
  sessionFeeCents: number;
  commissionCents: number;
  compensationCents: number;
  adjustmentCents: number;
  version: number;
  canClose: boolean;
  pendingCount: number;
  blockedReason: "PAYROLL_PERIOD_NOT_ENDED" | "PAYROLL_PERIOD_BUCKETS_OPEN" | null;
  closedAt: string | null;
  metricsSnapshottedAt: string | null;
}

export interface GovernanceOption {
  id: number;
  name: string;
  code?: string | null;
  type?: string | null;
  status?: string | null;
  version?: number;
}

export interface GovernanceOptions {
  cardProducts: GovernanceOption[];
  courses: GovernanceOption[];
  compensationRoles: GovernanceOption[];
  staff: GovernanceOption[];
  members: GovernanceOption[];
  memberCards: GovernanceOption[];
  sessions: GovernanceOption[];
}

function scopedPath(scope: ScopeRef, suffix: string) {
  return `/admin/tenants/${scope.tenantId}/sites/${scope.siteId}${suffix}`;
}

function query(filters: ListFilters = {}) {
  const params = new URLSearchParams();
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== "") params.set(key, String(value));
  });
  const value = params.toString();
  return value ? `?${value}` : "";
}

export async function scopedList<T>(scope: ScopeRef, endpoint: string, filters: ListFilters = {}) {
  return (await apiRequest<Paginated<T>>(`${scopedPath(scope, endpoint)}${query(filters)}`)).data;
}

export async function scopedCommand<T>(
  scope: ScopeRef,
  endpoint: string,
  method: "POST" | "PUT" | "PATCH",
  body: Record<string, unknown>,
) {
  return (await apiRequest<T>(scopedPath(scope, endpoint), {
    method,
    body: JSON.stringify(body),
  })).data;
}

export async function governanceOptions(scope: ScopeRef) {
  return (await apiRequest<GovernanceOptions>(scopedPath(scope, "/card-consumption/options"))).data;
}

export function commandKey() {
  return crypto.randomUUID();
}

export function localDateString(date = new Date()) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

export function localMonthString(date = new Date()) {
  return localDateString(date).slice(0, 7);
}

export function formatCents(value: number | null | undefined, currency = "CNY") {
  if (value === null || value === undefined) return "—";
  return new Intl.NumberFormat("zh-CN", {
    style: "currency",
    currency,
    minimumFractionDigits: 2,
  }).format(value / 100);
}

export function formatBasisPoints(value: number | null | undefined) {
  if (value === null || value === undefined) return "—";
  return `${(value / 100).toFixed(2).replace(/\.00$/, "")}%`;
}

export function formatDateTime(value: string | null | undefined) {
  return value ? new Date(value).toLocaleString("zh-CN") : "—";
}

export function scopeRef(tenantId: number | null, siteId: number | null): ScopeRef | null {
  return tenantId && siteId ? { tenantId, siteId } : null;
}
