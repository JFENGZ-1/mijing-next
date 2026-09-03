import { useApiClient } from "@/api/client";
import type {
  StaffDashboardAppointmentFeed,
  StaffDashboardSalesFeed,
  StaffDashboardSummary,
} from "@/types/dashboard";

export async function fetchStaffDashboardSummary(siteId: number) {
  const response = await useApiClient().request<StaffDashboardSummary>(`/staff/sites/${siteId}/dashboard/summary`);
  return response.data;
}

export async function fetchStaffDashboardSalesFeed(siteId: number, page = 1, perPage = 20) {
  const qs = `?page=${page}&perPage=${perPage}`;
  const response = await useApiClient().request<StaffDashboardSalesFeed>(`/staff/sites/${siteId}/dashboard/sales-feed${qs}`);
  return response.data;
}

export async function fetchStaffDashboardAppointmentFeed(siteId: number, page = 1, perPage = 20) {
  const qs = `?page=${page}&perPage=${perPage}`;
  const response = await useApiClient().request<StaffDashboardAppointmentFeed>(
    `/staff/sites/${siteId}/dashboard/appointment-feed${qs}`,
  );
  return response.data;
}
