import { useApiClient } from "@/api/client";

export interface PlatformSubscriptionOrderItem {
  orderId: number | null;
  siteId: number;
  siteName: string;
  planCode: string | null;
  planLabel: string | null;
  status: string;
  expiresAt: string | null;
  daysRemaining: number | null;
}

export interface PlatformSubscriptionOrders {
  items: PlatformSubscriptionOrderItem[];
}

export async function fetchPlatformSubscriptionOrders() {
  const response = await useApiClient().request<PlatformSubscriptionOrders>(
    "/staff/platform/subscription/orders",
  );
  return response.data;
}

export interface PlatformSubscriptionPlan {
  configId: number;
  yearName: string;
  realPrice: string;
  originalPrice: string;
  durationDays: number;
  priceCents: number;
  originalPriceCents: number;
  currency: string;
  code: string;
}

export async function fetchPlatformSubscriptionPricing() {
  const response = await useApiClient().request<{ list: PlatformSubscriptionPlan[] }>(
    "/staff/platform/subscription/pricing",
  );
  return response.data;
}

export interface PlatformSubscriptionAgreement {
  version: string;
  title: string;
  html: string;
  effectiveAt: string | null;
  support: {
    customServicer: boolean;
    servicerNickName: string | null;
    protocolUrl: string | null;
  };
}

export async function fetchPlatformSubscriptionAgreement() {
  const response = await useApiClient().request<PlatformSubscriptionAgreement>(
    "/staff/platform/subscription/agreement",
  );
  return response.data;
}

export interface PlatformSiteSubscriptionStatus {
  siteId: number;
  siteName: string;
  subscription: {
    planCode: string | null;
    planLabel: string | null;
    status: string;
    expiresAt: string | null;
    daysRemaining: number | null;
  };
}

export async function fetchPlatformSiteSubscriptionStatus(siteId: number) {
  const response = await useApiClient().request<PlatformSiteSubscriptionStatus>(
    `/staff/sites/${siteId}/platform/subscription/status`,
  );
  return response.data;
}

export interface PlatformSubscriptionPayResult {
  commandKey: string;
  planId: number;
  planCode: string;
  amount: string;
  currency: string;
  paymentStatus: string;
  paidAt: string;
  demo?: boolean;
}

export async function payPlatformSubscription(payload: { planId: number; commandKey: string }) {
  const response = await useApiClient().request<PlatformSubscriptionPayResult>(
    "/staff/platform/subscription/pay",
    { method: "POST", data: payload },
  );
  return response.data;
}
