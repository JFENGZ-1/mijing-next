# Revert: navigation / stutter soft-refresh experiments

**Date:** 2026-07-13  
**Baseline:** git tag `d66f69ac-ui-parity` (commit `5b0c34d`)

## What was rolled back

All member-miniapp experiments aimed at page-switch smoothness / soft-refresh (soft page load helpers, session caches such as site detail cache, page-level soft-nav wiring, related stores/utils, and soft-refresh docs) were discarded.

## How

- `git checkout d66f69ac-ui-parity -- songguo-next/apps/member-miniapp/`
- Removed untracked soft-nav docs (e.g. `docs/MEMBER-SOFT-REFRESH.md`) and any orphan soft-nav stores/utils if present
- Left unrelated untracked API audit docs intact; did **not** `reset --hard` the whole repo
- Did **not** touch `会员端/` or `管理端/`

## Note

Tried soft-refresh / cache approaches did not fix perceived stutter. Root cause may be WeChat `navigateTo` first-paint / DevTools behavior and needs a different approach later—not another soft-refresh layer on the same paths.
