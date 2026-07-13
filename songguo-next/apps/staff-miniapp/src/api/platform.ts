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
