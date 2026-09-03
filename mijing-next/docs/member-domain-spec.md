# Member Domain Specification

## Identity Boundaries

- `Account` is the global authentication principal for one natural person.
- A person may use the member client and also hold one or more staff profiles.
- `MemberProfile` is user-controlled personal information and verified contact state.
- `Member` is the relationship between an account and a tenant; it is not another login account.
- `MemberSite` records explicit site relationships inside the member tenant.
- OpenID is scoped to one AppID. UnionID or mobile may support a verified linking flow, but neither silently merges accounts.

## Profile State

Profile completeness is derived from server facts, never accepted from a client boolean.

```text
missing display name -> incomplete
missing required privacy consent -> incomplete
otherwise -> complete
```

Avatar, gender, birthday, height and weight are optional. Government identity numbers are outside this stage and must not be collected without a separately approved purpose, encryption and field-level authorization.

## Membership State

```text
lead -> active -> frozen -> closed
```

Profile completion does not automatically make a commercial member active. A user with no tenant membership remains a valid authenticated user and sees an empty site state. Joining a site creates or restores the tenant member and site relation through a server transaction.

## Lead-to-Account Linking

A staff-created lead remains an unlinked tenant `Member`; a verified mobile match is only candidate evidence.

```text
pending_member_confirmation
  -> pending_staff_review
     -> linked
     -> separate_approved
     -> rejected
     -> conflict
```

- The member chooses `link` or `not_me`; neither choice mutates `Member.account_id` directly.
- A site-scoped employee with `crm.member.link.review` makes the final decision with a reason.
- Approval rechecks the current verified mobile, locks the request and members, and writes audit evidence in the same transaction.
- `linked` attaches the existing lead to the Account without overwriting tenant CRM fields or global `MemberProfile` fields.
- `separate_approved` creates or reuses a different tenant Member while retaining the original lead.
- `rejected` remains locked against repeated self-service attempts. Pending requests expire after seven days and must be re-evidenced.
- If the Account already has another Member in the tenant, the request becomes `conflict`; asset merging is a separate reviewed workflow.

Phone authorization remains optional. Without a verified phone the system cannot detect a matching staff-entered lead, so later duplicate reconciliation remains required.

## Tenant Access Policy

`Member.app_access_status` and `Member.status` are tenant-local policy facts. Every tenant business endpoint must resolve the Account's Member for that tenant and call the shared access policy before cards, bookings, orders or other resources are returned or mutated.

- `blocked` or `closed` rejects the target tenant operation.
- Global onboarding/profile/legal APIs remain available.
- Other tenants remain available.
- Staff identities and staff tokens on the same Account remain unaffected.
- Tenant-local blocking must not clear the global member token.

## Legal Consent

- Legal documents are immutable version rows with draft, published and retired states.
- Consent records bind an account to the exact document version, time and source.
- Privacy policy and member agreement are separate documents.
- Registration cannot claim completion while a required published document is missing or unaccepted.

## API Surface

```text
GET    /api/v1/member/onboarding
PUT    /api/v1/member/profile
POST   /api/v1/member/profile/verify-mobile  # optional until a key business requires it
GET    /api/v1/member/sites
POST   /api/v1/member/memberships
GET    /api/v1/member/legal-documents
```

All routes require a `client:member` token. Staff tokens cannot mutate the member self-service profile unless a future explicit account-switch flow issues a member-context token.

## Security Invariants

- Phone authorization is optional for general entry. Key businesses may require `mobileVerified` as an explicit capability precondition.
- Phone numbers are encrypted at rest, indexed only by a keyed hash, and returned masked.
- A verified phone collision returns conflict and never merges accounts.
- WeChat phone codes are one-time values, never logged or stored.
- Profile updates use optimistic version checks.
- Site selection is resolved server-side to an active site and tenant.
- Membership creation is idempotent and same-tenant site relations are transactionally enforced.
- Consent document IDs must refer to currently published required documents.
- Member-client actions do not change staff roles, permissions or staff sessions.
- Mobile matches never auto-link a lead or merge Accounts.
- Link-request public IDs are opaque, requests are account/site scoped, versioned and expire.

## Deferred Decisions

- The old member QR page remains unreviewed because its payload and verifier are absent.
- SMS phone verification is required for production fallback but is not implemented in this stage.
- Account merge, export and erasure need dedicated reviewed workflows.
- Tenant CRM fields, tags, notes and follow-up ownership belong to the tenant `Member`, not the global profile.
