# Revert Navigation Refactor (39981eb0 / fa0c6c2d)

**Date:** 2026-07-13  
**Requested by:** User — cancel navigation refactor changes

## What was reverted

Navigation refactor from subagents `39981eb0` (navigation layer) and `fa0c6c2d` (subpackage fix) was manually rolled back to the **ae0a8cf2 baseline** documented in `REVERT-SESSION-ae0a8cf2.md`.

### Deleted

- `apps/member-miniapp/src/utils/navigation.ts`
- `apps/member-miniapp/src/components/PageShell.vue`
- `docs/MEMBER-NAVIGATION-GUIDE.md`

### Restored

- `apps/member-miniapp/src/composables/member-context.ts` — module cache only (removed `useMemberPageLoad`, `useTabPageLoad`)
- `apps/member-miniapp/src/pages.json` — flat pages list (no `subPackages`, no `preloadRule`, standard mine nav)
- Tab pages from `d66f69ac` transcript snapshot: `pages/index`, `pages/booking/index`, `pages/booking/detail`, `pages/mine/index`
- Sub-pages to `requireMemberAuth` + `u-loading-page` pattern: `orders/index`, `cards/index`, `booking/my-appointments`

### Unchanged (per user request)

- Backend
- Staff miniapp
- Legal publish, wechat bindings, etc.
- `会员端/` and `管理端/`

## Verification

| Command | Result |
|---------|--------|
| `pnpm typecheck` | **PASS** |
| `pnpm build:member` | **PASS** |

## DevTools next step (user)

1. Open WeChat DevTools → project: `songguo-next/apps/member-miniapp/dist/build/mp-weixin`
2. Click **编译** (Compile)
3. Test: 首页 / 约课 / 我的 Tab 切换；我的 → 我的订单、我的预约、我的会员卡
