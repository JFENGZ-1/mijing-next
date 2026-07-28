import { useApiClient } from "@/api/client";

function sitePath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}${suffix}`;
}

export interface MemberPointLedgerItem {
  id: number;
  title: string | null;
  amountDelta: number;
  direction: "credit" | "debit";
  reason: string | null;
  actorStaffId: number | null;
  createdAt: string | null;
}

export interface MemberPointLedgerResponse {
  totalPoint: number;
  items: MemberPointLedgerItem[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export async function fetchMemberPointLedger(siteId: number, memberId: number, page = 1, perPage = 20) {
  const response = await useApiClient().request<MemberPointLedgerResponse>(
    sitePath(siteId, `/members/${memberId}/point-ledger?page=${page}&perPage=${perPage}`),
  );
  return response.data;
}

export async function adjustMemberPoints(
  siteId: number,
  memberId: number,
  payload: { direction: "credit" | "debit"; amount: number; reason: string; commandKey: string },
) {
  const response = await useApiClient().request<{ ledgerEntryId: number; totalPoint: number; created: boolean }>(
    sitePath(siteId, `/members/${memberId}/point-adjustments`),
    { method: "POST", data: payload },
  );
  return response.data;
}

export interface PointsConfig {
  pointsEnabled: boolean;
  descriptionText: string | null;
  policy?: {
    earnPerVisit?: number;
    earnPerPurchase?: number;
    debitEnabled?: boolean;
    descriptionText?: string | null;
  };
}

export async function fetchPointsConfig(siteId: number) {
  const response = await useApiClient().request<PointsConfig>(sitePath(siteId, "/points-config"));
  return response.data;
}

export async function updatePointsConfig(siteId: number, payload: PointsConfig) {
  const response = await useApiClient().request<PointsConfig>(sitePath(siteId, "/points-config"), {
    method: "PUT",
    data: payload,
  });
  return response.data;
}
