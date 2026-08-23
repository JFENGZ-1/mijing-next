export type MemberStatus = "lead" | "active" | "frozen" | "closed";

export interface CrmTag {
  id: number;
  name: string;
  color: string;
}

export interface CrmMemberMetrics {
  totalPayAmount: string | null;
  groupMonthCount: number | null;
  groupTotalCount: number | null;
  privateMonthCount: number | null;
  privateTotalCount: number | null;
  absenceMonthCount: number | null;
  absenceTotalCount: number | null;
  consumedAmount: string | null;
  residualValue: string | null;
  noClassDays: number | null;
}

export interface CrmMember {
  id: number;
  memberNo: string;
  name: string;
  mobileMasked: string | null;
  mobileVerified: boolean;
  gender: "male" | "female" | "undisclosed" | null;
  birthDate: string | null;
  nationalIdMasked: string | null;
  heightCm: number | null;
  weightKg: number | null;
  status: MemberStatus;
  appAccessStatus: "allowed" | "blocked";
  owner: { id: number; name: string } | null;
  tags: CrmTag[];
  stickyRemark?: string | null;
  hasStickyRemark?: boolean;
  version: number;
  joinedAt: string | null;
  notesCount?: number;
  accountLinked?: boolean;
  pointsEnabled?: boolean;
  totalPoint?: number | null;
  metrics?: CrmMemberMetrics;
  /** 列表摘要字段（dashboard/list） */
  avatarUrl?: string | null;
  pinyinInitial?: string;
  cardCount?: number;
  cardType?: string | null;
  balanceAmount?: number | null;
  balanceUnit?: string | null;
  lastAppointDate?: string | null;
  /** 请假到期日（对标原版 holidayDate 行内 chip） */
  holidayDate?: string | null;
  /** 备注提示（对标原版 hintMsg 行内 chip） */
  hintMsg?: string | null;
}

export interface CrmPagination {
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
}

export interface CrmMemberList {
  items: CrmMember[];
  pagination: CrmPagination;
}

export interface CrmPinyinBucket {
  initial: string;
  count: number;
  pingyinChar?: string;
  ncount?: number;
}

/** 代约选会员（对标原版 member-search 的 findUserdy2 返回项） */
export interface BookingPickerMember {
  id: number;
  memberNo: string;
  name: string | null;
  initial: string;
  avatarUrl: string | null;
  mobileMasked: string | null;
  joinedAt: string | null;
  status: MemberStatus;
  appAccessStatus: "allowed" | "blocked";
  /** 全部店模式下会员归属其它店时的门店名（对标原版 otherSiteName） */
  otherSiteName?: string | null;
  balanceAmount: number | null;
  balanceUnit: string | null;
}

export interface BookingPickerGroup {
  initial: string;
  count: number;
  items: BookingPickerMember[];
}

export interface BookingPickerResult {
  totalCount: number;
  pinyinIndex: CrmPinyinBucket[];
  groups: BookingPickerGroup[];
}

export interface CrmDashboardSummary {
  totalCount: number;
  monthCount: number;
  validUserCount: number;
  invalidUserCount: number;
  nocardUserCount: number;
  nologinUserCount: number;
  pinyinIndex: CrmPinyinBucket[];
}

export interface CrmMemberListQuery {
  page?: number;
  perPage?: number;
  q?: string;
  status?: MemberStatus;
  pinyinInitial?: string;
  sumMode?: string;
  runOff?: number;
  includeVisitors?: boolean;
  flag?: number;
}

export interface CrmFilterPresetQuery {
  sumMode?: string;
  runOff?: number;
  flag?: number;
}

export interface CrmSumModePreset {
  id: string;
  label: string;
  query: CrmFilterPresetQuery;
}

export interface CrmFlagPreset {
  flag: number;
  label: string;
  query: CrmFilterPresetQuery;
  listSupported?: boolean;
}

export interface CrmRunOffPreset {
  runOff: number;
  label: string;
  query: CrmFilterPresetQuery;
}

export interface CrmMemberFilterPresets {
  sumModePresets: CrmSumModePreset[];
  flagPresets: CrmFlagPreset[];
  runOffPresets: CrmRunOffPreset[];
}

export interface CrmSalesStaff {
  id: number;
  name: string;
  employeeNo: string;
}

export interface CrmBatchImportError {
  line: number;
  raw: string;
  code: string;
  message: string;
}

export interface CrmBatchImportResult {
  successCount: number;
  failCount: number;
  errors: CrmBatchImportError[];
}

export interface CrmDeletedMember {
  id: number;
  memberNo: string;
  name: string;
  mobileMasked: string | null;
  status: MemberStatus;
  owner: { id: number; name: string } | null;
  archivedAt: string;
  version: number;
}

export interface CrmDeletedMemberList {
  items: CrmDeletedMember[];
  pagination: CrmPagination;
}

export interface MemberNote {
  id: number;
  body: string;
  correctionOfId: number | null;
  author: string | null;
  createdAt: string;
}

export interface MemberLinkReview {
  requestId: string;
  status: "pending_staff_review" | "linked" | "separate_approved" | "rejected" | "conflict";
  memberDecision: "link" | "not_me";
  leadMember: {
    id: number;
    memberNo: string;
    name: string;
    mobileMasked: string | null;
    status: MemberStatus;
    appAccessStatus: "allowed" | "blocked";
  };
  account: { displayName: string | null; mobileMasked: string | null; mobileVerified: boolean };
  resolvedMemberId: number | null;
  reviewer: { id: number; name: string } | null;
  reviewReason: string | null;
  createdAt: string;
  expiresAt: string;
  version: number;
}

export interface StaffMemberCardSummary {
  id: number;
  cardNo: string;
  cardType: string;
  status: string;
  memberVisibility: string;
  faceStyle?: number;
  faceGradient?: string | null;
  name: string | null;
  cachedBalance: string | null;
  cachedRemainingCount: number | null;
  validFrom: string | null;
  validUntil: string | null;
  issuedAt: string | null;
  staffRemark?: string | null;
  openingType?: string | null;
}

export interface StaffBookingHistoryItem {
  id: number;
  siteId: number;
  sessionId: number;
  status: string;
  memberCardId?: number | null;
  bookedAt: string | null;
  cancelledAt: string | null;
  absentMarkedAt: string | null;
  staffNotes: string | null;
  courseName: string | null;
  courseType: string | null;
  sessionKind: string | null;
  startsAt: string | null;
  endsAt: string | null;
  roomName: string | null;
  coachName: string | null;
}

export type MemberLinkDecision = "approve_link" | "approve_separate" | "reject";

export interface CrmMemberCreateInput {
  name: string;
  mobile?: string | null;
  gender?: "male" | "female" | "undisclosed" | null;
  birthDate?: string | null;
  nationalId?: string | null;
  heightCm?: number | null;
  weightKg?: number | null;
  ownerStaffId?: number | null;
  assignToMe?: boolean;
}

export interface CrmMemberUpdateInput {
  version: number;
  name?: string;
  mobile?: string | null;
  gender?: "male" | "female" | "undisclosed" | null;
  birthDate?: string | null;
  nationalId?: string | null;
  heightCm?: number | null;
  weightKg?: number | null;
  ownerStaffId?: number | null;
}

export interface CrmMemberStatusTransitionInput {
  version: number;
  targetStatus: "active" | "frozen" | "closed";
  reason: string;
}

export interface CrmMemberOwnerClaimInput {
  version: number;
}

export interface CrmMemberAppAccessInput {
  version: number;
  status: "allowed" | "blocked";
  reason: string;
}

export interface CrmMemberTagsInput {
  version: number;
  tagIds: number[];
}

export interface CrmMemberNoteCreateInput {
  body: string;
  correctionOfId?: number | null;
}

export interface MemberLinkReviewDecisionInput {
  version: number;
  decision: MemberLinkDecision;
  reason: string;
}

export const CRM_MEMBER_FILTER_STORAGE_KEY = "crm_member_list_filters";

export interface CrmStoredMemberFilters {
  label?: string;
  query: CrmFilterPresetQuery;
  /** 筛选页点清除时写入，主页读到后重置 sumMode/runOff/flag */
  cleared?: boolean;
}
