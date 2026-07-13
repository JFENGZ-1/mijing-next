export interface StaffDashboardGreeting {
  headline: string;
  hint: string;
}

export interface StaffDashboardKpis {
  todayRevenue: string;
  groupAppointmentCount: number;
  privateAppointmentCount: number;
  saleCardCount: number;
  newMemberCount: number;
}

export interface StaffDashboardSummary {
  greeting: StaffDashboardGreeting;
  kpis: StaffDashboardKpis;
  asOf: string;
}

export interface StaffDashboardSalesFeedItem {
  id: number;
  orderNo: string;
  memberId: number;
  memberName?: string | null;
  memberAvatarUrl?: string | null;
  soldAt: string;
  isNewMember: boolean;
  cardName?: string | null;
  amount: string;
  paymentChannel?: string | null;
  remark?: string | null;
}

export interface StaffDashboardSalesFeed {
  items: StaffDashboardSalesFeedItem[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}

export interface StaffDashboardAppointmentFeedItem {
  id: number;
  memberId: number;
  memberName?: string | null;
  memberAvatarUrl?: string | null;
  courseName?: string | null;
  courseType?: string | null;
  sessionKind?: string | null;
  startsAt?: string | null;
  endsAt?: string | null;
  coachName?: string | null;
  status: string;
  bookedAt?: string | null;
}

export interface StaffDashboardAppointmentFeed {
  items: StaffDashboardAppointmentFeedItem[];
  pagination: {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
}
