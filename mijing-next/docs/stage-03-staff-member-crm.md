# Stage 03: Staff Member CRM

## Status

The staff member CRM foundation is implemented and verified. It is not a complete member lifecycle system: explicit lead-to-account linking, closed/archive policy, card and balance domains, reassignment, and several database/audit hardening items remain release blockers for those capabilities.

## First-Principles Boundaries

- `Account` represents one natural person; `Staff` and tenant-scoped `Member` are independent roles of that account.
- A CRM lead may have no `account_id`. A mobile match is evidence only and never silently merges accounts or members.
- Member-owned profile data and tenant-owned CRM data are separate. Joining a tenant creates a CRM projection so staff operations never depend on a nullable CRM record.
- Staff-entered mobile is an unverified tenant contact. APIs return only a masked value.
- Member status, member-app access, cards, balances, validity, leave, orders, bookings and points are separate state machines.
- Notes and status/audit events are append-only. Hard deletion of a member and its revenue or appointment history is rejected.

## Delivered

- Tenant/site-scoped member list, search, pagination and detail.
- Unlinked lead creation and optimistic profile editing.
- Explicit `lead -> active`, `active -> frozen`, and `frozen -> active` transitions with reasons.
- Atomic owner claim, app-access changes and audit recording.
- Append-only internal notes and tenant-scoped tags.
- Member-app access blocking on the currently implemented tenant membership entry point.
- Automatic CRM projection when a member joins a tenant from the member Mini Program.
- Database composite tenant constraints for staff/site/role/member pivots.
- Employee Mini Program list, detail and create/edit pages using uni-app and uView Plus.
- Per-site permission payloads and a selectable current-site context; CRM controls now follow the selected site's permissions.
- Independent detail/notes/tag loading, correct add-only note behavior, stable edit drafts across `onShow`, and pagination that advances only after success.

## Verification

- OpenAPI 3.0.3 parsed successfully: 20 paths and 38 schemas.
- Laravel exposes 12 staff CRM routes, including both `PUT` and `PATCH` profile updates.
- Backend suite: 33 tests and 121 assertions passed.
- Member and staff TypeScript checks passed.
- Staff WeChat Mini Program production build passed.
- WeChat DevTools 0.2.5 was logged in and opened the built staff project. Real `wx.login`, staff API login, `/me`, lead creation, list and detail calls passed; the temporary token was revoked.
- DevTools inspection found that both Mini Programs were missing the uView Plus easycom map and theme variables; `<u-*>` tags had compiled as unknown elements. Both integrations were fixed and rebuilt, and the employee login button now renders correctly.
- After proper component registration, DevTools automation cannot cross the uView custom-component boundary to tap the internal native button. No production test bypass was added, so authenticated CRM-page visual automation remains pending manual click or improved component automation support.
- Legacy traceability remains complete as an inventory: after the subsequent CRM-adjacent review, 138 pages and 195 APIs remain explicitly `UNREVIEWED` and therefore blocking.

## Adversarial Findings and Disposition

- `app_access_status` originally changed data without enforcing behavior. Membership join now rejects the blocked tenant member. Every future tenant business endpoint must repeat this tenant-specific policy check; global profile/onboarding and the same account's staff role must not be blocked.
- Member self-join originally omitted `member_crm_profiles`. It now creates the tenant CRM projection transactionally from non-sensitive profile fields.
- Owner claim and app-access audit writes originally could commit separately. They now share the business transaction.
- Mobile verification originally used a read-then-save version check. Existing profiles now require a version and update by compare-and-swap.
- The legacy delete-member behavior remains rejected. `closed` is reserved but cannot be entered until closure effects on cards, orders, bookings, retention and reactivation are specified.
- Disposed CRM page rows do not mean feature parity. Their adjacent calls now have dispositions, but card, order, booking, points and configuration operations remain planned until their own stages are implemented and accepted.

## Remaining Release Work

- Build an explicit `unlinked lead -> link pending -> linked/rejected` workflow with verified challenge, conflict review, transaction locking and complete audit evidence.
- Decide whether masked mobile and birth date need separate field-read permissions; add a full CRM permission deny matrix.
- Define owner reassignment and behavior when an owner leaves employment.
- Add database checks for status values, same-tenant audit references, same-member note corrections and append-only database privileges or triggers.
- Add route/OpenAPI contract automation and generated TypeScript clients.
- Complete the remaining 138-page and 195-API archaeology before claiming legacy parity.
- Close the CRM-adjacent card, card ledger, order, booking history, points, dynamic fields, batch import and QR capabilities as separate reviewed domains; none are implied by the CRM foundation status.
