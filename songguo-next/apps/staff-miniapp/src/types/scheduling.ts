export type ScheduleSessionStatus = "scheduled" | "suspended" | "cancelled" | "completed";
export type ScheduleSessionKind = "group" | "private";
export type CourseType = "group" | "private";
export type AppointmentStatus = "confirmed" | "waitlisted" | "cancelled" | "absent" | "completed";

export interface ScheduleSession {
  id: number;
  courseId: number;
  courseName?: string | null;
  roomId?: number | null;
  roomName?: string | null;
  coachStaffId: number;
  coachName?: string | null;
  startsAt: string;
  endsAt: string;
  capacity: number;
  bookedCount: number;
  status: ScheduleSessionStatus;
  sessionKind: ScheduleSessionKind;
  version: number;
  courseType?: CourseType | null;
  courseFaceStyle?: number | null;
  courseFaceGradient?: string | null;
  displayColor?: string | null;
  courseDisplayColor?: string | null;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface StaffBookingDailyBoardItem extends ScheduleSession {
  courseType: CourseType;
  courseFaceStyle?: number | null;
  courseFaceGradient?: string | null;
  attendees?: { memberId: number; name: string | null }[];
  waitlistCount?: number;
}

export interface StaffBookingDailyBoard {
  date: string;
  items: StaffBookingDailyBoardItem[];
}

export interface StaffAppointment {
  id: number;
  siteId: number;
  sessionId: number;
  memberId: number;
  status: AppointmentStatus;
  memberCardId?: number | null;
  memberNo?: string | null;
  memberName?: string | null;
  ledgerEntryId?: number | null;
  bookedByAccountId?: number | null;
  createdByStaffId?: number | null;
  bookedAt: string;
  cancelledAt?: string | null;
  staffNotes?: string | null;
  memberRemark?: string | null;
  absentMarkedAt?: string | null;
  rescheduledFromSessionId?: number | null;
  penaltyLedgerEntryId?: number | null;
  // 对标原版行内信息：卡名/余额/本次扣费/代约操作人
  cardName?: string | null;
  cardUnit?: string | null;
  cardBalance?: string | null;
  deductAmount?: string | null;
  operatorStaffName?: string | null;
}

export interface StaffAppointmentList {
  items: StaffAppointment[];
}

export interface ScheduleSessionCreateInput {
  courseId: number;
  roomId?: number | null;
  coachStaffId: number;
  startsAt: string;
  endsAt: string;
  capacity: number;
  sessionKind: ScheduleSessionKind;
}

export interface ScheduleSessionUpdateInput {
  version: number;
  courseId?: number;
  roomId?: number | null;
  coachStaffId?: number;
  startsAt?: string;
  endsAt?: string;
  capacity?: number;
  sessionKind?: ScheduleSessionKind;
  displayColor?: string | null;
  acknowledgeGroupOverlap?: boolean;
}

export interface ScheduleChangeCoursePreflight {
  targetCourseId: number;
  sessionCount: number;
  canProceed: boolean;
  blocked: { sessionId: number; code: string; bookedCount: number }[];
}

export interface ScheduleSessionColorItem {
  key: string;
  label: string;
  color: string;
}

export interface ScheduleSessionColorPalette {
  palette: ScheduleSessionColorItem[];
}

export interface StaffCreateAppointmentInput {
  memberId: number;
  memberCardId: number;
  commandKey: string;
}

export interface StaffCancelAppointmentInput {
  commandKey: string;
}

export interface StaffPromoteWaitlistInput {
  commandKey: string;
}

export interface StaffMarkAbsentInput {
  commandKey: string;
}

export interface StaffUpdateAppointmentNotesInput {
  staffNotes: string;
}

export interface StaffRescheduleAppointmentInput {
  sessionId: number;
  commandKey: string;
}

export interface ScheduleBatchFailure {
  sessionId: number;
  code: "SCHEDULE_BATCH_CANCEL_HAS_APPOINTMENTS";
}

export interface ScheduleBatchCopyInput {
  commandKey: string;
  sourceSessionIds?: number[];
  sourceFrom?: string;
  sourceTo?: string;
  targetFrom?: string;
  dayOffset?: number;
}

export interface ScheduleBatchCopyResult {
  commandKey: string;
  createdSessionIds: number[];
}

export interface ScheduleBatchSuspendInput {
  sessionIds: number[];
  commandKey: string;
  reason?: string;
}

export interface ScheduleBatchSuspendResult {
  commandKey: string;
  reason: string | null;
  succeededSessionIds: number[];
  failed: ScheduleBatchFailure[];
}

export interface ScheduleBatchCancelInput {
  sessionIds: number[];
  commandKey: string;
  reason?: string;
}

export interface ScheduleBatchCancelResult {
  commandKey: string;
  reason: string | null;
  succeededSessionIds: number[];
  failed: ScheduleBatchFailure[];
}

export interface ScheduleDisplayTag {
  key: string;
  label: string;
  color: string;
}

export interface ScheduleDisplayConfig {
  displayTitle: string;
  copyHint: string;
  displayTags: ScheduleDisplayTag[];
}
