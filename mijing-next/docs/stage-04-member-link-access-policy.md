# Stage 04: Member Link and Tenant Access Policy

## Status

**COMPLETE** (2026-07-11). Backend, TypeScript, API contracts, DevTools runtime, link-request
seed data, member-home pending warning UI, staff review-card UI and default production builds
with embedded `VITE_API_BASE_URL` are verified locally.

This stage does not merge Accounts or financial/booking assets.

## First-Principles Rules

- A phone match is evidence, not identity authority.
- Only a WeChat-verified member mobile can produce a candidate.
- The member and a site-authorized employee must both make explicit decisions.
- `Account`, global `MemberProfile`, tenant `Member`, tenant CRM fields and `Staff` remain separate ownership boundaries.
- Tenant access restrictions never disable the global Account, other tenants or the same person's Staff identity.
- The same Account may be both member and staff, but must not review its own member-link request.

## State Machine

```text
pending_member_confirmation
  -> pending_staff_review
     -> linked
     -> separate_approved
     -> rejected
     -> conflict

pending_* -> expired
```

`linked` assigns the existing lead to the Account. `separate_approved` preserves the lead and creates/reuses a separate Member. `rejected` keeps the candidate locked. `conflict` records that another tenant Member already exists and requires a future asset-aware reconciliation workflow.

## Delivered

- `member_link_requests` with opaque IDs, tenant/site/member/account composite constraints, optimistic version, evidence hash, active-request uniqueness and seven-day expiry.
- Member join returns `202 link_review` instead of silently linking or creating a duplicate when a verified candidate exists.
- Member `link/not_me` decision API and active-request query.
- Staff site-scoped review list and `approve_link/approve_separate/reject` API under `crm.member.link.review`.
- Current verified-mobile evidence is rechecked before linking with `lockForUpdate()` on profile/CRM rows.
- Self-review forbidden (`MEMBER_LINK_SELF_REVIEW_FORBIDDEN`).
- `approve_separate` requires prior member `not_me` (`MEMBER_LINK_DECISION_MISMATCH` otherwise).
- Business mutation and audit evidence commit in one transaction; staff audits attribute the actual staff Account and Staff profile.
- Member onboarding confirmation, member-home status warning and employee review page.
- Shared `TenantMemberAccessService` rejects blocked or closed tenant relationships while leaving global and cross-tenant abilities intact.

## Adversarial Decisions

- The legacy `transferToUser` immediate phone conversion is rejected and replaced by dual confirmation.
- The legacy `setUserNoLogin` global-looking toggle is replaced by tenant-local app access with reason and audit.
- Member rejection alone cannot create a new Member and bypass a blocked or disputed lead; staff review is still required.
- A rejected request remains active so repeated joins cannot create duplicates. A future privileged reopen operation is still required.
- If mobile evidence changes, staff approval fails with conflict and no Member is linked.
- Same-Account staff/member dual identity cannot self-approve a link request.

## Verification (2026-07-11 closeout)

| Check | Result |
|---|---|
| Backend full suite | **42 passed / 189 assertions** |
| MemberLinkingTest | 9 passed / 65 assertions |
| `pnpm typecheck` (member + staff) | PASS |
| DevTools skill / login | **0.2.5**, `Codex` client, `openid` present; MCP restored via `wechatide auth -c Codex` (port 39136) |
| Dist AppIDs | member `wx8778626edd0675bf`, staff `wx7c5af75c6472033d` |
| Real `wx.login` → `POST /auth/wechat/login` | **PASS** both `appType=member` and `appType=staff` via UI button tap (simulator codes) |
| `GET /me` | **PASS**; staff includes `crm.member.link.review` |
| Staff `pages/members/link-requests` authenticated | **PASS**; review card with seeded lead (`stage04-seed-staff-link-requests.png`) |
| `POST /auth/logout` | **PASS** |
| Member `GET /member/member-link-requests` | **PASS** API; returns 1 `pending_staff_review` after seed |
| Member home pending warning UI | **PASS**; `会员档案关联正在审核中` (`stage04-seed-member-home.png`) |
| Member onboarding page in simulator | **PASS** after UI login tap → `pages/onboarding/profile`; network shows login + onboarding APIs |
| `u-button` automator tap | **PASS** this session (`automation_element_action` tap fired `@click` on both apps) |
| OpenAPI paths / schemas | **24 paths / 45 schemas** |
| Default `pnpm build:*` API base URL | **PASS**; `.env.production` embeds `http://127.0.0.1:8010/api/v1` in both `dist/.../api/client.js` |

| Member blank page after rebuild | **Recovery SOP only**: `debug_clear_cache cleanAll` → reopen page; not a permanent code fix |

Evidence (under `docs/generated/`):

- Screenshots: `stage04-member-login-after-clear.png`, `stage04-member-onboarding-after-login.png`, `stage04-staff-link-requests-after-login.png`, `stage04-seed-member-home.png`, `stage04-seed-staff-link-requests.png`
- JSON (tokens redacted): `stage04-member-me-evidence.json`, `stage04-staff-me-evidence.json`, `stage04-staff-link-requests-api-evidence.json`, `stage04-staff-link-requests-network-evidence.json`, `stage04-member-link-requests-api-evidence.json`, `stage04-member-network-evidence.json`, `stage04-logout-evidence.json`, `stage04-seed-api-evidence.json`, `stage04-seed-member-ui-evidence.json`, `stage04-seed-staff-ui-evidence.json`, `stage04-seed-build-evidence.json`

## Local seed

`MemberLinkRequestSeeder` (invoked from `DatabaseSeeder` in `local`/`testing` only) idempotently:

- aligns account `#2` member profile/consent so home can load;
- syncs lead member `#1` CRM mobile evidence;
- creates one `pending_staff_review` link request for site `#1`.

Run: `php artisan db:seed --class=MemberLinkRequestSeeder`

## Remaining Work

- Detect and reconcile duplicates when the member initially joined without phone authorization and verifies a phone later.
- Design asset-aware Member reconciliation before cards, balances, orders, bookings or points can move between records.
- Add a privileged rejected-request reopen/cancel workflow and operational SLA notifications.
- Apply `TenantMemberAccessService` to every future tenant resource endpoint.
- Add production notification delivery for pending member/staff decisions.
- Add real DB concurrency proof for phone-evidence row locks beyond Feature tests.
