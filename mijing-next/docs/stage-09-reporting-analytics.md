# Stage 09: Reporting, Payroll, Analytics, and Platform Billing Archaeology

## Status

**ARCHAEOLOGY COMPLETE** (2026-07-11).

**STAGE 09 BACKEND COMPLETE** (2026-07-11) — all 11 steps implemented; full suite **415** tests.

**STEP 1 IMPLEMENTED** (2026-07-11) — `report.dashboard.summary` read API with month-scoped KPIs and 12-month profit trend.

**STEP 2 IMPLEMENTED** (2026-07-11) — `report.finance.profit.*` read APIs derived from paid orders + amount corrections.

**STEP 3 IMPLEMENTED** (2026-07-11) — `report.course.*` read APIs derived from schedule sessions + appointments.

**STEP 4 IMPLEMENTED** (2026-07-11) — `report.*.rank` read APIs for order spend, course attendance, points credits, and sales staff attribution.

**STEP 5 IMPLEMENTED** (2026-07-11) — `notification.reminder.*` read APIs for anniversary, no-class, birthday, visitor, and holiday-due lists.

**STEP 6 IMPLEMENTED** (2026-07-11) — `export.member.export`, `export.job.list` with audited async/sync export jobs.

**STEP 7 IMPLEMENTED** (2026-07-11) — `platform.subscription.*` read APIs for SaaS pricing, agreement, and site license status.

**STEP 8 IMPLEMENTED** (2026-07-11) — `org.chain.*` read APIs for chain site roster and cross-site report rollups.

**STEP 9 IMPLEMENTED** (2026-07-11) — `report.coach.*` read APIs for coach monthly rankings and per-coach appointment detail.

**STEP 10 IMPLEMENTED** (2026-07-11) — `payroll.coach.*.config`, `payroll.sales.config.*` write APIs for site-scoped payroll modes and per-coach rule matrices (no recompute jobs).

**STEP 11 IMPLEMENTED** (2026-07-11) — `payroll.coach.report`, `payroll.sales.report`, `payroll.coach.course-commission.report`, `payroll.recompute.job` read/write APIs with immutable snapshots and scoped async recompute jobs (separate from ledger recount).

Classification script: `tools/classify-stage09-reporting-analytics-traceability.ps1`

### Step 1 — `report.dashboard.summary` (implemented)

| Item | Status |
|---|---|
| Route | `GET /staff/sites/{site}/reports/dashboard-summary` |
| Permission | `report.dashboard.read` |
| Capability | `report.dashboard.summary` |
| Distinction from Stage 10 | Staff **home** dashboard (`/dashboard/summary`) is today-operational KPIs + feeds; **report** dashboard is month analytics + 12-month profit trend (maps legacy `pages/report/report` / `mainpage`, not `todayReport`) |
| KPIs | today/month revenue, card sales counts, appointment counts, member totals |
| Tests | `StaffReportDashboardTest` (3 cases). Full suite: **347** tests. |

### Step 2 — `report.finance.profit.*` (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/sites/{site}/reports/finance/profit-summary`, `profit-calendar?year=`, `profit-daily?year=&month=` |
| Permission | `report.finance.read` |
| Capabilities | `report.finance.profit.summary`, `.calendar`, `.daily` |
| Legacy mapping | `profitList` → summary; `profitMonthList` year scope → calendar; `profitMonthList` dayList → daily |
| Derivation | Paid `member_card_orders` (`status=paid`) bucketed by `created_at`; revenue = `effectiveAmount` (latest non-reversed `order_amount_corrections` entry, else `amount`); voided/pending excluded |
| New members | Members with `joined_at` in period who hold ≥1 `member_card` at the site (maps legacy 新增会员 definition) |
| Tests | `StaffReportFinanceProfitTest` (5 cases). Full suite: **352** tests. |

#### Profit derivation assumptions (Step 2)

1. **Revenue date** — Orders are attributed to the calendar day/month/year of `member_card_orders.created_at` (payment/sale timestamp), not correction `occurred_at`.
2. **Amount** — `MemberCardOrderService::effectiveAmount` is the single source of truth; reversals net out superseded corrections.
3. **Refunds** — No separate refund order type yet; negative adjustments flow through amount-correction ledger entries only.
4. **Card sales count** — One per paid order (online + manual issuance), matching legacy 售卡.
5. **Read-only** — No `computeAgain` / payroll recompute endpoints in this step; clients poll these projections directly.
6. **Daily rows** — Only days with card sales or new members are returned (sparse list, matching legacy dayList).

### Step 3 — `report.course.*` (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/sites/{site}/reports/courses/summary`, `calendar?year=`, `daily?year=&month=&courseKind=` |
| Permission | `report.course.read` |
| Capabilities | `report.course.summary`, `.monthly-calendar`, `.daily-breakdown` |
| Legacy mapping | `sumMainCourseList` → summary; `CourseMonthList` year scope → calendar; `CourseMonthList` dayList → daily |
| Derivation | `schedule_sessions` (non-cancelled, by `starts_at`) + `appointments` in `confirmed`/`completed`/`absent`; group vs private from `session_kind` |
| Metrics | `groupScheduledCount` (团课排课), `groupHeldCount` (团课成课), `groupSignInCount` (签到人次), `privateSessionCount` (私教) |
| Tests | `StaffReportCourseTest` (5 cases). Full suite: **357** tests. |

#### Course derivation assumptions (Step 3)

1. **Session date** — Sessions attributed to calendar day/month/year of `schedule_sessions.starts_at`.
2. **Scheduled group** — Non-cancelled group sessions count toward `groupScheduledCount`.
3. **Held group** — Session `status=completed` OR at least one `completed` appointment.
4. **Sign-ins** — Count of `completed` appointments on group sessions.
5. **Private delivered** — Private session `status=completed` OR has qualifying appointment (`confirmed`/`completed`/`absent`).
6. **courseKind filter** — `group`/`private`/`all` on daily endpoint; sparse day list (only days with activity for selected kind).
7. **Read-only** — No recompute endpoints; clients poll projections directly.

### Step 4 — `report.*.rank` (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/sites/{site}/reports/rankings/orders`, `course-attendance`, `points`, `sales-staff`, `sales-staff/{staff}` — all `?year=&month=&page=&perPage=` |
| Permission | `report.rankings.read` |
| Capabilities | `report.order.rank`, `report.course.attendance-rank`, `report.points.rank`, `report.sales.rank`, `report.sales.rank.detail` |
| Legacy mapping | `userOrderRank` → orders; `userCourseRank` → course-attendance; `findUserPointList` → points; `salerList` / `findUserDetailOfOneSaler` → sales-staff |
| Derivation | Paid orders by `created_at` + `effectiveAmount`; completed appointments by session `starts_at`; point ledger credits by `created_at`; sales attribution via `created_by_staff_id` |
| PII | Member names/avatars masked without `crm.member.read`; `memberNo` always returned |
| Points gate | `404` when tenant `points_enabled=false` |
| Tests | `StaffReportRankingTest` (8 cases). Full suite: **365** tests. |

#### Ranking derivation assumptions (Step 4)

1. **Period** — All rankings scoped to calendar month via `year` + `month` query params (maps legacy mode 3 / default month view).
2. **Order spend** — Paid `member_card_orders` only; revenue = `effectiveAmount`; sorted by spend desc, `memberId` asc tie-break.
3. **Course attendance** — `completed` appointments on sessions with `starts_at` in period (site-scoped).
4. **Points** — Sum of `credit` direction `point_ledger_entries` in period for site members; no `clearUserPoint`.
5. **Sales staff** — Orders attributed to `created_by_staff_id`; detail drill-down groups by member.
6. **Pagination** — Default `perPage=20`, max 50; rank numbers reflect global position not page offset alone.
7. **Read-only** — No mutation or recompute endpoints.

### Step 5 — `notification.reminder.*` (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/sites/{site}/reports/reminders/anniversary`, `no-class`, `birthdays`, `visitors`, `holiday-due` |
| Permission | `notification.reminder.read` |
| Capabilities | `notification.reminder.anniversary`, `.no-class`, `.birthday`, `.visitor`, `.holiday-due` |
| Legacy mapping | `findUserAnniversary`, `findNoclassUserList`, `findUserBirthday`, `findUserByVisitor`, `findHolidayCardList` |
| Tests | `StaffNotificationReminderTest` (8 cases). Full suite: **373** tests. |

### Step 6 — `export.member.export`, `export.job.list` (implemented)

| Item | Status |
|---|---|
| Routes | `POST /staff/sites/{site}/exports/members`, `GET /staff/sites/{site}/exports/jobs`, `GET /staff/sites/{site}/exports/jobs/{job}/download` |
| Permissions | `export.member.create`, `export.job.read` |
| Capabilities | `export.member.export`, `export.job.list`, `export.job.download` |
| Legacy mapping | `exportuser` → member export; `findExportLog` → job list |
| Schema | `export_jobs` — tenant, site, type, status, file_path, requested_by_staff_id, filters JSON, created_at, completed_at |
| Job lifecycle | `pending` → `processing` → `completed` \| `failed`; sync generation in-process when no queue worker |
| Audit | Append-only `audit_events` for `export.job.created`, `.completed`, `.failed`, `.downloaded`; completed job metadata cannot be deleted |
| PII | CSV respects CRM field policy visibility; mobile full only with `crm.member.read`; no ciphertext/hash/secrets in files |
| Download auth | Completed jobs only; creator or staff with `export.job.read` |
| Tests | `StaffExportJobTest` (7 cases). Full suite: **380** tests. |

### Step 7 — `platform.subscription.*` read APIs (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/platform/subscription/pricing`, `GET /staff/platform/subscription/agreement`, `GET /staff/sites/{site}/platform/subscription/status` |
| Permission | `platform.subscription.read` |
| Capabilities | `platform.subscription.pricing`, `platform.subscription.agreement`, `platform.subscription.site-status` |
| Legacy mapping | `pricelist` → pricing; `getAgreement` → agreement; `getSiteInfo` → site-status (`softwareExpire` overlay) |
| Schema | `platform_subscription_plans`, `platform_service_agreements`; tenant fields `subscription_plan`, `subscription_expires_at`, `subscription_status` |
| Separation | SaaS platform billing only — no `member_card_orders`, no jscode `findMyOrder` lookup |
| Tests | `StaffPlatformSubscriptionTest` (7 cases). Full suite: **387** tests. |

#### Platform billing read assumptions (Step 7)

1. **Pricing** — Global plan catalog; legacy `configId` maps to plan `id`; display prices as yuan strings for UI parity.
2. **Agreement** — Single `current` platform agreement version; HTML only, no tenant membership agreement mixing.
3. **Site status** — Tenant-scoped license; `softwareExpire.daynum` mirrors legacy expiry alert; support contact from platform agreement metadata.
4. **Read-only** — Pay, license-key activation, and order history deferred to later steps / Stage 05.
5. **Auth** — Staff session required; site-status additionally scoped to staff site membership.

### Step 8 — `org.chain.*` chain rollup reports (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/chain/sites`, `GET /staff/chain/reports/finance/summary?siteIds=`, `GET /staff/chain/reports/courses/summary?siteIds=`, `GET /staff/chain/reports/members/summary?siteIds=` |
| Permission | `org.chain.read` |
| Capabilities | `org.chain.sites.list`, `org.chain.reports.finance.summary`, `org.chain.reports.courses.summary`, `org.chain.reports.members.summary` |
| Legacy mapping | `getLinkSite` → sites list; linkage/report2 rollups merged into single cross-site queries |
| Finance/course rollup | Reuses `ReportFinanceProfitService` / `ReportCourseService` per site, sums year/month metrics |
| Member dedup | `totalMemberCount` and `monthNewMemberCount` count distinct `members.id` across selected sites; `bySite` shows per-site membership rows (may overlap) |
| Site scope | `siteIds` comma-separated; omit to default to staff-assigned sites; 404 when requesting unassigned site |
| Tests | `StaffChainReportTest` (8 cases). Full suite: **395** tests. |

#### Chain rollup assumptions (Step 8)

1. **Sites list** — All active tenant sites returned; `accessible` flag marks staff assignment (maps legacy linked-branch roster).
2. **Finance/course** — Per-site summaries summed; new-member counts are site-scoped sums (not deduped).
3. **Members** — Dedup by `members.id` within tenant for rollup totals; per-site breakdown remains site-scoped.
4. **Read-only** — No mutations; chain card-sales write paths remain in order/card stages.
5. **Auth** — `org.chain.read` at tenant role scope; each report site must be in staff `site_staff` roster.

### Step 9 — `report.coach.*` coach appointment reports (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/sites/{site}/reports/coaches/rankings?year=&month=&sortBy=`, `GET /staff/sites/{site}/reports/coaches/{staff}/appointments?year=&month=&sessionKind=&page=&perPage=` |
| Permission | `report.coach.read` |
| Capabilities | `report.coach.monthly-rank`, `report.coach.appointment-detail` |
| Legacy mapping | `staffByMonth` → rankings; `getOnestaffInMonthDetail` + `findPrivateAppontmentofStaffuserid` → appointment detail (unifies triplicate coach report pages) |
| Rankings | Coaches ranked by delivered sessions (`groupSessionCount` + `privateSessionCount`); `sortBy=total\|group\|private` maps legacy 按团课/按私教排序 |
| Detail lines | Appointment rows with session time, course, masked member, `status`, `signInState` (`booked`/`signed_in`/`cancelled`/`absent`) |
| Derivation | Reuses Stage 06 `schedule_sessions.coach_staff_id` + `appointments`; group held = session completed or any completed appointment; private delivered = session completed or qualifying appointment |
| PII | Member names masked without `crm.member.read`; `memberNo` always returned |
| Tests | `StaffReportCoachTest` (7 cases). Full suite: **402** tests. |

#### Coach report assumptions (Step 9)

1. **Period** — Calendar month via `year` + `month` on session `starts_at`.
2. **Rankings** — Only coaches with ≥1 delivered session in period; tie-break by secondary metric then `staffId`.
3. **Detail** — Includes `confirmed`, `completed`, `cancelled`, `absent` appointments; excludes `waitlisted`.
4. **sessionKind** — Optional `group`/`private`/`all` filter on detail endpoint (maps legacy 团课/私教 tabs).
5. **Tenant scope** — Coach path param must belong to viewer tenant; cross-tenant coach returns 404.
6. **Read-only** — No payroll recompute or salary config in this step.

### Step 10 — Payroll config + rules (implemented)

| Item | Status |
|---|---|
| Routes | `GET/PUT /staff/sites/{site}/payroll/coach-config`, `GET/PUT /staff/sites/{site}/payroll/coach-rules?staffId=`, `GET/PUT /staff/sites/{site}/payroll/sales-config`, `GET /staff/sites/{site}/payroll/coaches` |
| Permissions | `payroll.config.read`, `payroll.config.write` |
| Capabilities | `payroll.coach.config.mode`, `payroll.coach.config.list`, `payroll.coach.rule.*`, `payroll.sales.config.mode` |
| Legacy mapping | `getSalaryConfig`/`saveSalaryConfig` → coach-config; `SalaryStaffList` → coaches; `getSalaryConfigOfOneStaff`/`saveSalaryConfigOfOneStaff` → coach-rules; `getMemeberSalaryConfig`/`saveMemeberSalaryConfig` → sales-config |
| Schema | `payroll_coach_configs` (enabled + mode), `payroll_coach_rules` (matrix JSON + version per coach), `payroll_sales_configs` (enabled + mode + tier settings) |
| Coach modes | `fixed_hours`, `headcount`, `amount` (maps legacy modes 1–3) |
| Sales modes | `flat_rate`, `tiered` (maps legacy modes 1–2) |
| Rule matrix | Group/private course rows merged with active catalog; amounts in cents; optional headcount supplemental cents or amount-mode rate percent |
| Deferred | ~~`ReComputeSalary`, `sumSaleSalary`, payroll report reads, async recompute jobs → **Step 11**~~ **DONE Step 11** |
| Tests | `StaffPayrollConfigTest` (6 cases). Full suite: **408** tests. |

### Step 11 — Payroll reports + recompute jobs (implemented)

| Item | Status |
|---|---|
| Routes | `GET /staff/sites/{site}/payroll/coach-reports`, `coach-reports/{staff}`, `sales-reports`, `sales-reports/{staff}`, `course-commission`; `POST/GET /staff/sites/{site}/payroll/recompute-jobs` |
| Permissions | `payroll.report.read`, `payroll.recompute.execute` |
| Capabilities | `payroll.coach.report`, `payroll.coach.detail`, `payroll.sales.report`, `payroll.sales.detail`, `payroll.coach.course-commission.report`, `payroll.recompute.job`, `payroll.recompute.job.list` |
| Legacy mapping | `findWebstaffSalaryList` → coach-reports; `personalSalaryDetail` → coach-reports/{staff}; `findSaleManSalary` → sales-reports; `findStaffCourseWeb` → course-commission; `ReComputeSalary`/`sumSaleSalary` → recompute-jobs (scoped, idempotent; **NOT** `computeAgain`/ledger recount) |
| Schema | `payroll_report_snapshots` (append-only immutable rows per staff/site/month/type); `payroll_recompute_jobs` (status, scope, command_key, requested_by_staff_id) |
| Read model | Returns latest snapshot when present (`source=snapshot`); else on-the-fly projection from appointments + paid orders + coach/sales config (`source=computed`) |
| Job lifecycle | `pending` → `processing` → `completed` \| `failed`; sync in-process when no queue worker; idempotent via `(tenant_id, site_id, command_key)` |
| Scope | `site` (coach + sales), `coach` (optional staffId), `sales` (optional staffId); cross-site blocked by staff site assignment |
| Audit | `payroll.recompute.job.created`, `.completed`, `.failed` on `payroll_recompute_job` subject |
| C3 separation | Payroll recompute jobs are distinct from Stage 05 `ledger.reconciliation-job.create` / report-tab `computeAgain` |
| Tests | `StaffPayrollReportTest` (7 cases). Full suite: **415** tests. |

#### Payroll report assumptions (Step 11)

1. **Coach pay** — Derived from delivered sessions (Stage 06 facts) × per-coach rule matrix × site coach mode (`fixed_hours`, `headcount`, `amount`).
2. **Sales commission** — Derived from paid `member_card_orders` attributed to `created_by_staff_id` × sales config (flat or tiered); `metadata.saleCategory` distinguishes new vs renewal.
3. **Snapshots** — Append-only; no update/delete once written; recompute inserts new rows; reads use latest `computed_at`.
4. **Idempotency** — Recompute jobs dedupe on `commandKey`; no client-side daily counter anti-pattern.
5. **Auth** — Report reads require `payroll.report.read`; job create/list require `payroll.recompute.execute`; site scope enforced via `site_staff`.

#### Payroll config assumptions (Step 10)

1. **Site scope** — Coach and sales payroll modes are per-site; staff must be assigned to the site to configure rules.
2. **Coach list** — Returns all active site staff with `rulesConfigured` flag (maps legacy `SalaryStaffList`).
3. **Rule read** — Merges saved matrix with active course catalog so unset courses appear with `configured=false`.
4. **Rule write** — Increments `matrixVersion` on each save; only explicitly submitted course rows are stored.
5. **Sales tiers** — Tiered mode stores `fromAmountCents`/`toAmountCents`/`ratePercent` bands; flat mode stores percent fields only.
6. **No recompute** — Config changes do not trigger salary aggregation; separate authorized async jobs in Step 11 (C3 vs ledger recount).

#### Export job assumptions (Step 6)

1. **Filters** — Member export accepts same list filters as `GET /members` (`status`, `q`, `sumMode`, etc.) plus optional `columns` array.
2. **Sync path** — Jobs process synchronously in the request thread for now; schema supports future async workers.
3. **Immutability** — Completed `export_jobs` rows are append-only; physical CSV stored on local disk under `exports/{tenant}/{site}/{job}.csv`.
4. **Field policy** — Hidden CRM fields are omitted from default column set; explicit `columns` still gated by tenant visibility policy.
5. **Download audit** — Every download records `export.job.downloaded` with staff + job id.

Prerequisites:

- Stage 05 order/payment facts and member-card ledger (profit and consumption ranks drill to orders/ledger).
- Stage 06 booking attendance facts (course analytics and coach appointment lines).
- Stage 07 points ledger (points rank/config).
- Stage 08 CRM member lists (reminder pages open member detail).

## Scope and Boundaries

### In scope (this session)

1. **Report tab shell** (`pages/report/report`) — dashboard KPIs and recount gate.
2. **Finance reports** (`pageReport/income/*`) — profit summary, monthly calendar, daily detail.
3. **Course reports** (`pageReport/course/*`, `pageReport/courseAnalyze/*`) — rollups and ranks.
4. **Rankings** (`pageReport/rank/*` except already-classified card ranks) — order, course, points, sales, audit log.
5. **Member reminders** (`pageReport/remind/*` remaining) — anniversary, no-class, birthday, visitor, holiday.
6. **Member analytics** (`pageReport/memberAnalyze/allMember`).
7. **Coach statistics duplicates** (`pageReport/coach/*`, `pageChain/courseStatistics/*`).
8. **Payroll** (`pageReport/teacherMembership/*`, shared `head-hint` recompute banner).
9. **League statistics** (`pageReport/league/*`) — closed in Stage 10 as **REJECT** (orphan routes + ghost APIs).
10. **Platform SaaS billing** (`pageServer/*`, `/b/platform/*` except already-classified order pay).
11. **Chain reporting/org adjacency** (`pageChain/brand`, `instructions`, `configStaff`, `storesManagement`, `/b/linkage/*` UNREVIEWED).
12. **Export hub** (`pageConfig/shopReport`, `/b/export/exportuser`, `/b/export/findExportLog`).
13. **`/b/report2/*` UNREVIEWED** endpoints with static call-site evidence.

### Out of scope (remain UNREVIEWED or other stages)

| Item | Reason |
|---|---|
| `pageReport/coach/privateDetail`, `pageReport/coach/leagueDelete` | Missing registered routes; referenced from detailed pages — Stage 09 REJECT/MERGE |
| Main package shell, staff RBAC, site, tenant config | **Stage 10** — see `stage-10-identity-staff-tenant-cleanup.md` |

### Already classified (do not re-archaeologize)

| Legacy | Disposition | Capability | Stage ref |
|---|---|---|---|
| `pageReport/remind/cardExpires`, `cardWill`, `penalizeCard`, `findBalaceZeroCard` | REPLACE | `member-card.reminder.*` | 05 |
| `pageReport/remind/component/*Setting` | MERGE | `member-card.reminder.config` | 05 |
| `pageReport/rank/memberCard`, `memberConsumptionCardRank`, `memberCardAnalyze` | REPLACE | `report.card-product.*` | 05 |
| `pageServer/order` | MERGE | `platform.subscription.orders` | 05 |
| `pageChain/cardStatistics/*` | REPLACE/MERGE | `order.report.card-sales.*` | 05 |
| `pageChain/card/*` card-product editors | MERGE | `card-product.*` | 05 |
| `/b/report2/computeAgain` | REPLACE | `ledger.reconciliation-job.create` | 05 |
| `/b/report2/addUserPoint`, `substractUserPoint`, `PointListByUserId`, etc. | REPLACE/REJECT | `points.*` | 07 |
| `/b/export/exportcard` | MERGE | `card-product.export` | 05 |

## Ghost APIs (UI calls absent from catalog)

These appear only in compiled page JS and **cannot** be closed in the API matrix until backend contract is recovered or product confirms removal.

| Client export | Call site | Params observed | Disposition |
|---|---|---|---|
| `getTeamArrangeCount` | `pageReport/league/statistics.js:56` | `{ year, month }` | **REJECT** (Stage 10 AC-10-18) — ghost export; page orphan |
| `getOnestaffInMonthDetailByCourseid` | `pageReport/league/details.js:137` | `{ year, month, courseid }` | **REJECT** (Stage 10 AC-10-19) — ghost export; page orphan |
| `/pageReport/coach/privateDetail` route | `teacherMembership/detailed.js:249`, `coach/detailed.js:217` | navigation only | **UNREVIEWED** missing page — same data likely via `findPrivateAppontmentofStaffuserid` |

## Old Capabilities Inventory (Evidence)

### Report dashboard and finance

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `pages/report/report` | `mainpage`, `computeAgain` with daily local counter `storage_recount_report` | MERGE | `report.dashboard.summary` |
| `pageReport/income/businessReportForm` | `profitList` | REPLACE | `report.finance.profit.summary` |
| `pageReport/income/businessData` | `profitMonthList` → `dayList` | REPLACE | `report.finance.profit.calendar` + `report.finance.profit.daily` |
| `pageReport/income/businessDataDetail` | `profitDayList` | REPLACE | `report.finance.profit.daily-detail` *(Step 2b — line-item drill-down)* |

### Course reporting

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageReport/course/courseReportForm` | `sumMainCourseList` | REPLACE | `report.course.summary` |
| `pageReport/course/courseReportFormMonth` | `CourseMonthList` | REPLACE | `report.course.monthly-calendar` |
| `pageReport/course/courseReportFormDay` | `TeamCourseDayList` + `PriCourseDayList` | REPLACE | `report.course.daily-breakdown` |
| `pageReport/courseAnalyze/teamCourseRank` | `findCourseRepListForWeb` | REPLACE | `report.course.group.rank` |
| `pageReport/courseAnalyze/teamCourseDetailRank` | `findCourseRepListForWebDetail` | REPLACE | `report.course.group.detail` |
| `pageReport/courseAnalyze/privateCourseRank` | `findPrivateCourseList` | REPLACE | `report.course.private.rank` |
| `pageReport/courseAnalyze/privateCourseDetailRank` | `findPrivateCourseWebDetail` | REPLACE | `report.course.private.detail` |

### Rankings and member analytics

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageReport/rank/rankTop` | `userOrderRank` modes 1–4 date filters | REPLACE | `report.order.rank` |
| `pageReport/rank/courseRank` | `userCourseRank` | REPLACE | `report.course.attendance-rank` |
| `pageReport/rank/memberPointRank` | `findUserPointList` gated by `getUserPointConfig.start` | REPLACE | `report.points.rank` |
| `pageReport/rank/memberPointConfig` | `getUserPointConfig`, `saveUserPointConfig`, `clearUserPoint` UI | REPLACE | `points.config` |
| `pageReport/rank/membershipRank` | `salerList` | REPLACE | `report.sales.rank` |
| `pageReport/rank/membershipDetailRank` | `findUserDetailOfOneSaler` | REPLACE | `report.sales.detail` |
| `pageReport/rank/siteModifyLog` | `FindsiteModifyLog`, `getsiteModifyType`, `getAllStaff` filter | REPLACE | `report.audit.site-modify` |
| `pageReport/memberAnalyze/allMember` | `UserCardAnalyze` modes 8/9 holiday/stop | REPLACE | `report.member-card.analytics` |
| `pageReport/userCost/userCost` | `findUserCostForWeb` sortId paging | REPLACE | `report.member.cost` |

### Reminders (notification domain)

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageReport/remind/initiationDay` | `findUserAnniversary` | REPLACE | `notification.reminder.anniversary` |
| `pageReport/remind/missClassRemind` | `findNoclassUserList` | REPLACE | `notification.reminder.no-class` |
| `pageReport/remind/birthdayRemind` | `findUserBirthday` | REPLACE | `notification.reminder.birthday` |
| `pageReport/remind/visitorRemind` | `findUserByVisitor` | REPLACE | `notification.reminder.visitor` |
| `pageReport/remind/leavedDue` | `findHolidayCardList` | REPLACE | `member-card.reminder.holiday` |

### Coach statistics (triplicate surfaces)

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageReport/coach/classStatistics` | `staffByMonth` | MERGE | `report.coach.monthly-rank` |
| `pageChain/courseStatistics/index` | `staffByMonth` | MERGE | `report.coach.monthly-rank` |
| `pageReport/coach/detailed` | `getOnestaffInMonthDetail`, `findPrivateAppontmentofStaffuserid` | MERGE | `report.coach.appointment-detail` |
| `pageChain/courseStatistics/detailed` | same linkage APIs | MERGE | `report.coach.appointment-detail` |
| `pageReport/teacherMembership/detailed` | report2 variants + broken `privateDetail` nav | MERGE | `report.coach.appointment-detail` |

### Payroll (coach + sales)

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `personalSalaryType` | `getSalaryConfig` / `saveSalaryConfig` mode+started | REPLACE | `payroll.coach.config.mode` |
| `personalSalarySetting` | `SalaryStaffList` | REPLACE | `payroll.coach.config.list` |
| `personalSalaryFixed*` (3 pages) | `getSalaryConfigOfOneStaff` / `saveSalaryConfigOfOneStaff` plist/tlist | REPLACE | `payroll.coach.rule.*` |
| `personalSalary` | `findWebstaffSalaryList` | REPLACE | `payroll.coach.report` |
| `personalSalaryDetail` | `findStaffTeamAppointment`, `findStaffPrivateAppointment` | REPLACE | `payroll.coach.detail` |
| `memberSalaryType` | `getMemeberSalaryConfig` / `saveMemeberSalaryConfig` | REPLACE | `payroll.sales.config.mode` |
| `memberShipSalary` | `findSaleManSalary` | REPLACE | `payroll.sales.report` |
| `memberShipSalaryDetail` | `findSaleManSalary_user`, `findSaleManSalary_card` | REPLACE | `payroll.sales.detail` |
| `staffCourse` | `findStaffCourseWeb` | REPLACE | `payroll.coach.course-commission.report` |
| `head-hint` component | `ReComputeSalary`, `sumSaleSalary` confirm modals | MERGE | `payroll.recompute.banner` |

### Platform SaaS billing

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageServer/index` | navigation hub only | MERGE | `platform.subscription.hub` |
| `pageServer/serve-agreement` | `getAgreement` → HTML nodes | REPLACE | `platform.subscription.agreement` |
| `pageServer/videoHelp` | static `videoList` + channel feedIds | KEEP | `platform.help.videos` |
| `/b/platform/pricelist` | `pageServer/order.js` package picker | REPLACE | `platform.subscription.pricing` |
| `/b/platform/submitSecretkey` | offline key activation | REPLACE | `platform.subscription.license-key` |
| `/b/platform/getSiteInfo` | expiry alert + post-pay refresh | MERGE | `platform.subscription.site-status` |

### Export and chain org

| Page / API | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageConfig/shopReport` | `exportuser`, `exportcard`, `findExportLog` | MERGE | `export.job.hub` |
| `pageChain/brand/index` | `saveLinkInfo` unName/unLogo | REPLACE | `org.brand.upsert` |
| `pageChain/instructions` | static images only | KEEP | `org.chain.onboarding` |
| `pageChain/storesManagement` | `getLinkSite`, `deleteSiteBysiteOwner` | REPLACE | `org.chain.sites.list` (+ remove) |
| `pageChain/configStaff/*` | `getAllStaff`, `savestaff`, `changeStatus` | MERGE | `staff.directory` / `staff.upsert` |

## Proposed New System Capability IDs

### Reporting reads (`report.*`)

| Capability | Acceptance (AC-09-*) |
|---|---|
| `report.dashboard.summary` | AC-09-01: Staff report tab shows profit trend, reminder counts, card analytics timestamps from immutable facts |
| `report.finance.profit.summary` | AC-09-03: Monthly rolling profit list matches sum of paid order facts minus refunds |
| `report.finance.profit.calendar` | AC-09-02: Year month grid reconciles to daily breakdown totals |
| `report.finance.profit.daily` | AC-09-02: Month day rows reconcile to order facts |
| `report.finance.profit.daily-detail` | AC-09-04: Line items drill to order/payment IDs |
| `report.course.summary` | AC-09-05: Course rollup matches booking attendance counts |
| `report.course.monthly-calendar` | AC-09-06: Month grid matches per-day breakdown |
| `report.course.daily-breakdown` | AC-09-07: Team + private sections sum to session facts |
| `report.course.group.rank` / `.detail` | AC-09-21/22: Group class rank totals match booking sign-ins |
| `report.course.private.rank` / `.detail` | AC-09-23/24: Private rank matches private booking facts |
| `report.order.rank` | AC-09-14: Recharge rank modes (month/custom) match order totals |
| `report.course.attendance-rank` | AC-09-15: Attendance rank matches completed bookings per member |
| `report.points.rank` | AC-09-16: Rank uses ledger-derived balances, respects points policy start flag |
| `report.sales.rank` / `.detail` | AC-09-18/19: Sales rank attributes orders to assigned consultant |
| `report.audit.site-modify` | AC-09-20: Immutable audit entries with staff + type filters |
| `report.member-card.analytics` | AC-09-13: Cohort modes (inactive, holiday, stop) match card state facts |
| `report.member.cost` | AC-09-34: Cost sort orders members by consumption ledger |
| `report.coach.monthly-rank` | AC-09-36: Coach month list matches staff booking counts |
| `report.coach.appointment-detail` | AC-09-37: Line list matches per-coach month appointments |

### Payroll (`payroll.*`)

| Capability | Acceptance |
|---|---|
| `payroll.coach.config.mode` | AC-09-27: Tenant can enable/disable coach payroll and pick calculation mode |
| `payroll.coach.config.list` | AC-09-26: Lists coaches with configured rules |
| `payroll.coach.rule.fixed-hours/headcount/amount` | AC-09-29–31: Per-coach matrices persist and version |
| `payroll.coach.report` / `.detail` | AC-09-39/33: Computed payout matches rule engine output |
| `payroll.sales.config.mode` | AC-09-28: Sales commission mode stored with audit |
| `payroll.sales.report` / `.detail` | AC-09-38/32: Sales payout lines trace to attributed orders |
| `payroll.coach.course-commission.report` | AC-09-25: Course commission report ties to delivered sessions |
| `payroll.recompute.job` | AC-09-35: Async recompute is idempotent, scoped, authorized; no client daily counter |
| `payroll.sales.aggregate.job` | AC-09-35: Sales aggregate job same constraints |

### Notification reminders (`notification.reminder.*`)

| Capability | Acceptance |
|---|---|
| `notification.reminder.anniversary` | AC-09-08: Lists members hitting membership anniversary window |
| `notification.reminder.no-class` | AC-09-09: Lists members exceeding no-class threshold |
| `notification.reminder.birthday` | AC-09-11: Lists upcoming birthdays with CRM consent |
| `notification.reminder.visitor` | AC-09-12: Lists visitor follow-ups |
| `notification.reminder.holiday-due` | AC-09-10: Lists cards on holiday ending within threshold |

### Platform / export / org

| Capability | Acceptance |
|---|---|
| `platform.subscription.hub` | AC-09-40: Hub links to pricing, agreement, orders |
| `platform.subscription.agreement` | AC-09-41: Renders current platform agreement version |
| `platform.subscription.pricing` | AC-09-40: Package list drives renewal checkout |
| `platform.subscription.license-key` | AC-09-40: Key activation extends tenant subscription with audit |
| `platform.help.videos` | AC-09-42: Static help content; no PII |
| `export.job.hub` | AC-09-48: Export creates audited async job; log lists status/download |
| `org.brand.upsert` | AC-09-43: Chain brand name/logo saved per union |
| `org.chain.sites.list` | AC-09-47: Lists linked branches; remove requires owner + policy |
| `org.chain.reports.finance.summary` | AC-09-49: Cross-site profit rollup reconciles to per-site finance facts |
| `org.chain.reports.courses.summary` | AC-09-49: Cross-site course rollup reconciles to per-site session facts |
| `org.chain.reports.members.summary` | AC-09-49: Member totals dedupe by member id across selected sites |

## Adversarial Review Notes

1. **`ReComputeSalary` / `sumSaleSalary`**: Legacy UI triggers global recompute with only a success toast. New system must require explicit permission, scope (site/month/staff), idempotent job id, and dry-run preview.
2. **`computeAgain` on report tab**: Same pattern as card-consumption recount — already mapped to `ledger.reconciliation-job.create`; report tab must not bypass it.
3. **`clearUserPoint` on memberPointConfig**: Already **REJECT** (`API-157`); UI must not offer zeroing — only audited adjustments.
4. **`exportuser`**: Exports member PII columns selected in UI — requires field-level policy, rate limit, and download audit.
5. **`deleteSiteBysiteOwner`**: Chain branch removal must define fate of bookings, cards, and staff assignments — no silent cascade.
6. **Triplicate `getAllStaff`**: `/b/linkage`, `/b/report2`, `/b/staff` must collapse to one `staff.directory.list` with site/chain scope parameter.
7. **Duplicate coach report UIs**: `pageReport/coach/*` and `pageChain/courseStatistics/*` are compile-time forks — new system ships one component backed by same APIs.
8. **Missing `privateDetail` route**: Do not recreate dead route; fold into `report.coach.appointment-detail` with query params.

## Top Contradictions / Open Questions

| # | Contradiction | Evidence | Resolution path |
|---|---|---|---|
| C1 | League pages call APIs absent from catalog | `league/statistics.js`, `league/details.js` | **Closed Stage 10**: REJECT orphan pages; do not implement ghost APIs |
| C2 | `getAllStaff` ×3 with unclear scope differences | linkage, report2, staff vendor exports | MERGE; prove parity with integration tests per scope |
| C3 | Payroll recompute vs ledger recount | `computeAgain`, `ReComputeSalary`, `sumSaleSalary` | Separate job types; shared job framework from Stage 05 reconciliation |
| C4 | `memberPointConfig` clear vs immutable ledger | `clearUserPoint` toast "积分已清除" | REJECT clear; config page only adjusts policy + future accrual |
| C5 | Chain card statistics expose order mutations | `cardStatistics` uses `delUserOrder`/`saveOrderAmount` | Reporting stage read-only; mutations stay order stage with reject/merge rules |
| C6 | Platform `getSiteInfo` duplicates `/b/site/getSiteInfo` | expiredAlert uses both paths | MERGE into tenant site status with SaaS overlay fields |

## Recommended Implementation Order (Stage 09 build)

1. ~~**Report dashboard** — `report.dashboard.summary` + read models from orders/bookings/cards.~~ **DONE** — `GET /staff/sites/{site}/reports/dashboard-summary`; permission `report.dashboard.read`; tests `StaffReportDashboardTest` (3 cases). Full suite: **347** tests.
2. ~~**Finance profit reports** — `report.finance.profit.*` (depends Stage 05 payment facts).~~ **DONE** — summary/calendar/daily read APIs; permission `report.finance.read`; tests `StaffReportFinanceProfitTest` (5 cases). Full suite: **352** tests.
3. ~~**Course analytics reads** — `report.course.*` (depends Stage 06 attendance).~~ **DONE** — summary/calendar/daily read APIs; permission `report.course.read`; tests `StaffReportCourseTest` (5 cases). Full suite: **357** tests.
4. ~~**Rankings** — order/course/points/sales ranks (mostly read-only projections).~~ **DONE** — five ranking read APIs; permission `report.rankings.read`; tests `StaffReportRankingTest` (8 cases). Full suite: **365** tests.
5. ~~**Member reminders** — `notification.reminder.*` (may share Stage 10 notification delivery).~~ **DONE** — five read-only reminder list APIs under `/reports/reminders/*`; permission `notification.reminder.read`; tests `StaffNotificationReminderTest` (8 cases). Full suite: **373** tests.
6. ~~**Export jobs** — `export.member.export`, `export.job.list` with audit.~~ **DONE** — member export create + job list/download APIs; permissions `export.member.create`, `export.job.read`; tests `StaffExportJobTest` (7 cases). Full suite: **380** tests.
7. ~~**Platform billing reads** — pricing, agreement, site status (pay flow partially in Stage 05).~~ **DONE** — three read-only SaaS subscription APIs; permission `platform.subscription.read`; tests `StaffPlatformSubscriptionTest` (7 cases). Full suite: **387** tests.
8. ~~**Chain rollup reports** — merge linkage report2 duplicates into single queries.~~ **DONE** — four read-only chain APIs; permission `org.chain.read`; tests `StaffChainReportTest` (8 cases). Full suite: **395** tests.
9. ~~**Coach appointment detail** — unify triplicate pages before payroll.~~ **DONE** — two read-only coach report APIs; permission `report.coach.read`; tests `StaffReportCoachTest` (7 cases). Full suite: **402** tests.
10. ~~**Payroll config + rules** — `payroll.coach.*.config`, `payroll.sales.config.*`.~~ **DONE** — five config/rule APIs; permissions `payroll.config.read`/`payroll.config.write`; tests `StaffPayrollConfigTest` (6 cases). Full suite: **408** tests.
11. ~~**Payroll reports + recompute jobs** — `payroll.coach.report`, `payroll.sales.report`, `payroll.recompute.job`, `payroll.sales.aggregate.job`; highest risk; implement last with Stage 05 job framework. Explicitly excludes client `ReComputeSalary`/`sumSaleSalary` daily counters (C3).~~ **DONE** — seven report/job APIs; permissions `payroll.report.read`/`payroll.recompute.execute`; tests `StaffPayrollReportTest` (7 cases). Full suite: **415** tests. **STAGE 09 BACKEND COMPLETE.**

## Traceability Batch Stats

| Metric | Before | After (expected) |
|---|---:|---:|
| Pages reviewed this batch | — | 51 |
| APIs reviewed this batch | — | 58 |
| Pages UNREVIEWED | 92 | 41 |
| APIs UNREVIEWED | 90 | 32 |

Run script to materialize CSV updates and refresh SHA-256 hashes:

```powershell
pwsh -File .\tools\classify-stage09-reporting-analytics-traceability.ps1
```

## Explicit Rejects Carried Forward

| Legacy | Rule |
|---|---|
| `clearUserPoint` (API-157) | No zeroing points; reversing entries only |
| `delUserOrder` on reporting surfaces | No physical delete from report UI |
| Client-global `ReComputeSalary` without scope | Replace with authorized async job |
| `getTeamArrangeCount` / `getOnestaffInMonthDetailByCourseid` | Do not invent schema — remain UNREVIEWED until contract found |
