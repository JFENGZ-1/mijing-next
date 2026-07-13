export type PayrollReportSource = "snapshot" | "computed";
export type PayrollRecomputeScope = "site" | "coach" | "sales";
export type PayrollRecomputeJobStatus = "pending" | "processing" | "completed" | "failed";

export interface PayrollCoachReportSummary {
  staffId: number;
  staffName: string;
  employeeNo: string | null;
  mode: string | null;
  groupSessionCount: number;
  privateSessionCount: number;
  deliveredSessionCount: number;
  totalPayCents: number;
  matrixVersion: number;
}

export interface PayrollCoachReportList {
  year: number;
  month: number;
  source: PayrollReportSource;
  totals: {
    staffCount: number;
    totalPayCents: number;
  };
  items: PayrollCoachReportSummary[];
  asOf: string;
}

export interface PayrollSalesReportSummary {
  staffId: number;
  staffName: string;
  employeeNo: string | null;
  cardSalesCount: number;
  revenueCents: number;
  commissionCents: number;
  newSaleCommissionCents: number;
  renewalCommissionCents: number;
}

export interface PayrollSalesReportList {
  year: number;
  month: number;
  source: PayrollReportSource;
  totals: {
    staffCount: number;
    totalPayCents: number;
  };
  items: PayrollSalesReportSummary[];
  asOf: string;
}

export interface PayrollRecomputeJob {
  id: number;
  status: PayrollRecomputeJobStatus;
  scope: PayrollRecomputeScope;
  year: number;
  month: number;
  staffId: number | null;
  staffName: string | null;
  commandKey: string;
  requestedByStaffId: number;
  requestedByStaffName: string | null;
  errorMessage: string | null;
  createdAt: string | null;
  completedAt: string | null;
}

export interface PayrollRecomputeJobList {
  items: PayrollRecomputeJob[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export interface PayrollRecomputeJobInput {
  year: number;
  month: number;
  scope: PayrollRecomputeScope;
  staffId?: number;
  commandKey: string;
}

export type PayrollCoachMode = "fixed_hours" | "headcount" | "amount";
export type PayrollSalesMode = "flat_rate" | "tiered";

export interface PayrollCoachConfig {
  enabled: boolean;
  mode: PayrollCoachMode | null;
}

export interface PayrollSalesTier {
  fromAmountCents: number;
  toAmountCents: number | null;
  ratePercent: number;
}

export interface PayrollSalesSettings {
  newSaleRatePercent: number | null;
  renewalRatePercent: number | null;
  newSaleTiers: PayrollSalesTier[];
  renewalTiers: PayrollSalesTier[];
}

export interface PayrollSalesConfig {
  enabled: boolean;
  mode: PayrollSalesMode | null;
  settings: PayrollSalesSettings;
}

export interface PayrollCoachListItem {
  staffId: number;
  name: string;
  employeeNo: string;
  rulesConfigured: boolean;
  matrixVersion: number;
}

export interface PayrollCoachList {
  coachConfig: PayrollCoachConfig;
  items: PayrollCoachListItem[];
}

export interface PayrollCoachRuleCourseRow {
  courseId: number;
  courseName: string;
  coachStaffId: number | null;
  configured: boolean;
  unitPriceCents: number | null;
  additionalPriceCents: number | null;
  supplementalRatePercent: number | null;
}

export interface PayrollCoachRules {
  coach: { staffId: number; name: string; employeeNo: string };
  coachConfig: PayrollCoachConfig;
  matrixVersion: number;
  groupCourses: PayrollCoachRuleCourseRow[];
  privateCourses: PayrollCoachRuleCourseRow[];
}

export interface PayrollCoachRulesInput {
  groupCourses: Array<{
    courseId: number;
    unitPriceCents: number;
    supplementalRatePercent?: number | null;
  }>;
  privateCourses: Array<{
    courseId: number;
    unitPriceCents: number;
    additionalPriceCents?: number | null;
  }>;
}
