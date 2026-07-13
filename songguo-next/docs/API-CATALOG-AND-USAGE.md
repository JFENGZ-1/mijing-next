# API 全量目录与使用方法
Generated: 2026-07-13 (adversarial review)

> 精简差距清单：[API-FRONTEND-GAP-AUDIT.md](./API-FRONTEND-GAP-AUDIT.md)

## 摘要

| 端 | 路由数 | FULL | PARTIAL | NONE | 触达率 |
|---|---:|---:|---:|---:|---:|
| **Staff** | **239** | **171** | **4** | **64** | **73.2%** |
| **Member** | **46** | **43** | **1** | **2** | **95.7%** |

- 后端 `api/v1` 路由：**292** 条 · OpenAPI **287** operationId · 契约测试 **100%**
- 扫描前端文件：**153** 个（staff + member `src/**`）
- `traceability-apis.csv` **REJECT** 10 行已排除在优先级建议外

## 使用方法说明

### Base URL / Auth / 上下文

```
BASE = import.meta.env.VITE_API_BASE_URL   // e.g. http://127.0.0.1:8010/api/v1
Authorization: Bearer <token>               // @songguo/api-client 自动附加
员工端路径: /staff/sites/{siteId}/...        // siteId 来自 session store
会员端查询: ?tenantId={id}&siteId={id}       // member.ts tenantQuery/siteQuery
```

### staff-miniapp（`src/api/*.ts`）

| 模块 | 封装函数示例 | 路径模式 |
|---|---|---|
| `client.ts` | `useApiClient().request(path)` | 通用 |
| `crm.ts` | `fetchCrmMembers`, `syncMemberTags` | `sitePath(siteId, suffix)` |
| `scheduling.ts` | `fetchStaffScheduleSessions`, `cancelStaffAppointment` | `sitePath` |
| `reports.ts` | `fetchReportFinanceProfitSummary` | `reportsPath(siteId, suffix)` |
| `member-cards.ts` | `freezeMemberCard`, `issueMemberCard` | `cardPath` / 直连 |
| `settings.ts` | `fetchBookingPolicy`, `createSiteClosure` | 直连 `/staff/sites/{id}/...` |
| `profile.ts` | `uploadStaffAvatar` | `uni.uploadFile` → `/staff/profile/avatar` |

### member-miniapp

| 模块 | 说明 |
|---|---|
| `api/member.ts` | 主 API 层，导出 `getMemberHome` 等 30+ 函数 |
| `composables/member-context.ts` | `/member/sites`, `/member/memberships` |
| `pages/onboarding/profile.vue` | onboarding / verify-mobile / join / link decision |

### 共享端点

| Method | Path | 前端 |
|---|---|---|
| POST | `/auth/logout` | `/apps/staff-miniapp/src/pages/settings/personal/index.vue` |
| POST | `/auth/wechat/login` | `/apps/staff-miniapp/src/api/staff-invite.ts` |
| POST | `/identity/wechat/unionid` | `/apps/staff-miniapp/src/api/staff-invite.ts` |
| GET | `/me` | `/apps/staff-miniapp/src/api/card-products.ts` |

## Staff API 全表

| Method | Path | operationId | 用途 | 认证 | 测试 | 前端 | 调用位置/建议 |
|---|---|---|---|---|---|---|---|
| GET | `/sites` | `listSites` | index | staff token | MemberSitePublicTest.php | **NONE** | — · **P2** 待接 |
| POST | `/sites` | `createSite` | store | staff token | MemberSitePublicTest.php | **NONE** | — · **P2** 待接 |
| GET | `/sites/{id}` | `—` | show | staff token | MemberSitePublicTest.php | **NONE** | — · **P2** 待接 |
| PUT | `/sites/{id}` | `—` | update | staff token | MemberSitePublicTest.php | **NONE** | — · **P2** 待接 |
| DELETE | `/sites/{id}` | `—` | destroy | staff token | MemberSitePublicTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/chain/brand` | `getStaffChainBrand` | brand | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| PUT | `/staff/chain/brand` | `updateStaffChainBrand` | updateBrand | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/chain/reports/courses/summary` | `getStaffChainCourseSummary` | courseSummary | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/chain/reports/finance/summary` | `getStaffChainFinanceSummary` | financeSummary | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/chain/reports/members/summary` | `getStaffChainMembersSummary` | membersSummary | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/chain/sites` | `getStaffChainSites` | sites | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/chain/staff` | `listStaffChainHeadquartersStaff` | staffDirectory | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/chain/store-courses` | `listStaffChainStoreCourses` | storeCourses | staff token | StaffChainManagementTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/constants/common-data` | `getStaffCommonData` | commonData | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/constants/regions` | `getStaffRegionConstants` | regions | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/site-profile.ts` |
| GET | `/staff/invites/{id}` | `—` | preview | public | StaffInviteTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-invite.ts` |
| POST | `/staff/invites/{id}/accept` | `—` | accept | staff token | StaffInviteTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-invite.ts` |
| GET | `/staff/member-tags` | `listMemberTags` | index | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| POST | `/staff/member-tags` | `createMemberTag` | store | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/permission-catalog` | `getStaffPermissionCatalog` | permissionCatalog | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-roles.ts` |
| GET | `/staff/platform/subscription/agreement` | `getStaffPlatformSubscriptionAgreement` | agreement | staff token | StaffPlatformSubscriptionTest.php | **NONE** | — · **P1** 待接 |
| GET | `/staff/platform/subscription/orders` | `—` | orders | staff token | StaffPlatformSubscriptionTest.php | **FULL** | `/apps/staff-miniapp/src/api/platform.ts` |
| POST | `/staff/platform/subscription/pay` | `payStaffPlatformSubscription` | pay | staff token | StaffPlatformSubscriptionTest.php | **NONE** | — · **P1** 待接 |
| GET | `/staff/platform/subscription/pricing` | `getStaffPlatformSubscriptionPricing` | pricing | staff token | StaffPlatformSubscriptionTest.php | **NONE** | — · **P1** 待接 |
| GET | `/staff/profile` | `getStaffProfile` | show | staff token | StaffProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts` |
| PUT | `/staff/profile` | `—` | update | staff token | StaffProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts` |
| POST | `/staff/profile/avatar` | `uploadStaffProfileAvatar` | uploadAvatar | staff token | StaffProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts` |
| GET | `/staff/roles` | `listStaffRoles` | roles | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts`, `/apps/staff-miniapp/src/api/staff-roles.ts` |
| POST | `/staff/roles` | `upsertStaffRole` | upsert | staff token | StaffRoleEditorTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts`, `/apps/staff-miniapp/src/api/staff-roles.ts` |
| GET | `/staff/roles/{id}` | `—` | show | staff token | StaffRoleEditorTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-roles.ts` |
| POST | `/staff/sites/{id}/appointments/{id}/cancel` | `—` | cancel | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/staff/sites/{id}/appointments/{id}/mark-absent` | `—` | markAbsent | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/appointments/{id}/mark-check-in` | `—` | markCheckIn | staff token | StaffCheckInTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/appointments/{id}/promote` | `—` | promote | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/appointments/{id}/reschedule` | `—` | reschedule | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| PATCH | `/staff/sites/{id}/appointments/{id}/staff-notes` | `—` | updateStaffNotes | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| GET | `/staff/sites/{id}/booking-policy` | `—` | show | staff token | StaffBookingPolicyTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/booking-policy` | `—` | update | staff token | StaffBookingPolicyTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/booking/daily-board` | `—` | index | staff token | StaffBookingDailyBoardTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| GET | `/staff/sites/{id}/booking/upcoming` | `—` | staffUpcoming | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/card-products` | `—` | index | staff token | StaffCardProductCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/pages/members/issue-card.vue` |
| POST | `/staff/sites/{id}/card-products` | `—` | store | staff token | StaffCardProductCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/pages/members/issue-card.vue` |
| GET | `/staff/sites/{id}/card-products/cross-site-links` | `—` | index | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts` |
| POST | `/staff/sites/{id}/card-products/export-jobs` | `—` | createExportJob | staff token | StaffCardProductCatalogTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/card-products/face-library` | `—` | faceLibrary | staff token | StaffCardProductCatalogTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/card-products/{id}` | `—` | show | staff token | StaffCardProductCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/pages/settings/card-products/index.vue` |
| PUT | `/staff/sites/{id}/card-products/{id}` | `—` | update | staff token | StaffCardProductCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/pages/settings/card-products/index.vue` |
| DELETE | `/staff/sites/{id}/card-products/{id}` | `—` | destroy | staff token | StaffCardProductCatalogTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/card-products/{id}/archive` | `—` | archive | staff token | StaffCardProductCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts` |
| PUT | `/staff/sites/{id}/card-products/{id}/cross-site-link` | `—` | update | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts` |
| GET | `/staff/sites/{id}/card-products/{id}/group-history` | `—` | groupHistory | staff token | StaffCardProductCatalogTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/card-products/{id}/restore` | `—` | restore | staff token | StaffCardProductCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts` |
| POST | `/staff/sites/{id}/check-in/resolve` | `—` | resolve | staff token | StaffCheckInTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| GET | `/staff/sites/{id}/closure-calendar` | `—` | index | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| POST | `/staff/sites/{id}/closure-calendar` | `—` | store | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PATCH | `/staff/sites/{id}/closure-calendar/{id}` | `—` | update | staff token | — | **PARTIAL** | — |
| GET | `/staff/sites/{id}/course-tags` | `—` | tags | staff token | StaffCourseCatalogTest.php | **NONE** | — · **P2** 待接 |
| PUT | `/staff/sites/{id}/course-tags` | `—` | updateTags | staff token | StaffCourseCatalogTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/courses` | `—` | index | staff token | StaffCourseCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/catalog.ts`, `/apps/staff-miniapp/src/api/chain.ts` |
| POST | `/staff/sites/{id}/courses` | `—` | store | staff token | StaffCourseCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/catalog.ts`, `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/sites/{id}/courses/{id}` | `—` | show | staff token | StaffCourseCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts`, `/apps/staff-miniapp/src/api/reports.ts` |
| PUT | `/staff/sites/{id}/courses/{id}` | `—` | update | staff token | StaffCourseCatalogTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts`, `/apps/staff-miniapp/src/api/reports.ts` |
| DELETE | `/staff/sites/{id}/courses/{id}` | `—` | destroy | staff token | StaffCourseCatalogTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/courses/{id}/archive` | `—` | archive | staff token | StaffCourseCatalogTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/courses/{id}/delete-preflight` | `—` | deletePreflight | staff token | StaffCourseCatalogTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/courses/{id}/restore` | `—` | restore | staff token | StaffCourseCatalogTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/crm/dashboard-summary` | `—` | summary | staff token | StaffCrmDashboardTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/staff/sites/{id}/crm/member-field-policy` | `—` | memberFieldPolicy | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/crm/member-field-policy` | `—` | updateMemberFieldPolicy | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/crm/member-filter-presets` | `—` | memberFilterPresets | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/staff/sites/{id}/crm/sales-staff` | `—` | salesStaff | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/dashboard/appointment-feed` | `—` | appointmentFeed | staff token | StaffDashboardTest.php | **FULL** | `/apps/staff-miniapp/src/api/dashboard.ts` |
| GET | `/staff/sites/{id}/dashboard/sales-feed` | `—` | salesFeed | staff token | StaffDashboardTest.php | **FULL** | `/apps/staff-miniapp/src/api/dashboard.ts` |
| GET | `/staff/sites/{id}/dashboard/summary` | `—` | summary | staff token | StaffDashboardTest.php | **FULL** | `/apps/staff-miniapp/src/api/dashboard.ts` |
| GET | `/staff/sites/{id}/exports/jobs` | `—` | listJobs | staff token | StaffExportJobTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/exports/jobs/{id}/download` | `—` | downloadJob | staff token | StaffExportJobTest.php | **FULL** | — |
| POST | `/staff/sites/{id}/exports/members` | `—` | createMemberExport | staff token | StaffExportJobTest.php | **FULL** | — |
| POST | `/staff/sites/{id}/ledger-reconciliation-jobs` | `—` | createJob | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/ledger-reconciliation-jobs` | `—` | listJobs | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/legal/membership-agreement` | `—` | membershipAgreement | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/legal/membership-agreement` | `—` | updateMembershipAgreement | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/member-card-reminder-config` | `—` | showConfig | staff token | StaffMemberCardReminderTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| PUT | `/staff/sites/{id}/member-card-reminder-config` | `—` | updateConfig | staff token | StaffMemberCardReminderTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/member-card-reminders/expiring` | `—` | expiring | staff token | StaffMemberCardReminderTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-card-reminders/penalized` | `—` | penalized | staff token | StaffMemberCardReminderTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-card-reminders/pending-open` | `—` | pendingOpen | staff token | StaffMemberCardReminderTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-card-reminders/zero-balance` | `—` | zeroBalance | staff token | StaffMemberCardReminderTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-cards/archived` | `—` | archivedIndex | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| POST | `/staff/sites/{id}/member-cards/batch-balance-adjustments` | `—` | batchBalanceAdjustments | staff token | — | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/member-cards/batch-freeze` | `—` | batchFreeze | staff token | — | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/member-cards/batch-unfreeze` | `—` | batchUnfreeze | staff token | — | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/member-cards/batch-validity-extensions` | `—` | batchValidityExtensions | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-cards/{id}` | `—` | show | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/staff/sites/{id}/member-cards/{id}/archive` | `—` | archive | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| POST | `/staff/sites/{id}/member-cards/{id}/balance-adjustments` | `—` | balanceAdjust | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/member-cards/{id}/benefits` | `—` | benefits | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/staff/sites/{id}/member-cards/{id}/count-adjustments` | `—` | countAdjust | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/member-cards/{id}/default-fee` | `—` | defaultFee | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-cards/{id}/dynamic-fields` | `—` | dynamicFields | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/member-cards/{id}/freeze` | `—` | freeze | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/member-cards/{id}/freeze-ledger-last` | `—` | freezeLedgerLast | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/member-cards/{id}/holiday-last` | `—` | holidayLast | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/member-cards/{id}/holiday/end` | `—` | holidayEnd | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| POST | `/staff/sites/{id}/member-cards/{id}/holiday/start` | `—` | holidayStart | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/member-cards/{id}/ledger-entries` | `—` | ledgerEntries | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| PATCH | `/staff/sites/{id}/member-cards/{id}/opening-type` | `—` | updateOpeningType | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P2** 待接 |
| PATCH | `/staff/sites/{id}/member-cards/{id}/remark` | `—` | updateRemark | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/member-cards/{id}/restore` | `—` | restore | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/staff/sites/{id}/member-cards/{id}/transfer-share-token` | `—` | transferShareToken | staff token | StaffMemberCardAdjustTest.php | **NONE** | — · **P0** 待接 |
| POST | `/staff/sites/{id}/member-cards/{id}/unfreeze` | `—` | unfreeze | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| POST | `/staff/sites/{id}/member-cards/{id}/validity-extensions` | `—` | validityExtension | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/member-carousel` | `—` | carousel | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/member-carousel` | `—` | updateCarousel | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/member-link-requests` | `—` | index | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| POST | `/staff/sites/{id}/member-link-requests/{id}/decision` | `—` | decide | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| GET | `/staff/sites/{id}/member-miniapp-layout` | `—` | miniappLayout | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/member-miniapp-layout` | `—` | updateMiniappLayout | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/member-onboarding-help` | `—` | onboardingHelp | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/member-onboarding-help` | `—` | updateOnboardingHelp | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/member-warm-hint` | `—` | warmHint | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/member-warm-hint` | `—` | updateWarmHint | staff token | StaffMemberExperienceTest.php | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/members` | `—` | index | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts`, `/apps/staff-miniapp/src/api/crm.ts` |
| POST | `/staff/sites/{id}/members` | `—` | store | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts`, `/apps/staff-miniapp/src/api/crm.ts` |
| POST | `/staff/sites/{id}/members/batch-import` | `—` | batchImport | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/staff-miniapp/src/pages/members/index.vue` |
| GET | `/staff/sites/{id}/members/deleted` | `—` | deletedIndex | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/staff-miniapp/src/pages/members/index.vue` |
| GET | `/staff/sites/{id}/members/{id}` | `—` | show | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts`, `/apps/staff-miniapp/src/api/crm.ts` |
| PUT | `/staff/sites/{id}/members/{id}` | `—` | update | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts`, `/apps/staff-miniapp/src/api/crm.ts` |
| POST | `/staff/sites/{id}/members/{id}/app-access` | `—` | changeAppAccess | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/staff/sites/{id}/members/{id}/booking-history` | `—` | memberHistory | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/staff/sites/{id}/members/{id}/member-cards` | `—` | index | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/staff-miniapp/src/api/member-cards.ts` |
| POST | `/staff/sites/{id}/members/{id}/member-cards` | `—` | store | staff token | StaffMemberCardAdjustTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/staff-miniapp/src/api/member-cards.ts` |
| GET | `/staff/sites/{id}/members/{id}/notes` | `—` | notes | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| POST | `/staff/sites/{id}/members/{id}/notes` | `—` | addNote | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/staff/sites/{id}/members/{id}/orders` | `—` | index | staff token | StaffMemberCardOrderTest.php | **NONE** | — · **P0** 待接 |
| POST | `/staff/sites/{id}/members/{id}/owner-claim` | `—` | claimOwner | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| POST | `/staff/sites/{id}/members/{id}/point-adjustments` | `—` | pointAdjust | staff token | StaffMemberBookingHistoryTest.php | **NONE** | — · **P0** 待接 |
| POST | `/staff/sites/{id}/members/{id}/restore` | `—` | restore | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/staff-miniapp/src/api/member-cards.ts` |
| POST | `/staff/sites/{id}/members/{id}/status-transitions` | `—` | transitionStatus | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| PATCH | `/staff/sites/{id}/members/{id}/sticky-remark` | `—` | updateStickyRemark | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| PUT | `/staff/sites/{id}/members/{id}/tags` | `—` | syncTags | staff token | StaffMemberBookingHistoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/staff/sites/{id}/notices` | `—` | index | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/staff/sites/{id}/notices` | `—` | store | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts`, `/apps/member-miniapp/src/api/member.ts` |
| PATCH | `/staff/sites/{id}/notices/{id}` | `—` | update | staff token | — | **PARTIAL** | `/apps/staff-miniapp/src/api/settings.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/staff/sites/{id}/notices/{id}/archive` | `—` | archive | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/notification-channels` | `—` | show | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PUT | `/staff/sites/{id}/notification-channels` | `—` | update | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| DELETE | `/staff/sites/{id}/orders/{id}` | `—` | destroy | staff token | StaffMemberCardOrderTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/orders/{id}/amount-corrections` | `—` | correctAmount | staff token | StaffMemberCardOrderTest.php | **NONE** | — · **P0** 待接 |
| POST | `/staff/sites/{id}/orders/{id}/internal-notes` | `—` | appendInternalNote | staff token | StaffMemberCardOrderTest.php | **NONE** | — · **P1** 待接 |
| POST | `/staff/sites/{id}/orders/{id}/void` | `—` | void | staff token | StaffMemberCardOrderTest.php | **NONE** | — · **P0** 待接 |
| GET | `/staff/sites/{id}/payment-marketing` | `—` | paymentMarketing | staff token | — | **PARTIAL** | `/apps/staff-miniapp/src/api/settings.ts` |
| GET | `/staff/sites/{id}/payroll/coach-config` | `—` | coachConfig | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| PUT | `/staff/sites/{id}/payroll/coach-config` | `—` | updateCoachConfig | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/coach-reports` | `—` | coachReports | staff token | StaffPayrollReportTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/coach-reports/{id}` | `—` | coachReportDetail | staff token | StaffPayrollReportTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/payroll/coach-rules` | `—` | coachRules | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| PUT | `/staff/sites/{id}/payroll/coach-rules` | `—` | updateCoachRules | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/coaches` | `—` | coaches | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/course-commission` | `—` | courseCommission | staff token | StaffPayrollReportTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/payroll/recompute-jobs` | `—` | createRecomputeJob | staff token | StaffPayrollReportTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/recompute-jobs` | `—` | listRecomputeJobs | staff token | StaffPayrollReportTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/sales-config` | `—` | salesConfig | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| PUT | `/staff/sites/{id}/payroll/sales-config` | `—` | updateSalesConfig | staff token | StaffPayrollConfigTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/sales-reports` | `—` | salesReports | staff token | StaffPayrollReportTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/payroll/sales-reports/{id}` | `—` | salesReportDetail | staff token | StaffPayrollReportTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/platform/subscription/status` | `—` | siteStatus | staff token | StaffPlatformSubscriptionTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/points-config` | `—` | show | staff token | — | **NONE** | — · **P2** 待接 |
| PUT | `/staff/sites/{id}/points-config` | `—` | update | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/profile` | `—` | show | staff token | StaffSiteProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts`, `/apps/staff-miniapp/src/api/site-profile.ts` |
| PUT | `/staff/sites/{id}/profile` | `—` | update | staff token | StaffSiteProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts`, `/apps/staff-miniapp/src/api/site-profile.ts` |
| GET | `/staff/sites/{id}/reports/calendar/month-options` | `—` | monthOptions | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/reports/card-product-analytics` | `—` | cardProductAnalytics | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/card-sales/detail` | `—` | detail | staff token | — | **FULL** | — |
| GET | `/staff/sites/{id}/reports/card-sales/summary` | `—` | summary | staff token | — | **FULL** | — |
| GET | `/staff/sites/{id}/reports/coaches/rankings` | `—` | monthlyRank | staff token | StaffReportCoachTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/coaches/{id}/appointments` | `—` | appointmentDetail | staff token | StaffReportCoachTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/reports/courses/calendar` | `—` | calendar | staff token | StaffReportCourseTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/courses/daily` | `—` | daily | staff token | StaffReportCourseTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/courses/summary` | `—` | summary | staff token | StaffReportCourseTest.php | **FULL** | `/apps/staff-miniapp/src/api/chain.ts` |
| GET | `/staff/sites/{id}/reports/dashboard-summary` | `—` | summary | staff token | StaffReportDashboardTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/finance/profit-calendar` | `—` | profitCalendar | staff token | StaffReportFinanceProfitTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/finance/profit-daily` | `—` | profitDaily | staff token | StaffReportFinanceProfitTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/finance/profit-summary` | `—` | profitSummary | staff token | StaffReportFinanceProfitTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/card-product-sales` | `—` | cardProductSales | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/course-attendance` | `—` | courseAttendance | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/member-card-consumption` | `—` | memberCardConsumption | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/orders` | `—` | orders | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/points` | `—` | points | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/sales-staff` | `—` | salesStaff | staff token | StaffReportRankingTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/rankings/sales-staff/{id}` | `—` | salesStaffDetail | staff token | StaffReportRankingTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/reports/reminders/anniversary` | `—` | anniversary | staff token | StaffNotificationReminderTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/reminders/birthdays` | `—` | birthdays | staff token | StaffNotificationReminderTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/reminders/holiday-due` | `—` | holidayDue | staff token | StaffNotificationReminderTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/reminders/no-class` | `—` | noClass | staff token | StaffNotificationReminderTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/reports/reminders/visitors` | `—` | visitors | staff token | StaffNotificationReminderTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/rooms` | `—` | index | staff token | StaffRoomTest.php | **FULL** | `/apps/staff-miniapp/src/api/catalog.ts`, `/apps/staff-miniapp/src/pages/settings/rooms/edit.vue` |
| POST | `/staff/sites/{id}/rooms` | `—` | store | staff token | StaffRoomTest.php | **FULL** | `/apps/staff-miniapp/src/api/catalog.ts`, `/apps/staff-miniapp/src/pages/settings/rooms/edit.vue` |
| GET | `/staff/sites/{id}/rooms/{id}` | `—` | show | staff token | StaffRoomTest.php | **FULL** | `/apps/staff-miniapp/src/pages/settings/rooms/edit.vue`, `/apps/staff-miniapp/src/pages/settings/rooms/index.vue` |
| PUT | `/staff/sites/{id}/rooms/{id}` | `—` | update | staff token | StaffRoomTest.php | **FULL** | `/apps/staff-miniapp/src/pages/settings/rooms/edit.vue`, `/apps/staff-miniapp/src/pages/settings/rooms/index.vue` |
| DELETE | `/staff/sites/{id}/rooms/{id}` | `—` | destroy | staff token | StaffRoomTest.php | **NONE** | — · **P2** 待接 |
| POST | `/staff/sites/{id}/rooms/{id}/archive` | `—` | archive | staff token | StaffRoomTest.php | **FULL** | — |
| GET | `/staff/sites/{id}/schedule-display-config` | `—` | show | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| PUT | `/staff/sites/{id}/schedule-display-config` | `—` | update | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-export-image` | `—` | exportImage | staff token | — | **NONE** | — · **P1** 待接 |
| GET | `/staff/sites/{id}/schedule-recurring-template` | `—` | recurringTemplate | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/schedule-session-colors` | `—` | sessionColors | staff token | — | **NONE** | — · **P2** 待接 |
| PUT | `/staff/sites/{id}/schedule-session-colors` | `—` | updateSessionColors | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/schedule-sessions` | `—` | index | staff token | StaffScheduleSessionTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions` | `—` | store | staff token | StaffScheduleSessionTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/batch-cancel` | `—` | batchCancel | staff token | StaffScheduleBatchTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/batch-change-course` | `—` | batchChangeCourse | staff token | StaffScheduleBatchTest.php | **NONE** | — · **P1** 待接 |
| POST | `/staff/sites/{id}/schedule-sessions/batch-copy` | `—` | batchCopy | staff token | StaffScheduleBatchTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/batch-suspend` | `—` | batchSuspend | staff token | StaffScheduleBatchTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/batch-unsuspend` | `—` | batchUnsuspend | staff token | StaffScheduleBatchTest.php | **NONE** | — · **P1** 待接 |
| GET | `/staff/sites/{id}/schedule-sessions/change-course-preflight` | `—` | changeCoursePreflight | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/schedule-sessions/copy-preflight` | `—` | copyPreflight | staff token | — | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/schedule-sessions/{id}` | `—` | show | staff token | StaffScheduleSessionTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| PUT | `/staff/sites/{id}/schedule-sessions/{id}` | `—` | update | staff token | StaffScheduleSessionTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| GET | `/staff/sites/{id}/schedule-sessions/{id}/appointments` | `—` | index | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/{id}/appointments` | `—` | store | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/{id}/cancel` | `—` | cancel | staff token | StaffScheduleSessionTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/{id}/share-link` | `—` | createShareLink | staff token | — | **NONE** | — · **P1** 待接 |
| POST | `/staff/sites/{id}/schedule-sessions/{id}/suspend` | `—` | suspend | staff token | StaffScheduleSessionTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| POST | `/staff/sites/{id}/schedule-sessions/{id}/unsuspend` | `—` | unsuspend | staff token | StaffScheduleSessionTest.php | **NONE** | — · **P1** 待接 |
| GET | `/staff/sites/{id}/schedule-sessions/{id}/waitlist` | `—` | waitlist | staff token | StaffAppointmentTest.php | **FULL** | `/apps/staff-miniapp/src/api/scheduling.ts` |
| GET | `/staff/sites/{id}/settings-hub` | `—` | hub | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| POST | `/staff/sites/{id}/sharing/staff-miniapp-code` | `—` | createStaffMiniappCode | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/sharing.ts` |
| GET | `/staff/sites/{id}/staff-directory` | `—` | index | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts`, `/apps/staff-miniapp/src/pages/course/session-form.vue` |
| POST | `/staff/sites/{id}/staff-directory` | `—` | store | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts`, `/apps/staff-miniapp/src/pages/course/session-form.vue` |
| GET | `/staff/sites/{id}/staff-directory/{id}` | `—` | show | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts` |
| PUT | `/staff/sites/{id}/staff-directory/{id}` | `—` | update | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts` |
| POST | `/staff/sites/{id}/staff-directory/{id}/departure` | `—` | departure | staff token | StaffDirectoryTest.php | **FULL** | `/apps/staff-miniapp/src/api/staff-directory.ts` |
| POST | `/staff/sites/{id}/staff-directory/{id}/transfer-ownership` | `—` | transferOwnership | staff token | StaffDirectoryTest.php | **NONE** | — · **P2** 待接 |
| GET | `/staff/sites/{id}/staff-vacations` | `—` | index | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts`, `/apps/staff-miniapp/src/pages/settings/operations/staff-vacations/index.vue` |
| GET | `/staff/sites/{id}/staff/{id}/vacations` | `—` | staffIndex | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| POST | `/staff/sites/{id}/staff/{id}/vacations` | `—` | store | staff token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts` |
| PATCH | `/staff/sites/{id}/staff/{id}/vacations/{id}` | `—` | update | staff token | — | **PARTIAL** | — |
| GET | `/staff/sites/{id}/support/contact` | `—` | contact | staff token | StaffSupportTest.php | **FULL** | `/apps/staff-miniapp/src/api/support.ts` |
| GET | `/staff/sites/{id}/support/video-help` | `—` | videoHelp | staff token | StaffSupportTest.php | **FULL** | `/apps/staff-miniapp/src/api/support.ts` |

## Member API 全表

| Method | Path | operationId | 用途 | 认证 | 测试 | 前端 | 调用位置/建议 |
|---|---|---|---|---|---|---|---|
| GET | `/member/booking/appointments` | `listMemberAppointments` | index | member token | MemberAppointmentTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/booking/appointments` | `createMemberAppointment` | store | member token | MemberAppointmentTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/booking/appointments/{id}/cancel` | `—` | cancel | member token | MemberAppointmentTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/booking/appointments/{id}/promote` | `—` | promote | member token | MemberAppointmentTest.php | **NONE** | — · **P0** 待接 |
| GET | `/member/booking/catalog` | `listMemberBookingCatalog` | index | member token | MemberBookingCatalogTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/booking/sessions/{id}` | `—` | show | member token | — | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/booking/sessions/{id}/payable-cards` | `—` | index | member token | — | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/card-products` | `listMemberCardProducts` | index | member token | — | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/pages/members/issue-card.vue` |
| POST | `/member/card-purchases` | `submitMemberCardPurchase` | submit | member token | — | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/card-transfers/{id}` | `—` | show | member token | MemberCardTransferTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/card-transfers/{id}/claim` | `—` | claim | member token | MemberCardTransferTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/home` | `getMemberHomeDashboard` | show | member token | MemberHomeDashboardTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/legal-documents` | `listCurrentMemberLegalDocuments` | legalDocuments | member token | — | **FULL** | `/apps/member-miniapp/src/api/member.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| GET | `/member/member-cards` | `listMemberWalletCards` | index | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/api/crm.ts` |
| GET | `/member/member-cards/hidden` | `listMemberHiddenCards` | hiddenIndex | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/member-cards/{id}` | `—` | show | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/member-cards/{id}/activate` | `—` | activate | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/member-cards/{id}/benefits` | `—` | benefits | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/member-cards/{id}/hide` | `—` | hide | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/member-cards/{id}/ledger-entries` | `—` | ledgerEntries | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/staff-miniapp/src/api/member-cards.ts`, `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/member-cards/{id}/restore-visibility` | `—` | restoreVisibility | member token | MemberMemberCardActivateTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/member-link-requests` | `listOwnMemberLinkRequests` | index | member token | MemberLinkingTest.php | **PARTIAL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| POST | `/member/member-link-requests/{id}/decision` | `—` | decide | member token | MemberLinkingTest.php | **FULL** | `/apps/staff-miniapp/src/api/crm.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| GET | `/member/memberships` | `—` | memberships | member token | — | **FULL** | `/apps/member-miniapp/src/composables/member-context.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| POST | `/member/memberships` | `joinMemberSite` | join | member token | — | **FULL** | `/apps/member-miniapp/src/composables/member-context.ts`, `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| GET | `/member/mine` | `getMemberMineDashboard` | show | member token | — | **FULL** | `/apps/member-miniapp/src/api/member.ts`, `/apps/member-miniapp/src/pages/cards/catalog.vue` |
| GET | `/member/notices` | `listMemberNotices` | index | member token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts`, `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/notices/{id}` | `—` | show | member token | — | **FULL** | `/apps/staff-miniapp/src/api/settings.ts`, `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/official-account-follow` | `getMemberOfficialAccountFollow` | show | member token | MemberOfficialAccountFollowTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/onboarding` | `getMemberOnboarding` | show | member token | MemberProfileTest.php | **FULL** | `/apps/member-miniapp/src/auth/navigation.ts`, `/apps/member-miniapp/src/pages/booking/index.vue` |
| GET | `/member/orders` | `listMemberOrders` | index | member token | — | **FULL** | `/apps/staff-miniapp/src/api/platform.ts`, `/apps/staff-miniapp/src/api/reports.ts` |
| GET | `/member/orders/{id}` | `—` | show | member token | — | **FULL** | `/apps/member-miniapp/src/api/member.ts`, `/apps/member-miniapp/src/pages/cards/catalog.vue` |
| GET | `/member/points/ledger` | `listMemberPointLedger` | index | member token | MemberPointLedgerTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/profile` | `getMemberTenantProfile` | readProfile | member token | MemberProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts`, `/apps/staff-miniapp/src/api/site-profile.ts` |
| PUT | `/member/profile` | `updateOwnMemberProfile` | update | member token | MemberProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts`, `/apps/staff-miniapp/src/api/site-profile.ts` |
| POST | `/member/profile/avatar` | `uploadMemberAvatar` | uploadAvatar | member token | MemberProfileTest.php | **FULL** | `/apps/staff-miniapp/src/api/profile.ts`, `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/profile/purchase-gate` | `getMemberPurchaseGate` | purchaseGate | member token | MemberProfileTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| PATCH | `/member/profile/ranking-opt-in` | `updateMemberRankingOptIn` | updateRankingOptIn | member token | MemberProfileTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| POST | `/member/profile/verify-mobile` | `verifyOwnWechatMobile` | verifyMobile | member token | MemberProfileTest.php | **FULL** | `/apps/member-miniapp/src/pages/onboarding/profile.vue` |
| GET | `/member/ranking/monthly` | `listMemberMonthlyRanking` | index | member token | MemberMonthlyRankingTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/sites` | `listJoinableMemberSites` | sites | member token | — | **FULL** | `/apps/staff-miniapp/src/api/card-products.ts`, `/apps/staff-miniapp/src/api/catalog.ts` |
| GET | `/member/sites/{id}/closure-status` | `—` | closureStatus | member token | MemberSitePublicTest.php | **NONE** | — · **P1** 待接 |
| GET | `/member/sites/{id}/public-detail` | `—` | show | member token | MemberSitePublicTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/stats/month` | `getMemberMonthStats` | month | member token | MemberStatsTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/stats/month/appointments` | `listMemberMonthAppointments` | monthAppointments | member token | MemberStatsTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |
| GET | `/member/stats/year` | `getMemberYearStats` | year | member token | MemberStatsTest.php | **FULL** | `/apps/member-miniapp/src/api/member.ts` |

## 未使用 API 清单（对抗式）

### Staff P0

- `POST /staff/sites/{id}/member-cards/{id}/transfer-share-token` (`—`) — StaffMemberCardAdjustTest.php
- `GET /staff/sites/{id}/members/{id}/orders` (`—`) — StaffMemberCardOrderTest.php
- `POST /staff/sites/{id}/members/{id}/point-adjustments` (`—`) — StaffMemberBookingHistoryTest.php
- `POST /staff/sites/{id}/orders/{id}/amount-corrections` (`—`) — StaffMemberCardOrderTest.php
- `POST /staff/sites/{id}/orders/{id}/void` (`—`) — StaffMemberCardOrderTest.php

### Staff P1

- `GET /staff/platform/subscription/agreement` (`getStaffPlatformSubscriptionAgreement`) — StaffPlatformSubscriptionTest.php
- `POST /staff/platform/subscription/pay` (`payStaffPlatformSubscription`) — StaffPlatformSubscriptionTest.php
- `GET /staff/platform/subscription/pricing` (`getStaffPlatformSubscriptionPricing`) — StaffPlatformSubscriptionTest.php
- `POST /staff/sites/{id}/orders/{id}/internal-notes` (`—`) — StaffMemberCardOrderTest.php
- `POST /staff/sites/{id}/schedule-export-image` (`—`) — —
- `POST /staff/sites/{id}/schedule-sessions/batch-change-course` (`—`) — StaffScheduleBatchTest.php
- `POST /staff/sites/{id}/schedule-sessions/batch-unsuspend` (`—`) — StaffScheduleBatchTest.php
- `POST /staff/sites/{id}/schedule-sessions/{id}/share-link` (`—`) — —
- `POST /staff/sites/{id}/schedule-sessions/{id}/unsuspend` (`—`) — StaffScheduleSessionTest.php

### Staff P2

- `GET /sites` (`listSites`) — MemberSitePublicTest.php
- `POST /sites` (`createSite`) — MemberSitePublicTest.php
- `GET /sites/{id}` (`—`) — MemberSitePublicTest.php
- `PUT /sites/{id}` (`—`) — MemberSitePublicTest.php
- `DELETE /sites/{id}` (`—`) — MemberSitePublicTest.php
- `GET /staff/constants/common-data` (`getStaffCommonData`) — —
- `POST /staff/member-tags` (`createMemberTag`) — —
- `GET /staff/sites/{id}/booking/upcoming` (`—`) — —
- `POST /staff/sites/{id}/card-products/export-jobs` (`—`) — StaffCardProductCatalogTest.php
- `GET /staff/sites/{id}/card-products/face-library` (`—`) — StaffCardProductCatalogTest.php
- `DELETE /staff/sites/{id}/card-products/{id}` (`—`) — StaffCardProductCatalogTest.php
- `GET /staff/sites/{id}/card-products/{id}/group-history` (`—`) — StaffCardProductCatalogTest.php
- `GET /staff/sites/{id}/course-tags` (`—`) — StaffCourseCatalogTest.php
- `PUT /staff/sites/{id}/course-tags` (`—`) — StaffCourseCatalogTest.php
- `DELETE /staff/sites/{id}/courses/{id}` (`—`) — StaffCourseCatalogTest.php
- `GET /staff/sites/{id}/courses/{id}/delete-preflight` (`—`) — StaffCourseCatalogTest.php
- `GET /staff/sites/{id}/crm/sales-staff` (`—`) — —
- `POST /staff/sites/{id}/ledger-reconciliation-jobs` (`—`) — —
- `GET /staff/sites/{id}/ledger-reconciliation-jobs` (`—`) — —
- `GET /staff/sites/{id}/member-card-reminders/expiring` (`—`) — StaffMemberCardReminderTest.php
- `GET /staff/sites/{id}/member-card-reminders/penalized` (`—`) — StaffMemberCardReminderTest.php
- `GET /staff/sites/{id}/member-card-reminders/pending-open` (`—`) — StaffMemberCardReminderTest.php
- `GET /staff/sites/{id}/member-card-reminders/zero-balance` (`—`) — StaffMemberCardReminderTest.php
- `POST /staff/sites/{id}/member-cards/batch-balance-adjustments` (`—`) — —
- `POST /staff/sites/{id}/member-cards/batch-freeze` (`—`) — —
- `POST /staff/sites/{id}/member-cards/batch-unfreeze` (`—`) — —
- `POST /staff/sites/{id}/member-cards/batch-validity-extensions` (`—`) — —
- `GET /staff/sites/{id}/member-cards/{id}/default-fee` (`—`) — StaffMemberCardAdjustTest.php
- `GET /staff/sites/{id}/member-cards/{id}/dynamic-fields` (`—`) — StaffMemberCardAdjustTest.php
- `GET /staff/sites/{id}/member-cards/{id}/freeze-ledger-last` (`—`) — StaffMemberCardAdjustTest.php
- `GET /staff/sites/{id}/member-cards/{id}/holiday-last` (`—`) — StaffMemberCardAdjustTest.php
- `PATCH /staff/sites/{id}/member-cards/{id}/opening-type` (`—`) — StaffMemberCardAdjustTest.php
- `PATCH /staff/sites/{id}/member-cards/{id}/remark` (`—`) — StaffMemberCardAdjustTest.php
- `DELETE /staff/sites/{id}/orders/{id}` (`—`) — StaffMemberCardOrderTest.php
- `GET /staff/sites/{id}/payroll/coach-reports/{id}` (`—`) — StaffPayrollReportTest.php
- `GET /staff/sites/{id}/payroll/course-commission` (`—`) — StaffPayrollReportTest.php
- `GET /staff/sites/{id}/payroll/sales-reports/{id}` (`—`) — StaffPayrollReportTest.php
- `GET /staff/sites/{id}/platform/subscription/status` (`—`) — StaffPlatformSubscriptionTest.php
- `GET /staff/sites/{id}/points-config` (`—`) — —
- `PUT /staff/sites/{id}/points-config` (`—`) — —
- `GET /staff/sites/{id}/reports/calendar/month-options` (`—`) — —
- `GET /staff/sites/{id}/reports/coaches/{id}/appointments` (`—`) — StaffReportCoachTest.php
- `GET /staff/sites/{id}/reports/rankings/sales-staff/{id}` (`—`) — StaffReportRankingTest.php
- `DELETE /staff/sites/{id}/rooms/{id}` (`—`) — StaffRoomTest.php
- `GET /staff/sites/{id}/schedule-recurring-template` (`—`) — —
- `GET /staff/sites/{id}/schedule-session-colors` (`—`) — —
- `PUT /staff/sites/{id}/schedule-session-colors` (`—`) — —
- `GET /staff/sites/{id}/schedule-sessions/change-course-preflight` (`—`) — —
- `GET /staff/sites/{id}/schedule-sessions/copy-preflight` (`—`) — —
- `POST /staff/sites/{id}/staff-directory/{id}/transfer-ownership` (`—`) — StaffDirectoryTest.php

### Member P0

- `POST /member/booking/appointments/{id}/promote` (`—`) — MemberAppointmentTest.php

### Member P1

- `GET /member/sites/{id}/closure-status` (`—`) — MemberSitePublicTest.php

### Member P2

_（无）_

## 部分对接 API

| API | 说明 |
|---|---|
| `GET /member/member-link-requests` | `decision` 在 onboarding 已用；列表未用 |
| `GET /staff/.../payment-marketing` | 只读展示；后端无 PUT |
| `PATCH closure-calendar/{id}` | 仅 create 已接 |
| `PATCH notices/{id}` | 仅 create + archive |
| `PATCH staff/.../vacations/{id}` | 仅 create |

## 故意无前端

- `GET /health` — 探活
- `GET /public/booking/share/sessions/{token}` — H5/外链分享（非小程序内）
- `GET /public/booking/warm-hint/sites/{site}` — 公开暖场文案
- `GET|POST|PUT|PATCH|DELETE /sites` — apiResource；小程序用 `chain/sites` + `staff/sites/{id}/profile`

## OpenAPI 契约一致性

- `OpenApiRouteContractTest`: **287/287**（100%）
- `route:list` 条目 **292** vs OpenAPI ops **287**（`GET|HEAD` 合并差异）

## 对抗式诚实差距

1. **订单运营（P0）**：`void` / `amount-corrections` / `internal-notes` / `GET members/{id}/orders` 有 PHPUnit，员工端 **NONE**
2. **转赠（P0）**：`transfer-share-token` 未接；会员 `claim` 已接
3. **候补（P0）**：会员 `appointments/{id}/promote` **NONE**
4. **平台订阅（P1）**：仅 `orders`；`pricing`/`agreement`/`pay` **NONE**
5. **课表进阶（P1）**：`unsuspend`/`batch-unsuspend`/`batch-change-course`/`share-link`/`export-image` **NONE**
6. **CRM 积分（P1）**：`point-adjustments` **NONE**
7. **会员闭馆（P1）**：`closure-status` **NONE**
8. **路由陷阱**：`catalog.ts` 教室 `archive` 为 POST（正确）；`DELETE rooms/{id}` 未接