import { useApiClient } from "@/api/client";
import type { SiteProfile, SiteProfileUpdatePayload, SiteRegionConstants } from "@/types/site-profile";

export async function fetchSiteProfile(siteId: number) {
  const response = await useApiClient().request<SiteProfile>(`/staff/sites/${siteId}/profile`);
  return response.data;
}

export async function updateSiteProfile(siteId: number, payload: SiteProfileUpdatePayload) {
  const response = await useApiClient().request<SiteProfile>(`/staff/sites/${siteId}/profile`, {
    method: "PATCH" as UniApp.RequestOptions["method"],
    data: payload,
  });
  return response.data;
}

export async function fetchSiteRegionConstants() {
  const response = await useApiClient().request<SiteRegionConstants>("/staff/constants/regions");
  return response.data;
}
