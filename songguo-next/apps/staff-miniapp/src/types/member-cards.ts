import type { StaffMemberCardSummary } from "@/types/crm";

export interface StaffMemberCardDetail extends StaffMemberCardSummary {
  memberId: number;
  cardProductId: number;
  snapshot: {
    name?: string | null;
    cardType?: string;
    faceValue?: string | null;
    initialCount?: number | null;
    validityDays?: number | null;
    activationMode?: string | null;
    productVersion?: string | null;
  };
  freezeState: {
    frozenAt?: string;
    reason?: string;
    holiday?: { startedAt?: string; plannedEndAt?: string; startedByStaffId?: number };
  } | null;
  issuedByStaffId: number | null;
  archivedAt: string | null;
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
  courseScopes?: StaffCardProductCourseScopeInput[];
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
  courseScopes?: StaffCardProductCourseScopeInput[];
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
