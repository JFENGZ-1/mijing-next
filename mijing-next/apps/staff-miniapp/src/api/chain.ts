import { useApiClient } from "@/api/client";
import type {
  ChainCourseSummary,
  ChainFinanceSummary,
  ChainMembersSummary,
  ChainSites,
} from "@/types/chain";

function chainSiteIdsQuery(siteIds: number[]) {
  if (!siteIds.length) return "";
  return `?siteIds=${siteIds.join(",")}`;
}

export async function fetchChainSites() {
  const response = await useApiClient().request<ChainSites>("/staff/chain/sites");
  return response.data;
}

export async function fetchChainFinanceSummary(siteIds: number[]) {
  const response = await useApiClient().request<ChainFinanceSummary>(
    `/staff/chain/reports/finance/summary${chainSiteIdsQuery(siteIds)}`,
  );
  return response.data;
}

export async function fetchChainCourseSummary(siteIds: number[]) {
  const response = await useApiClient().request<ChainCourseSummary>(
    `/staff/chain/reports/courses/summary${chainSiteIdsQuery(siteIds)}`,
  );
  return response.data;
}

export async function fetchChainMemberSummary(siteIds: number[]) {
  const response = await useApiClient().request<ChainMembersSummary>(
    `/staff/chain/reports/members/summary${chainSiteIdsQuery(siteIds)}`,
  );
  return response.data;
}

export async function fetchChainBrand() {
  const response = await useApiClient().request<import("@/types/chain").ChainBrand>("/staff/chain/brand");
  return response.data;
}

export async function updateChainBrand(payload: { name?: string | null; logoUrl?: string | null }) {
  const response = await useApiClient().request<import("@/types/chain").ChainBrand>("/staff/chain/brand", {
    method: "PUT",
    data: payload,
  });
  return response.data;
}

export async function fetchChainStoreCourses() {
  const response = await useApiClient().request<{ items: import("@/types/chain").ChainStoreCourseItem[] }>(
    "/staff/chain/store-courses",
  );
  return response.data;
}

export async function fetchChainStaffDirectory() {
  const response = await useApiClient().request<{
    items: import("@/types/chain").ChainStaffDirectoryItem[];
    activeCount: number;
    departedCount: number;
  }>("/staff/chain/staff");
  return response.data;
}
