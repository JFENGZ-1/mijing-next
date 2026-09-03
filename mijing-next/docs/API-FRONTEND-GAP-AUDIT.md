# API ↔ Frontend Gap Audit
Generated: 2026-07-13

## Summary

| Scope | Total routes | FULL | PARTIAL | NONE |
|-------|-------------|------|---------|------|
| **Staff** (`/api/v1/staff/*`) | **232** | **~170** | **~12** | **~50** |
| **Member** (`/api/v1/member/*`) | **47** | **~44** | **2** | **3** |

Member coverage is strong (~94% full). Staff has substantial backend surface with no miniapp UI yet (~22% unused).

**Sources:** `mijing-next/apps/server/routes/api.php`, `mijing-next/docs/openapi.yaml` (287 `operationId`s; `OpenApiRouteContractTest` asserts >200 ops, ≥95% route coverage), `docs/traceability-apis.csv` (10 `REJECT` rows excluded), frontend grep across `staff-miniapp/src` and `member-miniapp/src` (paths use `/staff/...` and `/member/...` via `@mijing/api-client`, not literal `/api/v1/` strings).

- Staff routes total: **232** / used **~170** / partial **~12** / unused **~50**
- Member routes total: **47** / used **~44** / partial **2** / unused **3**
- OpenAPI: **287** operations; contract test ≥95% matched to `api.php`
- Excluded: **10** REJECT-only ops from `docs/traceability-apis.csv`

## Staff — Unused or Partial (priority sorted)

| Route | Method | Backend tests | Frontend | Gap | Priority | Suggested UI |
|-------|--------|---------------|----------|-----|----------|--------------|
| `/staff/sites/{site}/orders/{order}/void` | POST | StaffMemberCardOrderTest | NONE | No void UI | P0 | Order detail actions on member profile |
| `/staff/sites/{site}/orders/{order}/amount-corrections` | POST | StaffMemberCardOrderTest | NONE | No correction UI | P0 | Order detail modal |
| `/staff/sites/{site}/orders/{order}/internal-notes` | POST | StaffPlannedApisTest | NONE | No notes UI | P1 | Order detail notes |
| `/staff/sites/{site}/members/{member}/orders` | GET | StaffMemberCardOrderTest | NONE | No order list | P0 | Member detail "订单" tab |
| `/staff/sites/{site}/member-cards/{card}/transfer-share-token` | POST | MemberCardTransferTest | NONE | Staff can't initiate transfer | P0 | Card detail "转赠" |
| `/staff/sites/{site}/schedule-sessions/{session}/share-link` | POST | PublicBookingShareTest | NONE | No share UI | P1 | Session detail share button |
| `/staff/sites/{site}/schedule-export-image` | POST | StaffPlannedApisTest | NONE | No export image | P1 | Timetable export |
| `/staff/sites/{site}/schedule-sessions/batch-unsuspend` | POST | StaffScheduleBatchTest | NONE | Batch unsuspend missing | P1 | batch-tools page |
| `/staff/sites/{site}/schedule-sessions/batch-change-course` | POST | StaffScheduleBatchTest | NONE | Batch course change | P1 | batch-tools page |
| `/staff/sites/{site}/schedule-sessions/{session}/unsuspend` | POST | StaffScheduleSessionTest | NONE | Single unsuspend | P1 | session-detail |
| `/staff/sites/{site}/members/{member}/point-adjustments` | POST | StaffMemberPointAdjustmentTest | NONE | No points adjust | P1 | Member detail points |
| `/staff/member-tags` | POST | StaffCrmOperationsTest | NONE | Tag create missing | P2 | Tag management |
| `/staff/sites/{site}/points-config` | GET/PUT | StaffPlannedApisTest | NONE | Points config | P2 | Settings > 积分 |
| `/staff/sites/{site}/ledger-reconciliation-jobs` | POST/GET | StaffPlannedApisTest | NONE | Reconciliation jobs | P2 | Admin tools |
| `/staff/platform/subscription/pay` | POST | StaffPlannedApisTest | NONE | Pay flow | P1 | Subscription checkout |
| `/staff/platform/subscription/pricing` | GET | StaffPlatformSubscriptionTest | NONE | Pricing display | P1 | Subscription page |
| `/staff/platform/subscription/agreement` | GET | StaffPlatformSubscriptionTest | NONE | Agreement | P1 | Subscription page |
| `/staff/sites/{site}/platform/subscription/status` | GET | StaffPlatformSubscriptionTest | NONE | Per-site status | P2 | Site settings |
| `/staff/sites/{site}/reports/rankings/sales-staff/{id}` | GET | StaffReportRankingTest | NONE | Sales detail | P2 | Rankings drill-down |
| `/staff/sites/{site}/payroll/coach-reports/{id}` | GET | StaffPayrollReportTest | NONE | Coach detail | P2 | Payroll drill-down |
| `/staff/sites/{site}/payroll/sales-reports/{id}` | GET | StaffPayrollReportTest | NONE | Sales payroll detail | P2 | Payroll drill-down |
| `/staff/sites/{site}/payroll/course-commission` | GET | StaffPayrollReportTest | NONE | Commission view | P2 | Payroll report |
| `/staff/sites/{site}/member-cards/batch-*` (4 routes) | POST | StaffMemberCardBatchTest | NONE | Batch card ops | P2 | Bulk card tools |
| `/staff/sites/{site}/card-products/face-library` | GET | StaffPlannedApisTest | NONE | Face library picker | P2 | Card product editor |
| `/staff/sites/{site}/courses/{id}/delete-preflight` | GET | StaffPlannedApisTest | NONE | Safe delete check | P2 | Course editor |
| `/staff/sites/{site}/courses/{id}` | DELETE | StaffCourseCatalogWriteTest | NONE | Hard delete | P3 | Course editor |
| `/staff/sites/{site}/course-tags` | GET/PUT | StaffPlannedApisTest | NONE | Tag dictionary | P2 | Course settings |
| `/staff/constants/common-data` | GET | — | NONE | Shared constants | P3 | Internal bootstrap |
| `/staff/sites/{site}/staff-directory/{id}/transfer-ownership` | POST | StaffDirectoryTest | NONE | Ownership transfer | P2 | Staff edit |
| `/staff/sites/{site}/payment-marketing` | GET | TenantSettingsHubTest | PARTIAL | Read-only | P3 | Add edit if backend supports PUT |
| `/staff/sites/{site}/closure-calendar/{id}` | PATCH | StaffTenantOperationsTest | PARTIAL | Create only | P2 | Closure edit |
| `/staff/sites/{site}/notices/{id}` | PATCH | StaffTenantOperationsTest | PARTIAL | Create/archive only | P2 | Notice edit |
| `/staff/sites/{site}/staff/{id}/vacations/{id}` | PATCH | StaffTenantOperationsTest | PARTIAL | Create only | P2 | Vacation edit |

## Member — Unused or Partial

| Route | Method | Backend tests | Frontend | Gap | Priority | Suggested UI |
|-------|--------|---------------|----------|-----|----------|--------------|
| `/member/booking/appointments/{id}/promote` | POST | StaffWaitlistTest (staff) | NONE | Waitlist promote | P0 | my-appointments waitlist CTA |
| `/member/sites/{site}/closure-status` | GET | StaffPlannedApisTest | NONE | Closure banner | P1 | Booking/home closure notice |
| `/member/member-link-requests` | GET | MemberLinkingTest (staff side) | PARTIAL | List unused; decision wired | P2 | Link review inbox (optional) |

## Top 10 High-Priority Gaps
1. Staff order void/corrections/notes — revenue integrity ops with tests but no UI
2. Staff card transfer share token — blocks staff-initiated transfers
3. Member waitlist promote — member can't accept waitlist slot
4. Staff schedule share-link + export-image — marketing/ops workflows
5. Platform subscription pay/pricing/agreement — incomplete billing UX
6. Staff member point adjustments — CRM gap vs legacy
7. Staff batch schedule unsuspend/change-course — timetable ops incomplete
8. Member closure-status — booking UX during closures
9. Staff member orders list — member commerce visibility
10. Staff session unsuspend — suspended sessions can't be restored from UI

## Adversarial notes

| Finding | Interpretation |
|---------|----------------|
| `GET /member/member-link-requests` never called | **PARTIAL** — `decision` used from onboarding join flow; list endpoint unused |
| `GET /staff/sites/{site}/payment-marketing` | **PARTIAL** — read-only page, no write API |
| `POST .../rooms/{id}/archive` in `catalog.ts` | **Route mismatch** — controller has `archive()` but `api.php` only registers `DELETE`; frontend may 404 |
| `GET /v1/sites` apiResource | **NONE** in miniapps — staff uses `chain/sites` + `staff/sites/{id}/profile` |
| Dynamic `sitePath()` / `reportsPath()` helpers | False-negative risk if grep misses suffix-only paths — mitigated by reading all `api/*.ts` files |
| 10 `REJECT` rows in traceability-apis.csv | Excluded (e.g. `getAllCardForHasAgreement` — vendor-only) |

## Already wired (sample reference)
- Staff: `GET .../dashboard/summary`, CRM `.../members`, scheduling `.../schedule-sessions`, `.../booking/daily-board`, reports, payroll, exports
- Member: `GET /member/home`, `/member/booking/catalog`, `/member/member-cards`, `/member/card-purchases`, onboarding flow
