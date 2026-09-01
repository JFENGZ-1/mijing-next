import { useApiClient } from "@/api/client";
import type {
  StaffCardProductCatalogList,
  StaffCardProductDetail,
  StaffCardProductUpdatePayload,
  StaffCardProductUpsertPayload,
} from "@/types/member-cards";

function buildQuery(params: Record<string, string | number | undefined>): string {
  const parts = Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== "")
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
  return parts.length ? `?${parts.join("&")}` : "";
}

export type CardProductCatalogFilter = "active" | "archived";

function productPath(siteId: number, cardProductId?: number, suffix = "") {
  const base = `/staff/sites/${siteId}/card-products`;
  return cardProductId == null ? base : `${base}/${cardProductId}${suffix}`;
}

export async function fetchCardProducts(
  siteId: number,
  page = 1,
  perPage = 50,
  q?: string,
  catalogStatus: CardProductCatalogFilter = "active",
) {
  return useApiClient().request<StaffCardProductCatalogList>(
    `${productPath(siteId)}${buildQuery({
      page,
      perPage,
      q,
      catalogStatus: catalogStatus === "archived" ? "archived" : undefined,
    })}`,
  );
}

export async function fetchAllCardProducts(
  siteId: number,
  q?: string,
  catalogStatus: CardProductCatalogFilter = "active",
) {
  const items: StaffCardProductCatalogList["items"] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchCardProducts(siteId, page, 50, q, catalogStatus);
    items.push(...response.data.items);
    lastPage = response.data.pagination?.lastPage ?? page;
    page += 1;
  } while (page <= lastPage);
  return items;
}

export async function fetchCardProduct(siteId: number, cardProductId: number) {
  return useApiClient().request<StaffCardProductDetail>(productPath(siteId, cardProductId));
}

export async function createCardProduct(siteId: number, payload: StaffCardProductUpsertPayload) {
  return useApiClient().request<StaffCardProductDetail>(productPath(siteId), {
    method: "POST",
    data: payload,
  });
}

export async function updateCardProduct(
  siteId: number,
  cardProductId: number,
  payload: StaffCardProductUpdatePayload,
) {
  return useApiClient().request<StaffCardProductDetail>(productPath(siteId, cardProductId), {
    method: "PUT",
    data: payload,
  });
}

export async function archiveCardProduct(siteId: number, cardProductId: number) {
  return useApiClient().request<StaffCardProductDetail>(productPath(siteId, cardProductId, "/archive"), {
    method: "POST",
  });
}

export async function restoreCardProduct(siteId: number, cardProductId: number) {
  return useApiClient().request<StaffCardProductDetail>(productPath(siteId, cardProductId, "/restore"), {
    method: "POST",
  });
}

export interface CrossSiteCardProductSite {
  siteId: number;
  siteName: string;
  linked: boolean;
}

export interface CrossSiteCardProductItem {
  cardProductId: number;
  name: string;
  cardType: string;
  price: string;
  linkedSiteIds: number[];
  sites: CrossSiteCardProductSite[];
}

export interface CrossSiteCardProductList {
  items: CrossSiteCardProductItem[];
}

export async function fetchCrossSiteCardProducts(siteId: number) {
  const response = await useApiClient().request<CrossSiteCardProductList>(
    `/staff/sites/${siteId}/card-products/cross-site-links`,
  );
  return response.data;
}

export async function updateCrossSiteCardProductLink(
  siteId: number,
  cardProductId: number,
  linkedSiteIds: number[],
) {
  const response = await useApiClient().request<{ cardProductId: number; linkedSiteIds: number[] }>(
    `/staff/sites/${siteId}/card-products/${cardProductId}/cross-site-link`,
    { method: "PUT", data: { linkedSiteIds } },
  );
  return response.data;
}

// 卡面图案库（平台级，总 Web 后台管理）
export interface CardFaceLibraryItem {
  id: number;
  name: string;
  gradient: string;
}

export async function fetchCardFaceLibrary(siteId: number) {
  const response = await useApiClient().request<{ items: CardFaceLibraryItem[] }>(
    `/staff/sites/${siteId}/card-products/face-library`,
  );
  return response.data;
}
