# Stage 08: Staff CRM Operations Archaeology

## Status

**STEP 1 IMPLEMENTED** (2026-07-11) — `crm.member.dashboard.summary` and extended
`crm.member.list` filters (search, pinyin, visitor toggle, sumMode, tag/flag).

**STEPS 2–3 IMPLEMENTED** (2026-07-11) — `crm.member-field-policy.get` with upsert
validation; `crm.member.filter` preset metadata; `runOff` churn filter on list.

**STEPS 4–7 IMPLEMENTED** (2026-07-11) — batch import, sales staff list, sticky
remark update, deleted archive list + restore.

Archaeology complete; step 8 (staff miniapp UI) remains.

Classification script: `tools/classify-stage08-staff-crm-traceability.ps1`

Prerequisites:

- Stage 03 staff member CRM foundation (list, detail, create/edit, notes, tags,
  status, app-access, owner claim).
- Stage 04 member link dual-confirmation (`transferToUser` → link review).
- Stage 05 member-card issue/read/adjust (detail tab card panes).
- Stage 06 booking member history (courseDetail / recordDetails).
- Stage 07 points ledger (memberPoint drill-down).

## Scope and Boundaries

### In scope (this session)

1. **会员 tab shell** (`pages/member/member`) — dashboard chips, pinyin index
   list, inline filters, card-count toggle, quick card popup.
2. **Member search** (`pageMember/search`) — keyword lookup with highlight.
3. **Member screen / filter** (`pageMember/screen`) — advanced presets, batch
   mode entry, `sumMode` / `runOff` cohorts.
4. **Member detail hub** (`pageMember/details/index`) — profile summary, tag,
   sticky remark, app-access block, lead transfer popup, card wallet shortcuts.
5. **Member form** (`pageMember/information/index`) — create/edit lead, dynamic
   required fields, sales consultant picker, batch text import.
6. **Deleted-member archive** (`pageMember/del-member/del-member`) — list +
   restore (reject hard delete).
7. **CRM `/b/manageuser/*` operations** not already owned by Stage 05 card
   ledger (exclude card issue/adjust/freeze/holiday batch endpoints).

### Out of scope (other stages / remain UNREVIEWED)

| Item | Reason |
|---|---|
| `pageMember/details/cardDetail` and card components | Stage 05 `member-card.*` |
| `pageMember/details/*` booking/payment/points drill-downs | Stage 06–07 |
| `pageMember/del-card/del-card` | Stage 05 `member-card.archive` list UI |
| `components/cardToolbox/member-details` card popup writes | Stage 05 card ops |
| `/b/export/exportuser` (API-059) | `pageConfig/shopReport` shop export, not 会员 tab |
| `/b/report2/findUserBirthday`, `findUserByVisitor` | Report remind pages |
| `pageReport/memberAnalyze/allMember` (PAGE-111) | Analytics stage |
| `pageReport/remind/*` birthday/visitor pages | Reminder/notification stage |

### Already implemented (do not re-archaeologize)

| Legacy | New capability | Stage |
|---|---|---|
| `/b/manageuser/setUserNoLogin` | `crm.member.app-access.change` | 03 |
| `/b/manageuser/transferToUser` | `crm.member-link.review` | 04 |
| Basic list/search/create/edit/notes/tags/status | `crm.member.*` routes | 03 |

### Explicit rejects carried forward

| Legacy | Rule |
|---|---|
| `/b/manageuser/delUser` (API-112) | No cascading delete of appointments/revenue; UI still calls it from information page — new system uses lifecycle archive only |
| Hard delete checkbox `delorderTag` | Reject order/revenue destruction; retain audit trail |

## Old Capabilities Inventory (Evidence)

### 会员 tab + list surfaces

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pages/member/member` | `sumReport`, `findAllUser`/`findUser2` with `pingyinChars`, `cardId`/`tagValue`/`hasremark`/`userStatus`/`cardStatus`, `cardCountTag`, permission `getUserFunc(31)` | MERGE | `crm.member.list` |
| `pageMember/search` | `findUser` keywords `pagesize:100`, highlight name/phone, opens detail or card popup | MERGE | `crm.member.list` |
| `pageMember/screen` | `findUser`/`findAllUser2` with card/tag/remark/leave/stop filters, `sumMode`, `runOff`, batch mode `num=1/2`, preset `flag=0–11` from tab chips | MERGE | `crm.member.filter` |
| `pageMember/details/index` | `getUserCardInfo`, `getSumCardInfo`, `updateUserTag`, `updateUserRemark`, `setUserNoLogin`, `make-over`→`transferToUser`, `createAppCode` claim QR | KEEP | `crm.member.detail` |
| `pageMember/information/index` | `getuserFieldSetting`, `saveuser`, `adduserbatch`, `getsalestaffuserid`, `delUser` (reject) | REPLACE | `crm.member.form` |
| `pageMember/del-member/del-member` | `getDelUserList`, `unDeleteUser` restore with `601` blocked state | REPLACE | `crm.member.deleted` |

### Detail drill-down pages (cross-stage refs only)

| Page | Disposition | Capability | Stage ref |
|---|---|---|---|
| `pageMember/details/cardDetail` | REPLACE | `member-card.detail` | 05 |
| `pageMember/details/memberPoint` | REPLACE | `points.ledger` | 07 |
| `pageMember/details/card-consumption` | REPLACE | `member-card.usage-ledger` | 05 |
| `pageMember/details/rechargeAmount` | REPLACE | `payment.ledger` | 05 |
| `pageMember/details/courseDetail` | REPLACE | `booking.history` | 06 |
| `pageMember/details/recordDetails` | REPLACE | `booking.detail` | 06 |
| `pageMember/del-card/del-card` | REPLACE | `member-card.archive` | 05 |

### CRM APIs refined (was generic `planned:crm`)

| Legacy endpoint | Evidence call site | Disposition | New operation |
|---|---|---|---|
| `/b/manageuser/sumReport` | `pages/member/member.js` dashboard + pinyin buckets | REPLACE | `crm.member.dashboard.summary` |
| `/b/manageuser/findUser2` | member tab, search, screen (`findUser`/`findAllUser`) | REPLACE | `crm.member.list` |
| `/b/manageuser/pinyinList` | `pagesCourse/components/member-search.js` | REPLACE | `crm.member.pinyin-index` |
| `/b/manageuser/getpinyinCharList` | exported only; **no static call site** | REPLACE | `crm.member.pinyin-index` |
| `/b/manageuser/saveuser` | `pageMember/information/index.js` | REPLACE | `crm.member.upsert` |
| `/b/manageuser/adduserbatch` | `information/index.js` paste import | REPLACE | `crm.member.batch-import` |
| `/b/manageuser/getsalestaffuserid` | `membershipConsultant.js` | REPLACE | `crm.member.sales-staff.list` |
| `/b/manageuser/updateUserTag` | `details/index.js`, `member-details.js` | REPLACE | `crm.member.tag.assign` |
| `/b/manageuser/updateUserRemark` | `details/index.js` sticky remark icon | REPLACE | `crm.member.sticky-remark.update` |
| `/b/manageuser/getDelUserList` | `del-member/del-member.js` | REPLACE | `crm.member.deleted.list` |
| `/b/manageuser/unDeleteUser` | `del-member/del-member.js` | REPLACE | `crm.member.restore` |
| `/b/manageuser/delUser` | `information/index.js` delete confirm | REJECT | — |
| `/b/manageuser/setUserNoLogin` | `details/index.js` | REPLACE | `crm.member.app-access.change` (Stage 03) |
| `/b/manageuser/transferToUser` | `make-over.js` phone+name | REPLACE | `crm.member-link.review` (Stage 04) |
| `/b/setting/getuserFieldSetting` | `information/index.js` | REPLACE | `crm.member-field-policy.get` |

## Proposed New System Capability IDs

### List and discovery (`crm.member.list`, `crm.member.filter`)

- `crm.member.dashboard.summary` — total/month/valid/invalid/no-card/blocked counts
- `crm.member.pinyin-index` — letter buckets with counts for segmented fetch
- `crm.member.list` — site-scoped query: keywords, tags, remark flag, card
  product filters, holiday/frozen hints, include-visitors toggle
- `crm.member.filter` — persisted advanced filter UI + batch-mode entry (card
  validity cohorts, run-off, sumMode presets)

### Profile operations (`crm.member.form`, `crm.member.detail`)

- `crm.member.upsert` — create lead or patch CRM profile with field policy
- `crm.member-field-policy.get` — tenant field visibility/required matrix
- `crm.member.sales-staff.list` — selectable sales/会籍顾问 staff directory
- `crm.member.sticky-remark.update` — single visible remark line (`hasremark`);
  distinct from append-only `crm.member.note.*`
- `crm.member.tag.assign` — color flag `tagValue` 0–5
- `crm.member.batch-import` — paste/import many leads with per-line error report

### Lifecycle (`crm.member.deleted`)

- `crm.member.deleted.list` — soft-deleted/archived members
- `crm.member.restore` — undo archive; block when business rules conflict (`601`)

### Carried from prior stages

- `crm.member.app-access.change` (Stage 03)
- `crm.member-link.review` (Stage 04)
- `crm.member.note.*`, `crm.member.status.*`, `crm.member.owner.claim` (Stage 03)

## Acceptance Cases (P0)

| ID | Case | Pass criteria |
|---|---|---|
| AC-08-01 | Member tab dashboard + pinyin list | Summary counts match list filters; pinyin sidebar loads segments; >300 members uses chunked `pingyinChars` fetch |
| AC-08-02 | Keyword search | `q` matches name; mobile search requires `crm.member.mobile.search`; results paginate; highlight safe (no HTML injection) |
| AC-08-03 | Advanced filter / screen | Card/tag/remark/holiday/frozen filters combine; preset chips (`flag`) map to server enums; batch mode routes to card batch APIs with explicit permission |
| AC-08-04 | Member detail hub | Detail returns profile, tags, sticky remark, app-access flag, wallet summary cards; tag/remark updates are audited |
| AC-08-05 | Member form + field policy | Required fields enforced per tenant policy; sales consultant optional/required per policy; create returns lead status |
| AC-08-06 | Deleted member archive | Deleted list is read-only history; restore is transactional; hard delete endpoint absent |
| AC-08-07 | Batch text import | Import returns success/fail counts and line errors; duplicates do not silently merge accounts |
| AC-08-08 | Lead phone transfer | Staff-entered mobile triggers link-review flow; `601` surfaced as conflict, not auto-merge |
| AC-08-09 | App access block | `noLogin` toggle maps to tenant `app_access_status`; member app join blocked; staff CRM still readable |
| AC-08-10 | Sticky remark vs notes | `updateUserRemark` maps to sticky field; internal notes remain append-only with separate permission |
| AC-08-11 | Include visitors toggle | `cardCountTag` excludes/includes no-card leads without leaking other tenants |
| AC-08-12 | Sales staff picker | Empty staff list shows graceful empty state; selection persists on `saveuser` |

## Adversarial Findings

1. **Pinyin chunking** — legacy loads `pagesize:9999`; new API must cap page size
   and stream index buckets to avoid staff-device OOM.
2. **Sticky remark overwrite** — `updateUserRemark` replaces in place; do not
   conflate with append-only notes introduced in Stage 03.
3. **Delete UI** — `information/index.js` still calls `delUser` with revenue
   deletion flag; new UI must not expose equivalent action.
4. **transferToUser** — success modal is optimistic; Stage 04 link review must
   be the authority for account binding.
5. **Field policy** — legacy uses positional `userField[n].isMust`; new policy
   must be typed IDs, enforced server-side on upsert.
6. **getpinyinCharList** — no call site in compiled tree; treat as dead export
   or alias of `pinyinList` until proven otherwise.
7. **sumReport domain** — prior matrix tagged `member-card`; evidence shows CRM
   dashboard only (no card mutation).

## Recommended Implementation Order

1. ~~`crm.member.dashboard.summary` + extend `crm.member.list` filters (pinyin,
   card/tag/remark, visitor toggle).~~ **DONE** — `GET /staff/sites/{site}/crm/dashboard-summary`;
   list params `q`, `pinyinInitial`, `includeVisitors`, `sumMode`, `tagIds`, `flag`.
2. ~~`crm.member-field-policy.get` wired into upsert validation.~~ **DONE** —
   `GET /staff/sites/{site}/crm/member-field-policy`; upsert enforces
   `CRM_FIELD_REQUIRED` / `CRM_FIELD_NOT_EDITABLE`.
3. ~~`crm.member.filter` preset mapping (`flag`, `sumMode`, `runOff`).~~ **DONE** —
   `GET /staff/sites/{site}/crm/member-filter-presets`; list `runOff=1` churn cohort.
4. ~~`crm.member.batch-import` with line-level error report.~~ **DONE** —
   `POST /staff/sites/{site}/members/batch-import`; `lines[]` or pasted `text`.
5. ~~`crm.member.sales-staff.list` + owner assignment on form.~~ **DONE** —
   `GET /staff/sites/{site}/crm/sales-staff`.
6. ~~`crm.member.sticky-remark.update` (separate from notes API).~~ **DONE** —
   `PATCH /staff/sites/{site}/members/{member}/sticky-remark`.
7. ~~`crm.member.deleted.list` + `crm.member.restore`.~~ **DONE** —
   `GET /staff/sites/{site}/members/deleted`; `POST .../restore`; no hard delete.
8. Staff miniapp UI: 会员 tab parity → search → screen → detail hub (card/booking
   panes reuse Stage 05–07 components).

## Step 1 Implementation Notes

| Route | Permission | Capability |
|---|---|---|
| `GET /staff/sites/{site}/crm/dashboard-summary` | `crm.member.read` | `crm.member.dashboard.summary` |
| `GET /staff/sites/{site}/members` (extended) | `crm.member.read` | `crm.member.list` |

Dashboard KPIs mirror legacy `sumReport`: `totalCount`, `monthCount`,
`validUserCount`, `invalidUserCount`, `nocardUserCount`, `nologinUserCount`, plus
`pinyinIndex` buckets (`pingyinChar`/`ncount` aliases retained).

Tests: `StaffCrmDashboardTest` (6 cases). Full suite: **265** tests.

## Step 2–3 Implementation Notes

| Route | Permission | Capability |
|---|---|---|
| `GET /staff/sites/{site}/crm/member-field-policy` | `crm.member.read` | `crm.member-field-policy.get` |
| `GET /staff/sites/{site}/crm/member-filter-presets` | `crm.member.read` | `crm.member.filter` |
| `POST/PUT/PATCH /staff/sites/{site}/members` (policy) | `crm.member.create` / `crm.member.update` | `crm.member.upsert` |
| `GET /staff/sites/{site}/members?runOff=1` | `crm.member.read` | `crm.member.list` |

Field policy keys mirror legacy `userField[0..7]`: `mobile`, `name`, `gender`,
`birthDate`, `nationalId`, `heightCm`, `weightKg`, `ownerStaffId`. Tenant overrides
stored in `tenants.crm_field_policy` JSON (`fields.{key}.{isRequired,isVisible,staffEditable}`).

Filter presets document `sumMode` dashboard chips, `flag` 0–11 screen navigation
(3–8 metadata-only until class-attendance filters land), and `runOff=1` churn
(无效会员 + 最近三个月无新卡 + 末卡失效超过三个月).

Tests: `StaffCrmFieldPolicyTest` (7 cases). Full suite: **272** tests.

## Step 4–7 Implementation Notes

| Route | Permission | Capability |
|---|---|---|
| `POST /staff/sites/{site}/members/batch-import` | `crm.member.batch-import` | `crm.member.batch-import` |
| `GET /staff/sites/{site}/crm/sales-staff` | `crm.member.read` | `crm.member.sales-staff.list` |
| `PATCH /staff/sites/{site}/members/{member}/sticky-remark` | `crm.member.update` | `crm.member.sticky-remark.update` |
| `GET /staff/sites/{site}/members/deleted` | `crm.member.deleted.read` | `crm.member.deleted.list` |
| `POST /staff/sites/{site}/members/{member}/restore` | `crm.member.restore` | `crm.member.restore` |

Batch import accepts structured `lines[]` or legacy pasted `text` (`手机号+姓名`
per line). Returns `successCount`, `failCount`, and per-line `errors` (no silent
partial failures). Duplicate mobiles within tenant are reported, never merged.

`member_crm_profiles.sticky_remark` is a single overwrite field (`hasStickyRemark`);
append-only `crm.member.note.*` remains separate.

Deleted members use `members.archived_at` soft archive only — no `delUser` /
physical delete endpoint. Restore returns `MEMBER_RESTORE_BLOCKED` when an active
member already holds the same mobile (legacy `601`).

Tests: `StaffCrmOperationsTest` (8 cases). Full suite: **280** tests.

## Traceability Delta (this session)

- Pages refined: **6** CRM shell/form rows (`PAGE-039`, `084`–`086`, `093`, `095`);
  **7** drill-down rows received cross-stage review notes only.
- APIs refined: **13** CRM operations (including `sumReport` re-domain to `crm`).
- APIs left UNREVIEWED in adjacent scope: `API-059` exportuser, `API-180/181`
  remind queries, plus 88 other non-CRM endpoints.
