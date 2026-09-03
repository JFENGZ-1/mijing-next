import { useApiClient } from "@/api/client";

export interface StaffPermissionCatalogItem {
  id: number;
  code: string;
  name: string;
}

export interface StaffPermissionCatalogModule {
  module: string;
  permissions: StaffPermissionCatalogItem[];
}

export interface StaffRoleDetail {
  id: number;
  name: string;
  code: string;
  isSystem: boolean;
  permissionCount: number;
  permissions: Array<StaffPermissionCatalogItem & { module: string }>;
}

export async function fetchPermissionCatalog() {
  const response = await useApiClient().request<{ modules: StaffPermissionCatalogModule[] }>(
    "/staff/permission-catalog",
  );
  return response.data.modules;
}

export async function fetchStaffRoleDetail(roleId: number) {
  const response = await useApiClient().request<StaffRoleDetail>(`/staff/roles/${roleId}`);
  return response.data;
}

export async function upsertStaffRole(payload: { id?: number; name: string; permissionIds: number[] }) {
  const response = await useApiClient().request<StaffRoleDetail>("/staff/roles", {
    method: "POST",
    data: payload,
  });
  return response.data;
}
