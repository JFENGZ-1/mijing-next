import { useApiClient, usePublicApiClient } from "@/api/client";

export interface StaffInvitePreview {
  sign: string;
  expiresAt: string;
  status: "pending" | "accepted" | "expired";
  site: { id: number; name: string };
  invitee: {
    id: number;
    displayName: string;
    role: { id: number; name: string; code: string } | null;
    capabilities: string[];
  };
  wechatBinding?: {
    openid: string;
    unionid: string | null;
    alreadyBoundToInvitee: boolean;
  };
}

export async function fetchStaffInvitePreview(sign: string, code?: string) {
  const query = code ? `?code=${encodeURIComponent(code)}` : "";
  const response = await usePublicApiClient().request<StaffInvitePreview>(`/staff/invites/${sign}${query}`);
  return response.data;
}

export async function resolveStaffWechatUnionId(code: string) {
  const response = await useApiClient().request<{ openid: string; unionid: string | null }>(
    "/identity/wechat/unionid",
    { method: "POST", data: { code } },
  );
  return response.data;
}

export async function acceptStaffInvite(sign: string, payload: { code: string; phoneCode: string }) {
  const response = await useApiClient().request<{
    accepted: boolean;
    siteId: number;
    staff: { tenantId: number; permissions: string[]; sites: { id: number; name: string }[] };
  }>(`/staff/invites/${sign}/accept`, { method: "POST", data: payload });
  return response.data;
}

export async function loginForInvite(sign: string, code: string) {
  const response = await usePublicApiClient().request<{
    accessToken: string;
    inviteBootstrap: boolean;
    staff: { tenantId: number; permissions: string[]; sites: { id: number; name: string }[] } | null;
  }>("/auth/wechat/login", {
    method: "POST",
    data: { appType: "staff", code, inviteSign: sign, deviceName: "staff-miniapp" },
    timeout: 15_000,
  });
  return response.data;
}
