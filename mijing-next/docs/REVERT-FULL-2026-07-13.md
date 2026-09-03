# Full Revert — 2026-07-13

**Target:** `d66f69ac-ui-parity` (before perf/nav/mine-nav/wxss hacks)  
**Method:** Manual transcript restore + bug fixes (no git reset/checkout)  
**Scope:** `mijing-next/apps/member-miniapp/` only

## Phase 1 — Errors found (before fix)

| Check | Result |
|-------|--------|
| `pnpm build:member` | **PASS** (build was green; runtime was broken) |
| `calc(rpx + env())` in src/dist | **None** in current tree |
| Illegal WXSS in d66f69ac baseline `index.vue` | `calc(17rpx + env(safe-area-inset-bottom))` in transcript — **already fixed** in working tree |
| `MemberAppointmentRow` without component file | **BROKEN** — stats page referenced deleted component |
| `usePageReady` removed but `loading` never cleared | **11 sub-pages stuck on `u-loading-page` forever** |
| `notice-card-gold` / `surface-card` / `hero-*` classes | Orphaned ffad78ec classes with no styles in `common.scss` |

### Stuck-loading pages (root cause of “blank/broken UI”)

1. `pages/cards/benefits.vue`
2. `pages/cards/catalog.vue`
3. `pages/cards/hidden.vue`
4. `pages/cards/transfer.vue`
5. `pages/follow/official-account.vue`
6. `pages/legal/index.vue`
7. `pages/mine/profile.vue`
8. `pages/notices/detail.vue`
9. `pages/notices/index.vue`
10. `pages/orders/result.vue`
11. `pages/sites/detail.vue`

## Phase 2 — d66f69ac baseline comparison

Compared current files to replayed transcript `d66f69ac-c2b5-421a-be6c-be592d13344e`:

| File | Status |
|------|--------|
| `styles/theme.scss` | MATCH |
| `styles/common.scss` | MATCH |
| `pages/booking/index.vue` | MATCH |
| `pages/booking/detail.vue` | MATCH |
| `pages/mine/index.vue` | MATCH |
| `pages/index/index.vue` | DIFFER (intentional: WXSS-safe follow-banner wrapper) |

Tab pages and theme already matched d66f69ac. Sub-pages needed post-revert bug fixes, not full rewrites.

### Confirmed absent (post-checkpoint artifacts)

- `src/utils/navigation.ts` — not present
- `src/components/PageShell.vue` — not present
- `src/composables/usePageReady.ts` — not present
- `src/utils/tap.ts`, `course-style.ts` — not present
- `src/components/MemberAppointmentRow.vue`, `MemberWalletCard.vue`, `PageSkeleton.vue` — not present

`src/auth/navigation.ts` **kept** — this is login/onboarding redirect helper (predates nav refactor), not the deleted `utils/navigation.ts`.

## Phase 3 — Files touched (this session)

### Loading fix (`finally { loading.value = false }`)

- `src/pages/cards/benefits.vue`
- `src/pages/cards/catalog.vue`
- `src/pages/cards/hidden.vue` (also removed stale `usePageReady` comment)
- `src/pages/cards/transfer.vue`
- `src/pages/follow/official-account.vue`
- `src/pages/legal/index.vue`
- `src/pages/mine/profile.vue`
- `src/pages/orders/result.vue`
- `src/pages/sites/detail.vue`

### ffad78ec artifact cleanup

- `src/pages/notices/index.vue` — reverted to simple `notice-card` markup + fixed loading
- `src/pages/notices/detail.vue` — reverted to simple title/time/body + fixed loading
- `src/pages/mine/stats.vue` — restored inline `appointment-card` rows (removed `MemberAppointmentRow`)
- `src/pages/legal/index.vue` — replaced orphan `surface-card` with `doc-header`
- `src/pages/cards/transfer.vue` — local `hero-green` styles
- `src/pages/follow/official-account.vue` — local `hero-coral` styles
- `src/pages/mine/profile.vue` — yellow header background (removed orphan `hero-yellow` class)

### Unchanged (already correct)

- `src/pages/index/index.vue` — follow-banner wrap pattern (WXSS-safe)
- `src/styles/theme.scss`, `common.scss`, `App.vue`, `pages.json`
- Tab pages: `booking/index`, `booking/detail`, `mine/index`

### Docs added

- `docs/BASELINE-SNAPSHOT.md`
- `docs/REVERT-FULL-2026-07-13.md` (this file)

## Phase 4 — Verification

| Command | Result |
|---------|--------|
| `pnpm typecheck` | **PASS** |
| `pnpm build:member` | **PASS** |
| Stuck `loading` pages | **0** remaining |
| `calc(rpx+env)` in src/dist wxss | **None** |
| DevTools `simulator_open_page` index | **PASS** |
| DevTools `compile_wxss` index.wxss | **PASS** |

## What was still broken before this session

1. **11 sub-pages infinite loading spinner** — main user-visible regression after `usePageReady` removal
2. **stats.vue** — runtime error from missing `MemberAppointmentRow` component
3. **notices/legal/hero pages** — ffad78ec CSS class names with no stylesheet definitions

## Next steps for you

1. **Initialize git** (strongly recommended):

   ```bash
   cd mijing-next
   git init
   git add .
   git commit -m "Baseline: d66f69ac member-miniapp stable"
   git tag d66f69ac-ui-parity
   ```

2. **DevTools smoke test** — open `dist/build/mp-weixin`, compile, test:
   - 首页 / 约课 / 我的 tabs
   - 我的 → 我的订单、我的预约、上课统计
   - 场馆通知、购卡续费 sub-pages (should no longer hang on loading)

3. If anything still looks wrong, use **git diff** against the tag instead of transcript archaeology.
