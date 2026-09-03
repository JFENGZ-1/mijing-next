# Stage 10: Identity Shell, Staff Directory, and Tenant Configuration Archaeology

## Status

**ARCHAEOLOGY COMPLETE** (2026-07-11) — no implementation this session.

**STEP 1 IMPLEMENTED** (2026-07-11) — `staff.dashboard.home` backend + staff miniapp home wiring (AC-10-02 partial: summary + sales-feed; appoint feed deferred).

**STEP 2 IMPLEMENTED** (2026-07-11) — AC-10-02 complete: appointment feed endpoint + staff miniapp section.

**STEP 3 IMPLEMENTED** (2026-07-11) — AC-10-09: staff self-profile read/update + logout wiring.

**STEP 4 IMPLEMENTED** (2026-07-11) — AC-10-10: site profile read/update + region constants + staff miniapp venue settings form.

**STEP 5 IMPLEMENTED** (2026-07-11) — AC-10-11 & AC-10-12: staff directory list/upsert, soft departure, ownership transfer, role list, permission catalog + staff miniapp pages.

**STEP 6 IMPLEMENTED** (2026-07-11) — AC-10-13 & AC-10-14: staff invite accept flow + custom role permission editor + staff miniapp pages.

**STEP 7 IMPLEMENTED** (2026-07-11) — AC-10-03 & AC-10-20: tenant settings hub + CRM field policy write + staff miniapp pages.

**STEP 8 IMPLEMENTED** (2026-07-11) — AC-10-23 through AC-10-29: member experience tenant config + staff miniapp editors.

**STEP 9 IMPLEMENTED** (2026-07-11) — AC-10-21/22, AC-10-24, AC-10-30 through AC-10-37: tenant operations config + staff miniapp editors.

**STAGE 10 BACKEND COMPLETE** (2026-07-11) — all classified tenant-config and notification ACs implemented; league orphan pages remain explicit REJECTs (AC-10-18/19).

Classification script: `tools/classify-stage10-identity-staff-tenant-traceability.ps1`

Prerequisites:

- Stage 01 identity/session foundations (`identity.staff.session.bootstrap`, `/me` guards).
- Stage 06 `site.context.list` adjacency for venue switcher.
- Stage 08 CRM field settings referenced from tenant config hub.
- Stage 09 reporting batch left league orphan pages and non-reporting shell rows UNREVIEWED.

## Scope

### In scope (this session)

1. **Staff app shell** — `pages/start`, `pages/home`, `pages/login`, `pages/shop`.
2. **Staff onboarding** — authorization info/phone flows (main + `pagesImp` duplicates).
3. **Staff self-profile** — `pagesImp/shop/setting/personal/*`.
4. **Staff directory & RBAC** — list, edit, invite, permission popup, `/b/staff/*`, `/b/staffuser/*` (except booking adjacency already classified).
5. **Site profile** — `store-setting`, `/b/site/*`.
6. **Tenant configuration hub** — all remaining `pageConfig/*` pages not covered in Stage 09 (`shopReport` was Stage 09).
7. **Platform identity helpers** — `/common/const`, `/wx/*` staff onboarding.
8. **League orphan pages** — close as REJECT with ghost-API evidence (carried from Stage 09).

### Out of scope (already classified)

| Item | Stage |
|---|---|
| `pageConfig/shopReport` | 09 export hub |
| `/b/staffuser/selectAppoint` | 06 `booking.staff.upcoming` |
| CRM, booking, card, order, reporting pages/APIs | 05–09 |

## Ghost APIs and Dead Exports

| Client export | Evidence | Disposition |
|---|---|---|
| `getTeamArrangeCount` | `pageReport/league/statistics.js:56`; absent from `docs/generated/api-catalog.csv` | Page **REJECT** AC-10-18 |
| `getOnestaffInMonthDetailByCourseid` | `pageReport/league/details.js:137`; absent from api-catalog | Page **REJECT** AC-10-19 |
| `/b/card/getAllCardForHasAgreement` | vendor export only; no call site | API **REJECT** AC-05-99 |
| `/b/staff/getOne` (`getUserOne`) | vendor export only; staff-edit uses URL `staffone` JSON | API **REJECT** AC-10-39 |
| `/b/site/mergeOpentime` | vendor export only; hours via `saveSiteInfo.openTimeList` | API **REJECT** AC-10-38 |
| `/common/dict` (`dictData`) | vendor export only in 管理端; `dictVal` from session bootstrap | API **REJECT** AC-10-42 |

## Classification Summary

### Pages (41 rows)

| Domain | KEEP | MERGE | REPLACE | REJECT |
|---|---:|---:|---:|---:|
| identity | 0 | 12 | 8 | 1 |
| tenant-config | 0 | 8 | 12 | 0 |
| notification | 0 | 1 | 2 | 0 |
| reporting | 0 | 0 | 0 | 2 |
| **Total** | **0** | **21** | **18** | **2** |

Plus `pagesImp/QRcode/QRcode` REJECT (static asset page).

### APIs (32 rows)

| Domain | MERGE | REPLACE | REJECT |
|---|---:|---:|---:|
| identity | 4 | 20 | 1 |
| tenant-config | 0 | 3 | 1 |
| member-card | 0 | 0 | 1 |
| platform | 0 | 1 | 1 |
| **Total** | **4** | **24** | **4** |

## Acceptance Cases (new)

| ID | Capability | Verify |
|---|---|---|
| AC-10-01 | `identity.deeplink.router` | Scene `go=1/2/3/4` routes to class, PT, platform, or home after session bootstrap |
| AC-10-02 | `staff.dashboard.home` | Home shows todayReport KPIs; saleCard/appoint feeds respect permissions |
| AC-10-03 | `tenant.settings.hub` | Shop tab lists pageConfig tools gated by `shop_basics` |
| AC-10-04 | `identity.staff.login` | Login exchanges code, bootstraps session, honors redirect |
| AC-10-09 | `staff.profile.self` | Staff reads/updates own profile; logout revokes session |
| AC-10-10 | `site.profile.form` | Staff reads/updates site profile (name, address, phone, hours, logo, region) |
| AC-10-11 | `staff.directory.list` | Owner sees staff list; invite deep link opens accept flow |
| AC-10-12 | `staff.directory.upsert` | Create/edit/depart/delete staff with booking preflight |
| AC-10-13 | `staff.invite.accept` | Invite sign → union/phone → accept → site context |
| AC-10-14 | `staff.role.editor` | Custom role saves permission matrix |
| AC-10-20 | `tenant.crm.field-config` | Toggle required CRM lead fields |
| AC-10-23 | `tenant.member.onboarding-help` | How-to-book poster/step image URLs persist |
| AC-10-25 | `tenant.member.warm-hint` | List private/group warm hints |
| AC-10-26/27 | `tenant.member.home-carousel` | Member home carousel CRUD with sort order |
| AC-10-28 | `tenant.member.warm-hint` | Save/clear warm hint per course type |
| AC-10-29 | `tenant.member.miniapp-layout` | Hide/show member mini-app modules persists |
| AC-10-21 | `tenant.legal.membership-agreement` | Read membership agreement rich text |
| AC-10-22 | `tenant.legal.membership-agreement` | Edit membership agreement rich text |
| AC-10-24 | `tenant.payment.marketing` | Static payment-setting marketing hub (no legacy API) |
| AC-10-30 | `tenant.site.closure-calendar` | List site holiday closures |
| AC-10-34 | `tenant.site.closure-calendar` | Create/edit closure windows |
| AC-10-31 | `tenant.staff.vacation.list` | Coach vacation rollup |
| AC-10-32 | `tenant.staff.vacation.detail` | Per-coach vacation lines |
| AC-10-33 | `tenant.staff.vacation.write` | Create/edit coach vacation |
| AC-10-35 | `notification.channel.config` | Toggle hint channels and manager recipients |
| AC-10-36 | `notice.announcement.list` | Staff paginated site announcement list |
| AC-10-37 | `notice.announcement.editor` | Create/edit/archive announcement |
| AC-10-18/19 | League pages | **REJECT** — do not implement orphan ghost-API reports |

## Explicit Rejects

| Legacy | Rule |
|---|---|
| `pageReport/league/*` | Orphan routes + ghost APIs; use `report.course.group.*` instead |
| `pagesImp/QRcode/QRcode` | Static follow QR; not a product capability |
| `getAllCardForHasAgreement` | No call site |
| `getUserOne`, `mergeOpentime`, `dictData` (管理端) | Dead vendor exports |

## Traceability Batch Stats

| Metric | Before | After |
|---|---:|---:|
| Pages reviewed this batch | — | 41 |
| APIs reviewed this batch | — | 32 |
| Pages UNREVIEWED | 41 | **0** |
| APIs UNREVIEWED | 32 | **0** |

```powershell
pwsh -File .\tools\classify-stage10-identity-staff-tenant-traceability.ps1
```

## Step 1 Implementation Notes (staff dashboard home)

| Route | Permission | Capability |
|---|---|---|
| `GET /staff/sites/{site}/dashboard/summary` | `staff.dashboard.read` or `crm.member.read` | `staff.dashboard.summary` |
| `GET /staff/sites/{site}/dashboard/sales-feed` | `staff.dashboard.read` or `order.read` | `staff.dashboard.sales-feed` |
| `GET /staff/sites/{site}/dashboard/appointment-feed` | `staff.dashboard.read` | `staff.dashboard.appointment-feed` |

Summary KPIs mirror legacy `todayReport.report`: `todayRevenue` (SaleMoney), `groupAppointmentCount` (ManCountTeam), `privateAppointmentCount` (ManCountPrivate), `saleCardCount` (SaleCardCount), `newMemberCount` (TodayUserCount), plus `greeting` (hellomsg). SaaS `softwareExpire` deferred to tenant subscription work.

Sales feed mirrors legacy `saleCard` / `selectPayList`: today's paid card orders with member name, card name, amount, new-member flag, remark.

Appointment feed mirrors legacy `appointRecord` / `selectAppoint`: today's confirmed appointments ordered by session start; excludes cancelled and waitlisted; member name masked without `crm.member.read`.

Staff miniapp `pages/index/index` wired to summary, sales-feed, and appointment-feed with permission gating and pull-to-refresh.

Tests: `StaffDashboardTest` (13 cases). Full suite: **293** tests.

## Step 3 Implementation Notes (staff self-profile)

| Route | Capability | Legacy |
|---|---|---|
| `GET /staff/profile` | `staff.profile.self.read` | `/b/staffuser/getMyInfo` |
| `PATCH /staff/profile` | `staff.profile.self.update` | `/b/staffuser/updateMyInfo` (displayName) |
| `POST /staff/profile/avatar` | `staff.profile.self.update` | avatar upload + `updateMyInfo` staffFace |
| `POST /auth/logout` | (Stage 01) | `loginout` — reuses existing token revoke |

Self endpoint always resolves from token `staff_context`; no staff id parameter — cannot read other staff.

Staff miniapp `pages/settings/personal/index` wired to profile read/update, avatar upload, and logout. Home settings icon navigates to personal page.

Tests: `StaffProfileTest` (6 cases). Full suite: **299** tests.

## Step 4 Implementation Notes (site profile form)

| Route | Permission | Capability | Legacy |
|---|---|---|---|
| `GET /staff/sites/{site}/profile` | `site.profile.read` | `site.profile.read` | `/b/site/getSiteInfo` |
| `PATCH /staff/sites/{site}/profile` | `site.profile.write` | `site.profile.update` | `/b/site/saveSiteInfo` |
| `GET /staff/constants/regions` | (staff session) | `platform.constants.read` | `/common/const` getConst |

Profile fields: `name`, `phone`, `address`, `logoUrl`, `description`, `region` (province/city/county), `businessHours` (weekDays + timeValue), `longitude`, `latitude`, `timezone`, `version`.

Staff miniapp `pages/settings/site/index` wired to profile read/update. Home venue quick action and site name link navigate when `site.profile.read` is granted; otherwise venue switcher (`pages/sites/index`).

Tests: `StaffSiteProfileTest` (8 cases). Full suite: **307** tests.

## Step 5 Implementation Notes (staff directory list + upsert)

| Route | Permission | Capability | Legacy |
|---|---|---|---|
| `GET /staff/sites/{site}/staff-directory` | `staff.directory.read` | `staff.directory.list` | `/b/staff/getAll` getAllStaff |
| `GET /staff/sites/{site}/staff-directory/{staff}` | `staff.directory.read` | `staff.directory.list` | URL `staffone` JSON (getOne rejected) |
| `POST /staff/sites/{site}/staff-directory` | `staff.directory.write` | `staff.directory.upsert` | `/b/staff/savestaff` create |
| `PATCH /staff/sites/{site}/staff-directory/{staff}` | `staff.directory.write` | `staff.directory.upsert` | `/b/staff/savestaff` update |
| `POST .../departure` | `staff.departure.soft` | `staff.departure.soft` | `/b/staff/leaveWork` |
| `POST .../transfer-ownership` | `staff.directory.transfer-ownership` | `staff.directory.transfer-ownership` | `/b/staff/changeSiteOwner` |
| `GET /staff/roles` | `staff.directory.read` | `staff.role.list` | `/b/staff/findRoleList` |
| `GET /staff/permission-catalog` | `staff.directory.write` | `staff.permission-catalog.read` | `/b/staff/findAllFunction` prep |

Directory fields: `displayName`, `mobile`, `gender`, `capabilities` (coach/sales), site-scoped `role`, `isSiteOwner`, `hasWechatBinding`, `hasFutureBookings` preflight. Soft departure only — legacy `delstaff` hard delete not ported.

Stage 01 overlap: reuses `Staff`, `Role`, `Permission`, `role_staff`, `site_staff` models; new permissions `staff.directory.*` complement legacy `access.staff.read/manage` seeds.

Staff miniapp `pages/settings/staff/index` (list) and `pages/settings/staff/edit` (create/edit/depart) wired; home「员工」quick action navigates when `staff.directory.read` granted.

Tests: `StaffDirectoryTest` (9 cases). Full suite: **316** tests.

## Step 6 Implementation Notes (staff invite accept + role editor)

| Route | Permission | Capability | Legacy |
|---|---|---|---|
| `GET /staff/invites/{sign}` | (none — opaque sign, not auth) | `staff.invite.preview` | `/b/staff/getInviteData` + `getUserInfoBySign` |
| `POST /staff/invites/{sign}/accept` | (account session) | `staff.invite.accept` | `/b/staff/acceptInvite` |
| `POST /identity/wechat/unionid` | (account session) | `identity.wechat.unionid.resolve` | `/wx/getUnionId` |
| `POST /staff/roles` | `staff.directory.write` | `staff.role.upsert` | `/b/staff/saveRole` |
| `GET /staff/roles/{role}` | `staff.directory.read` | `staff.role.detail` | role detail with permissions |

Invite sign is HMAC-signed opaque token (7-day TTL); validates expiry but does not authenticate. Red-team: sign query/bearer must not unlock staff endpoints. Invite bootstrap login (`inviteSign` on `/auth/wechat/login`) binds WeChat to pre-created staff account before accept.

Staff directory detail includes `inviteSign` when `hasWechatBinding` is false.

Staff miniapp `pages/invite/accept` — deep link landing (`?sign=`) with wx.login preview + getPhoneNumber accept flow. `pages/settings/roles/edit` — permission checkbox matrix from permission-catalog.

Tests: `StaffInviteTest` (4 cases), `StaffRoleEditorTest` (3 cases). Full suite: **323** tests.

## Step 7 Implementation Notes (tenant settings hub + CRM field config)

| Route | Permission | Capability | Legacy |
|---|---|---|---|
| `GET /staff/sites/{site}/settings-hub` | `tenant.settings.read` | `tenant.settings.hub` | `pages/shop/shop.js` + `getSiteConfig` feature flags |
| `GET /staff/sites/{site}/crm/member-field-policy` | `crm.member.read` | `crm.member-field-policy.get` | `/b/setting/getuserFieldSetting` (Stage 08) |
| `PUT /staff/sites/{site}/crm/member-field-policy` | `tenant.crm.field-config.write` | `tenant.crm.field-config` | `/b/setting/saveuserFieldSetting` |

Settings hub returns categorized sections mirroring legacy shop tab: onboarding, basics, defaults, member-experience, operations, chain, support. Section visibility maps `permissionInfo.shop_*` flags to `tenant.settings.*` sub-permissions. Items include `implemented` + `route` for staff miniapp navigation; setup counts drive onboarding checklist.

Field policy write reuses `MemberCrmFieldPolicyService` + `tenants.crm_field_policy` JSON from Stage 08. GET remains on `crm.member.read` for member form consumption; PUT is admin-only via `tenant.crm.field-config.write`. Locked rules: `name` always required/visible; `mobile` required when visible.

Stage 08 overlap: same storage + read endpoint; AC-10-20 adds tenant-admin write path and `pageConfig/membership` UI replacement without changing upsert validation semantics.

Staff miniapp `pages/settings/hub/index` wired from home「场馆」quick action; `pages/settings/crm/field-config/index` for field toggles.

Tests: `TenantSettingsHubTest` (3 cases), `StaffCrmFieldPolicyTest` (+3 cases). Full suite: **329** tests.

## Step 8 Implementation Notes (member experience tenant config)

| Route | Permission | Capability | Legacy |
|---|---|---|---|
| `GET /staff/sites/{site}/member-warm-hint` | `tenant.member-experience.read` | `tenant.member.warm-hint` | `/b/setting/getwarmHint` |
| `PUT /staff/sites/{site}/member-warm-hint` | `tenant.member-experience.write` | `tenant.member.warm-hint` | `/b/setting/saveWarmHint` |
| `GET /staff/sites/{site}/member-carousel` | `tenant.member-experience.read` | `tenant.member.home-carousel` | `/b/setting/getsavefaceimage` |
| `PUT /staff/sites/{site}/member-carousel` | `tenant.member-experience.write` | `tenant.member.home-carousel` | `/b/setting/savefaceimage` |
| `GET /staff/sites/{site}/member-miniapp-layout` | `tenant.member-experience.read` | `tenant.member.miniapp-layout` | `/b/setting/getClientConfig` |
| `PUT /staff/sites/{site}/member-miniapp-layout` | `tenant.member-experience.write` | `tenant.member.miniapp-layout` | `/b/setting/saveClientConfig` |
| `GET /staff/sites/{site}/member-onboarding-help` | `tenant.member-experience.read` | `tenant.member.onboarding-help` | static `memberConfigCourse` assets |
| `PUT /staff/sites/{site}/member-onboarding-help` | `tenant.member-experience.write` | `tenant.member.onboarding-help` | (new — legacy had no API) |

**Reuse (Stage 07):** Carousel read/write uses existing `site_carousel_items` table; member home (`GET /member/home`) already serves `carousel.items` from the same table. `showMonthRank` layout toggle syncs to `tenants.show_month_rank` for backward compatibility with Stage 07 mine dashboard.

**Storage:** `sites.member_warm_hints` JSON (course types 6/7), `sites.member_miniapp_layout` JSON, `sites.member_onboarding_help` JSON, `sites.carousel_default_image_url` for empty-carousel fallback.

Staff miniapp routes: `pages/settings/member/warm-hint`, `carousel`, `miniapp-layout`, `onboarding-help` — wired from settings hub member-experience section.

Tests: `StaffMemberExperienceTest` (7 cases). Full suite: **336** tests.

## Step 9 Implementation Notes (tenant operations config)

| Route | Permission | Capability | Legacy |
|---|---|---|---|
| `GET /staff/sites/{site}/legal/membership-agreement` | `tenant.legal.membership-agreement.read` | `tenant.legal.membership-agreement` | `/b/setting/getuserProtocolSetting` |
| `PUT /staff/sites/{site}/legal/membership-agreement` | `tenant.legal.membership-agreement.write` | `tenant.legal.membership-agreement` | `/b/setting/saveuserPtotocolSetting` |
| `GET /staff/sites/{site}/payment-marketing` | `tenant.settings.read` | `tenant.payment.marketing` | static `pageConfig/paySetting` |
| `GET /staff/sites/{site}/notification-channels` | `notification.channel.config.read` | `notification.channel.config` | `/b/setting/getHintSetting` |
| `PUT /staff/sites/{site}/notification-channels` | `notification.channel.config.write` | `notification.channel.config` | `/b/setting/saveHintSetting` |
| `GET /staff/sites/{site}/closure-calendar` | `tenant.site.closure-calendar.read` | `tenant.site.closure-calendar` | `/b/stopbusiness/findStopbusinessofSite` |
| `POST/PATCH /staff/sites/{site}/closure-calendar` | `tenant.site.closure-calendar.write` | `tenant.site.closure-calendar` | stopbusiness save/deletes |
| `GET /staff/sites/{site}/staff-vacations` | `tenant.staff.vacation.read` | `tenant.staff.vacation.list` | `/b/vacation/getMainHolidayList` |
| `GET/POST/PATCH .../staff/{staff}/vacations` | `tenant.staff.vacation.*` | `tenant.staff.vacation.*` | getHolidayOfOneStaff / saveVacation |
| `GET/POST/PATCH /staff/sites/{site}/notices` | `notice.announcement.*` | `notice.announcement.*` | `/b/notice/getNoticeList` / save / deletes |

**Storage:** `tenants.membership_agreement_html`, `sites.notification_channel_config` JSON, `site_closure_periods`, `staff_vacations`, `site_notices.display_days` (reuses Stage 07 `site_notices` for member feed).

Staff miniapp routes: `pages/settings/legal/membership-agreement`, `defaults/notification-channels`, `defaults/payment-marketing`, `operations/closure-calendar`, `operations/staff-vacations`, `operations/notices` — wired from settings hub.

Tests: `StaffTenantOperationsTest` (8 cases). Full suite: **344** tests.

## Cross-Stage Notes

1. **Triplicate `getAllStaff`**: Stage 09 noted `/b/linkage`, `/b/report2`, `/b/staff` — all map to `staff.directory.list` with scope parameter.
2. **`getSiteInfo` duplication**: Stage 09 C6 — merge SaaS overlay reads into `site.profile.read`.
3. **Staff onboarding duplicates**: Main package and `pagesImp/authorization/*` are compile-time forks — ship one onboarding flow.
