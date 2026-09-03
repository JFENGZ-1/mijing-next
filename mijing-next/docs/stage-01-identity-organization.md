# Stage 01: Identity and Organization

## Status

Implemented and verified for the current local scope. Stage 01 is not a production-release approval: the blocking and deferred items below remain explicit inputs to later stages.

## Delivered

- Request IDs and consistent API success/error envelopes.
- WeChat code exchange with separate member and staff AppID/secret configuration.
- One natural-person `Account` can hold both member and staff identities.
- App-scoped `(appid, openid)` identities with encrypted session keys; fixed `test` codes fail validation.
- Seven-day Sanctum access tokens, current-token logout and client-specific abilities.
- Staff tokens carry an explicit staff and tenant context; member tokens cannot call staff APIs.
- Active account, tenant, staff and assigned-site checks on staff operations.
- Tenant, site, account, WeChat identity, staff, role and permission models.
- Site list/create/show/update/logical-disable endpoints; update uses optimistic locking.
- Cross-tenant and unassigned-site reads return 404.
- Idempotent local bootstrap and explicit staff identity binding commands.
- Member/staff login pages, startup `/me` validation, 401 cleanup and protected-page deep-link guards.
- Full legacy traceability shells for 185 pages and 313 app-scoped endpoints.

## Identity Decisions

- `Account` represents a natural person, not an application role.
- The same account may be a customer and an employee at the same time.
- OpenID is only unique inside one AppID. Cross-AppID linking may use a verified UnionID where available; otherwise it requires an explicit verified binding flow.
- Application sessions are contextual: a member-client token grants member capabilities, while a staff-client token grants only one validated staff/tenant context.
- Client-provided tenant, site, role or permission claims are never trusted as authorization facts.

## Adversarial Findings Closed

1. Tests now force `mijing_next_test` and fail closed if the resolved database differs.
2. Role permissions use explicit collection flattening and have regression coverage.
3. Member/staff WeChat apps were separated and both were exercised with real `wx.login` exchanges.
4. A member token for an account that is also staff is rejected by staff APIs.
5. Disabled accounts and tenants lose protected staff access with existing tokens.
6. WeChat HTTP failures are converted to sanitized domain errors without propagating sensitive request URLs.
7. Local tokens are treated as unverified credentials until `/me` succeeds; deep links and 401 responses return to login.

## Verification

- MySQL migrations and local bootstrap completed.
- 14 backend tests and 29 assertions passed.
- Member and staff TypeScript checks passed.
- Real member exchange: account resolved, no staff context exposed, staff API rejected, token revoked.
- Real staff exchange: same account resolved, one staff context, one assigned site, five permissions, site-manage permission present, one site returned, token revoked.
- AppSecret and one-time login codes were not written to this report.

## Blocking Before Production

- Review all `UNREVIEWED` rows in `docs/traceability-pages.csv` and `docs/traceability-apis.csv`.
- Add database-enforced or transaction-enforced same-tenant constraints for staff/site/role pivots before role administration is writable.
- Add append-only audit events for login, binding, permission changes, staff departure and destructive operations.
- Make CLI staff binding tenant-specific; employee number alone is not globally unique.
- Define and implement verified cross-AppID account linking when UnionID is absent.
- Add production domain, privacy, consent, upload, payment and WeChat release checks.

## Deferred With Explicit Ownership

- Refresh-token rotation, device list, same-device token replacement, global logout and expired-token pruning.
- Multi-tenant staff-context selection UI for one account employed by more than one tenant.
- Staff departure workflow that revokes all affected staff sessions immediately.
- Site rooms/capacity, business hours, special closures, typed tenant settings and agreement versions.
- Generated TypeScript API client and automated OpenAPI contract tests.

See `stage-acceptance-matrix.md` for the current gate state.
