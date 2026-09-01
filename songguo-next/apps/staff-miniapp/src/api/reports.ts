import { useApiClient } from "@/api/client";
import type {
  MemberCardReminderList,
  ReminderHolidayList,
  ReminderMemberList,
  ReminderMemberStatus,
  ReportCoachAppointmentDetail,
  ReportCoachMonthlyRank,
  ReportCoachSessionKind,
  ReportCoachSortBy,
  ReportCourseAttendanceRanking,
  ReportCourseCalendar,
  ReportCourseDaily,
  ReportCourseKind,
  ReportCourseSummary,
  ReportDashboardSummary,
  ReportFinanceProfitCalendar,
  ReportFinanceProfitDaily,
  ReportFinanceProfitSummary,
  ReportOrderRanking,
  ReportPointsRanking,
  ReportSalesStaffRanking,
} from "@/types/reports";

function reportsPath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}/reports${suffix}`;
}

export async function fetchReportDashboardSummary(siteId: number) {
  const response = await useApiClient().request<ReportDashboardSummary>(reportsPath(siteId, "/dashboard-summary"));
  return response.data;
}

export async function fetchReportFinanceProfitSummary(siteId: number) {
  const response = await useApiClient().request<ReportFinanceProfitSummary>(reportsPath(siteId, "/finance/profit-summary"));
  return response.data;
}

export async function fetchReportFinanceProfitCalendar(siteId: number, year: number) {
  const response = await useApiClient().request<ReportFinanceProfitCalendar>(
    `${reportsPath(siteId, "/finance/profit-calendar")}?year=${year}`,
  );
  return response.data;
}

export async function fetchReportFinanceProfitDaily(siteId: number, year: number, month: number) {
  const response = await useApiClient().request<ReportFinanceProfitDaily>(
    `${reportsPath(siteId, "/finance/profit-daily")}?year=${year}&month=${month}`,
  );
  return response.data;
}

export async function fetchReportCourseSummary(siteId: number) {
  const response = await useApiClient().request<ReportCourseSummary>(reportsPath(siteId, "/courses/summary"));
  return response.data;
}

export async function fetchReportCourseCalendar(siteId: number, year: number) {
  const response = await useApiClient().request<ReportCourseCalendar>(
    `${reportsPath(siteId, "/courses/calendar")}?year=${year}`,
  );
  return response.data;
}

export async function fetchReportCourseDaily(
  siteId: number,
  year: number,
  month: number,
  courseKind: ReportCourseKind = "all",
) {
  const response = await useApiClient().request<ReportCourseDaily>(
    `${reportsPath(siteId, "/courses/daily")}?year=${year}&month=${month}&courseKind=${courseKind}`,
  );
  return response.data;
}

export async function fetchReportOrderRanking(siteId: number, year: number, month: number, page = 1, perPage = 20) {
  const response = await useApiClient().request<ReportOrderRanking>(
    `${reportsPath(siteId, "/rankings/orders")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export async function fetchReportCourseAttendanceRanking(
  siteId: number,
  year: number,
  month: number,
  page = 1,
  perPage = 20,
) {
  const response = await useApiClient().request<ReportCourseAttendanceRanking>(
    `${reportsPath(siteId, "/rankings/course-attendance")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export async function fetchReportPointsRanking(siteId: number, year: number, month: number, page = 1, perPage = 20) {
  const response = await useApiClient().request<ReportPointsRanking>(
    `${reportsPath(siteId, "/rankings/points")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export async function fetchReportSalesStaffRanking(
  siteId: number,
  year: number,
  month: number,
  page = 1,
  perPage = 20,
) {
  const response = await useApiClient().request<ReportSalesStaffRanking>(
    `${reportsPath(siteId, "/rankings/sales-staff")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

interface ReminderListQuery {
  days: number;
  page?: number;
  perPage?: number;
  memberStatus?: ReminderMemberStatus;
}

function reminderQueryString(query: ReminderListQuery) {
  const parts = [
    `days=${query.days}`,
    `page=${query.page ?? 1}`,
    `perPage=${query.perPage ?? 20}`,
  ];
  if (query.memberStatus) parts.push(`memberStatus=${query.memberStatus}`);
  return `?${parts.join("&")}`;
}

export async function fetchReminderAnniversary(siteId: number, query: ReminderListQuery) {
  const response = await useApiClient().request<ReminderMemberList>(
    `${reportsPath(siteId, "/reminders/anniversary")}${reminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchReminderNoClass(siteId: number, query: Omit<ReminderListQuery, "memberStatus">) {
  const response = await useApiClient().request<ReminderMemberList>(
    `${reportsPath(siteId, "/reminders/no-class")}${reminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchReminderBirthdays(siteId: number, query: ReminderListQuery) {
  const response = await useApiClient().request<ReminderMemberList>(
    `${reportsPath(siteId, "/reminders/birthdays")}${reminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchReminderVisitors(siteId: number, query: Omit<ReminderListQuery, "memberStatus">) {
  const response = await useApiClient().request<ReminderMemberList>(
    `${reportsPath(siteId, "/reminders/visitors")}${reminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchReminderHolidayDue(siteId: number, query: Omit<ReminderListQuery, "memberStatus">) {
  const response = await useApiClient().request<ReminderHolidayList>(
    `${reportsPath(siteId, "/reminders/holiday-due")}${reminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchCoachRankings(
  siteId: number,
  year: number,
  month: number,
  sortBy: ReportCoachSortBy = "total",
) {
  const response = await useApiClient().request<ReportCoachMonthlyRank>(
    `${reportsPath(siteId, "/coaches/rankings")}?year=${year}&month=${month}&sortBy=${sortBy}`,
  );
  return response.data;
}

export async function fetchCoachAppointments(
  siteId: number,
  staffId: number,
  year: number,
  month: number,
  sessionKind: ReportCoachSessionKind = "all",
  page = 1,
  perPage = 20,
) {
  const response = await useApiClient().request<ReportCoachAppointmentDetail>(
    `${reportsPath(siteId, `/coaches/${staffId}/appointments`)}?year=${year}&month=${month}&sessionKind=${sessionKind}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

function memberCardRemindersPath(siteId: number, suffix: string) {
  return `/staff/sites/${siteId}/member-card-reminders${suffix}`;
}

interface CardReminderListQuery {
  page?: number;
  perPage?: number;
  withinDays?: number;
}

function cardReminderQueryString(query: CardReminderListQuery) {
  const parts = [`page=${query.page ?? 1}`, `perPage=${query.perPage ?? 20}`];
  if (query.withinDays != null) parts.push(`withinDays=${query.withinDays}`);
  return `?${parts.join("&")}`;
}

export async function fetchCardReminderExpiring(siteId: number, query: CardReminderListQuery = {}) {
  const response = await useApiClient().request<MemberCardReminderList>(
    `${memberCardRemindersPath(siteId, "/expiring")}${cardReminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchCardReminderZeroBalance(siteId: number, query: Omit<CardReminderListQuery, "withinDays"> = {}) {
  const response = await useApiClient().request<MemberCardReminderList>(
    `${memberCardRemindersPath(siteId, "/zero-balance")}${cardReminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchCardReminderPendingOpen(siteId: number, query: Omit<CardReminderListQuery, "withinDays"> = {}) {
  const response = await useApiClient().request<MemberCardReminderList>(
    `${memberCardRemindersPath(siteId, "/pending-open")}${cardReminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchCardReminderPenalized(siteId: number, query: Omit<CardReminderListQuery, "withinDays"> = {}) {
  const response = await useApiClient().request<MemberCardReminderList>(
    `${memberCardRemindersPath(siteId, "/penalized")}${cardReminderQueryString(query)}`,
  );
  return response.data;
}

export async function fetchReportMemberCardConsumptionRanking(
  siteId: number,
  year: number,
  month: number,
  page = 1,
  perPage = 20,
) {
  const response = await useApiClient().request<import("@/types/reports").ReportMemberCardConsumptionRanking>(
    `${reportsPath(siteId, "/rankings/member-card-consumption")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export async function fetchReportCardProductSalesRanking(
  siteId: number,
  year: number,
  month: number,
  page = 1,
  perPage = 20,
) {
  const response = await useApiClient().request<import("@/types/reports").ReportCardProductSalesRanking>(
    `${reportsPath(siteId, "/rankings/card-product-sales")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}`,
  );
  return response.data;
}

export async function fetchReportCardProductAnalytics(siteId: number) {
  const response = await useApiClient().request<import("@/types/reports").ReportCardProductAnalytics>(
    reportsPath(siteId, "/card-product-analytics"),
  );
  return response.data;
}

export async function fetchReportCardSalesSummary(siteId: number, year: number, month: number) {
  const response = await useApiClient().request<import("@/types/reports").ReportCardSalesSummary>(
    `${reportsPath(siteId, "/card-sales/summary")}?year=${year}&month=${month}`,
  );
  return response.data;
}

export async function fetchReportCardSalesDetail(
  siteId: number,
  year: number,
  month: number,
  cardProductId: number | null,
  page = 1,
  perPage = 20,
) {
  const cardProductQuery = cardProductId != null ? `&cardProductId=${cardProductId}` : "";
  const response = await useApiClient().request<import("@/types/reports").ReportCardSalesDetail>(
    `${reportsPath(siteId, "/card-sales/detail")}?year=${year}&month=${month}&page=${page}&perPage=${perPage}${cardProductQuery}`,
  );
  return response.data;
}

// —— 会员卡分析 + 资产负债表 ——
export interface ReportCardAnalyzeCard {
  key: string;
  label: string;
  hint: string;
  count: number;
}

export interface ReportCardAnalyzeSummary {
  cards: ReportCardAnalyzeCard[];
  balanceSheet: {
    totalRevenue: string;
    consumedValue: string;
    remainingValue: string;
    excessConsumedValue: string;
    unvaluedCount: number;
    hasUnvalued: boolean;
    remainingValueIsEstimate: boolean;
    notes: string[];
  };
  generatedAt: string;
}

export async function fetchReportCardAnalyzeSummary(siteId: number) {
  const response = await useApiClient().request<ReportCardAnalyzeSummary>(
    reportsPath(siteId, "/card-analyze/summary"),
  );
  return response.data;
}

// —— 变更记录（发卡/请假/停卡/删卡） ——
export interface ReportChangeLogItem {
  id: number;
  entryType: string;
  entryLabel: string;
  category: string;
  memberId: number | null;
  memberName: string | null;
  memberCardId: number | null;
  cardNo: string | null;
  cardName: string | null;
  amountDelta: string | null;
  countDelta: number | null;
  reason: string | null;
  actorStaffId: number | null;
  actorStaffName: string | null;
  occurredAt: string | null;
}

export interface ReportChangeLogResponse {
  items: ReportChangeLogItem[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
  categories: { key: string; label: string }[];
}

export async function fetchReportChangeLog(
  siteId: number,
  params: { category?: string; dateFrom?: string; dateTo?: string; actorStaffId?: number; page?: number; perPage?: number } = {},
) {
  const parts = [`page=${params.page ?? 1}`, `perPage=${params.perPage ?? 20}`];
  if (params.category) parts.push(`category=${encodeURIComponent(params.category)}`);
  if (params.dateFrom) parts.push(`dateFrom=${encodeURIComponent(params.dateFrom)}`);
  if (params.dateTo) parts.push(`dateTo=${encodeURIComponent(params.dateTo)}`);
  if (params.actorStaffId) parts.push(`actorStaffId=${params.actorStaffId}`);
  const response = await useApiClient().request<ReportChangeLogResponse>(
    `${reportsPath(siteId, "/change-log")}?${parts.join("&")}`,
  );
  return response.data;
}
