export type StaffCapability = "coach" | "sales";

export type StaffDirectoryStatus = "active" | "departed";

export interface StaffDirectoryRole {
  id: number;
  name: string;
  code: string;
}

export interface StaffDirectoryListItem {
  id: number;
  displayName: string;
  employeeNo: string;
  gender: "male" | "female" | null;
  avatarUrl: string | null;
  status: StaffDirectoryStatus;
  role: StaffDirectoryRole | null;
  isSiteOwner: boolean;
  hasWechatBinding: boolean;
  capabilities: StaffCapability[];
  version: number;
}

export interface StaffDirectoryListResponse {
  items: StaffDirectoryListItem[];
  activeCount: number;
  departedCount: number;
}

export interface StaffDirectoryMember extends StaffDirectoryListItem {
  mobile: string | null;
  mobileMasked: string | null;
  hasFutureBookings: boolean | null;
  joinedOn: string | null;
  leftOn: string | null;
}

export interface StaffRoleOption {
  id: number;
  name: string;
  code: string;
  isSystem: boolean;
  permissionCount: number;
}

export interface StaffDirectoryUpsertPayload {
  displayName: string;
  mobile?: string | null;
  gender?: "male" | "female" | null;
  capabilities?: StaffCapability[];
  roleId: number;
}

export interface StaffDirectoryUpdatePayload extends Partial<StaffDirectoryUpsertPayload> {
  version: number;
}
