export type ConsumptionDimension = "coach" | "share" | "member" | "course" | "card";
export type ConsumptionSettlementFilterStatus = "provisional" | "final" | "adjusted" | "reversed";
export type ConsumptionSettlementStatus =
  | "reserved"
  | "pending"
  | "pending_day_close"
  | "settled"
  | "adjusted"
  | "reversed"
  | string;

export interface ConsumptionFormulaInputs {
  paidAmount?: string | null;
  deductedAmount?: string | null;
  totalCount?: number | null;
  deductedCount?: number | null;
  totalDays?: number | null;
  dayUseCount?: number | null;
  dailyBaseAmount?: string | null;
  perUseBaseAmount?: string | null;
  rateBasisPoints?: number | null;
}

export interface ConsumptionCommissionLine {
  id?: number;
  staffId?: number | null;
  staffName?: string | null;
  roleId: number;
  roleName: string;
  roleType: "delivery" | "share";
  component?: "session_fee" | "consumption_commission" | string;
  rateBasisPoints: number;
  allocationBps?: number;
  sessionFee: string;
  commissionAmount: string;
}

export interface AppointmentConsumptionPreview {
  appointmentId: number;
  memberId?: number;
  memberName?: string | null;
  courseId?: number;
  courseName?: string | null;
  cardProductId?: number | null;
  cardName?: string | null;
  cardType: "stored_value" | "count" | "period" | string;
  deductionKind: "amount" | "count" | "period_auto" | string;
  reservedAmount?: string | null;
  reservedCount?: number | null;
  consumptionValue?: string | null;
  settlementStatus: ConsumptionSettlementStatus;
  settlementHint?: string | null;
  formulaInputs?: ConsumptionFormulaInputs | null;
  sessionFee?: string | null;
  estimatedCommissionAmount?: string | null;
  ruleVersion?: number | null;
}

export interface ConsumptionSettlement extends AppointmentConsumptionPreview {
  id: number;
  settlementNo?: string | null;
  coachStaffId?: number | null;
  coachName?: string | null;
  shareStaffNames?: string[];
  occurredAt?: string | null;
  settledAt?: string | null;
  businessDate?: string | null;
  commissionLines: ConsumptionCommissionLine[];
  commissionAmount?: string | null;
  adjustmentReason?: string | null;
}

export interface ConsumptionSettlementListItem extends Partial<ConsumptionSettlement> {
  id: number;
  isAggregate?: boolean;
  dimensionKey?: string | number | null;
  dimensionName?: string | null;
  consumptionCount?: number;
}

export interface ConsumptionSettlementSummary {
  consumptionCount: number;
  consumptionValue: string;
  sessionFee: string;
  commissionAmount: string;
  pendingCount?: number;
}

export interface ConsumptionSettlementList {
  items: ConsumptionSettlementListItem[];
  summary?: ConsumptionSettlementSummary;
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export interface ConsumptionSettlementQuery {
  dimension?: ConsumptionDimension;
  from?: string;
  to?: string;
  query?: string;
  status?: ConsumptionSettlementFilterStatus;
  page?: number;
  perPage?: number;
}

export interface PayrollPeriod {
  id: number;
  year: number;
  month: number;
  startsOn?: string;
  endsOn?: string;
  status: "open" | "closing" | "closed" | string;
  version: number;
  settlementCount?: number;
  pendingCount?: number;
  consumptionValue?: string;
  sessionFee?: string;
  commissionAmount?: string;
  closedAt?: string | null;
  closedByStaffName?: string | null;
  canClose?: boolean;
  closeBlockedReason?: string | null;
}

export interface PayrollPeriodList {
  items: PayrollPeriod[];
  pagination?: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}
