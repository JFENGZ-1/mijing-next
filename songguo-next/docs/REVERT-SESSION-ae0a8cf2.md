# Revert to Session ae0a8cf2-f180-4c0d-9501-2ceffa1acb48

**Date:** 2026-07-13  
**Requested by:** User (restore code before recent member-miniapp perf/UI regressions)

## Session ID lookup

| Check | Result |
|-------|--------|
| Transcript folder `ae0a8cf2*` | **Not found** (not a stored agent-transcript UUID) |
| Cursor checkpoint files | **Not found** |
| Cursor logs (`requestTraces.log`) | **Found** — `ae0a8cf2-f180-4c0d-9501-2ceffa1acb48` is a **Composer requestId** on parent chat `56d3be75-8db3-49b0-9791-f6cf80cf53af` |
| Timestamp | **2026-07-13 07:43 AM (UTC+8)** — user message: 「继续优化会员端，直至全部完成」 |

**Interpretation:** Restore point = codebase **after** initial UI parity pass (subagent `d66f69ac`, 07:33) and **before** subsequent work:

- `ffad78ec` — full sub-page UI parity (07:43+)
- `e86ca9cd` — performance guide, subPackages, `usePageReady` (08:05+)
- `da1af825` / `27cc5f31` / `bc89e034` / `6db2db5e` / `37202b1b` — tap latency, mine nav, UI flash fixes

No git history exists in this workspace; restore was **manual** from transcript snapshots + reversing known post-checkpoint changes.

## Limitations (honest)

- **Exact byte-for-byte restore impossible** without Cursor checkpoint or git.
- **Approximate restore** to post-`d66f69ac` state: main tab UI parity kept; sub-pages reverted to simple `u-loading-page` + standard `onShow` (not ffad78ec gold-card / private-coach strip styling).
- **Staff-miniapp** unchanged except verified build still passes.
- **Backend** unchanged.
- **会员端/** and **管理端/** untouched.

## Files reverted / removed

### Deleted (added after checkpoint)

- `apps/member-miniapp/src/composables/usePageReady.ts`
- `apps/member-miniapp/src/composables/useCachedRequest.ts`
- `apps/member-miniapp/src/utils/tap.ts`
- `apps/member-miniapp/src/utils/course-style.ts`
- `apps/member-miniapp/src/components/PageSkeleton.vue`
- `apps/member-miniapp/src/components/MemberAppointmentRow.vue`
- `apps/member-miniapp/src/components/MemberWalletCard.vue`
- `docs/MEMBER-PERFORMANCE-GUIDE.md`
- `apps/member-miniapp/README.md` (perf pointer)

### Restored / simplified

- `apps/member-miniapp/src/pages.json` — **flat pages list** (no `subPackages`, no `preloadRule`, standard mine nav)
- `apps/member-miniapp/src/api/client.ts` — simple `createApiClient` (no GET dedup)
- `apps/member-miniapp/src/composables/member-context.ts` — module cache (no TTL/`cachedRequest`)
- `apps/member-miniapp/src/api/member.ts` — direct wallet card APIs
- `apps/member-miniapp/src/styles/common.scss` — d66f69ac baseline utilities
- Tab pages from transcript snapshot: `pages/index`, `pages/booking/index`, `pages/booking/detail`, `pages/mine/index`
- All sub-pages: removed `usePageReady` / `tap` / skeleton components; restored `requireMemberAuth` + `u-loading-page` pattern

### Docs

- `docs/MEMBER-UI-PARITY-NOTES.md` — restored to post-Phase-2 (d66f69ac) checklist

## Verification

| Command | Result |
|---------|--------|
| `pnpm typecheck` | **PASS** |
| `pnpm build:member` | **PASS** |
| `pnpm build:staff` | **PASS** |

## DevTools next step (user)

1. Open WeChat DevTools → project: `songguo-next/apps/member-miniapp/dist/build/mp-weixin`
2. Click **编译** (Compile) — do **not** run `cleanAll` unless blank page returns
3. Test: 首页 / 约课 / 我的 Tab 切换；我的 → 我的订单、我的预约
4. Expected vs pre-revert: no top white strip / bottom tab void during transition; no `usePageReady` module errors

## If user needs exact checkpoint

Use Cursor UI **Restore Checkpoint** on the chat message with requestId `ae0a8cf2-…` (07:43 AM), if still available — that is the only path to a guaranteed exact snapshot.
