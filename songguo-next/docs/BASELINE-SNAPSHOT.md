# Member-miniapp Baseline Snapshot

**Baseline name:** `d66f69ac-ui-parity`  
**Date established:** 2026-07-13  
**Source:** Subagent transcript `d66f69ac-c2b5-421a-be6c-be592d13344e` (07:33 UTC+8)

## What this baseline is

Stable member-miniapp UI state **after** the first legacy UI parity pass (green brand, tab bar, 首页/约课/我的 tabs) and **before**:

- `ffad78ec` — full sub-page gold-card / shared components
- `e86ca9cd` — performance (`usePageReady`, subPackages)
- `da1af825` / `27cc5f31` — tap latency / mine nav hacks
- `39981eb0` / `fa0c6c2d` — navigation refactor (`PageShell`, `utils/navigation.ts`)

## Core files at baseline

| Area | Files |
|------|-------|
| Theme | `src/styles/theme.scss`, `src/styles/common.scss`, `src/App.vue` |
| Routing | `src/pages.json` (flat pages, standard tab bar) |
| Tab pages | `pages/index`, `pages/booking/index`, `pages/booking/detail`, `pages/mine/index` |
| Assets | `src/static/tabbar/*` (copied from legacy 会员端) |

## Known-good follow-banner pattern (WXSS-safe)

Do **not** use `calc(17rpx + env(safe-area-inset-bottom))` — WeChat WXSS rejects `rpx` mixed with `env()` inside `calc()`.

```scss
.follow-banner-wrap {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  padding-bottom: env(safe-area-inset-bottom); /* px only */
}
.follow-banner {
  margin: 0 17rpx; /* rpx via margin, not calc */
  padding: 17rpx;
}
```

## Recommended: initialize git (one-time)

This workspace had **no commits** during the revert crisis, making every rollback manual transcript archaeology. To prevent that:

```bash
cd songguo-next
git init
git add .
git commit -m "Baseline: d66f69ac member-miniapp UI parity"
```

After each stable milestone, tag it:

```bash
git tag d66f69ac-ui-parity
```

## Verification commands

```bash
cd songguo-next
pnpm typecheck
pnpm build:member
```

DevTools project path:

```
songguo-next/apps/member-miniapp/dist/build/mp-weixin
```

## Do not modify

- `会员端/` and `管理端/` — read-only archaeology reference only
