import { useApiClient } from "@/api/client";
import type {
  BookingPolicyConfig,
  CrmMemberFieldPolicy,
  MemberCarouselConfig,
  MemberMiniappLayoutConfig,
  MemberOnboardingHelpConfig,
  MemberWarmHintConfig,
  MembershipAgreementConfig,
  NotificationChannelConfig,
  PaymentMarketingConfig,
  SettingsHub,
  SiteClosureConfig,
  SiteClosureItem,
  SiteNoticeAdminConfig,
  SiteNoticeAdminItem,
  StaffVacationEntry,
  StaffVacationRollupItem,
} from "@/types/settings";

export async function fetchSettingsHub(siteId: number) {
  const response = await useApiClient().request<SettingsHub>(`/staff/sites/${siteId}/settings-hub`);
  return response.data;
}

export async function fetchCrmMemberFieldPolicy(siteId: number) {
  const response = await useApiClient().request<CrmMemberFieldPolicy>(`/staff/sites/${siteId}/crm/member-field-policy`);
  return response.data;
}

export async function updateCrmMemberFieldPolicy(
  siteId: number,
  fields: Array<{
    key: string;
    isRequired?: boolean;
    isVisible?: boolean;
    staffEditable?: boolean;
  }>,
) {
  const response = await useApiClient().request<CrmMemberFieldPolicy>(`/staff/sites/${siteId}/crm/member-field-policy`, {
    method: "PUT",
    data: { fields },
  });
  return response.data;
}

export async function fetchMemberWarmHint(siteId: number) {
  const response = await useApiClient().request<MemberWarmHintConfig>(`/staff/sites/${siteId}/member-warm-hint`);
  return response.data;
}

export async function updateMemberWarmHint(
  siteId: number,
  payload: { courseType: number; title?: string; text?: string },
) {
  const response = await useApiClient().request<MemberWarmHintConfig>(`/staff/sites/${siteId}/member-warm-hint`, {
    method: "PUT",
    data: payload,
  });
  return response.data;
}

export async function fetchMemberCarousel(siteId: number) {
  const response = await useApiClient().request<MemberCarouselConfig>(`/staff/sites/${siteId}/member-carousel`);
  return response.data;
}

export async function updateMemberCarousel(
  siteId: number,
  payload: {
    items: Array<{ imageUrl: string; linkUrl?: string | null; sortOrder?: number }>;
    defaultImageUrl?: string | null;
  },
) {
  const response = await useApiClient().request<MemberCarouselConfig>(`/staff/sites/${siteId}/member-carousel`, {
    method: "PUT",
    data: payload,
  });
  return response.data;
}

export async function fetchMemberMiniappLayout(siteId: number) {
  const response = await useApiClient().request<MemberMiniappLayoutConfig>(
    `/staff/sites/${siteId}/member-miniapp-layout`,
  );
  return response.data;
}

export async function updateMemberMiniappLayout(
  siteId: number,
  items: Array<{ key: string; enabled: boolean }>,
) {
  const response = await useApiClient().request<MemberMiniappLayoutConfig>(
    `/staff/sites/${siteId}/member-miniapp-layout`,
    { method: "PUT", data: { items } },
  );
  return response.data;
}

export async function fetchMemberOnboardingHelp(siteId: number) {
  const response = await useApiClient().request<MemberOnboardingHelpConfig>(
    `/staff/sites/${siteId}/member-onboarding-help`,
  );
  return response.data;
}

export async function updateMemberOnboardingHelp(
  siteId: number,
  payload: { posterUrl?: string | null; stepUrl?: string | null },
) {
  const response = await useApiClient().request<MemberOnboardingHelpConfig>(
    `/staff/sites/${siteId}/member-onboarding-help`,
    { method: "PUT", data: payload },
  );
  return response.data;
}

export async function fetchMembershipAgreement(siteId: number) {
  const response = await useApiClient().request<MembershipAgreementConfig>(
    `/staff/sites/${siteId}/legal/membership-agreement`,
  );
  return response.data;
}

export async function updateMembershipAgreement(siteId: number, html: string) {
  const response = await useApiClient().request<MembershipAgreementConfig>(
    `/staff/sites/${siteId}/legal/membership-agreement`,
    { method: "PUT", data: { html } },
  );
  return response.data;
}

export async function fetchSiteClosures(siteId: number) {
  const response = await useApiClient().request<SiteClosureConfig>(`/staff/sites/${siteId}/closure-calendar`);
  return response.data;
}

export async function createSiteClosure(
  siteId: number,
  payload: { reason?: string; beginDate: string; endDate: string },
) {
  const response = await useApiClient().request<SiteClosureItem>(`/staff/sites/${siteId}/closure-calendar`, {
    method: "POST",
    data: payload,
  });
  return response.data;
}

export async function fetchStaffVacationRollup(siteId: number) {
  const response = await useApiClient().request<{ items: StaffVacationRollupItem[] }>(
    `/staff/sites/${siteId}/staff-vacations`,
  );
  return response.data;
}

export async function fetchStaffVacations(siteId: number, staffId: number) {
  const response = await useApiClient().request<{ staff: { id: number; displayName: string }; items: StaffVacationEntry[] }>(
    `/staff/sites/${siteId}/staff/${staffId}/vacations`,
  );
  return response.data;
}

export async function createStaffVacation(
  siteId: number,
  staffId: number,
  payload: {
    beginAt: string;
    endAt: string;
    groupBookingPolicy?: string;
    privateBookingPolicy?: string;
    remark?: string;
  },
) {
  const response = await useApiClient().request<StaffVacationEntry>(
    `/staff/sites/${siteId}/staff/${staffId}/vacations`,
    { method: "POST", data: payload },
  );
  return response.data;
}

export async function fetchNotificationChannels(siteId: number) {
  const response = await useApiClient().request<NotificationChannelConfig>(
    `/staff/sites/${siteId}/notification-channels`,
  );
  return response.data;
}

export async function updateNotificationChannels(
  siteId: number,
  payload: {
    channels?: Array<{ key: string; enabled: boolean }>;
    managerStaffIds?: number[];
  },
) {
  const response = await useApiClient().request<NotificationChannelConfig>(
    `/staff/sites/${siteId}/notification-channels`,
    { method: "PUT", data: payload },
  );
  return response.data;
}

export async function fetchSiteNotices(siteId: number) {
  const response = await useApiClient().request<SiteNoticeAdminConfig>(`/staff/sites/${siteId}/notices`);
  return response.data;
}

export async function createSiteNotice(
  siteId: number,
  payload: { title: string; body: string; displayDays: number },
) {
  const response = await useApiClient().request<SiteNoticeAdminItem>(`/staff/sites/${siteId}/notices`, {
    method: "POST",
    data: payload,
  });
  return response.data;
}

export async function archiveSiteNotice(siteId: number, noticeId: number) {
  const response = await useApiClient().request<SiteNoticeAdminItem>(
    `/staff/sites/${siteId}/notices/${noticeId}/archive`,
    { method: "POST" },
  );
  return response.data;
}

export async function fetchPaymentMarketing(siteId: number) {
  const response = await useApiClient().request<PaymentMarketingConfig>(`/staff/sites/${siteId}/payment-marketing`);
  return response.data;
}

export async function fetchBookingPolicy(siteId: number) {
  const response = await useApiClient().request<BookingPolicyConfig>(`/staff/sites/${siteId}/booking-policy`);
  return response.data;
}

export async function updateBookingPolicy(siteId: number, payload: BookingPolicyConfig) {
  const response = await useApiClient().request<BookingPolicyConfig>(`/staff/sites/${siteId}/booking-policy`, {
    method: "PUT",
    data: payload,
  });
  return response.data;
}
