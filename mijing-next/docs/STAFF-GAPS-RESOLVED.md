# Staff Miniapp Gaps — Session 2026-07-13

Evidence-driven inventory and fixes for staff features that existed in code but were not reachable.

## Gaps found (prior session)

| Gap | Severity | Evidence |
|---|---|---|
| `pages/members/batch-import` missing from `pages.json` | **P0** — 404 from CRM list toolbar | `members/index.vue` navigates; Vue file + API + PHPUnit exist |
| `pages/settings/defaults/payment-marketing/index` missing from `pages.json` | **P0** — 404 from settings hub | Hub route set; page + API exist |
| Settings hub `card-course-links` marked unimplemented (`route: null`) | **P1** | Traceability maps course-scope to `card-products/edit`; list entry is valid entry point |
| Settings hub `data-export` used non-existent permission `report.export.read` | **P1** | Always disabled; export UI at `pages/report/exports/index` |
| Dashboard shortcuts **发卡** / **签到** showed stub toast | **P1** | `index/index.vue` fell through to generic defer message |
| Settings hub stub toast said "后续阶段" | **P2** | Honesty: now "暂未开放" |

## Implemented this session (deferred staff features — full)

### Chain HQ management

| Item | Backend | Staff page | Hub route |
|---|---|---|---|
| `chain-instructions` | Static (no API; archaeology PAGE-151) | `pages/settings/chain/instructions/index` | Wired |
| `chain-stores` | `GET/PUT /staff/chain/sites`, `GET/PUT /staff/chain/brand` | `pages/settings/chain/stores/index` | Wired |
| `chain-store-courses` | `GET /staff/chain/store-courses` | `pages/settings/chain/store-courses/index` | Wired |
| `chain-staff` | `GET /staff/chain/staff` | `pages/settings/chain/staff/index` | Wired |

### Customer service + video help

| Item | Backend | Staff page |
|---|---|---|
| `customer-service` | `GET /staff/sites/{site}/support/contact` (tenant config or seeded defaults) | `pages/settings/support/customer-service/index` |
| `video-help` | `GET /staff/sites/{site}/support/video-help` (demo placeholder URLs when unset) | `pages/settings/support/video-help/index` |

### Dedicated staff check-in scanner

| Item | Backend | Staff page |
|---|---|---|
| Check-in resolve | `POST /staff/sites/{site}/check-in/resolve` | `pages/check-in/scan/index` |
| Mark check-in | `POST /staff/sites/{site}/appointments/{id}/mark-check-in` | Same page + dashboard **签到** shortcut |

Permission: `booking.fulfillment.check-in` (ledger-safe status transition to `completed`).

## Still deferred (honest)

| Item | Reason |
|---|---|
| L5 runtime screenshots | Path-invalid captures; runner fix separate track |
| WeChat Pay production, token revocation, device sessions | Matrix PARTIAL/DEFERRED per `stage-acceptance-matrix.md` |
| Production member QR encoding | Scanner accepts `memberNo:` / numeric / `member:{id}`; formal WeChat QR contract needs prod AppID |
| Support video CDN | Demo placeholder URLs until tenant `staff_support_config.videoHelp` populated in prod |
| Chain branch delete with biometrics | Legacy `deleteSiteBysiteOwner` deferred; new system uses `organization.site.manage` disable only |

## Verification

```bash
cd mijing-next && pnpm typecheck && pnpm build:staff
cd mijing-next/apps/server && php artisan migrate --force
cd mijing-next/apps/server && php artisan test
```

Targeted:

```bash
php artisan test --filter=TenantSettingsHub
php artisan test --filter=StaffChainManagement
php artisan test --filter=StaffSupport
php artisan test --filter=StaffCheckIn
```
