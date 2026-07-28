# 还原点：阶段 A — 私教约课闭环（2026-07-29）

**Git 标签**：`checkpoint/phase-a-booking-2026-07-29`（与本次 commit 同锚点）

**状态**：产品侧已验收 OK。

## 已定产品规则

1. **管理端（员工）不受会员预约策略限制**：代约/取消跳过提前天数、截止、每日上限等；仍受教练时间冲突与团课重合策略约束。
2. **`overlap_warn` 仅员工可确认**：会员端无 `acknowledgeGroupOverlap`；员工代约/改约可选「团课重合」槽并弹窗确认后提交。

## 阶段 A 清单

| 项 | 内容 | 状态 |
|----|------|------|
| A1 | 会员私教：profile / time-slots / payable-cards / book + `booking/coach.vue` | ✅ |
| A2 | 员工代约团课重合：槽位「团课重合」+ 弹窗 + `acknowledgeGroupOverlap` | ✅ |
| A3 | 员工代约日期条不绑 `private.advanceBookingDays`（较长可选日） | ✅ |
| A4 | 教练看板「修改预约」：`time-slots` + 改节 `PATCH schedule-sessions` 团课重合校验与确认 | ✅（看板入口） |

**A4 边角（未统一）**：`session-detail` 改约列表、`session-form` 改时间等入口若未接同一校验，后续阶段可扫。

## 关键路径

- 会员私教：`member-miniapp` → `pages/booking/coach.vue` → `GET/POST /api/v1/member/booking/private-coaches/*`
- 员工私教看板：`staff-miniapp` → `pages/course/coach-board.vue` → `private-coaches/{profile}/time-slots|book`
- 改预约时间：`PATCH /api/v1/staff/sites/{site}/schedule-sessions/{id}` + `acknowledgeGroupOverlap`
- 预约策略：`BookingPolicyService` + `PrivateCoachAvailabilityService`；员工设置 `pages/settings/booking-policy`

## 团课重合说明（验收常问）

- 判定为 **私教整段 [开始, 开始+课目时长]** 与 **团课排课时段**（及课后准备分钟）是否相交，不是「开始时间必须相同」。
- 例：团课 21:00 开始、私教 60 分钟 → **20:15 开始**会标重合（20:15–21:15 与 21:00 重叠）。

## 建议测试命令

```bash
cd songguo-next/apps/server
php artisan test --filter=PrivateCoachBookingPolicyTest
php artisan test --filter=MemberPrivateCoachBookingTest
php artisan test --filter=ScheduleSessionManagementTest
```

```bash
cd songguo-next/apps/staff-miniapp && pnpm run build:mp-weixin
cd songguo-next/apps/member-miniapp && pnpm run build:mp-weixin
```

## 还原方式

```bash
git checkout checkpoint/phase-a-booking-2026-07-29
# 或
git reset --hard <本标签指向的 commit>
```
