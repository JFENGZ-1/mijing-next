export interface ReportDashboardKpis {
  todayRevenue: string;
  monthRevenue: string;
  todayCardSalesCount: number;
  monthCardSalesCount: number;
  todayAppointmentCount: number;
  monthAppointmentCount: number;
  totalMemberCount: number;
  monthNewMemberCount: number;
}

export interface ReportDashboardProfitTrendItem {
  year: number;
  month: number;
  label: string;
  revenue: string;
}

export interface ReportDashboardSummary {
  kpis: ReportDashboardKpis;
  profitTrend: ReportDashboardProfitTrendItem[];
  asOf: string;
}

export interface ReportFinanceProfitTotals {
  newMemberCount: number;
  cardSalesCount: number;
  revenue: string;
}

export interface ReportFinanceProfitPeriod {
  year: number;
  month: number;
  day?: number;
  newMemberCount: number;
  cardSalesCount: number;
  revenue: string;
}

export interface ReportFinanceProfitYearBlock {
  year: number;
  isCurrentYear: boolean;
  newMemberCount: number;
  cardSalesCount: number;
  revenue: string;
  months: ReportFinanceProfitPeriod[];
}

export interface ReportFinanceProfitSummary {
  years: ReportFinanceProfitYearBlock[];
  asOf: string;
}

export interface ReportFinanceProfitCalendar {
  year: number;
  totals: ReportFinanceProfitTotals;
  months: ReportFinanceProfitPeriod[];
  asOf: string;
}

export interface ReportFinanceProfitDaily {
  year: number;
  month: number;
  totals: ReportFinanceProfitTotals;
  days: ReportFinanceProfitPeriod[];
  asOf: string;
}

export interface ReportCourseTotals {
  groupScheduledCount: number;
  groupHeldCount: number;
  groupSignInCount: number;
  privateSessionCount: number;
}

export interface ReportCoursePeriod {
  year: number;
  month: number;
  day?: number;
  groupScheduledCount: number;
  groupHeldCount: number;
  groupSignInCount: number;
  privateSessionCount: number;
}

export interface ReportCourseYearBlock {
  year: number;
  isCurrentYear: boolean;
  groupScheduledCount: number;
  groupHeldCount: number;
  groupSignInCount: number;
  privateSessionCount: number;
  months: ReportCoursePeriod[];
}

export interface ReportCourseSummary {
  years: ReportCourseYearBlock[];
  asOf: string;
}

export interface ReportCourseCalendar {
  year: number;
  totals: ReportCourseTotals;
  months: ReportCoursePeriod[];
  asOf: string;
}

export type ReportCourseKind = "group" | "private" | "all";

export interface ReportCourseDaily {
  year: number;
  month: number;
  courseKind: ReportCourseKind;
  totals: ReportCourseTotals;
  days: ReportCoursePeriod[];
  asOf: string;
}

export interface ReportRankingPagination {
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export interface ReportRankingMemberItem {
  rank: number;
  memberId: number;
  memberNo: string;
  memberName: string | null;
  memberAvatarUrl: string | null;
}

export interface ReportOrderRankingItem extends ReportRankingMemberItem {
  orderCount: number;
  totalSpend: string;
}

export interface ReportOrderRanking {
  year: number;
  month: number;
  totals: { memberCount: number; orderCount: number; totalSpend: string };
  items: ReportOrderRankingItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface ReportCourseAttendanceRankingItem extends ReportRankingMemberItem {
  completedAppointments: number;
}

export interface ReportCourseAttendanceRanking {
  year: number;
  month: number;
  totals: { memberCount: number; completedAppointments: number };
  items: ReportCourseAttendanceRankingItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface ReportPointsRankingItem extends ReportRankingMemberItem {
  creditPoints: number;
}

export interface ReportPointsRanking {
  year: number;
  month: number;
  totals: { memberCount: number; creditPoints: number };
  items: ReportPointsRankingItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface ReportSalesStaffRankingItem {
  rank: number;
  staffId: number;
  staffName: string;
  cardSalesCount: number;
  revenue: string;
  memberCount: number;
}

export interface ReportSalesStaffRanking {
  year: number;
  month: number;
  totals: { staffCount: number; cardSalesCount: number; revenue: string };
  items: ReportSalesStaffRankingItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface ReminderPagination {
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export type ReminderMemberStatus = "valid" | "invalid" | "all";

export interface ReminderMemberBase {
  memberId: number;
  memberNo: string;
  memberName: string | null;
  memberAvatarUrl: string | null;
}

export interface ReminderMemberItem extends ReminderMemberBase {
  joinedAt?: string | null;
  anniversaryOn?: string | null;
  daysUntilAnniversary?: number | null;
  birthDate?: string | null;
  birthdayOn?: string | null;
  daysUntilBirthday?: number | null;
  lastClassDate?: string | null;
  daysSinceLastClass?: number | null;
  status?: string | null;
}

export interface ReminderMemberList {
  thresholdDays: number;
  memberStatus?: ReminderMemberStatus | null;
  items: ReminderMemberItem[];
  pagination: ReminderPagination;
  computedAt: string;
}

export interface ReminderHolidayItem extends ReminderMemberBase {
  memberCardId: number;
  cardNo: string;
  cardType: string;
  status: string;
  name: string | null;
  holidayStartedAt: string | null;
  holidayEndsAt: string | null;
  daysUntilHolidayEnds: number | null;
  lastClassDate: string | null;
}

export interface ReminderHolidayList {
  thresholdDays: number;
  items: ReminderHolidayItem[];
  pagination: ReminderPagination;
  computedAt: string;
}

export type ReportCoachSortBy = "total" | "group" | "private";

export interface ReportCoachMonthlyRankItem {
  rank: number;
  staffId: number;
  staffName: string | null;
  avatarUrl: string | null;
  groupSessionCount: number;
  privateSessionCount: number;
  completedSessionCount: number;
}

export interface ReportCoachMonthlyRank {
  year: number;
  month: number;
  sortBy: ReportCoachSortBy;
  totals: {
    coachCount: number;
    groupSessionCount: number;
    privateSessionCount: number;
  };
  items: ReportCoachMonthlyRankItem[];
  asOf: string;
}

export interface ReportCoachSummary {
  staffId: number;
  staffName: string | null;
  avatarUrl: string | null;
}

export type ReportCoachSessionKind = "group" | "private" | "all";

export type ReportCoachSignInState = "booked" | "signed_in" | "cancelled" | "absent" | "waitlisted";

export interface ReportCoachAppointmentLine {
  appointmentId: number;
  sessionId: number;
  sessionKind: "group" | "private" | null;
  startsAt: string | null;
  endsAt: string | null;
  courseId: number | null;
  courseName: string | null;
  memberId: number | null;
  memberName: string | null;
  memberNo: string | null;
  status: string;
  signInState: ReportCoachSignInState;
  bookedAt: string | null;
}

export interface ReportCoachAppointmentDetail {
  year: number;
  month: number;
  sessionKind: ReportCoachSessionKind;
  coach: ReportCoachSummary;
  totals: {
    appointmentCount: number;
    signedInCount: number;
    cancelledCount: number;
    absentCount: number;
  };
  items: ReportCoachAppointmentLine[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface MemberCardReminderConfig {
  expiringWithinDays: number;
  zeroBalanceThreshold: string;
}

export interface MemberCardReminderItem {
  memberCardId: number;
  memberId: number;
  memberName: string | null;
  cardNo: string;
  cardType: string;
  status: string;
  name: string | null;
  cachedBalance: string | null;
  cachedRemainingCount: number | null;
  validFrom: string | null;
  validUntil: string | null;
  issuedAt: string | null;
}

export interface MemberCardReminderList {
  config: MemberCardReminderConfig;
  items: MemberCardReminderItem[];
  pagination: ReminderPagination;
}

export interface ReportMemberCardConsumptionItem extends ReportRankingMemberItem {
  rank: number;
  consumptionCount: number;
  consumptionAmount: string;
}

export interface ReportMemberCardConsumptionRanking {
  year: number;
  month: number;
  totals: { memberCount: number; consumptionCount: number; consumptionAmount: string };
  items: ReportMemberCardConsumptionItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface ReportCardProductSalesItem {
  rank: number;
  cardProductId: number | null;
  cardProductName: string;
  salesCount: number;
  revenue: string;
}

export interface ReportCardProductSalesRanking {
  year: number;
  month: number;
  totals: { cardProductCount: number; salesCount: number; revenue: string };
  items: ReportCardProductSalesItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}

export interface ReportCardProductAnalyticsItem {
  cardProductId: number;
  cardProductName: string;
  siteId: number;
  siteName: string | null;
  cardType: string;
  issuedCount: number;
  linkedSiteCount: number;
}

export interface ReportCardProductAnalytics {
  siteId: number;
  totals: { cardProductCount: number; issuedCount: number };
  items: ReportCardProductAnalyticsItem[];
  asOf: string;
}

export interface ReportCardSalesSummaryItem {
  cardProductId: number | null;
  cardProductName: string;
  salesCount: number;
  revenue: string;
}

export interface ReportCardSalesSummary {
  year: number;
  month: number;
  totals: { cardProductCount: number; salesCount: number; revenue: string };
  items: ReportCardSalesSummaryItem[];
  asOf: string;
}

export interface ReportCardSalesDetailItem {
  orderId: number;
  orderNo: string;
  memberId: number;
  memberName: string | null;
  amount: string;
  paidAt: string | null;
}

export interface ReportCardSalesDetail {
  year: number;
  month: number;
  cardProductId: number | null;
  totals: { salesCount: number; revenue: string };
  items: ReportCardSalesDetailItem[];
  pagination: ReportRankingPagination;
  asOf: string;
}
