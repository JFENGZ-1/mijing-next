# Stage 07: Member Profile, Home, and Check-In Archaeology

## Status

**STAGE 07 BACKEND COMPLETE** (2026-07-11) — member home/mine dashboards, carousel, notices, tenant profile CRUD + avatar, purchase gate, year/month stats + month appointment drill-down, points ledger with CMS description, monthly ranking + opt-in write, official-account follow.

Classification script: `tools/classify-stage07-member-profile-checkin-traceability.ps1`

### Implementation progress (Step 1-10)

| Capability | Endpoint | Status |
|---|---|---|
| `member.home.dashboard` | `GET /member/home?tenantId=&siteId=` | Done |
| `member.home.carousel` | included in home dashboard | Done |
| `member.mine.dashboard` | `GET /member/mine?tenantId=` | Done |
| `notice.list` | `GET /member/notices?tenantId=&siteId=` | Done |
| `notice.detail` | `GET /member/notices/{notice}?tenantId=` | Done |
| `member.profile.read` | `GET /member/profile?tenantId=` | Done |
| `member.profile.update` | `PATCH /member/profile?tenantId=` | Done |
| `member.avatar` | `POST /member/profile/avatar?tenantId=` | Done |
| `member.profile.purchase-gate` | `GET /member/profile/purchase-gate?tenantId=` | Done |
| `member.profile.ranking-opt-in` | `PATCH /member/profile/ranking-opt-in?tenantId=` | Done |
| `member.stats.year.summary` | `GET /member/stats/year?tenantId=&year=` | Done |
| `member.stats.month.calendar` | `GET /member/stats/month?tenantId=&year=&month=` | Done |
| `member.stats.month.appointments` | `GET /member/stats/month/appointments?tenantId=&year=&month=&courseKind=` | Done |
| `points.ledger.list` | `GET /member/points/ledger?tenantId=&page=` | Done |
| `points.adjust` (staff) | `POST /staff/sites/{site}/members/{member}/point-adjustments` | Done |
| `ranking.monthly.list` | `GET /member/ranking/monthly?tenantId=&year=&month=` | Done |
| `member.official-account-follow` | `GET /member/official-account-follow?tenantId=` | Done |
| `member.onboarding` | `GET /member/onboarding`, `PUT /member/profile` (no tenantId) | Existing |

Schema: `site_notices`, `site_carousel_items`, `point_ledger_entries`, `member_point_balances`, `site_official_account_follow`; tenant flags `points_enabled`, `show_month_rank`, `points_description_text`; member flag `ranking_opt_in`.

Field policy (`MemberProfileFieldPolicy`): member-editable `displayName`, `gender`, `birthDate`, `heightCm`, `weightKg`, `avatarObjectKey`; staff-only `mobile`, `nationalId`. Mobile change remains `POST /member/profile/verify-mobile`. Purchase gate checks `displayName`, legal consent, and tenant-required profile fields; returns `redirectHints.profile` and `redirectHints.cardCatalog` (`/member/card-products?tenantId=`).

Seeders: `NoticeSeeder`, `CarouselSeeder`, `PointLedgerSeeder`, `OfficialAccountFollowSeeder` (site 1).

Tests: `MemberHomeDashboardTest` (5), `MemberProfileTest` (6), `MemberPurchaseGateTest` (3), `MemberStatsTest` (5), `MemberPointLedgerTest` (4), `StaffMemberPointAdjustmentTest` (3), `MemberMonthlyRankingTest` (4), `MemberRankingOptInTest` (3), `MemberOfficialAccountFollowTest` (3). **Total: 259 tests passing.**

**Stage 07 backend status:** COMPLETE — frontend deferrals only: step 5 home upcoming booking UI wiring (Stage 06), step 9 `member-card.wechat-pack.*` (optional product decision).

Prerequisites:

- Stage 04 tenant access policy and member link flows.
- Stage 05 member-card wallet, ledger, visibility.
- Stage 06 booking member upcoming/detail and fulfillment status display.

## Scope and Boundaries

### In scope (this session)

1. **Member home tab** (`pages/index/index`) — venue carousel, notices, upcoming
   appointments, shortcuts, official-account follow nudge.
2. **Member mine tab** (`pages/mine/index`) — dashboard stats, wallet cards,
   navigation hub, WeChat card-pack CTA.
3. **Member profile** — `myInfo`, `modifidInfo`, avatar cropper, onboarding
   merge targets, purchase preflight gate.
4. **Member insight reads** — yearly/monthly stats, ranking, points ledger.
5. **Notice detail** — client-side notice resolution from list.
6. **PAGE-021 QR** — resolved as official-account follow landing (not member
   identity / card / redemption code).
7. **Check-in archaeology** — member client has **no self check-in write API**;
   `appointStatus=1` (“已签到”) is read-only display from staff fulfillment.

### Out of scope (deferred / other stages)

| Item | Reason |
|---|---|
| `pageHome/shopDetails`, `toggleShop` | Site domain (prior audit) |
| `pageHome/buyingCard/*` | Stage 05 card purchase |
| `pageHome/appointmentDetails` | Stage 06 booking detail |
| `pageMine/myInterests`, `useRecord`, `removeCard` | Stage 05 card ledger |
| `pages/myOrder/index` | Order/payment stage |
| `pages/start/index` login routing | Identity stage (prior audit) |
| Member feedback / settings pages | **No legacy page or API found** in 会员端 |
| Staff-side check-in writes | Staff fulfillment (`booking.fulfillment.*`, Stage 06) |

### Explicit rejects carried forward

| Legacy | Rule |
|---|---|
| `myOrderList_notoken` | One-time wx code is not order authorization (API-294 REJECT) |
| URL `tokenId` query auth | New system uses header bearer tokens |
| Client-only notice “detail” without server record | New `notice.detail` must fetch by ID |
| Static QR image as member identity | PAGE-021 is marketing follow page only |

## Check-In Finding (Negative Evidence)

Member端 has **no** dedicated check-in page, sign-in API, or member-initiated
fulfillment write.

Evidence:

- Grep across `会员端/` finds `已签到` only in `common/vendor.js`
  `unionStatusIdText()` mapping `appointStatus==1`.
- `components/appointment-list/index.js` renders status from `appointStatus` /
  `waitUserTag`; cancel is the only member write on list rows.
- Staff mark-absent / roster fulfillment is admin-side (Stage 06
  `booking.fulfillment.absent`).

**Conclusion:** Member “check-in” in legacy UI means **display of staff-recorded
attendance**, not a member capability. Stage 07 does not add
`member.check-in.*` operations.

## Old Capabilities Inventory (Evidence)

### Member home + mine tab

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pages/index/index` | `getSiteFaceimage`, `getNoticeList`, `selectAppoint`, `getUserInfoForUpdate`, `goFollow` → QR | KEEP | `member.home` |
| `pages/mine/index` | `myMainpage`, `finddelUsercard`, `putWXCardPackage` | KEEP | `member.mine` |
| `pageHome/informDetails/index` | noticeId → list lookup | REPLACE | `notice.detail` |
| `pageHome/QRcode/QRcode` | static QR + 提醒通知 copy; no JS API | KEEP | `member.official-account-follow` |

### Member profile

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageMine/myInfo/index` | `getMyUserInfo`, `UpdateUserInfo`, upload | REPLACE | `member.profile` |
| `pageMine/modifidInfo/index` | single-field + phone auth | MERGE | `member.profile` |
| `pages/tailor/.../u-avatar-cropper` | crop → upload → profile save | REPLACE | `member.avatar` |
| `pages/authorization/info`, `phone` | onboarding capture | MERGE | `member.onboarding` |

### Member insight (mine sub-pages)

| Page | Evidence | Disposition | New capability |
|---|---|---|---|
| `pageMine/totalStatistics/index` | `sumUserList` | KEEP | `member.stats.year` |
| `pageMine/appointmentStatistics/index` | `sumAppointOfMonth`, `selectAppointOfMonth` | KEEP | `member.stats.month` |
| `pageMine/rankingRecord/index` | `rankList` | REPLACE | `ranking.opt-in` |
| `pageMine/point/index` | `PointListByUserId` | KEEP | `points.ledger` |

### Member APIs refined (was generic `planned:member-*`)

| Legacy endpoint | Evidence call site | Disposition | New operation |
|---|---|---|---|
| `/c/user/getMyUserInfo` | `myInfo/index.js` | REPLACE | `member.profile.read` |
| `/c/user/UpdateUserInfo` | `myInfo`, `modifidInfo` | REPLACE | `member.profile.update` |
| `/c/user/getUserInfoForUpdate` | `index.js` buyCard gate | REPLACE | `member.profile.purchase-gate` |
| `/c/user/getNoticeList` | `index.js` | REPLACE | `notice.list` |
| `/c/user/getSiteFaceimage` | `index.js` | REPLACE | `member.home.carousel` |
| `/c/user/myMainpage` | `mine/index.js` | REPLACE | `member.mine.dashboard` |
| `/c/user/getwxCardParam` | `putWXCardPackage` | REPLACE | `member-card.wechat-pack.sign` |
| `/c/user/putweixincard` | `putWXCardPackage` | REPLACE | `member-card.wechat-pack.confirm` |
| `/c/user/putweixinList` | `card-info`, `mine` | REPLACE | `member-card.wechat-pack.pending` |
| `/c/user/findModifyLog` | `useRecord` tab 3 | REPLACE | `member-card.modify-log.list` |
| `/c/user/findUserAppointList` | `useRecord` tab 1 | REPLACE | `member-card.appointment-history.list` |
| `/c/user/PointListByUserId` | `point/index.js` | REPLACE | `points.ledger.list` |
| `/c/user/rankList` | `rankingRecord/index.js` | REPLACE | `ranking.monthly.list` |
| `/c/user/selectAppointOfMonth` | `appointmentStatistics` | REPLACE | `member.stats.month.appointments` |
| `/c/user/sumAppointOfMonth` | `appointmentStatistics` | REPLACE | `member.stats.month.calendar` |
| `/c/user/sumUserList` | `totalStatistics` | REPLACE | `member.stats.year.summary` |

## Proposed New System Capability IDs

### Member home (`member.home`, `member.mine`)

- `member.home` — authenticated home tab shell
- `member.home.carousel` — tenant/site hero images
- `member.mine` — mine tab shell
- `member.mine.dashboard` — aggregate stats + cardlist + hellomsg
- `member.official-account-follow` — static or CMS-driven follow landing

### Notices (`notice.*`)

- `notice.list` — home feed
- `notice.detail` — server fetch by notice ID (replace client list scan)

### Profile (`member.profile`, `member.avatar`, `member.onboarding`)

- `member.profile.read` — tenant field policy + current values
- `member.profile.update` — self-service patch with field-level validation
- `member.profile.purchase-gate` — pre-purchase completeness check
- `member.avatar` — crop + object storage upload
- `member.onboarding` — first login nickname/phone (Stage 04 link policy applies)

### Insight (`member.stats.*`, `points.*`, `ranking.*`)

- `member.stats.year.summary` — yearly buckets
- `member.stats.month.calendar` — month heatmap/summary
- `member.stats.month.appointments` — month list with course-type filter
- `points.ledger.list` — immutable point history (read)
- `ranking.monthly.list` — opt-in gated monthly board

### Cross-stage (card pack)

- `member-card.wechat-pack.{sign,confirm,pending}` — optional WeChat wallet
  integration; defer until product decision

## Acceptance Cases (P0)

| ID | Case | Pass criteria |
|---|---|---|
| AC-07-01 | Home dashboard | Member sees carousel, notices, paginated upcoming appointments; can cancel within policy |
| AC-07-02 | Mine dashboard | `myMainpage` equivalent returns stats, visible wallet cards, hellomsg; respects tenant access |
| AC-07-03 | Avatar update | Crop upload stores object key; profile read returns new avatar URL |
| AC-07-04 | Onboarding | Visitor cannot access mine sub-pages until profile/onboarding completes per Stage 04 |
| AC-07-05 | Notice detail | `notice.detail` fetches by ID; missing ID returns 404 not empty shell |
| AC-07-06 | Official-account page | Follow landing renders CMS/static asset; no PII in QR payload |
| AC-07-07 | Profile CRUD | `member.profile.read` honors tenant `userField` required flags; update rejects unknown fields |
| AC-07-08 | Year stats | `sumUserList` equivalent returns year buckets linking to month drill-down |
| AC-07-09 | Month stats | Calendar summary + filtered appointment list paginate independently |
| AC-07-10 | Ranking opt-in | When `showMonthRank` false, mine shows “-”; API still auth-scoped |
| AC-07-11 | Points ledger | Append-only ledger list; totalPoint matches sum of entries |
| AC-07-12 | Purchase gate | Incomplete profile blocks card catalog navigation with explicit reason |
| AC-07-13 | WeChat card pack | Sign bundle is short-lived; confirm is idempotent per userCardId |
| AC-07-14 | Per-card history | Appointment/modify logs scoped to session member + userCardId |

## Red Team Concerns

1. **Profile fields** — `userIdent` (national ID) is sensitive; new system should
   treat as optional encrypted CRM field with staff-only visibility toggle.
2. **Purchase gate** — `getUserInfoForUpdate` is opaque `code/msg`; new gate must
   return structured missing-field list, not generic toast.
3. **Notice detail** — legacy resolves from cached list; tampered `noticeId` may
   show wrong notice — server-side fetch required.
4. **Ranking** — public leaderboard exposes real names/avatars; require tenant
   opt-in and pseudonym option.
5. **Points** — `PointListByUserId` is POST write-classified in catalog but read
   only; new API must be GET with pagination cursors.
6. **WeChat card pack** — signature params are time-bound; replay of `putweixincard`
   must be idempotent.
7. **No member check-in** — do not add member write that bypasses staff roster;
   attendance remains staff fulfillment domain.
8. **Official-account QR** — static bitmap cannot be rotated per member; if tenant
   needs dynamic follow QR, use CMS URL not identity encoding.

## Top Contradictions Found

| # | Observation | Impact |
|---|---|---|
| C1 | Prior handoff blocked PAGE-021 as “identity/card/redemption unknown” | WXML proves official-account **notification follow**, not member code |
| C2 | Mission mentions “check-in” but member端 has no check-in API | Check-in is **read-only status** from `appointStatus`; implement via Stage 06 fulfillment reads |
| C3 | No feedback or settings pages in `app.json` | Do not invent `member.feedback` until staff/admin evidence found |
| C4 | `pointStarted` toggles mine KPI between absence vs points | New dashboard must preserve tenant feature flag semantics |
| C5 | `useRecord` APIs classified here for modify/appoint history but page is Stage 05 | Operation IDs scoped per-card; page disposition unchanged |
| C6 | ARCHAEOLOGY-HANDOFF §6.1 still says member pages “全部分类” except QR | QR now closed; handoff snapshot predates this session |

## Recommended Implementation Order

1. **`member.mine.dashboard` + `member.home.carousel` + `notice.list`** — unblock home/mine shells.
2. **`member.profile.{read,update}` + `member.avatar`** — CRM field policy from Stage 03.
3. **`member.profile.purchase-gate`** — wire to card catalog entry (Stage 05).
4. **`booking.member.upcoming` home integration** — already Stage 06; connect UI.
5. **`member.stats.{year,month}`** — read models over appointment facts.
6. **`points.ledger.list`** — after points write domain defined (staff CRM).
7. **`ranking.monthly.list`** — after opt-in policy + privacy review.
8. **`notice.detail`** — server-backed replacement for list scan.
9. **`member.official-account-follow`** — low priority CMS/static page.
10. **`member-card.wechat-pack.*`** — optional; defer pending product decision.

## Traceability Session Stats (2026-07-11)

```text
Pages reviewed/refined this session: 13
APIs reviewed/refined this session: 16
PAGE-021 de-blocked: UNREVIEWED → KEEP (member.official-account-follow)
Pages UNREVIEWED: 92  (was 93)
APIs UNREVIEWED: 90   (unchanged — member /c/user/* were already dispositioned)
pages SHA-256 E50FE9EA6467A392F02981C60661BDB748EF062308565B7F1BE58399679D908C
apis  SHA-256 102E6B143B2BF68E00ECCF83A9CB237BDFF5ECF22BB1D5C75CA9AE6C1E000C0F
```

Classification script: `tools/classify-stage07-member-profile-checkin-traceability.ps1`
