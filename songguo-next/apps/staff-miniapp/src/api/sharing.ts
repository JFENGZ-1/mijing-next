import { useApiClient } from "@/api/client";

export interface StaffMiniappCode {
  siteId: number;
  siteName: string;
  pagePath: string;
  scene: string;
  shareTitle: string;
  qrImageUrl: string | null;
  hint: string;
}

export async function createStaffMiniappCode(siteId: number) {
  const response = await useApiClient().request<StaffMiniappCode>(
    `/staff/sites/${siteId}/sharing/staff-miniapp-code`,
    { method: "POST" },
  );
  return response.data;
}
