export interface StaffProfile {
  id: number;
  displayName: string;
  employeeNo: string;
  mobile: string | null;
  mobileMasked: string | null;
  avatarUrl: string | null;
  tenantId: number;
  version: number;
}

export interface StaffProfileAvatar {
  avatarUrl: string;
  version: number;
}
