# Stage 06: Booking and Scheduling

## Status

**STEP 1 IMPLEMENTED** (2026-07-11) — course catalog + rooms staff APIs, schema, seeders, tests.

**STEP 2 IMPLEMENTED** (2026-07-11) — schedule session CRUD staff APIs, schema, seeder, tests.

**STEP 3 IMPLEMENTED** (2026-07-11) — booking policy read/write staff APIs, schema, seeder, tests.

**STEP 4 IMPLEMENTED** (2026-07-11) — member booking catalog + staff daily board read APIs, tests.

**STEP 5 IMPLEMENTED** (2026-07-11) — appointment create/cancel (member + staff), payable cards, ledger deduct on confirm, tests.

**STEP 6 IMPLEMENTED** (2026-07-11) — waitlist list, staff promote, auto-promote on confirmed cancel, tests.

**STEP 7 IMPLEMENTED** (2026-07-11) — fulfillment: absent mark, staff notes, private reschedule, member appointment list, tests.

**STEP 8 IMPLEMENTED** (2026-07-11) — batch schedule tools: copy, batch suspend, batch cancel, tests.

**STEP 9 IMPLEMENTED** (2026-07-11) — share landings: signed public read + staff share-link generation, tests.

**STEP 10 IMPLEMENTED** (2026-07-11) — CRM/history reads: staff member booking history + coach upcoming, tests.

**STAGE 06 BACKEND CORE: COMPLETE** — Step 11 (reporting merges) deferred.

Prerequisites:

- Stage 05 member-card ledger core (payable card list, freeze debit guard).
- Stage 04 tenant/site scoping on every booking write.
- Course catalog and timetable are separate bounded contexts from appointment
  fulfillment but must share stable course/session IDs.

Classification script: `tools/classify-stage06-booking-scheduling-traceability.ps1`

### Step 1 implementation (2026-07-11)

| Item | Status |
|---|---|
| Migration `2026_07_11_000009_create_course_catalog_tables` | Done — `courses`, `rooms` |
| Staff course catalog CRUD + archive/restore | Done — `course-catalog.read`, `course-catalog.write` |
| Staff rooms list/create/update | Done — `site.rooms.read`, `site.rooms.write` |
| `CourseCatalogSeeder` (site 1) | Done |
| Feature tests | Done — `StaffCourseCatalogTest`, `StaffCourseCatalogWriteTest`, `StaffRoomTest` |
| OpenAPI | Done — `/staff/sites/{site}/courses`, `/rooms` |

### Step 2 implementation (2026-07-11)

| Item | Status |
|---|---|
| Migration `2026_07_11_000010_create_schedule_sessions_table` | Done — `schedule_sessions` |
| Staff schedule session list/create/read/update | Done — `schedule.session.read`, `schedule.session.write` |
| Suspend/cancel lifecycle (no physical delete) | Done |
| Room overlap conflict detection | Done |
| `ScheduleSessionSeeder` (site 1, next 7 days) | Done |
| Feature tests | Done — `StaffScheduleSessionTest` |
| OpenAPI | Done — `/staff/sites/{site}/schedule-sessions` |

### Step 3 implementation (2026-07-11)

| Item | Status |
|---|---|
| Migration `2026_07_11_000011_create_booking_policies_table` | Done — `booking_policies` (one row per site) |
| Staff booking policy GET/PUT/PATCH | Done — `booking.policy.read`, `booking.policy.write` |
| Defaults when no row exists | Done — GET returns version `0` + sensible defaults |
| `BookingPolicySeeder` (site 1) | Done — mirrors legacy appointSetting reasonable defaults |
| Feature tests | Done — `StaffBookingPolicyTest` |
| OpenAPI | Done — `/staff/sites/{site}/booking-policy` |

### Step 4 implementation (2026-07-11)

| Item | Status |
|---|---|
| Member day catalog GET | Done — `GET /member/booking/catalog` (`booking.catalog`) |
| Staff daily board GET | Done — `GET /staff/sites/{site}/booking/daily-board` (`booking.staff-daily-board.read` or `schedule.session.read`) |
| Member session detail prep GET | Done — `GET /member/booking/sessions/{session}` |
| Booking policy window enforcement | Done — `calendarDisplayDays`, `advanceBookingDays`, `waitlistEnabled`, `showBookedCount` |
| Permission seed | Done — `booking.staff-daily-board.read` |
| Feature tests | Done — `MemberBookingCatalogTest`, `StaffBookingDailyBoardTest` |
| OpenAPI | Done — `/member/booking/catalog`, `/member/booking/sessions/{session}`, `/staff/sites/{site}/booking/daily-board` |

### Step 5 implementation (2026-07-11)

| Item | Status |
|---|---|
| Migration `2026_07_11_000012_create_appointments_table` | Done — `appointments` (no physical delete) |
| Member payable cards GET | Done — `GET /member/booking/sessions/{session}/payable-cards` |
| Member book/cancel | Done — `POST /member/booking/appointments`, `POST /member/booking/appointments/{appointment}/cancel` |
| Staff book/cancel | Done — `POST /staff/sites/{site}/schedule-sessions/{session}/appointments`, `POST /staff/sites/{site}/appointments/{appointment}/cancel` |
| Ledger deduct on confirm | Done — `BookingEntitlementService` appends `count_deduct` / `balance_adjust` debit; reversal on cancel |
| Policy cutoffs | Done — `assertBookingAllowed`, `assertCancellationAllowed` |
| Waitlist when full | Done — `status=waitlisted`, no `booked_count` / ledger until promote (step 6) |
| Permissions | Done — `booking.appointment.create`, `booking.appointment.cancel` |
| Feature tests | Done — `MemberAppointmentTest` (10), `StaffAppointmentTest` (4) |
| OpenAPI | Done — appointment + payable-card paths/schemas |

### Step 6 implementation (2026-07-11)

| Item | Status |
|---|---|
| Staff waitlist GET | Done — `GET /staff/sites/{site}/schedule-sessions/{session}/waitlist` (`schedule.session.read`) |
| Staff promote POST | Done — `POST /staff/sites/{site}/appointments/{appointment}/promote` (`booking.waitlist.promote`) |
| Promote business rules | Done — capacity check, payable card re-validation, ledger deduct, `booked_count` increment |
| Idempotent promote | Done — retry when already confirmed returns same appointment; ledger dedup by `commandKey` |
| Waitlist cancel (member) | Done — no ledger refund, no `booked_count` change (verified) |
| Auto-promote on confirmed cancel | Done — first waitlisted by `booked_at` when seat opens and policy `waitlistEnabled` (AC-06-02); skips if card no longer payable |
| Permission seed | Done — `booking.waitlist.promote` |
| Feature tests | Done — `StaffWaitlistTest` (7), `MemberAppointmentTest` waitlist cancel (+1) |
| OpenAPI | Done — waitlist + promote paths/schemas |

Legacy note: old staff UI used manual `replaceFormLine` for promote; confirmed cancel did not auto-promote in the client. New system auto-promotes on confirmed cancel per AC-06-02.

### Step 7 implementation (2026-07-11)

| Item | Status |
|---|---|
| Migration `2026_07_11_000013_extend_appointments_for_fulfillment` | Done — `staff_notes`, `absent_marked_at`, `rescheduled_from_session_id`, `penalty_ledger_entry_id` |
| Migration `2026_07_11_000014_create_appointment_events_table` | Done — append-only audit (`staff_note_updated`, `absent_marked`, `rescheduled`) |
| Staff mark absent POST | Done — `POST /staff/sites/{site}/appointments/{appointment}/mark-absent` (`booking.fulfillment.absent`) |
| Absent penalty | Done when `absentPenaltyEnabled` — `penalty` ledger entry + freeze active card (card-product week/month thresholds deferred) |
| Staff notes PATCH | Done — `PATCH /staff/sites/{site}/appointments/{appointment}/staff-notes` (`booking.fulfillment.notes`); replaces current text; prior text in `appointment_events` |
| Private reschedule POST | Done — `POST /staff/sites/{site}/appointments/{appointment}/reschedule` (`booking.appointment.reschedule`); transfers booking, adjusts `booked_count`, no ledger re-deduct |
| Member appointment list GET | Done — `GET /member/booking/appointments?tenantId=&scope=upcoming|past` (staff notes excluded) |
| Feature tests | Done — `StaffFulfillmentTest` (9), `MemberAppointmentTest` list (+1) |
| OpenAPI | Done — fulfillment paths/schemas |

Deferred to step 11: reporting merges (`reporting.staff-*-appointments`, league pages).

### Step 8 implementation (2026-07-11)

| Item | Status |
|---|---|
| Migration `2026_07_11_000015_create_schedule_batch_commands_table` | Done — idempotent `commandKey` storage |
| Staff batch copy POST | Done — `POST /staff/sites/{site}/schedule-sessions/batch-copy` (`schedule.batch.copy`) |
| Staff batch suspend POST | Done — `POST /staff/sites/{site}/schedule-sessions/batch-suspend` (`schedule.batch.suspend`) |
| Staff batch cancel POST | Done — `POST /staff/sites/{site}/schedule-sessions/batch-cancel` (`schedule.batch.cancel`) |
| Copy modes | Done — `sourceSessionIds` + `dayOffset`/`targetFrom`, or `sourceFrom`/`sourceTo`/`targetFrom` date-range shift |
| Room conflict on copy | Done — all-or-nothing (matches legacy `copyPlan` code 333) |
| Batch suspend with bookings | Done — suspends anyway (matches legacy `batchStopPlan`; no booking guard) |
| Batch cancel guard | Done — skips sessions with any appointment history; partial `failed[]` reporting |
| Permission seed | Done — `schedule.batch.copy`, `schedule.batch.suspend`, `schedule.batch.cancel` |
| Feature tests | Done — `StaffScheduleBatchTest` (8) |
| OpenAPI | Done — batch-copy/suspend/cancel paths/schemas |

Legacy behavior notes:

| Legacy API / UI | New behavior |
|---|---|
| `copyPlan` / `checkcopyPlan` (`pagesCourse/index/components/copy-timetable`) | Date-range copy with room conflict fail-closed; `checkcopyPlan` preflight deferred to client (409 on conflict) |
| `batchStopPlan` (`management-schedule`) | `batch-suspend` by explicit `sessionIds[]`; suspends even when `booked_count > 0` |
| `batchDeleteByDate` (`clear-timetable`) | `batch-cancel` by `sessionIds[]`; legacy code 333 when bookings exist → per-session `SCHEDULE_BATCH_CANCEL_HAS_APPOINTMENTS` |
| `batchDeleteByCourseid` | Not ported in step 8 — use `batch-cancel` with session IDs from course list |

### Step 9 implementation (2026-07-11)

| Item | Status |
|---|---|
| Public share session GET | Done — `GET /public/booking/share/sessions/{token}` (no auth) |
| Staff share-link POST | Done — `POST /staff/sites/{site}/schedule-sessions/{session}/share-link` (`booking.share.create`) |
| HMAC signed token | Done — `{base64url(payload)}.{base64url(hmac)}`, 7-day TTL |
| No PII on public read | Done — marketing fields only; `shareNotice` documents sign ≠ auth |
| Red team | Done — share token / `sign` query do not authenticate member endpoints |
| Permission seed | Done — `booking.share.create` |
| Feature tests | Done — `PublicBookingShareTest` (7) |
| OpenAPI | Done — public share + share-link paths/schemas |

Token payload: `{t: tenantId, s: siteId, ss: sessionId, exp: unix}`. Legacy `sign` query must not become authorization.

### Step 10 implementation (2026-07-11)

| Item | Status |
|---|---|
| Staff member booking history GET | Done — `GET /staff/sites/{site}/members/{member}/booking-history?scope=upcoming\|past` (`booking.member-history.list`) |
| Staff coach upcoming GET | Done — `GET /staff/sites/{site}/booking/upcoming` (`booking.staff-upcoming.read`) |
| CRM fields | Done — includes session summary + staff notes (staff-only) |
| Member appointment list | Done (step 7) — `GET /member/booking/appointments` unchanged |
| Tenant/site isolation | Done — cross-tenant member and cross-site session return 404 |
| Permission seed | Done — `booking.member-history.list`, `booking.staff-upcoming.read` |
| Feature tests | Done — `StaffMemberBookingHistoryTest` (6) |
| OpenAPI | Done — booking-history + staff-upcoming paths/schemas |

### Step 11 deferred (reporting)

| Item | Status |
|---|---|
| `reporting.staff-*-appointments` merges | Deferred |
| League report pages (`pageReport/league/*`) | Deferred — APIs not in catalog |
| Payroll/course analyze shells | Deferred — out of booking scope |

## Scope and Boundaries

### In scope

1. **Course catalog** — group class and private coach templates, tags, rooms,
   archive/delete preflight when sessions exist.
2. **Timetable / schedule** — create/update/suspend/delete sessions, batch
   tools, copy/clear/export, display title/tags/colors.
3. **Booking** — member and staff create/cancel/reschedule, waitlist promote,
   group and private flows, payable card selection at checkout.
4. **Fulfillment** — absent/no-show marking, staff-only notes, roster buckets
   (confirmed / waitlist / cancelled / absent).
5. **Booking policy** — ahead booking windows, cancel rules, auto-cancel under
   minimum students, absent penalty toggles.
6. **Member surfaces** — 约课 tab, group/private detail, share landings,
   upcoming list and appointment detail.
7. **Staff surfaces** — 课程 tab day board, timetable editor subpackage,
   group/private detail pages, CRM appointment history (read).

### Out of scope (defer)

- Pure payroll/salary report shells (`findPrivateCourseList` + `ReComputeSalary`
  on `pageReport/courseAnalyze/*`).
- League statistics report pages (`pageReport/league/*`) — client calls
  `getTeamArrangeCount` / `getOnestaffInMonthDetailByCourseid` but neither
  export appears in `api-catalog.csv`; stay `UNREVIEWED` until endpoint mapped.
- Payment capture after booking — order/payment stage.
- Entitlement deduction posting — consumes Stage 05 ledger; booking stage only
  initiates idempotent deduct commands.
- Member QR (`PAGE-021`) — still `UNREVIEWED`.

### Explicit rejects carried forward

| Legacy | Rule |
|---|---|
| Hard delete sessions with active bookings | `deleteOnePlan` returns 500 + terminate modal when bookings exist |
| Silent overwrite of staff remarks | Append/correct notes with audit (`booking.staff-note.append`) |
| Share `sign` query as long-lived auth | Replace with signed, expiring, non-replayable share tokens |
| `getOnePlan_noToken` / `findOneDrainerDetail_noToken` as open data | Public read only for marketing fields; booking writes require session |

## Old Capabilities Inventory (Evidence)

### Member client (约课 tab — reviewed prior sessions; operations refined)

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pages/appointmentCourse/index` | `findAllPrivateDrainerList`, `findTeamPlan` | KEEP | `booking.catalog` |
| `pageCourse/coachCourse/index` | private detail + cancel + warm hint | KEEP | `booking.private-detail` |
| `pageCourse/coachCourse/share-index` | no-token warm hint + cancel | MERGE | `booking.private-detail` |
| `pageCourse/clusterCourse/index` | `getOnePlan`, book/cancel | KEEP | `booking.group-detail` |
| `pageCourse/clusterCourse/share-index` | no-token share landing | MERGE | `booking.group-detail` |
| `pageHome/appointmentDetails/index` | `selectOneAppoint` | KEEP | `booking.detail` |
| `pages/index/index` | `selectAppoint` upcoming | cross-ref | `booking.member.upcoming` |
| `pageMine/appointmentStatistics/index` | month stats APIs | member-insight | (not booking writes) |

### Staff client — tab and subpackage (reviewed this session)

| Page | Evidence path | Disposition | New capability |
|---|---|---|---|
| `pages/course/course` | Staff 课程 tab day board | KEEP | `booking.staff-daily-board` |
| `pagesCourse/index/index` | Timetable editor shell | KEEP | `schedule.editor` |
| `pagesCourse/index/kind-reminder` | `getPlanHint` / `savePlanHint` | MERGE | `schedule.copy-hint` |
| `pagesCourse/index/course-title` | `getPlanTitle` / `savePlanTitle` | MERGE | `schedule.display-title` |
| `pagesCourse/index/course-option` | `getArrangeTagData` / `saveArrangeTagData` | MERGE | `schedule.display-tags` |
| `pagesCourse/index/all-course` | `findallcourse` | KEEP | `schedule.course-catalog` |
| `pagesCourse/index/management-schedule` | batch suspend/delete/change | KEEP | `schedule.batch-by-course` |
| `pagesCourse/subject/subject-edit` | session edit/update | MERGE | `schedule.session-editor` |
| `pagesCourse/personalTrainerDetails/index` | private roster + fulfillment | KEEP | `booking.staff-private-detail` |
| `pagesCourse/leagueClassDetails/index` | group roster + waitlist + fulfillment | KEEP | `booking.staff-group-detail` |
| `pagesCourse/home/venue` | `getMySiteList` | MERGE | `site.context-selector` |
| `pagesImp/subject/subject` | course template lists | KEEP | `course-catalog.hub` |
| `pagesImp/subject/subject-edit` | `saveTeamCourse` | KEEP | `course-catalog.group.editor` |
| `pagesImp/subject/subject-personal-edit` | `savePrivateCourse` | KEEP | `course-catalog.private.editor` |
| `pageConfig/appointSetting/index` | `getAllAppointConfig` | KEEP | `booking.policy-config` |
| `pageConfig/appointment/index` | `createAppCode` only | MERGE | `sharing.staff-miniapp-code` |

### CRM read surfaces (reviewed Stages 03–04)

| Page | New capability |
|---|---|
| `pageMember/details/courseDetail` | `booking.history` |
| `pageMember/details/recordDetails` | `booking.detail` |

## Proposed New System Capability IDs

### Course catalog (`course-catalog.*`)

- `course-catalog.hub` — list group + private templates
- `course-catalog.group.{read,upsert,archive}` + delete preflight
- `course-catalog.private.{read,upsert,archive,reassign-coach}`
- `course-catalog.tags.{list,write}`

### Schedule (`schedule.*`)

- `schedule.calendar.list` / `schedule.session.{read,create,update,delete}`
- `schedule.session.{suspend,unsuspend,replace-coach,change-course,color}`
- `schedule.batch.{suspend,unsuspend,delete-by-date,delete-by-course,change-course}`
- `schedule.copy.{preflight,execute}` + `schedule.copy-hint.{read,write}`
- `schedule.display-{title,tags}.{read,write}`
- `schedule.export.image`
- `schedule.course-catalog.list` / `schedule.course-sessions.list`
- `schedule.{group-session,private-coach}.day-board`
- `schedule.private-coach.{detail,slots}`

### Booking (`booking.*`)

- `booking.catalog` — member 约课 tab
- `booking.{group-session,private-detail,group-session.detail,staff-daily-board}`
- `booking.staff-{group-detail,private-detail}`
- `booking.appointment.{create,cancel,reschedule}`
- `booking.waitlist.promote`
- `booking.fulfillment.absent`
- `booking.staff-note.append`
- `booking.member.{upcoming,detail}`
- `booking.member-history.{list,detail}`
- `booking.staff.upcoming`
- `booking.policy.{read,write,hint,hint.public}`
- `booking.policy-config` (staff UI)

### Cross-stage

- `member-card.payable.list` — checkout card picker (Stage 05)
- `site.room.{list,upsert}` — classrooms
- `site.context-selector` — venue switcher

## Acceptance Cases (P0)

| ID | Case | Pass criteria |
|---|---|---|
| AC-06-01 | Member group book | Authenticated member books open seat; seat count decreases; idempotent retry returns same appointment |
| AC-06-02 | Waitlist promote | When seat frees, `booking.waitlist.promote` moves earliest waitlist row atomically |
| AC-06-03 | Cancel window | Cancel rejected outside policy window from `booking.policy.read` |
| AC-06-04 | Staff book member | Staff with permission books on behalf of member; audit names operator |
| AC-06-05 | Absent mark | `booking.fulfillment.absent` transitions state; optional penalty obeys policy |
| AC-06-06 | Frozen card block | Payable list excludes frozen cards; create returns `FROZEN_CARD_DEBIT_BLOCKED` |
| AC-06-07 | Session suspend | Suspending session blocks new bookings; existing bookings flagged for compensation workflow |
| AC-06-08 | Delete guard | Deleting session with bookings fails closed with explicit error |
| AC-06-09 | Tenant scope | Cross-tenant session/appointment IDs return 404 |
| AC-06-10 | Share landing | Public share read works without token; write requires member session |
| AC-06-11 | Staff note | Notes are append-only; prior text preserved in audit |
| AC-06-12 | Timetable copy | `schedule.copy.execute` is idempotent per source/target range key |

## Red Team Concerns

1. **Legacy `sign` query on share paths** — cluster/private share-index pass `sign`
   in URL; must not become authorization. Treat as opaque share ticket with TTL.
2. **`unionStatusId` color semantics** — compiled UI maps 1/4/5 to green (paid?)
   and others red; do not persist color as business truth — derive from
   appointment + payment + entitlement state.
3. **`appointStatus` enum** — values 0–5 (已预约/已签到/取消/旷课/上课中/下课)
   need explicit state machine; reject illegal transitions.
4. **Waitlist vs cancel** — `clickStatus` 2 uses `cancelAppoint` on waitlist;
   new API must distinguish waitlist removal from confirmed cancel.
5. **Batch delete** — `batchDeleteByDate` / `batchDeleteByCourseid` are high
   blast-radius; require elevated permission + preview + audit bundle.
6. **Auto-cancel under min students** — `cancelOpenCourse_team` policy can cancel
   class automatically; requires notification hook and member compensation path.
7. **Absent penalty** — policy toggles `isPunish` per team/private; deduction
   must go through entitlement ledger, never direct count edit.
8. **`deleteOnePlan` 500** — terminate modal implies linked revenue; new system
   must surface booking/refund facts, not silent failure.
9. **Duplicate API exports** — `selectAllTeamCourse` on `/b/arrange` and
   `/b/course`; `findUserAppointList` on manageuser and report2 — single
   operation per capability in OpenAPI.
10. **League report ghost APIs** — `getTeamArrangeCount` used in UI but missing
    from catalog; do not implement reporting until endpoint evidence recovered.

## Top Contradictions Found

| # | Observation | Impact |
|---|---|---|
| C1 | `pageConfig/appointment/index` path suggests booking settings but only renders staff mini-program QR (`createAppCode`) | Mis-routing risk in new IA |
| C2 | Member APIs classified generically as `planned:booking` in prior pass | Blocked implementation granularity — refined this session |
| C3 | Staff `cancelAppoint` used for both confirmed cancel and waitlist removal (`clickStatus` 0 vs 2) | State machine must split commands |
| C4 | `pageReport/league/*` calls APIs not in `api-catalog.csv` | Cannot close UNREVIEWED without further artifact search |
| C5 | Stage 05 deferral says booking checkout uses payable list, but member `applyAppointment` already couples card pick + book in one UI step | Stage 06 must define transaction boundary: book hold vs ledger deduct |
| C6 | Private coach template uses `drainer`/`Drainer` naming vs `private coach` in audits | New domain language must map without losing traceability |

## Recommended Implementation Order

1. **Course catalog + rooms** — templates, tags, archive preflight (`course-catalog.*`, `site.room.*`).
2. **Schedule session CRUD** — single session lifecycle before batch/copy tools.
3. **Booking policy** — read/write config driving validation service.
4. **Day boards** — member `booking.catalog` + staff `booking.staff-daily-board` read APIs.
5. **Appointment create/cancel** — member + staff, with `member-card.payable.list` and idempotency.
6. **Waitlist + promote** — line list buckets from `findOnePlan`, `replaceFormLine`.
7. **Fulfillment** — absent, staff notes, reschedule private slots.
8. **Batch schedule tools** — copy, batch suspend/delete (elevated permissions). **Done (step 8).**
9. **Share landings** — public read + signed deep links. **Done (step 9).**
10. **CRM/history reads** — `booking.member-history.*`, staff upcoming. **Done (step 10).**
11. **Reporting merges** — defer `reporting.staff-*-appointments` and league pages. **Deferred.**

## Traceability Session Stats (2026-07-11)

```text
Pages reviewed this session: 16
APIs reviewed/refined this session: 80
Pages UNREVIEWED: 93  (was 109)
APIs UNREVIEWED: 90   (was 151)
pages SHA-256 3C6C7684531D7743D57C3718CBCCBE7B4F35FB3C1C81BF7AAABF39D028E0447A
apis  SHA-256 A08960D492B52C16D10C7FA8DBEC907A172D32A58835254C3BCBA4AA424A12D1
```

Remaining booking-adjacent `UNREVIEWED` pages: `pageReport/league/*`,
`pageReport/courseAnalyze/privateCourse*` (reporting/payroll domain).
