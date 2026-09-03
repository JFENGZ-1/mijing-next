import { useApiClient } from "@/api/client";

export interface StaffSupportContact {
  phone: string;
  wechatId: string;
  hours: string;
  supportHint: string;
  siteName: string;
  faqLinks: Array<{ title: string; url: string }>;
}

export interface StaffSupportVideo {
  title: string;
  url: string;
  durationLabel: string;
  isPlaceholder: boolean;
}

export async function fetchSupportContact(siteId: number) {
  const response = await useApiClient().request<StaffSupportContact>(`/staff/sites/${siteId}/support/contact`);
  return response.data;
}

export async function fetchSupportVideoHelp(siteId: number) {
  const response = await useApiClient().request<{ videos: StaffSupportVideo[] }>(
    `/staff/sites/${siteId}/support/video-help`,
  );
  return response.data;
}
