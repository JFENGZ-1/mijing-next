# Member UI Parity Notes

Read-only archaeology source: `会员端/` (legacy WeChat mini program).  
Active implementation: `songguo-next/apps/member-miniapp/`.

## Phase 1 — Legacy UI Audit

### Tab bar (`会员端/app.json`)

| Token | Legacy | member-miniapp (before) | member-miniapp (after) |
|---|---|---|---|
| Unselected text | `#505050` | `#667085` | `#505050` |
| Selected text | `#181818` | `#1677ff` | `#181818` |
| Background | `#FFFFFF` | `#ffffff` | `#FFFFFF` |
| Icons | `static/tabbar/*_icon.png` | placeholder PNGs | copied from legacy |

### Global window

| Token | Legacy | Before | After |
|---|---|---|---|
| Nav bar background | `#F5F5F5` | `#F8F8F8` | `#F5F5F5` |
| Page background | `#F5F5F5` | `#F5F7FA` | `#F5F5F5` |
| Default title | `我要约课` | `松果约课` | `我要约课` |

### Color tokens (from `app.wxss` + page styles)

| Role | Legacy | SCSS token |
|---|---|---|
| Primary / brand green | `#22c788` | `$color-primary` |
| Primary dark | `#1dac75` | `$color-primary-dark` |
| Primary light bg | `#ecf8f3` | `$color-primary-light` |
| Mine header yellow | `#fbd128` | `$color-accent-yellow` |
| Follow banner coral | `#e77a76` | `$color-accent-coral` |
| Accent pink / CTA | `#dc3c5c` | `$color-accent-pink` |
| Badge orange | `#fc8c00` | `$color-badge-orange` |
| Main text | `#181818` / `#303133` | `$color-text` / `$color-text-body` |
| Secondary text | `#7e7e7e` | `$color-text-secondary` |
| Muted text | `#989898` / `#bfbfbf` | `$color-text-muted` |
| Page background | `#f5f5f5` | `$color-page` |
| Card / list muted bg | `#fafafa` | `$color-surface-muted` |
| Notice gold text | `#865b00` | `$color-notice` |

### Layout patterns

**首页 (`pages/index`)**
- Full-width hero carousel (~458rpx), fixed/bleed top
- White sheet overlapping hero (`border-radius: 32rpx 32rpx 0 0`, `margin-top: -30rpx`)
- Site row: circular logo, 42rpx site name, switch control
- Address + phone/share foot row
- 4-column quick actions with colored 83rpx rounded squares
- Notice cards on warm gold background
- Tabs for appointment lists (`u-tabs` bar `#22C788`)
- Fixed coral follow-official-account banner

**约课 (`pages/appointmentCourse`)**
- White header panel with rounded bottom
- Horizontal week date strip; selected day green dot `#5fc48d`
- “返回今天” pill: green text on `#ecf8f3`
- Course list on `#fafafa` background
- Course cards: ~688×278rpx, 21rpx radius, image/gradient background, white typography
- Status badges: 可预约 green pill; 已预约 pink tones; disabled gray overlay

**我的 (`pages/mine`)**
- Yellow header `#fbd128` with avatar + greeting
- 4-up stats row (46rpx numbers, 19rpx labels)
- White sheet (`border-radius: 26rpx`, `margin-top: -25rpx`)
- Stacked member cards + menu list rows (110rpx height, 26rpx text)

### Typography / spacing deltas (before → after)

| Element | Legacy | Before (member-miniapp) | After |
|---|---|---|---|
| Section title | 32–34rpx, `#181818` | 32rpx, `#202124` | 32–34rpx, `#181818` |
| Card radius | 21–32rpx | 8rpx (`$radius-md`) | 21–32rpx |
| Page padding | Full-bleed hero + inset sheet | Uniform 24rpx `page-container` | Flush hero + sheet inset |
| Primary accent | Green `#22c788` | Blue `#1677ff` | Green `#22c788` |
| Mine header | Yellow band | White card block | Yellow band |

## Phase 2 — Implemented (this session)

### Global
- `src/styles/theme.scss` — legacy color/spacing tokens
- `src/styles/common.scss` — CSS variables, badge/menu utilities
- `src/App.vue` — uView Plus primary CSS vars → green
- `src/pages.json` — tabBar + globalStyle parity
- `src/static/tabbar/*` — legacy icons copied

### Pages
- `src/pages/index/index.vue` — hero, site sheet, quick actions, notice styling, follow banner
- `src/pages/booking/index.vue` — date strip, gradient course cards, badge pills
- `src/pages/booking/detail.vue` — green hero card, status badge
- `src/pages/mine/index.vue` — yellow header, stats row, menu list

## Phase 3 — Verification

Run from `songguo-next/`:

```bash
pnpm typecheck
pnpm build:member
```

### Before / after summary

| Area | Before | After |
|---|---|---|
| Brand color | Generic admin blue | Legacy Songguo green |
| Tab bar | Blue selected state | Dark gray selected + legacy icons |
| 首页 | Flat list/cards | Hero + overlapping site sheet + quick actions |
| 约课 | Button date picker + plain cards | Week strip + gradient course cards |
| 我的 | White card + button grid | Yellow header + stats + menu list |

## Remaining for Phase 2+

- Login / onboarding screens (`pages/login`, `pages/onboarding/profile`)
- Sub-pages: cards wallet visuals, notices list/detail gold cards, site detail hero
- Private coach horizontal scroll section on booking (legacy `约私教`)
- Course card background images from API (legacy uses `courseBacklog`)
- Member card stack/swipe component on mine (legacy `member-card` component)
- Home appointment list items — reuse legacy `appointment-list` row component styling
- Share button on home site foot row
- Custom nav bar / status bar immersion on mine (legacy fixed yellow status bar)
