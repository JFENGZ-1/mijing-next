# Staff Miniapp UI Revert Notes

**Date:** 2026-07-13

## Checkpoint

Target session UUID `bbed7125-235f-444d-9547-004f8441a561` was **not found** in git history or agent transcripts (repo has no commits). Approximate restore point:

- **Before** Jul 13 DevTools capture sessions (`62e58be8`)
- **Before** uView Plus refactor subagent (`e1c03e0f`)
- **After** Jul 11 coach-picker fix on `session-form.vue` (~evening)

## Reverted (staff-miniapp)

| File | Change |
|------|--------|
| `apps/staff-miniapp/src/pages/course/session-form.vue` | Already native `<picker>` + `uni.showToast` (coach-picker fix retained) |
| `apps/staff-miniapp/src/pages/settings/booking-policy/index.vue` | `u-cell-group` → plain switch rows; `uni.$u.toast` → `uni.showToast` |
| `apps/staff-miniapp/src/pages/settings/courses/edit.vue` | `u-picker` / `u-modal` → native `<picker>` / `uni.showModal`; toasts → `uni.showToast` |
| `apps/staff-miniapp/src/pages/course/batch-tools.vue` | `u-subsection` / `u-checkbox` / `u-modal` → native buttons, checkbox, `uni.showModal` |
| `apps/staff-miniapp/src/env.d.ts` | Removed `uni.$u` TypeScript declarations added for uView toast |

## Not reverted

- **Overnight / new pages** (settings hub, reports, CRM extras, etc.) — kept unless they break login/home.
- **`docs/generated/*.js`** — DevTools scripts only reference `dist/build/mp-weixin`; no `src/` mutations found.
- **Member miniapp** `catalog.vue` uView refactor — out of scope (staff-only revert).
- **`docs/UI-UVIEW-PLUS.md`** — left in place; staff pages above no longer follow it for pickers/modals.

## Verified unchanged (coach-fix era)

- `apps/staff-miniapp/src/pages/login/index.vue` — WeChat login + `u-button` only
- `apps/staff-miniapp/src/pages/index/index.vue` — dashboard home (no uView picker/modal regressions)

## Next steps for you

1. In **微信开发者工具**，打开 `songguo-next/apps/staff-miniapp`，点击 **编译**（或先 `pnpm build:staff` 再打开 `dist/build/mp-weixin`）。
2. 执行 **清缓存 → 全部清除**（`cleanAll`）后再预览。
3. 使用 **员工微信登录** 进入工作台首页，确认课程 Tab / 新建排课 / 教练选择器可正常点击。
4. 若登录失败，检查后端 `127.0.0.1:8010` 与员工微信绑定（见 `docs/generated/` 内 DevTools 脚本说明）。
