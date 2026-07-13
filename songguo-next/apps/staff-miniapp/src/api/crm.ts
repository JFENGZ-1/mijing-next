import { useApiClient } from "@/api/client";
import type {
  CrmBatchImportResult,
  CrmDashboardSummary,
  CrmDeletedMemberList,
  CrmMember,
  CrmMemberAppAccessInput,
  CrmMemberCreateInput,
  CrmMemberFilterPresets,
  CrmMemberList,
  CrmMemberListQuery,
  CrmMemberNoteCreateInput,
  CrmMemberOwnerClaimInput,
  CrmMemberStatusTransitionInput,
  CrmMemberTagsInput,
  CrmMemberUpdateInput,
  CrmTag,
  MemberLinkReview,
  MemberLinkReviewDecisionInput,
  MemberNote,
  StaffBookingHistoryItem,
  StaffMemberCardSummary,
} from "@/types/crm";

function sitePath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}${suffix}`;
}

function buildQuery(params: Record<string, string | number | boolean | undefined>): string {
  const parts = Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== "")
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
  return parts.length ? `?${parts.join("&")}` : "";
}

export async function fetchCrmDashboardSummary(siteId: number) {
  return useApiClient().request<CrmDashboardSummary>(sitePath(siteId, "/crm/dashboard-summary"));
}

export async function fetchCrmMemberFilterPresets(siteId: number) {
  return useApiClient().request<CrmMemberFilterPresets>(sitePath(siteId, "/crm/member-filter-presets"));
}

export async function fetchCrmMembers(siteId: number, query: CrmMemberListQuery = {}) {
  const qs = buildQuery({
    page: query.page,
    perPage: query.perPage,
    q: query.q,
    status: query.status,
    pinyinInitial: query.pinyinInitial,
    sumMode: query.sumMode,
    runOff: query.runOff,
    flag: query.flag,
    includeVisitors: query.includeVisitors,
  });
  return useApiClient().request<CrmMemberList>(`${sitePath(siteId, "/members")}${qs}`);
}

export async function fetchCrmMember(siteId: number, memberId: number) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}`));
}

export async function createCrmMember(siteId: number, payload: CrmMemberCreateInput) {
  return useApiClient().request<CrmMember>(sitePath(siteId, "/members"), {
    method: "POST",
    data: payload,
  });
}

export async function updateCrmMember(siteId: number, memberId: number, payload: CrmMemberUpdateInput) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}`), {
    method: "PUT",
    data: payload,
  });
}

export async function fetchCrmDeletedMembers(siteId: number, page = 1, perPage = 20) {
  return useApiClient().request<CrmDeletedMemberList>(
    `${sitePath(siteId, "/members/deleted")}${buildQuery({ page, perPage })}`,
  );
}

export async function restoreCrmMember(siteId: number, memberId: number) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}/restore`), {
    method: "POST",
  });
}

export async function batchImportCrmMembers(siteId: number, payload: { text?: string; lines?: Array<{ name?: string; mobile?: string }> }) {
  return useApiClient().request<CrmBatchImportResult>(sitePath(siteId, "/members/batch-import"), {
    method: "POST",
    data: payload,
  });
}

export async function updateCrmStickyRemark(siteId: number, memberId: number, version: number, stickyRemark: string | null) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}/sticky-remark`), {
    method: "PATCH" as UniApp.RequestOptions["method"],
    data: { version, stickyRemark },
  });
}

export async function fetchMemberNotes(siteId: number, memberId: number) {
  return useApiClient().request<MemberNote[]>(sitePath(siteId, `/members/${memberId}/notes`));
}

export async function addMemberNote(siteId: number, memberId: number, payload: CrmMemberNoteCreateInput) {
  return useApiClient().request(sitePath(siteId, `/members/${memberId}/notes`), {
    method: "POST",
    data: payload,
  });
}

export async function fetchMemberTags(siteId: number) {
  return useApiClient().request<CrmTag[]>(`/staff/member-tags${buildQuery({ siteId })}`);
}

export async function syncMemberTags(siteId: number, memberId: number, payload: CrmMemberTagsInput) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}/tags`), {
    method: "PUT",
    data: payload,
  });
}

export async function transitionCrmMemberStatus(
  siteId: number,
  memberId: number,
  payload: CrmMemberStatusTransitionInput,
) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}/status-transitions`), {
    method: "POST",
    data: payload,
  });
}

export async function claimCrmMemberOwner(siteId: number, memberId: number, payload: CrmMemberOwnerClaimInput) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}/owner-claim`), {
    method: "POST",
    data: payload,
  });
}

export async function changeCrmMemberAppAccess(siteId: number, memberId: number, payload: CrmMemberAppAccessInput) {
  return useApiClient().request<CrmMember>(sitePath(siteId, `/members/${memberId}/app-access`), {
    method: "POST",
    data: payload,
  });
}

export async function fetchMemberLinkRequests(siteId: number, status?: MemberLinkReview["status"]) {
  return useApiClient().request<MemberLinkReview[]>(
    `${sitePath(siteId, "/member-link-requests")}${buildQuery({ status })}`,
  );
}

export async function reviewMemberLinkRequest(
  siteId: number,
  requestId: string,
  payload: MemberLinkReviewDecisionInput,
) {
  return useApiClient().request<MemberLinkReview>(
    sitePath(siteId, `/member-link-requests/${requestId}/decision`),
    { method: "POST", data: payload },
  );
}

export async function fetchMemberCards(siteId: number, memberId: number) {
  return useApiClient().request<StaffMemberCardSummary[]>(sitePath(siteId, `/members/${memberId}/member-cards`));
}

export async function fetchMemberBookingHistory(siteId: number, memberId: number, scope: "upcoming" | "past") {
  return useApiClient().request<{ items: StaffBookingHistoryItem[] }>(
    `${sitePath(siteId, `/members/${memberId}/booking-history`)}${buildQuery({ scope })}`,
  );
}
