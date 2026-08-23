import { useApiClient } from "@/api/client";
import type { StaffMemberCardSummary } from "@/types/crm";
import type { MemberCardReminderConfig } from "@/types/reports";
import type {
  StaffMemberCardAdjustResult,
  StaffMemberCardBenefits,
  StaffMemberCardDetail,
  StaffMemberCardIssued,
  StaffMemberCardLedgerList,
  StaffMemberCardLifecycleResult,
  StaffMemberCardStateResult,
} from "@/types/member-cards";

function buildQuery(params: Record<string, string | number | undefined>): string {
  const parts = Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== "")
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
  return parts.length ? `?${parts.join("&")}` : "";
}

function cardPath(siteId: number, memberCardId: number, suffix = "") {
  return `/staff/sites/${siteId}/member-cards/${memberCardId}${suffix}`;
}

export async function fetchMemberCardDetail(siteId: number, memberCardId: number) {
  return useApiClient().request<StaffMemberCardDetail>(cardPath(siteId, memberCardId));
}

export async function fetchMemberCardLedgerEntries(
  siteId: number,
  memberCardId: number,
  page = 1,
  perPage = 20,
) {
  return useApiClient().request<StaffMemberCardLedgerList>(
    `${cardPath(siteId, memberCardId, "/ledger-entries")}${buildQuery({ page, perPage })}`,
  );
}

export async function freezeMemberCard(
  siteId: number,
  memberCardId: number,
  payload: { reason: string; commandKey: string },
) {
  return useApiClient().request<StaffMemberCardStateResult>(cardPath(siteId, memberCardId, "/freeze"), {
    method: "POST",
    data: payload,
  });
}

export async function unfreezeMemberCard(
  siteId: number,
  memberCardId: number,
  payload: { reason: string; commandKey: string },
) {
  return useApiClient().request<StaffMemberCardStateResult>(cardPath(siteId, memberCardId, "/unfreeze"), {
    method: "POST",
    data: payload,
  });
}

export async function adjustMemberCardBalance(
  siteId: number,
  memberCardId: number,
  payload: {
    direction: "credit" | "debit";
    amount: number;
    reason: string;
    commandKey: string;
    correctsEntryId?: number;
  },
) {
  return useApiClient().request<StaffMemberCardAdjustResult>(
    cardPath(siteId, memberCardId, "/balance-adjustments"),
    { method: "POST", data: payload },
  );
}

export async function adjustMemberCardCount(
  siteId: number,
  memberCardId: number,
  payload: {
    direction: "credit" | "debit";
    count: number;
    reason: string;
    commandKey: string;
    correctsEntryId?: number;
  },
) {
  return useApiClient().request<StaffMemberCardAdjustResult>(
    cardPath(siteId, memberCardId, "/count-adjustments"),
    { method: "POST", data: payload },
  );
}

export async function archiveMemberCard(
  siteId: number,
  memberCardId: number,
  payload: { reason: string; commandKey: string },
) {
  return useApiClient().request<StaffMemberCardLifecycleResult>(cardPath(siteId, memberCardId, "/archive"), {
    method: "POST",
    data: payload,
  });
}

export async function issueMemberCard(
  siteId: number,
  memberId: number,
  payload: {
    cardProductId: number;
    commandKey: string;
    openingBalance?: number;
    openingCount?: number;
    reason?: string;
  },
) {
  return useApiClient().request<StaffMemberCardIssued>(
    `/staff/sites/${siteId}/members/${memberId}/member-cards`,
    { method: "POST", data: payload },
  );
}

export async function createMemberCardTransferShareToken(siteId: number, memberCardId: number) {
  return useApiClient().request<{ memberCardId: number; token: string; expiresAt: string }>(
    cardPath(siteId, memberCardId, "/transfer-share-token"),
    { method: "POST" },
  );
}

export async function updateMemberCardRemark(
  siteId: number,
  memberCardId: number,
  remark: string,
) {
  return useApiClient().request<{ memberCardId: number; remark: string }>(
    cardPath(siteId, memberCardId, "/remark"),
    { method: "PATCH" as UniApp.RequestOptions["method"], data: { remark } },
  );
}

export async function updateMemberCardOpeningType(
  siteId: number,
  memberCardId: number,
  openingType: string,
) {
  return useApiClient().request<{ memberCardId: number; openingType: string }>(
    cardPath(siteId, memberCardId, "/opening-type"),
    { method: "PATCH" as UniApp.RequestOptions["method"], data: { openingType } },
  );
}

export interface StaffMemberCardOrderItem {
  id: number;
  orderNo: string;
  memberId: number;
  memberCardId: number;
  originalAmount: string;
  effectiveAmount: string;
  status: string;
  voidedAt: string | null;
  createdAt: string | null;
  siteId?: number;
  siteName?: string | null;
  productName?: string | null;
  channel?: string | null;
  memberCard?: {
    id: number;
    cardType: string;
    status: string;
    name: string | null;
    cachedBalance: string | null;
    cachedRemainingCount: number | null;
    validFrom: string | null;
    validUntil: string | null;
  };
}

export async function fetchMemberOrders(siteId: number, memberId: number, page = 1, perPage = 20) {
  return useApiClient().request<{
    items: StaffMemberCardOrderItem[];
    pagination: { page: number; perPage: number; total: number; lastPage: number };
  }>(`${`/staff/sites/${siteId}/members/${memberId}/orders`}${buildQuery({ page, perPage })}`);
}

export async function correctMemberOrderAmount(
  siteId: number,
  orderId: number,
  payload: { amount: number; reason: string; commandKey: string; correctsEntryId?: number },
) {
  return useApiClient().request<{
    orderId: number;
    correctionEntryIds: number[];
    originalAmount: string;
    effectiveAmount: string;
  }>(`/staff/sites/${siteId}/orders/${orderId}/amount-corrections`, {
    method: "POST",
    data: payload,
  });
}

export async function appendMemberOrderInternalNote(
  siteId: number,
  orderId: number,
  payload: { body: string; commandKey: string },
) {
  return useApiClient().request<{
    id: number;
    orderId: number;
    body: string;
    authorStaffId: number;
    authorStaffName: string | null;
    createdAt: string | null;
  }>(`/staff/sites/${siteId}/orders/${orderId}/internal-notes`, {
    method: "POST",
    data: payload,
  });
}

export async function voidMemberOrder(
  siteId: number,
  orderId: number,
  payload: { reason: string; commandKey: string },
) {
  return useApiClient().request<{ orderId: number; status: string; voidedAt: string | null }>(
    `/staff/sites/${siteId}/orders/${orderId}/void`,
    { method: "POST", data: payload },
  );
}

export async function fetchMemberCardBenefits(siteId: number, memberCardId: number) {
  return useApiClient().request<StaffMemberCardBenefits>(cardPath(siteId, memberCardId, "/benefits"));
}

export async function startMemberCardHoliday(
  siteId: number,
  memberCardId: number,
  payload: { plannedEndDate: string; reason: string; commandKey: string; beginDate?: string },
) {
  return useApiClient().request<StaffMemberCardStateResult>(cardPath(siteId, memberCardId, "/holiday/start"), {
    method: "POST",
    data: payload,
  });
}

export async function endMemberCardHoliday(
  siteId: number,
  memberCardId: number,
  payload: { reason: string; commandKey: string; endDate?: string },
) {
  return useApiClient().request<StaffMemberCardStateResult>(cardPath(siteId, memberCardId, "/holiday/end"), {
    method: "POST",
    data: payload,
  });
}

export async function extendMemberCardValidity(
  siteId: number,
  memberCardId: number,
  payload: {
    reason: string;
    commandKey: string;
    extendDays?: number;
    validUntil?: string;
  },
) {
  return useApiClient().request<StaffMemberCardStateResult>(
    cardPath(siteId, memberCardId, "/validity-extensions"),
    { method: "POST", data: payload },
  );
}

export async function restoreMemberCard(
  siteId: number,
  memberCardId: number,
  payload: { reason: string; commandKey: string },
) {
  return useApiClient().request<StaffMemberCardLifecycleResult>(cardPath(siteId, memberCardId, "/restore"), {
    method: "POST",
    data: payload,
  });
}

export async function fetchArchivedMemberCards(siteId: number, page = 1, perPage = 20) {
  const response = await useApiClient().request<{
    items: StaffMemberCardSummary[];
    pagination: { page: number; perPage: number; total: number; lastPage: number };
  }>(`${`/staff/sites/${siteId}/member-cards/archived`}${buildQuery({ page, perPage })}`);
  return response.data;
}

export async function fetchMemberCardReminderConfig(siteId: number) {
  const response = await useApiClient().request<MemberCardReminderConfig>(
    `/staff/sites/${siteId}/member-card-reminder-config`,
  );
  return response.data;
}

export async function updateMemberCardReminderConfig(siteId: number, payload: MemberCardReminderConfig) {
  const response = await useApiClient().request<MemberCardReminderConfig>(
    `/staff/sites/${siteId}/member-card-reminder-config`,
    { method: "PUT", data: payload },
  );
  return response.data;
}

export interface MemberCardBatchResult {
  commandKey: string;
  succeeded: Array<{ memberCardId: number; ledgerEntryIds?: number[]; created?: boolean }>;
  failed: Array<{ memberCardId: number | null; code: string }>;
}

export async function batchAdjustMemberCardBalances(
  siteId: number,
  payload: {
    commandKey: string;
    items: Array<{
      memberCardId: number;
      commandKey: string;
      direction: "credit" | "debit";
      amount: number;
      reason?: string;
    }>;
  },
) {
  return useApiClient().request<MemberCardBatchResult>(
    `/staff/sites/${siteId}/member-cards/batch-balance-adjustments`,
    { method: "POST", data: payload },
  );
}

export async function batchExtendMemberCardValidity(
  siteId: number,
  payload: {
    commandKey: string;
    items: Array<{
      memberCardId: number;
      commandKey: string;
      validUntil: string;
      reason?: string;
    }>;
  },
) {
  return useApiClient().request<MemberCardBatchResult>(
    `/staff/sites/${siteId}/member-cards/batch-validity-extensions`,
    { method: "POST", data: payload },
  );
}

export async function batchFreezeMemberCards(
  siteId: number,
  payload: {
    commandKey: string;
    items: Array<{
      memberCardId: number;
      commandKey: string;
      reason?: string;
    }>;
  },
) {
  return useApiClient().request<MemberCardBatchResult>(
    `/staff/sites/${siteId}/member-cards/batch-freeze`,
    { method: "POST", data: payload },
  );
}

export async function batchUnfreezeMemberCards(
  siteId: number,
  payload: {
    commandKey: string;
    items: Array<{
      memberCardId: number;
      commandKey: string;
      reason?: string;
    }>;
  },
) {
  return useApiClient().request<MemberCardBatchResult>(
    `/staff/sites/${siteId}/member-cards/batch-unfreeze`,
    { method: "POST", data: payload },
  );
}
