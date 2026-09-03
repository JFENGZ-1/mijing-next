# Stage 02: Member Foundation

## Status

Backend and member Mini Program foundation implemented. Production completion remains blocked by real legal text, production phone verification credentials, object storage for avatars and the unresolved legacy QR-code purpose.

## Delivered

- Full archaeology and disposition review for 35 member pages and 49 app-scoped legacy endpoints.
- One global `MemberProfile` per account, independent from staff identities.
- Tenant-scoped `Member` and same-tenant `MemberSite` relations.
- Versioned immutable legal documents and append-only consent evidence.
- Encrypted verified mobile storage, keyed lookup hash and masked API output.
- Verified-mobile collision returns conflict and never merges accounts.
- Profile optimistic locking and server-derived registration state.
- Member-client token middleware; staff-client tokens cannot use member self-service APIs.
- Active site discovery and idempotent membership joining with server-derived tenant scope.
- Login response and `/me` now expose persisted registration state.
- Shared lock around first WeChat identity login to serialize `(AppID, OpenID)` account creation.
- Member onboarding and profile pages with loading, refusal, conflict and configuration states.

## Registration State

```text
configuration_required
profile_required
consent_required
complete
```

The state is calculated from persisted profile and current required consent records. Phone authorization is optional for general entry; key businesses may separately require `mobileVerified`. The client cannot submit or override completion.

## Verification

- 22 backend tests and 64 assertions passed.
- Member and staff TypeScript checks passed.
- Member WeChat build completed.
- Real member `wx.login` exchange returned `profile_required`.
- Real authenticated calls returned one published local-development legal document and one available site.
- Verification tokens were revoked after checks.
- WeChat phone exchange is covered with provider fakes. A local/testing-only CLI override is available for frontend state simulation and is disabled outside those environments.

Local simulation command:

```text
php artisan member:set-test-mobile <number> --account-id=<id> --confirm
```

The command never prints the full number and records `mobile_verification_method=local_test_override`.

## Traceability

- Member pages: 34 reviewed, 1 blocked.
- Member APIs: 49 reviewed, 0 unreviewed.
- `pageHome/QRcode/QRcode` remains `UNREVIEWED`: the compiled artifact does not identify whether it is an identity, card or redemption code.
- Direct avatar upload existed outside the old 49-API wrapper catalog. New avatar upload is intentionally blocked until authenticated object storage is implemented.

## Production Blockers

- Replace `local-development-privacy.md` with operator-owned, legally reviewed privacy and member agreement versions. The local document must never be published to users.
- Configure and verify production `getPhoneNumber`; add SMS OTP fallback, rate limiting and abuse controls.
- Build authenticated image upload, MIME/size validation, malware scanning and object lifecycle policy.
- Add trusted UnionID scope and explicit account-link challenge workflows; never use naked UnionID or mobile for silent merging.
- Add account closure/export/erasure and reviewed account/member merge workflows.
- Add CRM tags, append-only notes, owner staff and field-level authorization in the staff administration stage.
- Resolve the legacy member QR code with product and verifier evidence before implementation.
