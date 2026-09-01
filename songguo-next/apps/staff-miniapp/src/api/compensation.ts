import { useApiClient } from "@/api/client";
import type {
  CardProductCourseRule,
  CardProductCourseRules,
  CardProductCourseRulesUpdateInput,
  CompensationRole,
  CompensationRoleCreateInput,
  CompensationRoleList,
  CompensationRoleUpdateInput,
  CourseCompensationRule,
  CourseCompensationRuleUpdateInput,
  StaffCompensationRoleAssignments,
  StaffCompensationRoleAssignmentItem,
  StaffCompensationRoleAssignmentsInput,
} from "@/types/compensation";
import { centsToDecimal, decimalToCents } from "@/utils/money";

function sitePath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}${suffix}`;
}

interface CompensationRoleWire {
  id: number;
  name: string;
  type?: "delivery" | "share";
  roleType?: "delivery" | "share";
  status?: string;
  version?: number;
}

function mapRole(role: CompensationRoleWire): CompensationRole {
  return {
    id: Number(role.id),
    name: role.name,
    type: role.roleType ?? role.type ?? "share",
    status: role.status === "archived" ? "inactive" : (role.status ?? "active"),
    version: Number(role.version ?? 1),
  };
}

export async function fetchCompensationRoles(siteId: number, page = 1, perPage = 50) {
  const response = await useApiClient().request<{
    items: CompensationRoleWire[];
    pagination?: CompensationRoleList["pagination"];
  }>(`${sitePath(siteId, "/compensation-roles")}?page=${page}&perPage=${perPage}`);
  return {
    items: (response.data.items ?? []).map(mapRole),
    pagination: response.data.pagination,
  } satisfies CompensationRoleList;
}

export async function fetchAllCompensationRoles(siteId: number) {
  const items: CompensationRole[] = [];
  let page = 1;
  let lastPage = 1;
  do {
    const response = await fetchCompensationRoles(siteId, page, 50);
    items.push(...response.items);
    lastPage = response.pagination?.lastPage ?? page;
    page += 1;
  } while (page <= lastPage);
  return items;
}

export async function fetchCompensationRole(siteId: number, roleId: number) {
  // Staff API intentionally exposes list/create/update/archive only; avoid
  // inventing a non-existent GET /{role} route for the edit screen.
  const role = (await fetchAllCompensationRoles(siteId)).find((item) => item.id === roleId);
  if (!role) throw new Error("业务角色不存在或已不可见");
  return role;
}

export async function createCompensationRole(siteId: number, payload: CompensationRoleCreateInput) {
  const response = await useApiClient().request<CompensationRoleWire>(
    sitePath(siteId, "/compensation-roles"),
    {
      method: "POST",
      data: {
        name: payload.name,
        roleType: payload.type,
        commandKey: payload.commandKey,
        reason: payload.reason,
      },
    },
  );
  return mapRole(response.data);
}

export async function updateCompensationRole(
  siteId: number,
  roleId: number,
  payload: CompensationRoleUpdateInput,
) {
  const response = await useApiClient().request<CompensationRoleWire>(
    sitePath(siteId, `/compensation-roles/${roleId}`),
    {
      method: "PUT",
      data: {
        name: payload.name,
        version: payload.version,
        commandKey: payload.commandKey,
        reason: payload.reason,
      },
    },
  );
  return mapRole(response.data);
}

export async function archiveCompensationRole(
  siteId: number,
  roleId: number,
  payload: { version: number; reason: string; commandKey: string },
) {
  const response = await useApiClient().request<CompensationRoleWire>(
    sitePath(siteId, `/compensation-roles/${roleId}/archive`),
    { method: "POST", data: payload },
  );
  return mapRole(response.data);
}

interface AssignmentWire {
  staffId?: number;
  roles?: CompensationRoleWire[];
  roleIds?: number[];
  items?: Array<{
    id?: number;
    roleId: number;
    roleName?: string | null;
    roleType?: "delivery" | "share";
    effectiveFrom?: string | null;
    effectiveUntil?: string | null;
    effectiveState?: "current" | "scheduled" | "ended" | "expired";
    status?: string;
    version?: number;
  }>;
  assignments?: Array<{
    compensationRoleId: number;
    role?: CompensationRoleWire;
  }>;
}

function mapAssignments(data: AssignmentWire, fallbackStaffId: number): StaffCompensationRoleAssignments {
  const today = (() => {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60_000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
  })();
  const assignmentItems: StaffCompensationRoleAssignmentItem[] = (data.items ?? []).map((item) => {
    const state = item.effectiveState === "expired" ? "ended"
      : item.effectiveState ?? (item.effectiveFrom && item.effectiveFrom > today
      ? "scheduled"
      : item.effectiveUntil && item.effectiveUntil < today
        ? "ended"
        : ["inactive", "ended"].includes(item.status ?? "")
          ? "ended"
          : "current");
    return {
      id: item.id,
      roleId: Number(item.roleId),
      roleName: item.roleName ?? `角色 #${item.roleId}`,
      roleType: item.roleType ?? "share",
      effectiveFrom: item.effectiveFrom,
      effectiveUntil: item.effectiveUntil,
      status: item.status,
      state,
      version: item.version,
    };
  });
  const roles = data.roles?.map(mapRole)
    ?? assignmentItems.filter((item) => item.state === "current").map((item) => mapRole({
      id: item.roleId,
      name: item.roleName,
      roleType: item.roleType,
      status: "active",
      version: item.version,
    }))
    ?? data.assignments?.map((item) => item.role
      ? mapRole(item.role)
      : ({ id: item.compensationRoleId, name: `角色 #${item.compensationRoleId}`, type: "share", status: "active", version: 1 }))
    ?? [];
  return {
    staffId: Number(data.staffId ?? fallbackStaffId),
    roles,
    roleIds: data.roleIds
      ?? assignmentItems.filter((item) => item.state === "current").map((item) => item.roleId)
      ?? data.assignments?.map((item) => item.compensationRoleId)
      ?? roles.map((role) => role.id),
    items: assignmentItems,
  };
}

export async function fetchStaffCompensationRoleAssignments(siteId: number, staffId: number) {
  const response = await useApiClient().request<AssignmentWire>(
    sitePath(siteId, `/staff-directory/${staffId}/compensation-roles`),
  );
  return mapAssignments(response.data, staffId);
}

export async function fetchStaffCompensationRoleAssignmentSets(
  siteId: number,
  staffIds: number[],
  concurrency = 8,
) {
  const results = new Map<number, StaffCompensationRoleAssignments | null>();
  const batchSize = Math.max(1, Math.floor(concurrency));
  for (let offset = 0; offset < staffIds.length; offset += batchSize) {
    const ids = staffIds.slice(offset, offset + batchSize);
    const assignments = await Promise.all(
      ids.map((staffId) => fetchStaffCompensationRoleAssignments(siteId, staffId)),
    );
    ids.forEach((staffId, index) => results.set(staffId, assignments[index] ?? null));
  }
  return results;
}

export async function updateStaffCompensationRoleAssignments(
  siteId: number,
  staffId: number,
  payload: StaffCompensationRoleAssignmentsInput,
) {
  const response = await useApiClient().request<AssignmentWire>(
    sitePath(siteId, `/staff-directory/${staffId}/compensation-roles`),
    {
      method: "PUT",
      data: {
        assignments: (payload.assignments
          ?? payload.roleIds.map((roleId) => ({ roleId, activeFrom: null, activeUntil: null })))
          .map((assignment) => ({
            compensationRoleId: assignment.roleId,
            activeFrom: assignment.activeFrom ?? null,
            activeUntil: assignment.activeUntil ?? null,
          })),
        commandKey: payload.commandKey,
        reason: payload.reason,
      },
    },
  );
  return mapAssignments(response.data, staffId);
}

interface CourseRuleWire {
  courseId: number;
  courseName?: string | null;
  courseType?: "group" | "private" | null;
  enabled?: boolean;
  deductionKind?: CardProductCourseRule["deductionKind"];
  deductionType?: CardProductCourseRule["deductionKind"];
  deductionAmount?: string | null;
  amountCents?: number | null;
  deductionCount?: number | null;
  countUnits?: number | null;
  version?: number;
}

interface CourseRulesWire {
  cardProductId?: number;
  cardProductName?: string | null;
  cardType?: CardProductCourseRules["cardType"];
  rulesVersion?: number;
  version?: number;
  items: CourseRuleWire[];
}

function mapCourseRule(rule: CourseRuleWire): CardProductCourseRule {
  return {
    courseId: Number(rule.courseId),
    courseName: rule.courseName ?? `课程 #${rule.courseId}`,
    courseType: rule.courseType,
    enabled: rule.enabled ?? true,
    deductionKind: rule.deductionType ?? rule.deductionKind ?? "period_auto",
    deductionAmount: rule.deductionAmount ?? centsToDecimal(rule.amountCents),
    deductionCount: rule.deductionCount ?? rule.countUnits ?? null,
    version: Number(rule.version ?? 1),
  };
}

function mapCourseRules(data: CourseRulesWire, cardProductId: number): CardProductCourseRules {
  return {
    cardProductId: Number(data.cardProductId ?? cardProductId),
    cardProductName: data.cardProductName,
    cardType: data.cardType,
    version: data.rulesVersion ?? data.version,
    items: (data.items ?? []).map(mapCourseRule),
  };
}

export async function fetchCardProductCourseRules(siteId: number, cardProductId: number) {
  const response = await useApiClient().request<CourseRulesWire>(
    sitePath(siteId, `/card-products/${cardProductId}/course-rules`),
  );
  return mapCourseRules(response.data, cardProductId);
}

export async function fetchCardProductCourseRuleSets(
  siteId: number,
  cardProductIds: number[],
  concurrency = 8,
) {
  const results = new Map<number, CardProductCourseRules | null>();
  const batchSize = Math.max(1, Math.floor(concurrency));
  for (let offset = 0; offset < cardProductIds.length; offset += batchSize) {
    const ids = cardProductIds.slice(offset, offset + batchSize);
    const rules = await Promise.all(
      ids.map((cardProductId) => fetchCardProductCourseRules(siteId, cardProductId)),
    );
    ids.forEach((cardProductId, index) => results.set(cardProductId, rules[index] ?? null));
  }
  return results;
}

export async function updateCardProductCourseRules(
  siteId: number,
  cardProductId: number,
  payload: CardProductCourseRulesUpdateInput,
) {
  const response = await useApiClient().request<CourseRulesWire>(
    sitePath(siteId, `/card-products/${cardProductId}/course-rules`),
    {
      method: "PUT",
      data: {
        commandKey: payload.commandKey,
        reason: payload.reason,
        expectedVersion: payload.version,
        rules: payload.rules.map((rule) => ({
          courseId: rule.courseId,
          deductionType: rule.deductionKind,
          amountCents: rule.deductionKind === "amount" ? decimalToCents(rule.deductionAmount) : null,
          countUnits: rule.deductionKind === "count" ? rule.deductionCount : null,
        })),
      },
    },
  );
  return mapCourseRules(response.data, cardProductId);
}

interface RoleRateWire {
  roleId?: number;
  compensationRoleId?: number;
  roleName?: string | null;
  roleType?: "delivery" | "share";
  rateBasisPoints?: number;
  rateBps?: number;
}

interface CourseCompensationWire {
  courseId: number;
  courseName?: string | null;
  deliveryRoleId?: number | null;
  sessionFee?: string;
  sessionFeeCents?: number;
  roleRates?: RoleRateWire[];
  effectiveFrom?: string;
  effectiveAt?: string;
  version?: number;
}

function mapCourseCompensation(data: CourseCompensationWire): CourseCompensationRule {
  const roleRates = (data.roleRates ?? []).map((rate) => ({
    roleId: Number(rate.compensationRoleId ?? rate.roleId),
    roleName: rate.roleName ?? `角色 #${rate.compensationRoleId ?? rate.roleId}`,
    roleType: rate.roleType ?? "share",
    rateBasisPoints: Number(rate.rateBps ?? rate.rateBasisPoints ?? 0),
  }));
  return {
    courseId: Number(data.courseId),
    courseName: data.courseName,
    deliveryRoleId: data.deliveryRoleId ?? roleRates.find((rate) => rate.roleType === "delivery")?.roleId ?? null,
    sessionFee: data.sessionFee ?? centsToDecimal(data.sessionFeeCents) ?? "0.00",
    roleRates,
    effectiveFrom: data.effectiveAt ?? data.effectiveFrom ?? "",
    version: Number(data.version ?? 0),
  };
}

export async function fetchCourseCompensationRule(siteId: number, courseId: number) {
  const response = await useApiClient().request<CourseCompensationWire | { rule: CourseCompensationWire | null } | null>(
    sitePath(siteId, `/courses/${courseId}/compensation-rules`),
  );
  const data = response.data && "rule" in response.data ? response.data.rule : response.data;
  return data ? mapCourseCompensation(data) : null;
}

export async function fetchCourseCompensationRuleSets(
  siteId: number,
  courseIds: number[],
  concurrency = 8,
) {
  const results = new Map<number, CourseCompensationRule | null>();
  const batchSize = Math.max(1, Math.floor(concurrency));
  for (let offset = 0; offset < courseIds.length; offset += batchSize) {
    const ids = courseIds.slice(offset, offset + batchSize);
    const rules = await Promise.all(
      ids.map((courseId) => fetchCourseCompensationRule(siteId, courseId)),
    );
    ids.forEach((courseId, index) => results.set(courseId, rules[index] ?? null));
  }
  return results;
}

export async function updateCourseCompensationRule(
  siteId: number,
  courseId: number,
  payload: CourseCompensationRuleUpdateInput,
) {
  const response = await useApiClient().request<CourseCompensationWire | { rule: CourseCompensationWire }>(
    sitePath(siteId, `/courses/${courseId}/compensation-rules`),
    {
      method: "PUT",
      data: {
        sessionFeeCents: decimalToCents(payload.sessionFee) ?? 0,
        effectiveAt: payload.effectiveFrom,
        expectedVersion: payload.version,
        commandKey: payload.commandKey,
        reason: payload.reason,
        roleRates: payload.roleRates.map((rate) => ({
          compensationRoleId: rate.roleId,
          rateBps: rate.rateBasisPoints,
        })),
      },
    },
  );
  return mapCourseCompensation("rule" in response.data ? response.data.rule : response.data);
}
