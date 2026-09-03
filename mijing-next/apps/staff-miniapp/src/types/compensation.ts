export type CompensationRoleType = "delivery" | "share";
export type CompensationRoleStatus = "active" | "inactive";

export interface CompensationRole {
  id: number;
  name: string;
  type: CompensationRoleType;
  status: CompensationRoleStatus | string;
  version: number;
}

export interface CompensationRoleList {
  items: CompensationRole[];
  pagination?: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export interface CompensationRoleCreateInput {
  name: string;
  type: CompensationRoleType;
  status?: CompensationRoleStatus;
  commandKey?: string;
  reason?: string;
}

export interface CompensationRoleUpdateInput extends Partial<CompensationRoleCreateInput> {
  version: number;
}

export interface StaffCompensationRoleAssignments {
  staffId: number;
  roles: CompensationRole[];
  roleIds?: number[];
  items?: StaffCompensationRoleAssignmentItem[];
}

export interface StaffCompensationRoleAssignmentItem {
  id?: number;
  roleId: number;
  roleName: string;
  roleType: CompensationRoleType;
  effectiveFrom?: string | null;
  effectiveUntil?: string | null;
  status?: string;
  state: "current" | "scheduled" | "ended";
  version?: number;
}

export interface StaffCompensationRoleAssignmentsInput {
  roleIds: number[];
  assignments?: Array<{
    roleId: number;
    activeFrom?: string | null;
    activeUntil?: string | null;
  }>;
  commandKey?: string;
  reason?: string;
}

export type CourseDeductionKind = "amount" | "count" | "period_auto";

export interface CardProductCourseRule {
  courseId: number;
  courseName: string;
  courseType?: "group" | "private" | null;
  enabled?: boolean;
  deductionKind: CourseDeductionKind;
  deductionAmount: string | null;
  deductionCount: number | null;
  version: number;
}

export interface CardProductCourseRules {
  cardProductId: number;
  cardProductName?: string | null;
  cardType?: "stored_value" | "count" | "period" | null;
  version?: number;
  items: CardProductCourseRule[];
}

export interface CardProductCourseRuleInput {
  courseId: number;
  deductionKind: CourseDeductionKind;
  deductionAmount?: string | null;
  deductionCount?: number | null;
  version?: number;
}

export interface CardProductCourseRulesUpdateInput {
  version: number;
  rules: CardProductCourseRuleInput[];
  commandKey: string;
  reason?: string;
}

export interface CompensationRoleRate {
  roleId: number;
  roleName: string;
  roleType: CompensationRoleType;
  rateBasisPoints: number;
}

export interface CourseCompensationRule {
  courseId: number;
  courseName?: string | null;
  deliveryRoleId: number | null;
  sessionFee: string;
  roleRates: CompensationRoleRate[];
  effectiveFrom: string;
  version: number;
}

export interface CourseCompensationRuleUpdateInput {
  deliveryRoleId: number | null;
  sessionFee: string;
  roleRates: Array<{
    roleId: number;
    rateBasisPoints: number;
  }>;
  effectiveFrom: string;
  version: number;
  commandKey: string;
  reason?: string;
}
