# Stage 05: Member Card and Immutable Ledger

## Status

**STEP 10 DONE — STAGE 05 BACKEND CORE COMPLETE (PARTIAL deferrals)** (2026-07-11).
Order adjacency, reminders/reporting read APIs, and archive/hidden list reads
implemented. Cross-site union, card-sales reporting ranks, member purchase submit,
and platform subscription orders remain deferred.

Prerequisite: Stage 04 tenant access policy must gate every member-card and
order endpoint via `TenantMemberAccessService` / `StaffMemberAccessService`.

## Implementation Status (2026-07-11)

### Completed this session (steps 1–7)

| Item | Status | Notes |
|---|---|---|
| Migration `2026_07_11_000007_create_card_domain_tables` | DONE | `card_products`, `card_product_course_scopes`, `member_cards`, `entitlement_ledger_entries` |
| Eloquent models | DONE | `CardProduct`, `CardProductCourseScope`, `MemberCard`, `EntitlementLedgerEntry` |
| PHP enums | DONE | `CardType`, sale/catalog/member-card status, ledger entry type/direction |
| Ledger append-only guard | DONE | Model `booted()` rejects update/delete |
| Feature tests (ledger) | DONE | `tests/Feature/MemberCardLedgerTest.php` |
| Staff catalog read API | DONE | `GET /staff/sites/{site}/card-products`, `GET .../{cardProduct}`; permission `card-product.catalog.read` |
| Staff editor/archive write APIs | DONE | `POST/PUT` create/update, `POST .../archive`, `POST .../restore`; permissions `card-product.editor.write`, `card-product.archive`; `DELETE` rejected |
| Staff issue API | DONE | `POST /staff/sites/{site}/members/{member}/member-cards`; permission `member-card.issue`; idempotent `commandKey` |
| Member activate API | DONE | `POST /member/member-cards/{memberCard}/activate`; pending → active; period validity on activate |
| Member wallet read API | DONE | `GET /member/member-cards?tenantId=`; masked card no; active/pending/frozen visible cards only |
| Staff member card read APIs | DONE | `GET .../members/{member}/member-cards`, `GET .../member-cards/{memberCard}`, `GET .../benefits`; permission `member-card.read` or `crm.member.card.read` |
| `CardProductSeeder` | DONE | 2 sample products for site 1 (local/testing) |
| `MemberCardSeeder` | DONE | 1 sample issued card for account #2 member or member #1 |
| Feature tests (catalog) | DONE | `tests/Feature/StaffCardProductCatalogTest.php` (4 tests) |
| Feature tests (editor) | DONE | `tests/Feature/StaffCardProductEditorTest.php` (8 tests) |
| Feature tests (issue) | DONE | `tests/Feature/StaffMemberCardIssueTest.php` (8 tests) |
| Feature tests (activate) | DONE | `tests/Feature/MemberMemberCardActivateTest.php` (5 tests) |
| Feature tests (wallet/read) | DONE | `tests/Feature/MemberMemberCardWalletTest.php` (4 tests); `tests/Feature/StaffMemberCardReadTest.php` (6 tests) |
| OpenAPI card catalog paths | DONE | 16 paths + request/response schemas (catalog, editor, issue, activate, wallet, staff read, adjust, ledger) |

### Step 7 — balance/count adjust + ledger list (2026-07-11)

| Item | Status | Notes |
|---|---|---|
| Staff balance adjust API | DONE | `POST /staff/sites/{site}/member-cards/{memberCard}/balance-adjustments`; permission `member-card.balance.adjust`; idempotent `commandKey`; optional `correctsEntryId` reversal+correction |
| Staff count adjust API | DONE | `POST .../count-adjustments`; permission `member-card.count.adjust`; same immutable pattern |
| Staff ledger list API | DONE | `GET .../ledger-entries`; permission `member-card.read`; paginated append-only history |
| Member ledger list API | DONE | `GET /member/member-cards/{memberCard}/ledger-entries`; own card only; masked summary (no staff/commandKey) |
| `MemberCardAdjustService` | DONE | `lockForUpdate` on card row; append-only entries; cached balance/count update; `INSUFFICIENT_BALANCE` / `INSUFFICIENT_COUNT` guards |
| Feature tests (adjust) | DONE | `StaffMemberCardAdjustTest` (8 tests): credit/debit, count, reversal correction, idempotency, permission, insufficient balance, isolation, staff ledger list |
| Feature tests (member ledger) | DONE | `MemberMemberCardLedgerTest` (3 tests): own card summary, member isolation, tenant isolation |
| OpenAPI adjust/ledger paths | DONE | 4 new paths + `MemberCardBalanceAdjustInput`, `MemberCardCountAdjustInput`, ledger entry schemas |

**Ledger invariants verified (step 7):**

- No `UPDATE`/`DELETE` on `entitlement_ledger_entries` (model `booted()` guard unchanged).
- Corrections append `reversal` + `correction` rows; original entry `reason` untouched.
- Idempotent `command_key` per tenant on adjust commands.
- Staff `actor_staff_id` recorded on all adjust/correction rows.
- Negative balance/count blocked unless `allowNegative=true` on request.

### Step 8 — freeze/holiday/validity state machine (2026-07-11)

| Item | Status | Notes |
|---|---|---|
| Staff freeze API | DONE | `POST .../freeze`; permission `member-card.freeze`; `active` → `frozen`; ledger `freeze` |
| Staff unfreeze API | DONE | `POST .../unfreeze`; same permission; `frozen` → `active`; ledger `freeze_lift` |
| Staff holiday start API | DONE | `POST .../holiday/start`; permission `member-card.holiday.manage`; ledger `holiday_apply` |
| Staff holiday end API | DONE | `POST .../holiday/end`; ledger `holiday_cancel` + optional `validity_change` for period cards |
| Staff validity extension API | DONE | `POST .../validity-extensions`; permission `member-card.validity.extend`; `extendDays` or `validUntil` |
| `MemberCardStateService` | DONE | `lockForUpdate`; idempotent `commandKey`; append-only ledger rows |
| Frozen debit guard | DONE | `FROZEN_CARD_DEBIT_BLOCKED` on balance/count debit while `status=frozen`; credits still allowed |
| Feature tests (state) | DONE | `StaffMemberCardStateTest` (9 tests): freeze/unfreeze, wallet frozen visibility, holiday validity extension, validity extend, idempotency, 409 transitions, permission, isolation, frozen debit block |
| OpenAPI state paths | DONE | 5 new paths + state command/result schemas |

**State transition rules (step 8):**

| Current status / state | Command | Result | Error code |
|---|---|---|---|
| `active` (not on holiday) | freeze | `frozen` + `freeze_state.frozenAt` | — |
| `frozen` | unfreeze | `active` + `freeze_state=null` | `MEMBER_CARD_UNFREEZE_INVALID` if not frozen |
| `active` (not on holiday, not frozen) | holiday/start | `active` + `freeze_state.holiday` | `MEMBER_CARD_HOLIDAY_INVALID` if frozen or already on holiday |
| `active` (on holiday) | holiday/end | `active` + clear holiday; period card `valid_until` += inclusive holiday days | `MEMBER_CARD_HOLIDAY_INVALID` if not on holiday |
| `active` or `frozen` with `valid_until` | validity-extensions | `valid_until` updated (extend-only) | `MEMBER_CARD_VALIDITY_EXTENSION_INVALID` |
| `frozen` | balance/count debit | rejected | `FROZEN_CARD_DEBIT_BLOCKED` |

**Holiday validity extension rule:** On `holiday/end`, for `card_type=period` cards with
`valid_until`, extend by inclusive calendar days from `beginDate` (or holiday start)
through `endDate`. Stored-value and count cards record holiday events but do not
shift validity. Replaces legacy `applyHoliday` / `cancelHoliday` / `changeValidTime`.

**Wallet visibility:** Frozen cards remain visible in member wallet with
`status=frozen` (not hidden). Booking/pay deduction blocked at adjust layer until
Stage 06 checkout.

### Step 9 — archive/visibility commands (2026-07-11)

| Item | Status | Notes |
|---|---|---|
| Staff archive API | DONE | `POST .../archive`; permission `member-card.archive`; `archived` or `voided` (if consumption ledger exists); ledger `archive`/`void` |
| Staff restore API | DONE | `POST .../restore`; same permission; `archived` only (not `voided`); ledger `archive_restore` |
| Member hide API | DONE | `POST /member/member-cards/{memberCard}/hide`; session owner only; `visibility_change` ledger |
| Member restore visibility API | DONE | `POST .../restore-visibility`; session owner only |
| `MemberCardLifecycleService` | DONE | `lockForUpdate`; idempotent `commandKey`; append-only ledger rows |
| Archived mutation guard | DONE | `MEMBER_CARD_ARCHIVED_MUTATION_BLOCKED` on balance/count adjust |
| Feature tests (archive) | DONE | `StaffMemberCardArchiveTest` (7 tests): wallet exclusion, restore, voided+consumption, adjust block, idempotency, permission, isolation |
| Feature tests (visibility) | DONE | `MemberMemberCardVisibilityTest` (5 tests): hide/restore, staff CRM visibility, isolation, ledger preserved, idempotency |
| OpenAPI archive/visibility paths | DONE | 4 new paths + lifecycle/visibility schemas |

**Visibility matrix (step 9):**

| Card state | Member wallet (`GET /member/member-cards`) | Staff active list | Staff detail by ID | Balance/count adjust |
|---|---|---|---|---|
| `active` + `visible` | shown | shown | shown | allowed |
| `frozen` + `visible` | shown | shown | shown | credit only |
| `hidden` (member) | hidden | shown (`memberVisibility=hidden`) | shown | allowed if not archived |
| `archived` | hidden | hidden (active list) | shown | blocked |
| `voided` | hidden | hidden | 404 | blocked |

**Archive rules:**

| Condition | Archive result | Restore allowed |
|---|---|---|
| No consumption ledger | `status=archived`, `archived_at` set | yes → prior status from ledger metadata |
| Has `count_deduct` or debit `balance_adjust` | `status=voided`, `archived_at` set | no (`MEMBER_CARD_RESTORE_INVALID`) |
| Already archived/voided | rejected (`MEMBER_CARD_ARCHIVE_INVALID`) | — |

Replaces legacy `delUserCard` (staff archive), `deleteUserCard` (member hide),
`recoverdelUserCard` (member restore visibility).

### Step 10 — orders, reminders, archive lists (2026-07-11)

| Item | Status | Notes |
|---|---|---|
| Migration `2026_07_11_000008_create_order_and_reminder_tables` | DONE | `member_card_orders`, `order_amount_corrections`, `member_card_reminder_configs` |
| Staff order list API | DONE | `GET /staff/sites/{site}/members/{member}/orders`; permission `order.read` |
| Order amount correction API | DONE | `POST .../orders/{order}/amount-corrections`; append-only `order_amount_corrections`; `order.amount` immutable |
| Order void API | DONE | `POST .../orders/{order}/void`; `pending_payment` only; `DELETE` returns 403 |
| Reminder query APIs | DONE | expiring / zero-balance / pending-open / penalized; permission `member-card.reminder.read` |
| Reminder config API | DONE | `GET/PUT .../member-card-reminder-config`; merges legacy `saveconfig` threshold |
| Staff archived list API | DONE | `GET .../member-cards/archived`; permission `member-card.archive` |
| Member hidden list API | DONE | `GET /member/member-cards/hidden?tenantId=` |
| Feature tests (orders) | DONE | `StaffMemberCardOrderTest` (7 tests) |
| Feature tests (reminders) | DONE | `StaffMemberCardReminderTest` (8 tests) |
| Feature tests (archive lists) | DONE | `StaffMemberCardArchiveListTest` (2 tests) |
| `MemberCardOrderSeeder` | DONE | sample orders + reminder fixture cards |
| OpenAPI order/reminder paths | DONE | 12 new paths + schemas |

**Order invariants (step 10):**

- `member_card_orders.amount` is the immutable original; effective amount derived from latest non-reversed correction.
- `order_amount_corrections` append-only (model `booted()` guard); idempotent `commandKey` per tenant.
- Physical `DELETE` on orders rejected (`ORDER_DELETE_FORBIDDEN`); void transitions status only.
- Paid orders cannot void (`ORDER_VOID_INVALID`); refunds deferred to payment stage.

**Reminder query rules (step 10):**

| Endpoint | Query rule |
|---|---|
| `.../expiring` | `valid_until` within `expiringWithinDays` (default 30, configurable) |
| `.../zero-balance` | `card_type=stored_value` and `cached_balance <= 0` |
| `.../pending-open` | `status=pending_activation` |
| `.../penalized` | `status=frozen` OR `penalty` ledger entry exists |

### Deferred (post Stage 05 core)

| Item | Reason |
|---|---|
| Cross-site union (`isUnionCard`, `changeToLinkcard`) | Requires chain linkage policy; single-site FK stable first |
| `order.report.card-sales.{summary,detail}` | Reporting stage; read-only sales analytics |
| `report.card-product.sales-rank` / consumption rank | Reporting stage |
| `member-card.purchase.submit` | **PARTIAL** — demo auto-paid fulfillment at `POST /member/card-purchases`; WeChat Pay + payment webhook deferred (Stage 06+) |
| `platform.subscription.orders` | Separate SaaS billing schema |
| Member order list (`order.list` member-facing) | Stage 06+ after checkout path |
| Salary recompute shells in reminder pages | Reporting/payroll stage |

### Remaining (none for Stage 05 backend core)

Stage 05 backend core is **COMPLETE** with explicit deferrals above.

### Spec ambiguities noted

- `scope_key` on `card_product_course_scopes` is opaque until Catalog stage defines course IDs.
- `cached_balance` / `cached_remaining_count` on `member_cards` are performance caches; authoritative totals come from ledger sum (reconciliation service not yet built).
- Cross-site union (`isUnionCard`, `changeToLinkcard`) deferred; single-site FK pattern used.
- Grouped count-card bucket ledger uses `count_group_key` column; per-group opening rows not yet enforced at API layer.

## Scope and Boundaries

### In scope

1. **Card product catalog (templates)** — staff configuration of sellable
   储值卡 (`cardType=1`), 计次卡 (`cardType=2`), 期限卡 (`cardType=3`):
   price, face, validity, course scope, booking rules, sale status, sort order,
   soft archive/restore.
2. **Issued member cards (instances)** — per-member wallet cards with balance,
   remaining count, validity window, activation mode, freeze/holiday, staff
   issue and member self-purchase.
3. **Immutable ledgers** — balance changes, count deductions, validity changes,
   freeze/holiday events, penalties, metadata modifications; no physical delete
   of financial or entitlement facts.
4. **Member-facing wallet UX** — benefits text, usage/balance/change history,
   hide/restore card visibility.
5. **Staff CRM card surfaces already traced** — member detail card tab, balance
   ledger, usage ledger, recharge history, archived cards (`pageMember/*`).
6. **Adjacent read models** — card expiry/zero-balance/penalty/pending-open
   reminders and chain card-sales reports (query-only; implementation after core
   ledger).

### Out of scope (later stages)

- Booking seat deduction and `getCardListForPay` checkout (Stage 06+ booking).
- Points accrual rules beyond immutable adjustment primitives (Stage 07+).
- Gift/transfer claim flows (`card-transfer.*`, Stage 09).
- Member QR page (`PAGE-021`) — remains `UNREVIEWED` (payload unknown).
- `getAllCardForHasAgreement` (`API-029`) — exported but **no call site** found;
  stays `UNREVIEWED`.
- Salary recompute UI embedded in reminder/rank shells (`ReComputeSalary`,
  `sumSaleSalary`) — reporting stage.

### Explicit rejects carried forward

| Legacy | Disposition | New rule |
|---|---|---|
| `/b/userorder/delUserOrder` | REJECT | No physical order/revenue delete |
| `/b/report2/delPointLog` | REJECT | Points corrections via reversal |
| `/b/manageuser/delUser` | REJECT | Lifecycle transition only |
| `/c/user/myOrderList_notoken` | REJECT | One-time code ≠ order auth |
| `/b/platform/findMyOrder` | REJECT | Same jscode pattern as member order |
| `/b/report2/clearUserPoint` | REJECT | No zeroing points history |

## First-Principles Ledger Rules

```text
CardProduct (template, versioned config)
  -> MemberCard (issued instance per member/site)
       -> EntitlementSnapshot (derived balance/count/validity)
       -> LedgerEntry (append-only, typed, idempotent)
            -> optional link to Order / Booking / Adjustment
```

1. **No physical delete** of issued cards, balances, counts, orders, or ledger
   rows. Archive/hide/void only.
2. **Corrections are new rows** — `changeAmount`, `saveOrderAmount`,
   `changeInitCardAmount`, `editPointLog` must become paired
   reversal + correction with operator, reason, version and audit.
3. **Derived balances** — UI totals come from ledger sum, never a silently
   editable column.
4. **Template delete ≠ instance delete** — `delcard` archives catalog
   templates; `delUserCard` archives instances; existing instances keep
   historical facts.
5. **Tenant/site scope** — every read/write resolves tenant, site, member and
   staff permission before returning card facts.
6. **Idempotency** — purchase submit, staff issue, balance adjust, freeze and
   holiday commands accept client command keys.
7. **Concurrency** — balance/count mutations lock the `MemberCard` row
   (`lockForUpdate`) and append ledger in the same transaction.

## Old Capabilities Inventory (Evidence)

### Member client (already reviewed pages; refined operations)

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageHome/buyingCard/index` | `会员端/pageHome/buyingCard/` | KEEP | `card-purchase.catalog` |
| `pageHome/buyingCard/buySuccess` | payment landing only | REPLACE | `order.result` (not payment truth) |
| `pageMine/myInterests/index` | `cardPrivilege({cardId})` | KEEP | `member-card.benefits` |
| `pageMine/useRecord/index` | tabs: appoint / balance / change logs | KEEP | `member.ledger` |
| `pageMine/removeCard/index` | hide + recover | REPLACE | `member-card.visibility` |
| `pages/myOrder/index` | authenticated order list | KEEP | `order.list` |

### Staff card-product catalog (reviewed this session)

| Page | Evidence path | Disposition | New capability |
|---|---|---|---|
| `pagesImp/card/home/home` | list, search, sort `saveSortId` | KEEP | `card-product.catalog` |
| `pagesImp/card/home/del-card` | `getDelCardInfo` + `recoverDelCard` | KEEP | `card-product.recycle-bin` |
| `pagesImp/card/home/del-card-tp` | union recycle variant | MERGE | `card-product.recycle-bin` |
| `pagesImp/card/create/create` | type picker shell | MERGE | `card-product.type-picker` |
| `pagesImp/card/member-card/index` | full editor `saveCard` | KEEP | `card-product.editor` |
| `pagesImp/card/member-card/index-tp` | union editor | MERGE | `card-product.editor` |
| `pagesImp/card/card-subject/index` | `getAllCardPrice` matrix | REPLACE | `card-product.pricing-matrix` |
| `pagesImp/card/setting-more-subject/index` | group course scope | REPLACE | `card-product.course-scope.group` |
| `pagesImp/card/setting-single-subject/index` | single course scope | REPLACE | `card-product.course-scope.single` |
| `pagesImp/subject/suject-choice-card` | picker for subjects | MERGE | `card-product.picker` |
| `pageChain/card/home/home` | chain HQ list | MERGE | `card-product.catalog` |
| `pageChain/card/home/subbranch-home` | `changeToLinkcard` | REPLACE | `card-product.cross-site.link` |
| `pageChain/card/*` (create/subject/settings) | linkage duplicates | MERGE | same as `pagesImp/card/*` |

### Staff member-card instance (CRM — reviewed Stages 03–04 pages)

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageMember/details/cardDetail` | card detail + amount log | REPLACE | `member-card.detail` |
| `pageMember/details/card-consumption` | usage history | REPLACE | `member-card.usage-ledger` |
| `pageMember/details/rechargeAmount` | payment history | REPLACE | `payment.ledger` |
| `pageMember/del-card/del-card` | archived instance list | REPLACE | `member-card.archive` |

Key write call sites:

- `pageMember/components/userCard/card-management.js` → `changeAmount`
- `pageMember/details/cardDetail.js` → `findAmountChangeLog`, `getOneUserCardInfo`
- `pageMember/screen.js` → batch freeze/validity/amount operations

### Orders and platform billing (reviewed this session)

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pages/shopOrder/index` | SaaS renewal list | REPLACE | `platform.subscription.orders` |
| `pageServer/order` | `pricelist` + `submitwexinOrder` | MERGE | `platform.subscription.orders` |
| `pageChain/cardStatistics/index` | sales rollup + **rejected** order deletes | REPLACE | `order.report.card-sales.summary` |
| `pageChain/cardStatistics/detailed-records` | per-card sales detail | MERGE | `order.report.card-sales.detail` |

### Reminders and analytics (reviewed this session)

| Page | API | New capability |
|---|---|---|
| `pageReport/remind/cardExpires` | `findwillExpireCard` | `member-card.reminder.expired` |
| `pageReport/remind/cardWill` | `findWillOpenUserCardList` | `member-card.reminder.pending-open` |
| `pageReport/remind/penalizeCard` | `findPunishUserCardList` | `member-card.reminder.penalized` |
| `pageReport/remind/findBalaceZeroCard` | `findBalaceZeroCard` | `member-card.reminder.zero-balance` |
| `pageReport/remind/component/*Setting` | `saveconfig` | `member-card.reminder.config` |
| `pageReport/rank/memberCard` | `RankCardInfo_cardId` | `report.card-product.sales-rank` |
| `pageReport/rank/memberConsumptionCardRank` | `sumUserExpendPrice` | `report.member-card.consumption-rank` |
| `pageReport/rank/memberCardAnalyze` | `getAllCardInfoIncludeUnionReport` | `report.card-product.analytics` |

## Proposed New System Capability IDs

### Card product (`card-product.*`)

- `card-product.catalog` — list/search/sort templates
- `card-product.editor` — create/update template + rules
- `card-product.archive` / `card-product.restore` — soft lifecycle
- `card-product.sale-status` — on-sale / stopped
- `card-product.pricing-matrix` — group/single price views
- `card-product.course-scope.{group|single}.{get|save}`
- `card-product.cross-site.{list|convert}`
- `card-product.sellable.list` — member purchase catalog

### Member card instance (`member-card.*`)

- `member-card.wallet.list` / `member-card.detail`
- `member-card.issue` / `member-card.purchase.submit` / `member-card.activate`
- `member-card.balance.{adjust|batch-adjust}` + `member-card.balance-ledger.list`
- `member-card.freeze{,.batch,.lift,.batch-lift}` + freeze ledger
- `member-card.holiday.{apply,cancel,last}`
- `member-card.validity.{adjust,batch-adjust}`
- `member-card.change-ledger.list` / `member-card.penalty-ledger.list`
- `member-card.usage-ledger` (from booking deductions — cross-stage)
- `member-card.visibility.{hide,restore}` / `member-card.archive{,.list,.restore}`
- `member-card.benefits` / `member-card.summary`
- `member-card.reminder.*` (read models)

### Orders (`order.*` adjacency)

- `order.list` / `order.member-ledger.list` / `order.by-member-card.list`
- `order.amount.correct` (immutable correction)
- `order.void` (eligible unpaid only)
- `order.report.card-sales.{summary,detail}`

## Acceptance Cases (P0)

| ID | Case | Pass criteria |
|---|---|---|
| MC-01 | Staff creates 计次卡 template | Template saved with course scope; catalog shows on-sale row |
| MC-02 | Staff issues card to member | Instance created; opening entitlement ledger row; audit operator |
| MC-03 | Member views wallet | `member-card.wallet.list` returns only tenant-scoped active cards |
| MC-04 | Balance adjustment | `changeAmount` equivalent appends ledger row; derived balance matches sum |
| MC-05 | Freeze and lift | Freeze appends event; booking/pay blocked; lift restores prior state |
| MC-06 | Archive instance | Card hidden from wallet; ledger history still readable |
| MC-07 | Reject order delete | `delUserOrder` equivalent returns 403/409; no row removal |
| MC-08 | Member hide/restore | Visibility toggle does not delete entitlements |
| MC-09 | Tenant blocked member | `app_access_status=blocked` rejects card mutations |
| MC-10 | Idempotent purchase | Duplicate `submitcard` command key does not double-charge |

## Red Team Concerns

1. **Direct balance edit** — Legacy `changeAmount`/`saveOrderAmount` mutate facts
   in place. New system must reject silent overwrites (concurrency + reversal).
2. **Order delete in reports** — `pageChain/cardStatistics/*` exposes
   `delUserOrder` beside analytics; UI must not reintroduce physical delete.
3. **Platform vs member orders** — `pages/shopOrder` and `pageServer/order` are
   SaaS billing, not gym member card orders; must not share schema with tenant
   `order.*`.
4. **Cross-site union cards** — `changeToLinkcard` and `isUnionCard` complicate
   site scope; require explicit policy for which site owns issuance and
   deduction.
5. **Grouped count-card balances** — `findHistoryGroupName` and groupList edits
   risk partial count drift; ledger must track per-group buckets.
6. **buySuccess as payment truth** — Member `buySuccess` page is display-only;
   payment confirmation must be server webhook driven.
7. **Share key (`getShareKey`)** — Must not replay; Stage 09 needs signed,
   short-lived, scoped tokens.
8. **Member QR (`PAGE-021`)** — Unknown whether identity, card or check-in;
   blocking check-in/booking flows until resolved.
9. **Agreement cards (`API-029`)** — No frontend call site; backend rule unknown.
10. **Asset merge after Stage 04** — Linking accounts must not auto-merge cards
    or balances without reviewed reconciliation workflow.

## Contradictions Found in Old System

| Topic | Observation | New system decision |
|---|---|---|
| Card type enums | `1/2/3` = 储值/计次/期限 | Named enum in API, not magic numbers |
| `updateCardStatus` nstatus `2` | Labelled 删除 but recoverable for templates | Template archive, not instance delete |
| Duplicate APIs | `/b/card/*` vs `/b/linkage/*` | Single operation with `scope=site|chain` |
| `findAmountChangeLog` domain split | Member file uses balance tab; CSV had `member-insight` | Unified `member-card.balance-ledger.list` |
| Order amount edit + delete in analytics | Sales report allows finance mutation | Reports read-only; corrections via `order.amount.correct` |
| jscode order lookup | Staff `shopOrder` uses wx.login code | REJECT — requires staff token or member session |

## Recommended Implementation Order

1. **Schema + ledger primitives** — `card_products`, `member_cards`,
   `entitlement_ledger_entries`, command idempotency, audit hooks.
2. **Card product catalog APIs** — `card-product.{catalog,editor,archive}`.
3. **Issue + activate** — staff `member-card.issue`, member purchase path.
4. **Read wallet** — member `wallet.list`, staff `detail`, benefits.
5. **Balance/count adjustments** — immutable adjust commands + list endpoints.
6. **Freeze/holiday/validity** — state machine + ledger events.
7. **Archive/visibility** — staff archive + member hide/restore.
8. **Order adjacency** — member order list, amount correction, void rules.
9. **Reminders/reporting queries** — read-only after facts exist.
10. **Cross-site union policy** — chain linkage after single-site path stable.

## Traceability Session (2026-07-11)

Classification script: `tools/classify-stage05-member-card-traceability.ps1`

Reviewed this session:

- **29 pages** (card catalog, chain variants, reminders, ranks, platform billing,
  card sales reports)
- **77 APIs** (14 `/b/card`, 10 linkage card, platform, report2 card/points,
  28 `/b/manageuser` card refinements, 9 member `/c/user` card refinements,
  plus related merges)

Remaining `UNREVIEWED` blockers for this domain:

- `PAGE-021` member QR
- `API-029` `getAllCardForHasAgreement` (no call site)

## Verification (not started)

| Check | Result |
|---|---|
| Backend tests for ledger | **PASS** — `MemberCardLedgerTest` (migration, append-only, tenant isolation, idempotency key) |
| Backend tests for catalog | **PASS** — `StaffCardProductCatalogTest` (permission, isolation, archived exclusion) |
| Backend tests for editor/archive | **PASS** — `StaffCardProductEditorTest` (create/update, archive/restore, delete forbidden, snapshot immutability) |
| Backend tests for issue/activate | **PASS** — `StaffMemberCardIssueTest` (3 card types, ledger, snapshot freeze, idempotency, isolation, blocked member); `MemberMemberCardActivateTest` (pending activate, period validity, idempotent, isolation) |
| Backend tests for wallet/read | **PASS** — `MemberMemberCardWalletTest` (wallet filters, masking, tenant isolation); `StaffMemberCardReadTest` (permission, detail, benefits, alias, archived exclusion) |
| Backend tests for adjust/ledger | **PASS** — `StaffMemberCardAdjustTest` (8 tests); `MemberMemberCardLedgerTest` (3 tests) |
| Backend tests for state machine | **PASS** — `StaffMemberCardStateTest` (9 tests): freeze/unfreeze, frozen wallet visibility, frozen debit block, holiday validity extension, validity extend, idempotency, invalid transitions, permission, isolation |
| Backend tests for archive/visibility | **PASS** — `StaffMemberCardArchiveTest` (7 tests); `MemberMemberCardVisibilityTest` (5 tests) |
| Backend tests for orders | **PASS** — `StaffMemberCardOrderTest` (7 tests): list, immutable correction, idempotency, void rules, delete forbidden, permission, isolation |
| Backend tests for reminders | **PASS** — `StaffMemberCardReminderTest` (8 tests): expiring, zero-balance, pending-open, penalized, config, permission, isolation |
| Backend tests for archive/hidden lists | **PASS** — `StaffMemberCardArchiveListTest` (2 tests) |
| OpenAPI paths for card domain | **DONE** — 37 paths (catalog through orders/reminders/archive lists) |
| DevTools member wallet UI | PARTIAL | `pages/cards/index`, `detail`, `hidden` wired; `pnpm typecheck` + `build:member` pass | DevTools runtime screenshots; card catalog purchase flow |
| Adversarial delete/overwrite tests | PARTIAL — model-level append-only on ledger + order corrections; DB triggers not added |
