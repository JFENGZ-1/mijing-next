export type RegistrationState =
  | "configuration_required"
  | "profile_required"
  | "consent_required"
  | "complete";

export interface MemberProfileData {
  displayName: string | null;
  avatarObjectKey: string | null;
  mobileMasked: string | null;
  mobileVerified: boolean;
  mobileVerificationMethod: "wechat" | "sms" | "local_test_override" | null;
  gender: "male" | "female" | "undisclosed" | null;
  birthDate: string | null;
  heightCm: string | null;
  weightKg: string | null;
  version: number;
}

export interface MemberOnboardingData {
  state: RegistrationState;
  registrationRequired: boolean;
  missingFields: string[];
  legalConfigurationReady: boolean;
  acceptedDocumentIds: number[];
  profile: MemberProfileData | null;
}

export interface LegalDocumentData {
  id: number;
  type: "privacy" | "member_terms";
  version: string;
  title: string;
  content: string;
  contentHash: string;
  publishedAt: string;
}

export interface MemberSiteOption {
  id: number;
  tenantId: number;
  name: string;
  phone: string | null;
  address: string | null;
  timezone: string;
}

export type MemberLinkStatus =
  | "pending_member_confirmation"
  | "pending_staff_review"
  | "linked"
  | "separate_approved"
  | "rejected"
  | "conflict";

export interface MemberLinkReview {
  state: "link_review";
  requestId: string;
  status: MemberLinkStatus;
  memberDecision: "link" | "not_me" | null;
  site: { id: number; name: string; status: string };
  candidate: { nameMasked: string | null; mobileMasked: string | null };
  version: number;
  expiresAt: string;
}

export interface JoinedMembership {
  state: "joined";
  id: number;
  tenantId: number;
  memberNo: string;
  status: "lead" | "active" | "frozen" | "closed";
  sites: { id: number; name: string; status: string }[];
}

export type MemberMembershipResult = MemberLinkReview | JoinedMembership;

export interface MemberLinkRequestWarning {
  requestId: string;
  status: string;
  message: string;
}

export interface MemberHomeCarousel {
  items: { id: number; imageUrl: string; linkUrl: string | null }[];
  defaultImageUrl: string | null;
}

export interface MemberNoticeTeaser {
  id: number;
  title: string;
  excerpt: string;
  coverImageUrl: string | null;
  publishedAt: string | null;
}

export interface MemberNoticeDetail {
  id: number;
  siteId: number;
  title: string;
  body: string;
  coverImageUrl: string | null;
  publishedAt: string | null;
}

export type AppointmentStatus = "confirmed" | "waitlisted" | "cancelled" | "absent" | "completed";

export interface MemberAppointmentSummary {
  id: number;
  siteId: number;
  sessionId: number;
  status: AppointmentStatus;
  bookedAt: string;
  cancelledAt: string | null;
  absentMarkedAt: string | null;
  courseName?: string | null;
  courseType?: string | null;
  startsAt?: string | null;
  endsAt?: string | null;
  coachName?: string | null;
  coachAvatarUrl?: string | null;
  chargeAmountDelta?: string | null;
  chargeCountDelta?: number | null;
}

export interface MemberBookingSessionDetail extends MemberBookingCatalogItem {
  siteId: number;
  roomId: number | null;
  roomName: string | null;
  coachStaffId: number;
  durationMinutes: number | null;
  description: string | null;
}

export interface MemberAppointment extends MemberAppointmentSummary {
  memberId: number;
  memberCardId: number | null;
  ledgerEntryId: number | null;
  bookedByAccountId: number | null;
  createdByStaffId: number | null;
  rescheduledFromSessionId: number | null;
  penaltyLedgerEntryId: number | null;
}

export interface MemberHomeDashboard {
  carousel: MemberHomeCarousel;
  notices: MemberNoticeTeaser[];
  upcomingAppointments: MemberAppointmentSummary[];
  linkRequestWarning: MemberLinkRequestWarning | null;
}

export interface MemberMineProfileSummary {
  displayName: string | null;
  avatarObjectKey: string | null;
  avatarUrl: string | null;
  mobileMasked: string | null;
}

export type MemberCardStatus =
  | "pending_activation"
  | "active"
  | "frozen"
  | "expired"
  | "exhausted"
  | "archived"
  | "voided";

export interface MemberCardWalletSummary {
  id: number;
  siteId: number;
  cardType: "stored_value" | "count" | "period";
  status: MemberCardStatus | string;
  cardNoMasked: string;
  faceStyle?: number;
  faceGradient?: string | null;
  name: string | null;
  balance: string | null;
  remainingCount: number | null;
  validFrom: string | null;
  validUntil: string | null;
}

export interface MemberCardLedgerEntry {
  id: number;
  entryType: string;
  direction: "credit" | "debit";
  amountDelta: string | null;
  countDelta: number | null;
  summary: string;
  occurredAt: string;
}

export interface MemberCardLedger {
  items: MemberCardLedgerEntry[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface MemberCardVisibilityResult {
  memberCardId: number;
  ledgerEntryIds: number[];
  memberVisibility: "visible" | "hidden";
}

export interface MemberPurchaseGateMissingField {
  key: string;
  label: string;
}

export interface MemberPurchaseGate {
  allowed: boolean;
  missingFields: MemberPurchaseGateMissingField[];
  redirectHints: { profile: string; cardCatalog: string };
}

export interface MemberCardProductCatalogItem {
  id: number;
  cardType: "stored_value" | "count" | "period";
  name: string;
  description: string | null;
  price: string;
  faceValue: string | null;
  initialCount: number | null;
  validityDays: number | null;
  validityMode: string | null;
  activationMode: string;
}

export interface MemberCardProductCatalog {
  items: MemberCardProductCatalogItem[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface MemberCardPurchaseResult {
  order: {
    id: number;
    orderNo: string;
    memberId: number;
    memberCardId: number | null;
    originalAmount: string;
    effectiveAmount: string;
    status: string;
    voidedAt: string | null;
    createdAt: string | null;
  };
  memberCard?: {
    id: number;
    cardType: MemberCardProductCatalogItem["cardType"];
    status: MemberCardStatus | string;
    name: string | null;
    cachedBalance: string | null;
    cachedRemainingCount: number | null;
    validFrom: string | null;
    validUntil: string | null;
  };
  payment?: {
    driver: string;
    autoPaid: boolean;
    configured?: boolean;
    orderNo?: string;
    prepayId?: string;
    paymentParams?: {
      appId?: string;
      timeStamp: string;
      nonceStr: string;
      package: string;
      signType: string;
      paySign: string;
    };
  };
}

export interface MemberMineStats {
  appointCount: number;
  lastMonthAppointCount: number;
  absenceCount: number | null;
  totalPoint: number | null;
  monthRankNum: number | null;
}

export interface MemberMineDashboard {
  profile: MemberMineProfileSummary;
  cardCount: number;
  cardList: MemberCardWalletSummary[];
  helloMessage: string;
  pointsEnabled: boolean;
  showMonthRank: boolean;
  stats: MemberMineStats;
}

export interface MemberBookingCatalogItem {
  id: number;
  courseId: number;
  courseName: string | null;
  startsAt: string;
  endsAt: string;
  coachName: string | null;
  coachStaffId: number | null;
  coachAvatarUrl: string | null;
  capacity: number;
  bookedCount: number | null;
  bookedAvatars: (string | null)[];
  sessionKind: string;
  courseType: string;
  waitlistEnabled: boolean;
  bookable: boolean;
  memberAppointmentStatus: AppointmentStatus | null;
}

export interface MemberBookingCatalog {
  date: string;
  items: MemberBookingCatalogItem[];
  limits?: {
    catalogLastDate: string;
    groupLastBookableDate: string;
    privateLastBookableDate: string;
  };
}

export interface MemberPrivateCoachProfile {
  id: number;
  coachStaffId: number;
  coachName: string;
  subjectMode: string;
  uniformDurationMinutes: number;
  courses: Array<{ id: number; name: string; durationMinutes: number }>;
}

export interface MemberPrivateCoachTimeSlot {
  start: string;
  startsAt: string;
  available: boolean;
}

export interface MemberPrivateCoachTimeSlotsResponse {
  date: string;
  durationMinutes: number;
  slotIntervalMinutes: number;
  grayOutBookedSlots: boolean;
  slots: MemberPrivateCoachTimeSlot[];
  limits?: { privateLastBookableDate: string };
}

export interface MemberStatsBucket {
  year: number;
  month?: number;
  teamTimes: number;
  teamAbsent: number;
  privateTimes: number;
  privateAbsent: number;
  confirmedCount: number;
  cancelledCount: number;
}

export interface MemberYearStats extends MemberStatsBucket {
  tenantId: number;
  totalCount: number;
  months: MemberStatsBucket[];
}

export interface MemberMonthStats extends MemberStatsBucket {
  tenantId: number;
  month: number;
}

export interface MemberMonthAppointment {
  id: number;
  siteId: number;
  sessionId: number;
  status: string;
  bookedAt: string | null;
  cancelledAt: string | null;
  absentMarkedAt: string | null;
  courseName: string | null;
  courseType: "group" | "private" | null;
  startsAt: string | null;
  endsAt: string | null;
  coachName: string | null;
}

export interface MemberMonthAppointments {
  tenantId: number;
  year: number;
  month: number;
  courseKind: "group" | "private" | "all";
  items: MemberMonthAppointment[];
  pagination: { page: number; perPage: number; total: number; lastPage: number; hasNext: boolean };
}

export interface MemberPointLedgerEntry {
  id: number;
  title: string;
  amountDelta: number;
  direction: "credit" | "debit";
  reason: string;
  createdAt: string | null;
}

export interface MemberPointLedger {
  displayName: string | null;
  totalPoint: number;
  descriptionText: string | null;
  items: MemberPointLedgerEntry[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface MemberProfileFieldPolicyItem {
  key: string;
  label: string;
  memberEditable: boolean;
  required: boolean;
}

export interface MemberTenantProfile {
  tenantId: number;
  registration: {
    state: RegistrationState;
    registrationRequired: boolean;
    missingFields: string[];
    legalConfigurationReady: boolean;
    acceptedDocumentIds: number[];
  };
  fieldPolicy: { fields: MemberProfileFieldPolicyItem[] };
  profile: MemberProfileData & { avatarUrl: string | null };
}

export interface MemberAvatarUpload {
  avatarObjectKey: string;
  avatarUrl: string | null;
  version: number;
}

export interface MemberRankingOptIn {
  tenantId: number;
  rankingOptIn: boolean;
}

export interface MemberMonthlyRankingItem {
  rank: number;
  memberId: number;
  displayName: string | null;
  avatarObjectKey: string | null;
  avatarUrl: string | null;
  appointmentCount: number;
  isMe: boolean;
}

export interface MemberMonthlyRankingMyRank {
  rank: number | null;
  displayName: string | null;
  avatarObjectKey: string | null;
  avatarUrl: string | null;
  appointmentCount: number;
}

export interface MemberMonthlyRanking {
  tenantId: number;
  year: number;
  month: number;
  viewerOptIn: boolean;
  items: MemberMonthlyRankingItem[];
  myRank: MemberMonthlyRankingMyRank;
}

export interface MemberOfficialAccountFollow {
  tenantId: number;
  siteId: number;
  imageUrl: string;
  instructionsText: string;
}

export interface MemberOrderSummary {
  id: number;
  orderNo: string;
  memberId: number;
  memberCardId: number | null;
  originalAmount: string;
  effectiveAmount: string;
  status: "pending_payment" | "closing" | "paid" | "closed" | "voided" | string;
  voidedAt: string | null;
  paymentExpiresAt: string | null;
  closedAt: string | null;
  closeReason: string | null;
  createdAt: string | null;
  siteId?: number;
  siteName?: string | null;
  productName?: string | null;
  channel?: string | null;
  memberCard?: {
    id: number;
    cardType: MemberCardProductCatalogItem["cardType"];
    status: MemberCardStatus | string;
    name: string | null;
    cachedBalance: string | null;
    cachedRemainingCount: number | null;
    validFrom: string | null;
    validUntil: string | null;
  };
}

export interface MemberOrderList {
  items: MemberOrderSummary[];
  pagination: { page: number; perPage: number; total: number; lastPage: number };
}

export interface MemberCardBenefits {
  memberCardId: number;
  cardType: MemberCardProductCatalogItem["cardType"];
  name: string | null;
  courseScopes: unknown[];
  scopeConfig: unknown;
  bookingRules: unknown;
  entitlements: {
    cachedBalance: string | null;
    cachedRemainingCount: number | null;
    validFrom: string | null;
    validUntil: string | null;
  };
}

export interface MemberSitePublicDetail {
  id: number;
  tenantId: number;
  name: string;
  phone: string | null;
  address: string | null;
  description: string | null;
  logoUrl: string | null;
  businessHours: Record<string, unknown> | null;
  longitude: string | null;
  latitude: string | null;
  carousel: {
    items: { id: number; imageUrl: string; linkUrl: string | null }[];
    defaultImageUrl: string | null;
  };
  warmHints: {
    courseType: number;
    courseTypeLabel: string;
    title: string;
    text: string;
    hasContent: boolean;
  }[];
}

export type MemberPaymentCheckout = NonNullable<MemberCardPurchaseResult["payment"]>;

export interface MemberOrderPaymentResumeResult {
  order: MemberOrderSummary;
  payment: MemberPaymentCheckout;
}

export interface MemberSiteClosureStatus {
  siteId: number;
  isClosed: boolean;
  closure: {
    id: number;
    reason: string | null;
    beginDate: string | null;
    endDate: string | null;
  } | null;
}

export interface MemberCardTransferPreview {
  token: string;
  expiresAt: string;
  site: { id: number; name: string };
  card: MemberCardWalletSummary;
  claimable: boolean;
  alreadyClaimed: boolean;
  validMessage: string | null;
}
