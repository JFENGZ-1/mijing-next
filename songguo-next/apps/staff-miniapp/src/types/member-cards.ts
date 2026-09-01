import type { StaffMemberCardSummary } from "@/types/crm";

export interface StaffMemberCardDetail extends StaffMemberCardSummary {
  memberId: number;
  cardProductId: number;
  snapshot: {
    name?: string | null;
    cardType?: string;
    price?: string | null;
    faceValue?: string | null;
    initialCount?: number | null;
    validityDays?: number | null;
    activationMode?: string | null;
    productVersion?: string | null;
    openingType?: string | null;
    staffRemark?: string | null;
  };
  freezeState: {
    frozenAt?: string;
    reason?: string;
    holiday?: { startedAt?: string; plannedEndAt?: string; startedByStaffId?: number };
  } | null;
  issuedByStaffId: number | null;
  issuedByStaffName?: string | null;
  archivedAt: string | null;
  paidAmount?: string | null;
  unitConvert?: string | null;
  consumedAmount?: string | null;
  residualValue?: string | null;
  initialTotal?: string | null;
  holidaySummary?: { count: number; days: number };
  freezeSummary?: { count: number; days: number };
}

export interface StaffMemberCardLedgerEntry {
  id: number;
  entryType: string;
  direction: "credit" | "debit";
  amountDelta: string | null;
  countDelta: number | null;
  validFromAfter: string | null;
  validUntilAfter: string | null;
  countGroupKey: string | null;
  reversalOfId: number | null;
  reason: string | null;
  commandKey: string | null;
  actorStaffId: number | null;
  actorAccountId: number | null;
  occurredAt: string | null;
}

export interface StaffMemberCardLedgerList {
  items: StaffMemberCardLedgerEntry[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface StaffMemberCardAdjustResult {
  memberCardId: number;
  ledgerEntryIds: number[];
  cachedBalance: string | null;
  cachedRemainingCount: number | null;
}

export interface StaffMemberCardStateResult {
  memberCardId: number;
  ledgerEntryIds: number[];
  status: string;
  validFrom: string | null;
  validUntil: string | null;
  freezeState: { frozenAt?: string; reason?: string } | null;
}

export interface StaffMemberCardLifecycleResult {
  memberCardId: number;
  ledgerEntryIds: number[];
  status: string;
  memberVisibility: string;
  archivedAt: string | null;
}

export interface StaffCardProductCatalogItem {
  id: number;
  cardType: string;
  name: string;
  price: string;
  faceValue: string | null;
  initialCount: number | null;
  validityDays: number | null;
  saleStatus: string;
  catalogStatus: string;
  sortOrder: number;
  version: number;
  faceStyle?: number;
  faceGradient?: string | null;
  courseScopeCount?: number;
  courseScopeKeys?: number[];
  allowedPaymentMethods?: Array<"online" | "balance"> | null;
}

export interface StaffCardProductCatalogList {
  items: StaffCardProductCatalogItem[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface StaffCardProductCourseScope {
  id: number;
  scopeKind: string;
  scopeKey: string;
  displayName: string | null;
  priceOverride: string | null;
  sortOrder: number;
}

export interface StaffCardProductDetail extends StaffCardProductCatalogItem {
  description: string | null;
  validityMode: string | null;
  activationMode: string | null;
  scopeConfig: Record<string, unknown> | null;
  bookingRules: Record<string, unknown> | null;
  archivedAt: string | null;
  courseScopes: StaffCardProductCourseScope[];
  createdAt: string | null;
  updatedAt: string | null;
}

export interface StaffCardProductCourseScopeInput {
  scopeKind: "single" | "group";
  scopeKey: string;
  displayName?: string | null;
  priceOverride?: number | null;
  sortOrder?: number;
}

// 卡级预约规则（对标原版会员卡「高级选项」）
export interface CardProductBookingRules {
  defaultPrice?: string | number | null;
  timeRanges?: { start: string; end: string }[];
  activationDays?: number | null; // 购卡X天后自动开卡
  bookingLimit?: { perDay?: number | null; perWeek?: number | null; perMonth?: number | null };
  advanceLimit?: number | null;
  cancelLimit?: { perDay?: number | null; perWeek?: number | null; perMonth?: number | null };
  repeatBooking?: { mode?: "deny" | "limit" | "allow"; max?: number | null };
  multiPerson?: { mode?: "self" | "unlimited" | "limited"; enabled?: boolean; max?: number | null };
  absencePenalty?: {
    weekThreshold?: number | null;
    monthThreshold?: number | null;
    action?: "mark" | "no_refund" | "mark_or_no_refund" | "forbid" | "deduct";
    forbidDays?: number | null;
    deductValue?: number | null;
    // 旧结构兼容
    window?: "week" | "month";
    threshold?: number | null;
  };
  [key: string]: unknown;
}

export interface StaffCardProductUpsertPayload {
  cardType: "stored_value" | "count" | "period";
  name: string;
  price: number;
  description?: string | null;
  faceValue?: number | null;
  initialCount?: number | null;
  validityDays?: number | null;
  validityMode?: string | null;
  activationMode?: string | null;
  saleStatus?: "on_sale" | "stopped";
  sortOrder?: number;
  bookingRules?: CardProductBookingRules | null;
  scopeConfig?: Record<string, unknown> | null;
  courseScopes?: StaffCardProductCourseScopeInput[];
  allowedPaymentMethods?: Array<"online" | "balance">;
}

export interface StaffCardProductUpdatePayload {
  version: number;
  cardType?: StaffCardProductUpsertPayload["cardType"];
  name: string;
  price: number;
  description?: string | null;
  faceValue?: number | null;
  initialCount?: number | null;
  validityDays?: number | null;
  validityMode?: string | null;
  activationMode?: string | null;
  saleStatus?: "on_sale" | "stopped";
  sortOrder?: number;
  bookingRules?: CardProductBookingRules | null;
  scopeConfig?: Record<string, unknown> | null;
  courseScopes?: StaffCardProductCourseScopeInput[];
  allowedPaymentMethods?: Array<"online" | "balance">;
}

export interface StaffMemberCardIssued {
  id: number;
  cardNo: string;
  cardType: string;
  status: string;
  memberId: number;
  cardProductId: number | null;
  snapshot: StaffMemberCardDetail["snapshot"];
  cachedBalance: string | null;
  cachedRemainingCount: number | null;
  validFrom: string | null;
  validUntil: string | null;
  issuedAt: string;
  issuedByStaffId: number | null;
}

export interface StaffMemberCardIssueShareAssignment {
  roleId: number;
  staffId: number;
  allocationBps: number;
}

export interface StaffMemberCardShareAssignment extends StaffMemberCardIssueShareAssignment {
  id: number;
  staffName?: string | null;
  roleName?: string | null;
  roleType: "share" | string;
  effectiveFrom?: string | null;
  effectiveUntil?: string | null;
  effectiveState: "current" | "scheduled" | "expired" | string;
  status: string;
  version: number;
}

export interface StaffMemberCardShareAssignmentSet {
  memberCardId: number;
  version: number;
  items: StaffMemberCardShareAssignment[];
}

export interface StaffMemberCardShareAssignmentReplaceInput {
  assignments: Array<StaffMemberCardIssueShareAssignment & {
    effectiveFrom?: string | null;
    effectiveUntil?: string | null;
  }>;
  expectedVersion: number;
  reason: string;
  commandKey: string;
}

export interface StaffMemberCardIssueInput {
  cardProductId: number;
  commandKey: string;
  openingBalance?: number;
  openingCount?: number;
  openingType?: "immediate" | "first_use" | "first_class" | "keep_pending";
  reason?: string;
  actualAmount?: string;
  paymentMethod?: "online" | "balance";
  shareAssignments?: StaffMemberCardIssueShareAssignment[];
}

export interface StaffMemberCardCourseScope {
  scopeKind?: string;
  scopeKey?: string;
  displayName?: string | null;
  [key: string]: unknown;
}

export interface StaffMemberCardBenefits {
  memberCardId: number;
  cardType: string;
  name: string | null;
  courseScopes: StaffMemberCardCourseScope[];
  scopeConfig: Record<string, unknown> | null;
  bookingRules: Record<string, unknown> | null;
  entitlements: {
    cachedBalance: string | null;
    cachedRemainingCount: number | null;
    validFrom: string | null;
    validUntil: string | null;
  };
}
