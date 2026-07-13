import { useApiClient } from "@/api/client";
import type {
  MemberAppointment,
  MemberAppointmentSummary,
  MemberAvatarUpload,
  MemberBookingCatalog,
  MemberBookingSessionDetail,
  MemberCardLedger,
  MemberCardVisibilityResult,
  MemberCardWalletSummary,
  MemberHomeDashboard,
  MemberMineDashboard,
  MemberMonthAppointments,
  MemberMonthStats,
  MemberMonthlyRanking,
  MemberNoticeDetail,
  MemberNoticeTeaser,
  MemberOfficialAccountFollow,
  MemberPointLedger,
  MemberCardPurchaseResult,
  MemberCardProductCatalog,
  MemberPurchaseGate,
  MemberRankingOptIn,
  MemberTenantProfile,
  MemberYearStats,
  MemberCardTransferPreview,
  MemberOrderList,
  MemberOrderSummary,
  MemberSitePublicDetail,
  MemberCardBenefits,
  LegalDocumentData,
} from "@/types/member";

function tenantQuery(tenantId: number) {
  return `tenantId=${tenantId}`;
}

function siteQuery(tenantId: number, siteId: number) {
  return `${tenantQuery(tenantId)}&siteId=${siteId}`;
}

export async function getMemberHome(tenantId: number, siteId: number) {
  return useApiClient().request<MemberHomeDashboard>(`/member/home?${siteQuery(tenantId, siteId)}`);
}

export async function getMemberNotices(tenantId: number, siteId: number) {
  return useApiClient().request<{ items: MemberNoticeTeaser[] }>(
    `/member/notices?${siteQuery(tenantId, siteId)}`,
  );
}

export async function getMemberNoticeDetail(tenantId: number, noticeId: number) {
  return useApiClient().request<MemberNoticeDetail>(`/member/notices/${noticeId}?${tenantQuery(tenantId)}`);
}

export async function getMemberMine(tenantId: number) {
  return useApiClient().request<MemberMineDashboard>(`/member/mine?${tenantQuery(tenantId)}`);
}

export async function getMemberBookingCatalog(tenantId: number, siteId: number, date: string) {
  return useApiClient().request<MemberBookingCatalog>(
    `/member/booking/catalog?${siteQuery(tenantId, siteId)}&date=${encodeURIComponent(date)}`,
  );
}

export async function getMemberBookingSession(tenantId: number, sessionId: number) {
  return useApiClient().request<MemberBookingSessionDetail>(
    `/member/booking/sessions/${sessionId}?${tenantQuery(tenantId)}`,
  );
}

export async function getMemberBookingPayableCards(tenantId: number, sessionId: number) {
  return useApiClient().request<{ items: MemberCardWalletSummary[] }>(
    `/member/booking/sessions/${sessionId}/payable-cards?${tenantQuery(tenantId)}`,
  );
}

export async function getMemberAppointments(tenantId: number, scope: "upcoming" | "past" = "upcoming") {
  return useApiClient().request<{ items: MemberAppointmentSummary[] }>(
    `/member/booking/appointments?${tenantQuery(tenantId)}&scope=${scope}`,
  );
}

export async function createMemberAppointment(
  tenantId: number,
  payload: { sessionId: number; memberCardId: number; commandKey: string },
) {
  return useApiClient().request<MemberAppointment>(`/member/booking/appointments?${tenantQuery(tenantId)}`, {
    method: "POST",
    data: payload,
  });
}

export async function cancelMemberAppointment(
  tenantId: number,
  appointmentId: number,
  commandKey: string,
) {
  return useApiClient().request<MemberAppointment>(
    `/member/booking/appointments/${appointmentId}/cancel?${tenantQuery(tenantId)}`,
    {
      method: "POST",
      data: { commandKey },
    },
  );
}

export async function getMemberYearStats(tenantId: number, year: number) {
  return useApiClient().request<MemberYearStats>(
    `/member/stats/year?${tenantQuery(tenantId)}&year=${year}`,
  );
}

export async function getMemberMonthStats(tenantId: number, year: number, month: number) {
  return useApiClient().request<MemberMonthStats>(
    `/member/stats/month?${tenantQuery(tenantId)}&year=${year}&month=${month}`,
  );
}

export async function getMemberMonthAppointments(
  tenantId: number,
  year: number,
  month: number,
  courseKind: "group" | "private" | "all" = "all",
  page = 1,
) {
  return useApiClient().request<MemberMonthAppointments>(
    `/member/stats/month/appointments?${tenantQuery(tenantId)}&year=${year}&month=${month}&courseKind=${courseKind}&page=${page}`,
  );
}

export async function getMemberPointsLedger(tenantId: number, page = 1) {
  return useApiClient().request<MemberPointLedger>(
    `/member/points/ledger?${tenantQuery(tenantId)}&page=${page}`,
  );
}

export async function getMemberTenantProfile(tenantId: number) {
  return useApiClient().request<MemberTenantProfile>(`/member/profile?${tenantQuery(tenantId)}`);
}

export async function patchMemberTenantProfile(
  tenantId: number,
  payload: {
    displayName?: string;
    gender?: "male" | "female" | "undisclosed" | null;
    birthDate?: string | null;
    heightCm?: number | null;
    weightKg?: number | null;
    avatarObjectKey?: string | null;
    version: number;
  },
) {
  return useApiClient().request<MemberTenantProfile>(`/member/profile?${tenantQuery(tenantId)}`, {
    method: "PATCH" as UniApp.RequestOptions["method"],
    data: payload,
  });
}

export async function getMemberMonthlyRanking(tenantId: number, year: number, month: number) {
  return useApiClient().request<MemberMonthlyRanking>(
    `/member/ranking/monthly?${tenantQuery(tenantId)}&year=${year}&month=${month}`,
  );
}

export async function patchMemberRankingOptIn(tenantId: number, optIn: boolean) {
  return useApiClient().request<MemberRankingOptIn>(`/member/profile/ranking-opt-in?${tenantQuery(tenantId)}`, {
    method: "PATCH" as UniApp.RequestOptions["method"],
    data: { optIn },
  });
}

export function uploadMemberAvatar(tenantId: number, filePath: string, version?: number) {
  const session = uni.getStorageSync("access_token") as string;
  const baseUrl = import.meta.env.VITE_API_BASE_URL as string;
  const versionQuery = version ? `&version=${version}` : "";

  return new Promise<MemberAvatarUpload>((resolve, reject) => {
    uni.uploadFile({
      url: `${baseUrl}/member/profile/avatar?${tenantQuery(tenantId)}${versionQuery}`,
      filePath,
      name: "avatar",
      header: session ? { Authorization: `Bearer ${session}` } : {},
      success: (result) => {
        try {
          const payload = JSON.parse(result.data) as { data?: MemberAvatarUpload; message?: string };
          if (result.statusCode >= 400 || !payload.data) {
            reject(new Error(payload.message || "头像上传失败"));
            return;
          }
          resolve(payload.data);
        } catch {
          reject(new Error("头像上传失败"));
        }
      },
      fail: () => reject(new Error("头像上传失败")),
    });
  });
}

export async function getMemberOfficialAccountFollow(tenantId: number, siteId: number) {
  return useApiClient().request<MemberOfficialAccountFollow>(
    `/member/official-account-follow?${siteQuery(tenantId, siteId)}`,
  );
}

export async function getMemberWalletCards(tenantId: number) {
  return useApiClient().request<MemberCardWalletSummary[]>(`/member/member-cards?${tenantQuery(tenantId)}`);
}

export async function getMemberWalletCard(tenantId: number, memberCardId: number) {
  return useApiClient().request<MemberCardWalletSummary>(
    `/member/member-cards/${memberCardId}?${tenantQuery(tenantId)}`,
  );
}

export async function getMemberHiddenCards(tenantId: number) {
  return useApiClient().request<MemberCardWalletSummary[]>(
    `/member/member-cards/hidden?${tenantQuery(tenantId)}`,
  );
}

export async function activateMemberCard(memberCardId: number, commandKey: string) {
  return useApiClient().request<{
    id: number;
    cardNo: string;
    cardType: MemberCardWalletSummary["cardType"];
    status: MemberCardWalletSummary["status"];
    validFrom: string | null;
    validUntil: string | null;
  }>(`/member/member-cards/${memberCardId}/activate`, {
    method: "POST",
    data: { commandKey },
  });
}

export async function hideMemberCard(tenantId: number, memberCardId: number, commandKey: string) {
  return useApiClient().request<MemberCardVisibilityResult>(
    `/member/member-cards/${memberCardId}/hide?${tenantQuery(tenantId)}`,
    {
      method: "POST",
      data: { commandKey },
    },
  );
}

export async function restoreMemberCardVisibility(
  tenantId: number,
  memberCardId: number,
  commandKey: string,
) {
  return useApiClient().request<MemberCardVisibilityResult>(
    `/member/member-cards/${memberCardId}/restore-visibility?${tenantQuery(tenantId)}`,
    {
      method: "POST",
      data: { commandKey },
    },
  );
}

export async function getMemberCardLedgerEntries(memberCardId: number, page = 1, perPage = 20) {
  return useApiClient().request<MemberCardLedger>(
    `/member/member-cards/${memberCardId}/ledger-entries?page=${page}&perPage=${perPage}`,
  );
}

export async function getMemberPurchaseGate(tenantId: number) {
  return useApiClient().request<MemberPurchaseGate>(`/member/profile/purchase-gate?${tenantQuery(tenantId)}`);
}

export async function getMemberCardProductCatalog(tenantId: number, siteId: number, page = 1) {
  return useApiClient().request<MemberCardProductCatalog>(
    `/member/card-products?${siteQuery(tenantId, siteId)}&page=${page}`,
  );
}

export async function submitMemberCardPurchase(
  tenantId: number,
  siteId: number,
  payload: { cardProductId: number; commandKey: string },
) {
  return useApiClient().request<MemberCardPurchaseResult>(
    `/member/card-purchases?${siteQuery(tenantId, siteId)}`,
    {
      method: "POST",
      data: payload,
    },
  );
}

export async function getMemberOrders(tenantId: number, page = 1) {
  return useApiClient().request<MemberOrderList>(
    `/member/orders?${tenantQuery(tenantId)}&page=${page}`,
  );
}

export async function getMemberOrder(tenantId: number, orderId: number) {
  return useApiClient().request<MemberOrderSummary>(
    `/member/orders/${orderId}?${tenantQuery(tenantId)}`,
  );
}

export async function getMemberLegalDocuments() {
  return useApiClient().request<LegalDocumentData[]>("/member/legal-documents");
}

export async function getMemberSitePublicDetail(tenantId: number, siteId: number) {
  return useApiClient().request<MemberSitePublicDetail>(
    `/member/sites/${siteId}/public-detail?${tenantQuery(tenantId)}`,
  );
}

export async function getMemberCardBenefits(memberCardId: number) {
  return useApiClient().request<MemberCardBenefits>(
    `/member/member-cards/${memberCardId}/benefits`,
  );
}

export async function getMemberCardTransferPreview(token: string) {
  return useApiClient().request<MemberCardTransferPreview>(
    `/member/card-transfers/${encodeURIComponent(token)}`,
  );
}

export async function claimMemberCardTransfer(token: string, commandKey: string) {
  return useApiClient().request<{ memberCardId: number; status: string }>(
    `/member/card-transfers/${encodeURIComponent(token)}/claim`,
    {
      method: "POST",
      data: { commandKey },
    },
  );
}
