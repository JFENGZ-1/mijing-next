import type { ApiOperationSummary, ContractReport } from "@/types/admin";

export const apiOperations: ApiOperationSummary[] = [
  {
    "operationId": "listAdminAuditLogs",
    "method": "GET",
    "path": "/admin/audit-logs",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "loginSuperAdmin",
    "method": "POST",
    "path": "/admin/auth/login",
    "group": "identity",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "logoutSuperAdmin",
    "method": "POST",
    "path": "/admin/auth/logout",
    "group": "identity",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getAdminDashboard",
    "method": "GET",
    "path": "/admin/dashboard",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getSuperAdminProfile",
    "method": "GET",
    "path": "/admin/me",
    "group": "identity",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listAdminMediaAssets",
    "method": "GET",
    "path": "/admin/media-assets",
    "group": "identity",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createAdminMediaAsset",
    "method": "POST",
    "path": "/admin/media-assets",
    "group": "identity",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "updateAdminMediaAsset",
    "method": "PUT",
    "path": "/admin/media-assets/{mediaAsset}",
    "group": "identity",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "archiveAdminMediaAsset",
    "method": "POST",
    "path": "/admin/media-assets/{mediaAsset}/archive",
    "group": "identity",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getAdminMediaAssetContent",
    "method": "GET",
    "path": "/admin/media-assets/{mediaAsset}/content",
    "group": "identity",
    "disposition": "ADOPT"
  },
  {
    "operationId": "publishAdminMediaAsset",
    "method": "POST",
    "path": "/admin/media-assets/{mediaAsset}/publish",
    "group": "identity",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listAdminMembers",
    "method": "GET",
    "path": "/admin/members",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listAdminJobBatches",
    "method": "GET",
    "path": "/admin/queues/batches",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listAdminFailedJobs",
    "method": "GET",
    "path": "/admin/queues/failed",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "forgetAdminFailedJob",
    "method": "DELETE",
    "path": "/admin/queues/failed/{uuid}",
    "group": "platform",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "retryAdminFailedJob",
    "method": "POST",
    "path": "/admin/queues/failed/{uuid}/retry",
    "group": "platform",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listAdminQueueJobs",
    "method": "GET",
    "path": "/admin/queues/jobs",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getAdminQueueOverview",
    "method": "GET",
    "path": "/admin/queues/overview",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listAdminResourceData",
    "method": "GET",
    "path": "/admin/resources/{resource}",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getAdminWechatPaymentConfig",
    "method": "GET",
    "path": "/admin/settings/payments/wechat",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateAdminWechatPaymentConfig",
    "method": "PUT",
    "path": "/admin/settings/payments/wechat",
    "group": "commerce",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listAdminTenants",
    "method": "GET",
    "path": "/admin/tenants",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "logoutCurrentSession",
    "method": "POST",
    "path": "/auth/logout",
    "group": "identity",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "loginWithWechat",
    "method": "POST",
    "path": "/auth/wechat/login",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getHealth",
    "method": "GET",
    "path": "/health",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "resolveWechatUnionId",
    "method": "POST",
    "path": "/identity/wechat/unionid",
    "group": "identity",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getCurrentAccount",
    "method": "GET",
    "path": "/me",
    "group": "identity",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getPublishedMediaAsset",
    "method": "GET",
    "path": "/media/{uuid}",
    "group": "identity",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listMemberAppointments",
    "method": "GET",
    "path": "/member/booking/appointments",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "createMemberAppointment",
    "method": "POST",
    "path": "/member/booking/appointments",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "cancelMemberAppointment",
    "method": "POST",
    "path": "/member/booking/appointments/{appointment}/cancel",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "promoteMemberWaitlistAppointment",
    "method": "POST",
    "path": "/member/booking/appointments/{appointment}/promote",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberBookingCatalog",
    "method": "GET",
    "path": "/member/booking/catalog",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberBookingSession",
    "method": "GET",
    "path": "/member/booking/sessions/{session}",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberBookingPayableCards",
    "method": "GET",
    "path": "/member/booking/sessions/{session}/payable-cards",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberCardProducts",
    "method": "GET",
    "path": "/member/card-products",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "submitMemberCardPurchase",
    "method": "POST",
    "path": "/member/card-purchases",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "previewMemberCardTransfer",
    "method": "GET",
    "path": "/member/card-transfers/{token}",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "claimMemberCardTransfer",
    "method": "POST",
    "path": "/member/card-transfers/{token}/claim",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberHomeDashboard",
    "method": "GET",
    "path": "/member/home",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listCurrentMemberLegalDocuments",
    "method": "GET",
    "path": "/member/legal-documents",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberWalletCards",
    "method": "GET",
    "path": "/member/member-cards",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "activateMemberCard",
    "method": "POST",
    "path": "/member/member-cards/{memberCard}/activate",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberMemberCardBenefits",
    "method": "GET",
    "path": "/member/member-cards/{memberCard}/benefits",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "hideMemberCard",
    "method": "POST",
    "path": "/member/member-cards/{memberCard}/hide",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberCardLedgerEntries",
    "method": "GET",
    "path": "/member/member-cards/{memberCard}/ledger-entries",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "restoreMemberCardVisibility",
    "method": "POST",
    "path": "/member/member-cards/{memberCard}/restore-visibility",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberHiddenCards",
    "method": "GET",
    "path": "/member/member-cards/hidden",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listOwnMemberLinkRequests",
    "method": "GET",
    "path": "/member/member-link-requests",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "decideOwnMemberLinkRequest",
    "method": "POST",
    "path": "/member/member-link-requests/{linkRequest}/decision",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "joinMemberSite",
    "method": "POST",
    "path": "/member/memberships",
    "group": "members",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberMineDashboard",
    "method": "GET",
    "path": "/member/mine",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberNotices",
    "method": "GET",
    "path": "/member/notices",
    "group": "organization",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberNoticeDetail",
    "method": "GET",
    "path": "/member/notices/{notice}",
    "group": "organization",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberOfficialAccountFollow",
    "method": "GET",
    "path": "/member/official-account-follow",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberOnboarding",
    "method": "GET",
    "path": "/member/onboarding",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberOrders",
    "method": "GET",
    "path": "/member/orders",
    "group": "commerce",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberOrder",
    "method": "GET",
    "path": "/member/orders/{order}",
    "group": "commerce",
    "disposition": "IGNORE"
  },
  {
    "operationId": "resumeMemberOrderPayment",
    "method": "POST",
    "path": "/member/orders/{order}/payment",
    "group": "commerce",
    "disposition": "IGNORE"
  },
  {
    "operationId": "syncMemberOrderPayment",
    "method": "POST",
    "path": "/member/orders/{order}/sync-payment",
    "group": "commerce",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberPointLedger",
    "method": "GET",
    "path": "/member/points/ledger",
    "group": "entitlement",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberTenantProfile",
    "method": "GET",
    "path": "/member/profile",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "patchMemberTenantProfile",
    "method": "PATCH",
    "path": "/member/profile",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "updateOwnMemberProfile",
    "method": "PUT",
    "path": "/member/profile",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "uploadMemberAvatar",
    "method": "POST",
    "path": "/member/profile/avatar",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberPurchaseGate",
    "method": "GET",
    "path": "/member/profile/purchase-gate",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "updateMemberRankingOptIn",
    "method": "PATCH",
    "path": "/member/profile/ranking-opt-in",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "verifyOwnWechatMobile",
    "method": "POST",
    "path": "/member/profile/verify-mobile",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberMonthlyRanking",
    "method": "GET",
    "path": "/member/ranking/monthly",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listJoinableMemberSites",
    "method": "GET",
    "path": "/member/sites",
    "group": "organization",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberSiteClosureStatus",
    "method": "GET",
    "path": "/member/sites/{site}/closure-status",
    "group": "organization",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberSitePublicDetail",
    "method": "GET",
    "path": "/member/sites/{site}/public-detail",
    "group": "organization",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberMonthStats",
    "method": "GET",
    "path": "/member/stats/month",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listMemberMonthAppointments",
    "method": "GET",
    "path": "/member/stats/month/appointments",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getMemberYearStats",
    "method": "GET",
    "path": "/member/stats/year",
    "group": "identity",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getPublicBookingShareSession",
    "method": "GET",
    "path": "/public/booking/share/sessions/{token}",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "getPublicBookingWarmHint",
    "method": "GET",
    "path": "/public/booking/warm-hint/sites/{site}",
    "group": "booking",
    "disposition": "IGNORE"
  },
  {
    "operationId": "listSites",
    "method": "GET",
    "path": "/sites",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "createSite",
    "method": "POST",
    "path": "/sites",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "disableSite",
    "method": "DELETE",
    "path": "/sites/{site}",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getSite",
    "method": "GET",
    "path": "/sites/{site}",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateSite",
    "method": "PATCH",
    "path": "/sites/{site}",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffChainBrand",
    "method": "GET",
    "path": "/staff/chain/brand",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffChainBrand",
    "method": "PUT",
    "path": "/staff/chain/brand",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffChainCourseSummary",
    "method": "GET",
    "path": "/staff/chain/reports/courses/summary",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffChainFinanceSummary",
    "method": "GET",
    "path": "/staff/chain/reports/finance/summary",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffChainMembersSummary",
    "method": "GET",
    "path": "/staff/chain/reports/members/summary",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffChainSites",
    "method": "GET",
    "path": "/staff/chain/sites",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffChainHeadquartersStaff",
    "method": "GET",
    "path": "/staff/chain/staff",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffChainStoreCourses",
    "method": "GET",
    "path": "/staff/chain/store-courses",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffCommonData",
    "method": "GET",
    "path": "/staff/constants/common-data",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffRegionConstants",
    "method": "GET",
    "path": "/staff/constants/regions",
    "group": "platform",
    "disposition": "ADOPT"
  },
  {
    "operationId": "previewStaffInvite",
    "method": "GET",
    "path": "/staff/invites/{sign}",
    "group": "access",
    "disposition": "ADOPT"
  },
  {
    "operationId": "acceptStaffInvite",
    "method": "POST",
    "path": "/staff/invites/{sign}/accept",
    "group": "access",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listMemberTags",
    "method": "GET",
    "path": "/staff/member-tags",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createMemberTag",
    "method": "POST",
    "path": "/staff/member-tags",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffPermissionCatalog",
    "method": "GET",
    "path": "/staff/permission-catalog",
    "group": "access",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPlatformSubscriptionAgreement",
    "method": "GET",
    "path": "/staff/platform/subscription/agreement",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "payStaffPlatformSubscription",
    "method": "POST",
    "path": "/staff/platform/subscription/pay",
    "group": "commerce",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffPlatformSubscriptionPricing",
    "method": "GET",
    "path": "/staff/platform/subscription/pricing",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffProfile",
    "method": "GET",
    "path": "/staff/profile",
    "group": "identity",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffProfile",
    "method": "PATCH",
    "path": "/staff/profile",
    "group": "identity",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "uploadStaffProfileAvatar",
    "method": "POST",
    "path": "/staff/profile/avatar",
    "group": "identity",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffRoles",
    "method": "GET",
    "path": "/staff/roles",
    "group": "access",
    "disposition": "ADOPT"
  },
  {
    "operationId": "upsertStaffRole",
    "method": "POST",
    "path": "/staff/roles",
    "group": "access",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffRoleDetail",
    "method": "GET",
    "path": "/staff/roles/{role}",
    "group": "access",
    "disposition": "ADOPT"
  },
  {
    "operationId": "cancelStaffAppointment",
    "method": "POST",
    "path": "/staff/sites/{site}/appointments/{appointment}/cancel",
    "group": "booking",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "markStaffAppointmentAbsent",
    "method": "POST",
    "path": "/staff/sites/{site}/appointments/{appointment}/mark-absent",
    "group": "booking",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "markStaffAppointmentCheckIn",
    "method": "POST",
    "path": "/staff/sites/{site}/appointments/{appointment}/mark-check-in",
    "group": "booking",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "promoteStaffWaitlistAppointment",
    "method": "POST",
    "path": "/staff/sites/{site}/appointments/{appointment}/promote",
    "group": "booking",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "rescheduleStaffPrivateAppointment",
    "method": "POST",
    "path": "/staff/sites/{site}/appointments/{appointment}/reschedule",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffAppointmentNotes",
    "method": "PATCH",
    "path": "/staff/sites/{site}/appointments/{appointment}/staff-notes",
    "group": "booking",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffBookingPolicy",
    "method": "GET",
    "path": "/staff/sites/{site}/booking-policy",
    "group": "booking",
    "disposition": "ADOPT"
  },
  {
    "operationId": "patchStaffBookingPolicy",
    "method": "PATCH",
    "path": "/staff/sites/{site}/booking-policy",
    "group": "booking",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffBookingPolicy",
    "method": "PUT",
    "path": "/staff/sites/{site}/booking-policy",
    "group": "booking",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffBookingDailyBoard",
    "method": "GET",
    "path": "/staff/sites/{site}/booking/daily-board",
    "group": "booking",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffBookingUpcoming",
    "method": "GET",
    "path": "/staff/sites/{site}/booking/upcoming",
    "group": "booking",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffCardProducts",
    "method": "GET",
    "path": "/staff/sites/{site}/card-products",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffCardProduct",
    "method": "POST",
    "path": "/staff/sites/{site}/card-products",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "deleteStaffCardProduct",
    "method": "DELETE",
    "path": "/staff/sites/{site}/card-products/{cardProduct}",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCardProduct",
    "method": "GET",
    "path": "/staff/sites/{site}/card-products/{cardProduct}",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "patchStaffCardProduct",
    "method": "PATCH",
    "path": "/staff/sites/{site}/card-products/{cardProduct}",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffCardProduct",
    "method": "PUT",
    "path": "/staff/sites/{site}/card-products/{cardProduct}",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "archiveStaffCardProduct",
    "method": "POST",
    "path": "/staff/sites/{site}/card-products/{cardProduct}/archive",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCardProductGroupHistory",
    "method": "GET",
    "path": "/staff/sites/{site}/card-products/{cardProduct}/group-history",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "restoreStaffCardProduct",
    "method": "POST",
    "path": "/staff/sites/{site}/card-products/{cardProduct}/restore",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "createStaffCardProductExportJob",
    "method": "POST",
    "path": "/staff/sites/{site}/card-products/export-jobs",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCardProductFaceLibrary",
    "method": "GET",
    "path": "/staff/sites/{site}/card-products/face-library",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "resolveStaffCheckIn",
    "method": "POST",
    "path": "/staff/sites/{site}/check-in/resolve",
    "group": "booking",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listStaffSiteClosures",
    "method": "GET",
    "path": "/staff/sites/{site}/closure-calendar",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffSiteClosure",
    "method": "POST",
    "path": "/staff/sites/{site}/closure-calendar",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffSiteClosure",
    "method": "PATCH",
    "path": "/staff/sites/{site}/closure-calendar/{closure}",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCourseTags",
    "method": "GET",
    "path": "/staff/sites/{site}/course-tags",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffCourseTags",
    "method": "PUT",
    "path": "/staff/sites/{site}/course-tags",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffCourses",
    "method": "GET",
    "path": "/staff/sites/{site}/courses",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffCourse",
    "method": "POST",
    "path": "/staff/sites/{site}/courses",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "deleteStaffCourse",
    "method": "DELETE",
    "path": "/staff/sites/{site}/courses/{course}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCourse",
    "method": "GET",
    "path": "/staff/sites/{site}/courses/{course}",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "patchStaffCourse",
    "method": "PATCH",
    "path": "/staff/sites/{site}/courses/{course}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffCourse",
    "method": "PUT",
    "path": "/staff/sites/{site}/courses/{course}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "archiveStaffCourse",
    "method": "POST",
    "path": "/staff/sites/{site}/courses/{course}/archive",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCourseDeletePreflight",
    "method": "GET",
    "path": "/staff/sites/{site}/courses/{course}/delete-preflight",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "restoreStaffCourse",
    "method": "POST",
    "path": "/staff/sites/{site}/courses/{course}/restore",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCrmDashboardSummary",
    "method": "GET",
    "path": "/staff/sites/{site}/crm/dashboard-summary",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffCrmMemberFieldPolicy",
    "method": "GET",
    "path": "/staff/sites/{site}/crm/member-field-policy",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffCrmMemberFieldPolicy",
    "method": "PUT",
    "path": "/staff/sites/{site}/crm/member-field-policy",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffCrmMemberFilterPresets",
    "method": "GET",
    "path": "/staff/sites/{site}/crm/member-filter-presets",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffCrmSalesStaff",
    "method": "GET",
    "path": "/staff/sites/{site}/crm/sales-staff",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffDashboardAppointmentFeed",
    "method": "GET",
    "path": "/staff/sites/{site}/dashboard/appointment-feed",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffDashboardSalesFeed",
    "method": "GET",
    "path": "/staff/sites/{site}/dashboard/sales-feed",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffDashboardSummary",
    "method": "GET",
    "path": "/staff/sites/{site}/dashboard/summary",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffExportJobs",
    "method": "GET",
    "path": "/staff/sites/{site}/exports/jobs",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "downloadStaffExportJob",
    "method": "GET",
    "path": "/staff/sites/{site}/exports/jobs/{job}/download",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffMemberExportJob",
    "method": "POST",
    "path": "/staff/sites/{site}/exports/members",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffLedgerReconciliationJobs",
    "method": "GET",
    "path": "/staff/sites/{site}/ledger-reconciliation-jobs",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffLedgerReconciliationJob",
    "method": "POST",
    "path": "/staff/sites/{site}/ledger-reconciliation-jobs",
    "group": "reporting",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getStaffMembershipAgreement",
    "method": "GET",
    "path": "/staff/sites/{site}/legal/membership-agreement",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffMembershipAgreement",
    "method": "PUT",
    "path": "/staff/sites/{site}/legal/membership-agreement",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getMemberCardReminderConfig",
    "method": "GET",
    "path": "/staff/sites/{site}/member-card-reminder-config",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateMemberCardReminderConfig",
    "method": "PUT",
    "path": "/staff/sites/{site}/member-card-reminder-config",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listExpiringMemberCardReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/member-card-reminders/expiring",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listPenalizedMemberCardReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/member-card-reminders/penalized",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listPendingOpenMemberCardReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/member-card-reminders/pending-open",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listZeroBalanceMemberCardReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/member-card-reminders/zero-balance",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffMemberCard",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "archiveStaffMemberCard",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/archive",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "adjustStaffMemberCardBalance",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/balance-adjustments",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getStaffMemberCardBenefits",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/benefits",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "adjustStaffMemberCardCount",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/count-adjustments",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getStaffMemberCardDefaultFee",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/default-fee",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffMemberCardDynamicFields",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/dynamic-fields",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "freezeStaffMemberCard",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/freeze",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getStaffMemberCardFreezeLedgerLast",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/freeze-ledger-last",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffMemberCardHolidayLast",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/holiday-last",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "endStaffMemberCardHoliday",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/holiday/end",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "startStaffMemberCardHoliday",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/holiday/start",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffMemberCardLedgerEntries",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/ledger-entries",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "patchStaffMemberCardOpeningType",
    "method": "PATCH",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/opening-type",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "patchStaffMemberCardRemark",
    "method": "PATCH",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/remark",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "restoreStaffMemberCard",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/restore",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "issueMemberCardTransferShareToken",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/transfer-share-token",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "unfreezeStaffMemberCard",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/unfreeze",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "extendStaffMemberCardValidity",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/{memberCard}/validity-extensions",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffArchivedMemberCards",
    "method": "GET",
    "path": "/staff/sites/{site}/member-cards/archived",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "batchBalanceAdjustStaffMemberCards",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/batch-balance-adjustments",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "batchFreezeStaffMemberCards",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/batch-freeze",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "batchUnfreezeStaffMemberCards",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/batch-unfreeze",
    "group": "entitlement",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "batchValidityExtendStaffMemberCards",
    "method": "POST",
    "path": "/staff/sites/{site}/member-cards/batch-validity-extensions",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffMemberCarousel",
    "method": "GET",
    "path": "/staff/sites/{site}/member-carousel",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffMemberCarousel",
    "method": "PUT",
    "path": "/staff/sites/{site}/member-carousel",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffMemberLinkRequests",
    "method": "GET",
    "path": "/staff/sites/{site}/member-link-requests",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "reviewStaffMemberLinkRequest",
    "method": "POST",
    "path": "/staff/sites/{site}/member-link-requests/{linkRequest}/decision",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffMemberMiniappLayout",
    "method": "GET",
    "path": "/staff/sites/{site}/member-miniapp-layout",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffMemberMiniappLayout",
    "method": "PUT",
    "path": "/staff/sites/{site}/member-miniapp-layout",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffMemberOnboardingHelp",
    "method": "GET",
    "path": "/staff/sites/{site}/member-onboarding-help",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffMemberOnboardingHelp",
    "method": "PUT",
    "path": "/staff/sites/{site}/member-onboarding-help",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffMemberWarmHint",
    "method": "GET",
    "path": "/staff/sites/{site}/member-warm-hint",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffMemberWarmHint",
    "method": "PUT",
    "path": "/staff/sites/{site}/member-warm-hint",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffMembers",
    "method": "GET",
    "path": "/staff/sites/{site}/members",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffMemberLead",
    "method": "POST",
    "path": "/staff/sites/{site}/members",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffMember",
    "method": "GET",
    "path": "/staff/sites/{site}/members/{member}",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "partiallyUpdateStaffMember",
    "method": "PATCH",
    "path": "/staff/sites/{site}/members/{member}",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffMember",
    "method": "PUT",
    "path": "/staff/sites/{site}/members/{member}",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "changeStaffMemberAppAccess",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/app-access",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffMemberBookingHistory",
    "method": "GET",
    "path": "/staff/sites/{site}/members/{member}/booking-history",
    "group": "booking",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffMemberCards",
    "method": "GET",
    "path": "/staff/sites/{site}/members/{member}/member-cards",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "issueStaffMemberCard",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/member-cards",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffMemberNotes",
    "method": "GET",
    "path": "/staff/sites/{site}/members/{member}/notes",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "addStaffMemberNote",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/notes",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listMemberCardOrders",
    "method": "GET",
    "path": "/staff/sites/{site}/members/{member}/orders",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "claimStaffMemberOwner",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/owner-claim",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "adjustStaffMemberPoints",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/point-adjustments",
    "group": "members",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "restoreStaffMember",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/restore",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "transitionStaffMemberStatus",
    "method": "POST",
    "path": "/staff/sites/{site}/members/{member}/status-transitions",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffMemberStickyRemark",
    "method": "PATCH",
    "path": "/staff/sites/{site}/members/{member}/sticky-remark",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "syncStaffMemberTags",
    "method": "PUT",
    "path": "/staff/sites/{site}/members/{member}/tags",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "batchImportStaffMembers",
    "method": "POST",
    "path": "/staff/sites/{site}/members/batch-import",
    "group": "members",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listDeletedStaffMembers",
    "method": "GET",
    "path": "/staff/sites/{site}/members/deleted",
    "group": "members",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffSiteNotices",
    "method": "GET",
    "path": "/staff/sites/{site}/notices",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffSiteNotice",
    "method": "POST",
    "path": "/staff/sites/{site}/notices",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffSiteNotice",
    "method": "PATCH",
    "path": "/staff/sites/{site}/notices/{notice}",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "archiveStaffSiteNotice",
    "method": "POST",
    "path": "/staff/sites/{site}/notices/{notice}/archive",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffNotificationChannels",
    "method": "GET",
    "path": "/staff/sites/{site}/notification-channels",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffNotificationChannels",
    "method": "PUT",
    "path": "/staff/sites/{site}/notification-channels",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "deleteMemberCardOrderForbidden",
    "method": "DELETE",
    "path": "/staff/sites/{site}/orders/{order}",
    "group": "commerce",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "correctMemberCardOrderAmount",
    "method": "POST",
    "path": "/staff/sites/{site}/orders/{order}/amount-corrections",
    "group": "commerce",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "appendStaffOrderInternalNote",
    "method": "POST",
    "path": "/staff/sites/{site}/orders/{order}/internal-notes",
    "group": "commerce",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "voidMemberCardOrder",
    "method": "POST",
    "path": "/staff/sites/{site}/orders/{order}/void",
    "group": "commerce",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getStaffPaymentMarketing",
    "method": "GET",
    "path": "/staff/sites/{site}/payment-marketing",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPayrollCoachConfig",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/coach-config",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffPayrollCoachConfig",
    "method": "PUT",
    "path": "/staff/sites/{site}/payroll/coach-config",
    "group": "reporting",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listStaffPayrollCoachReports",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/coach-reports",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPayrollCoachReportDetail",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/coach-reports/{coachStaff}",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPayrollCoachRules",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/coach-rules",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffPayrollCoachRules",
    "method": "PUT",
    "path": "/staff/sites/{site}/payroll/coach-rules",
    "group": "reporting",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listStaffPayrollCoaches",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/coaches",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPayrollCourseCommissionReport",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/course-commission",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffPayrollRecomputeJobs",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/recompute-jobs",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffPayrollRecomputeJob",
    "method": "POST",
    "path": "/staff/sites/{site}/payroll/recompute-jobs",
    "group": "reporting",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "getStaffPayrollSalesConfig",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/sales-config",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffPayrollSalesConfig",
    "method": "PUT",
    "path": "/staff/sites/{site}/payroll/sales-config",
    "group": "reporting",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listStaffPayrollSalesReports",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/sales-reports",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPayrollSalesReportDetail",
    "method": "GET",
    "path": "/staff/sites/{site}/payroll/sales-reports/{salesStaff}",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPlatformSubscriptionSiteStatus",
    "method": "GET",
    "path": "/staff/sites/{site}/platform/subscription/status",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffPointsConfig",
    "method": "GET",
    "path": "/staff/sites/{site}/points-config",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffPointsConfig",
    "method": "PUT",
    "path": "/staff/sites/{site}/points-config",
    "group": "entitlement",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffSiteProfile",
    "method": "GET",
    "path": "/staff/sites/{site}/profile",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffSiteProfile",
    "method": "PATCH",
    "path": "/staff/sites/{site}/profile",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffReportCalendarMonthOptions",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/calendar/month-options",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportCoachAppointmentDetail",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/coaches/{coachStaff}/appointments",
    "group": "booking",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportCoachMonthlyRankings",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/coaches/rankings",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportCourseCalendar",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/courses/calendar",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportCourseDaily",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/courses/daily",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportCourseSummary",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/courses/summary",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportDashboardSummary",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/dashboard-summary",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportFinanceProfitCalendar",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/finance/profit-calendar",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportFinanceProfitDaily",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/finance/profit-daily",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportFinanceProfitSummary",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/finance/profit-summary",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportCourseAttendanceRanking",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/rankings/course-attendance",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportOrderRanking",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/rankings/orders",
    "group": "commerce",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportPointsRanking",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/rankings/points",
    "group": "entitlement",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportSalesStaffRanking",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/rankings/sales-staff",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffReportSalesStaffRankingDetail",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/rankings/sales-staff/{salesStaff}",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffAnniversaryReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/reminders/anniversary",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffBirthdayReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/reminders/birthdays",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffHolidayDueReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/reminders/holiday-due",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffNoClassReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/reminders/no-class",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffVisitorReminders",
    "method": "GET",
    "path": "/staff/sites/{site}/reports/reminders/visitors",
    "group": "reporting",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffRooms",
    "method": "GET",
    "path": "/staff/sites/{site}/rooms",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffRoom",
    "method": "POST",
    "path": "/staff/sites/{site}/rooms",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "deleteStaffRoom",
    "method": "DELETE",
    "path": "/staff/sites/{site}/rooms/{room}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffRoom",
    "method": "GET",
    "path": "/staff/sites/{site}/rooms/{room}",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "patchStaffRoom",
    "method": "PATCH",
    "path": "/staff/sites/{site}/rooms/{room}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffRoom",
    "method": "PUT",
    "path": "/staff/sites/{site}/rooms/{room}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffScheduleDisplayConfig",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-display-config",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffScheduleDisplayConfig",
    "method": "PUT",
    "path": "/staff/sites/{site}/schedule-display-config",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "exportStaffScheduleImage",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-export-image",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffScheduleRecurringTemplate",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-recurring-template",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffScheduleSessionColors",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-session-colors",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffScheduleSessionColors",
    "method": "PUT",
    "path": "/staff/sites/{site}/schedule-session-colors",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffScheduleSessions",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-sessions",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffScheduleSession",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffScheduleSession",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-sessions/{session}",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "patchStaffScheduleSession",
    "method": "PATCH",
    "path": "/staff/sites/{site}/schedule-sessions/{session}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffScheduleSession",
    "method": "PUT",
    "path": "/staff/sites/{site}/schedule-sessions/{session}",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffSessionAppointments",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/appointments",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffAppointment",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/appointments",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "cancelStaffScheduleSession",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/cancel",
    "group": "scheduling",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "createStaffScheduleSessionShareLink",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/share-link",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "suspendStaffScheduleSession",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/suspend",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "unsuspendStaffScheduleSession",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/unsuspend",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "listStaffSessionWaitlist",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-sessions/{session}/waitlist",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "batchCancelStaffScheduleSessions",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/batch-cancel",
    "group": "scheduling",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "batchChangeCourseStaffScheduleSessions",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/batch-change-course",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "batchCopyStaffScheduleSessions",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/batch-copy",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "batchSuspendStaffScheduleSessions",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/batch-suspend",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "batchUnsuspendStaffScheduleSessions",
    "method": "POST",
    "path": "/staff/sites/{site}/schedule-sessions/batch-unsuspend",
    "group": "scheduling",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "changeCoursePreflightStaffScheduleSessions",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-sessions/change-course-preflight",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "copyPreflightStaffScheduleSessions",
    "method": "GET",
    "path": "/staff/sites/{site}/schedule-sessions/copy-preflight",
    "group": "scheduling",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffSettingsHub",
    "method": "GET",
    "path": "/staff/sites/{site}/settings-hub",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffDirectory",
    "method": "GET",
    "path": "/staff/sites/{site}/staff-directory",
    "group": "access",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffDirectoryMember",
    "method": "POST",
    "path": "/staff/sites/{site}/staff-directory",
    "group": "access",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffDirectoryMember",
    "method": "GET",
    "path": "/staff/sites/{site}/staff-directory/{staffMember}",
    "group": "access",
    "disposition": "ADOPT"
  },
  {
    "operationId": "updateStaffDirectoryMember",
    "method": "PATCH",
    "path": "/staff/sites/{site}/staff-directory/{staffMember}",
    "group": "access",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "departStaffDirectoryMember",
    "method": "POST",
    "path": "/staff/sites/{site}/staff-directory/{staffMember}/departure",
    "group": "access",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "transferSiteOwnership",
    "method": "POST",
    "path": "/staff/sites/{site}/staff-directory/{staffMember}/transfer-ownership",
    "group": "access",
    "disposition": "CUSTOM"
  },
  {
    "operationId": "listStaffVacationRollup",
    "method": "GET",
    "path": "/staff/sites/{site}/staff-vacations",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "listStaffMemberVacations",
    "method": "GET",
    "path": "/staff/sites/{site}/staff/{staffMember}/vacations",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "createStaffMemberVacation",
    "method": "POST",
    "path": "/staff/sites/{site}/staff/{staffMember}/vacations",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "updateStaffMemberVacation",
    "method": "PATCH",
    "path": "/staff/sites/{site}/staff/{staffMember}/vacations/{vacation}",
    "group": "organization",
    "disposition": "UNCLASSIFIED"
  },
  {
    "operationId": "getStaffSupportContact",
    "method": "GET",
    "path": "/staff/sites/{site}/support/contact",
    "group": "organization",
    "disposition": "ADOPT"
  },
  {
    "operationId": "getStaffSupportVideoHelp",
    "method": "GET",
    "path": "/staff/sites/{site}/support/video-help",
    "group": "organization",
    "disposition": "ADOPT"
  }
];

export const contractReport: ContractReport = {
  "generatedAt": "2026年8月23日 10:17:17",
  "sourceHash": "6691048e7615e1fd110a3d5262d5862b7c76942502be80f2fb413a100e325662",
  "total": 312,
  "counts": {
    "ADOPT": 134,
    "CUSTOM": 31,
    "IGNORE": 50,
    "UNCLASSIFIED": 97
  },
  "changes": {
    "added": [],
    "changed": [],
    "removed": [],
    "sourceChanged": false
  }
};
