import { useApiClient } from "@/api/client";
import type {
  StaffDirectoryListResponse,
  StaffDirectoryMember,
  StaffDirectoryUpdatePayload,
  StaffDirectoryUpsertPayload,
  StaffRoleOption,
} from "@/types/staff-directory";

export async function fetchStaffDirectory(siteId: number) {
  const response = await useApiClient().request<StaffDirectoryListResponse>(`/staff/sites/${siteId}/staff-directory`);
  return response.data;
}

export async function fetchStaffDirectoryMember(siteId: number, staffId: number) {
  const response = await useApiClient().request<StaffDirectoryMember>(
    `/staff/sites/${siteId}/staff-directory/${staffId}`,
  );
  return response.data;
}

export async function createStaffDirectoryMember(siteId: number, payload: StaffDirectoryUpsertPayload) {
  const response = await useApiClient().request<StaffDirectoryMember>(`/staff/sites/${siteId}/staff-directory`, {
    method: "POST",
    data: payload,
  });
  return response.data;
}

export async function updateStaffDirectoryMember(siteId: number, staffId: number, payload: StaffDirectoryUpdatePayload) {
  const response = await useApiClient().request<StaffDirectoryMember>(
    `/staff/sites/${siteId}/staff-directory/${staffId}`,
    {
      method: "PATCH" as UniApp.RequestOptions["method"],
      data: payload,
    },
  );
  return response.data;
}

export async function departStaffDirectoryMember(siteId: number, staffId: number, force = false) {
  const response = await useApiClient().request<StaffDirectoryMember>(
    `/staff/sites/${siteId}/staff-directory/${staffId}/departure`,
    { method: "POST", data: force ? { force: true } : {} },
  );
  return response.data;
}

// 转让店长（权属人转移）
export async function transferStaffOwnership(siteId: number, staffId: number) {
  const response = await useApiClient().request<StaffDirectoryMember>(
    `/staff/sites/${siteId}/staff-directory/${staffId}/transfer-ownership`,
    { method: "POST" },
  );
  return response.data;
}

export async function fetchStaffRoleOptions() {
  const response = await useApiClient().request<{ items: StaffRoleOption[] }>("/staff/roles");
  return response.data.items;
}
