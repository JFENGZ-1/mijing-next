export interface SettingsHubItem {
  key: string;
  label: string;
  description?: string | null;
  route?: string | null;
  capability: string;
  requiredPermission: string;
  enabled: boolean;
  implemented: boolean;
  setupIncomplete: boolean;
}

export interface SettingsHubSection {
  key: string;
  label: string;
  legacyFlag: string;
  visible: boolean;
  items: SettingsHubItem[];
}

export interface SettingsHub {
  featureFlags: {
    shopBasics: boolean;
    shopDefault: boolean;
    shopMemberConfig: boolean;
    shopManagerTool: boolean;
    multipleShopConfig: boolean;
    shopServiceCenter: boolean;
  };
  setupCounts: {
    siteProfile: number;
    cardProducts: number;
    staffDirectory: number;
    courseCatalog: number;
    cardCourseLinks: number;
    scheduleSessions: number;
  };
  sections: SettingsHubSection[];
}

export interface CrmMemberFieldPolicyItem {
  key: string;
  label: string;
  legacyIndex: number;
  isRequired: boolean;
  isVisible: boolean;
  staffEditable: boolean;
}

export interface CrmMemberFieldPolicy {
  fields: CrmMemberFieldPolicyItem[];
}

export interface MemberWarmHint {
  courseType: number;
  courseTypeLabel: string;
  title: string;
  text: string;
  hasContent: boolean;
}

export interface MemberWarmHintConfig {
  hints: MemberWarmHint[];
}

export interface MemberCarouselItem {
  id?: number;
  imageUrl: string;
  linkUrl?: string | null;
  sortOrder: number;
}

export interface MemberCarouselConfig {
  items: MemberCarouselItem[];
  defaultImageUrl?: string | null;
  usesDefaultImage: boolean;
}

export interface MemberMiniappLayoutItem {
  key: string;
  label: string;
  group: string;
  enabled: boolean;
}

export interface MemberMiniappLayoutConfig {
  items: MemberMiniappLayoutItem[];
}

export interface MemberOnboardingHelpConfig {
  posterUrl?: string | null;
  stepUrl?: string | null;
}

export interface MembershipAgreementConfig {
  html: string;
}

export interface SiteClosureItem {
  id: number;
  reason?: string | null;
  beginDate: string;
  endDate: string;
  lifecycleStatus: string;
}

export interface SiteClosureConfig {
  items: SiteClosureItem[];
  summary: { total: number; completed: number };
}

export interface StaffVacationEntry {
  id: number;
  staffId: number;
  beginAt: string;
  endAt: string;
  groupBookingPolicy: string;
  privateBookingPolicy: string;
  remark?: string | null;
  lifecycleStatus: string;
}

export interface StaffVacationRollupItem {
  staff: { id: number; displayName: string };
  vacations: StaffVacationEntry[];
  activeCount: number;
  upcomingCount: number;
}

export interface NotificationChannelItem {
  legacyId: number;
  key: string;
  label: string;
  group: string;
  enabled: boolean;
}

export interface NotificationChannelConfig {
  channels: NotificationChannelItem[];
  managerRecipients: Array<{ id: number; displayName: string }>;
}

export interface SiteNoticeAdminItem {
  id: number;
  title: string;
  body: string;
  displayDays?: number | null;
  displayStatus: string;
  status: string;
}

export interface SiteNoticeAdminConfig {
  items: SiteNoticeAdminItem[];
  summary: { total: number; active: number };
}

export interface PaymentMarketingCard {
  key: string;
  title: string;
  description: string;
  contactLabel: string;
}

export interface PaymentMarketingConfig {
  cards: PaymentMarketingCard[];
  supportHint: string;
}

export interface BookingPolicyGroupSettings {
  advanceBookingDays: number;
  calendarDisplayDays: number;
  bookingCutoffMinutesBeforeStart: number;
  cancelCutoffMinutesBeforeStart: number;
  waitlistEnabled: boolean;
  showBookedCount: boolean;
  autoCancelUnderMinStudentsEnabled: boolean;
  absentPenaltyEnabled: boolean;
}

export interface BookingPolicyPrivateSettings {
  advanceBookingDays: number;
  minimumLeadMinutes: number;
  cancelCutoffMinutesBeforeStart: number;
  slotIntervalMinutes: number;
  grayOutBookedSlots: boolean;
  absentPenaltyEnabled: boolean;
}

export interface BookingPolicyConfig {
  version: number;
  group: BookingPolicyGroupSettings;
  private: BookingPolicyPrivateSettings;
  rules: Record<string, unknown>;
  updatedAt: string | null;
}
