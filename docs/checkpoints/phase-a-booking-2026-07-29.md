# 还原点：阶段 A — 私教约课闭环（2026-07-29）

**Git 标签**：`checkpoint/phase-a-booking-2026-07-29`  
**Commit**：`b00e930` — `checkpoint: phase A private booking validated`

**状态**：产品侧已验收 OK。

---

## 2026-07-29 当日工作记录（本会话）

承接阶段 A 执行与验收问题修复，并完成还原点提交。

### 1. A1 收尾与修复

| 事项 | 说明 |
|------|------|
| `CoachPrivateProfileService::resolveBookableSession` | 去掉多余 `{`，修复 ParseError / 500 |
| `member-miniapp` `booking/coach.vue` | 选卡展示对齐 `MemberCardWalletSummary`（`name` / `cardNoMasked` / `cardType`） |
| `MemberPrivateCoachBookingTest` | 会员拉私教 `time-slots` 冒烟测试 |
| 编译 | 会员端、员工端 `build:mp-weixin` 通过 |

### 2. 员工代约「团课重合」选不中（验收阻塞）

**现象**：策略为 `overlap_warn` 时，团课时段有人预约则槽位全灰，无法验证员工确认代约。

**原因**：`PrivateCoachAvailabilityService` 在 `overlap_warn` 下，团课若已有预约走 `hard`，不返回 `groupOverlapWarn`。

**修改**：`overlap_warn` 时与团课时间重叠一律标 `warn`（与是否已有团课预约无关）；已有预约的**私教**仍硬挡。

**测试**：`PrivateCoachBookingPolicyTest::test_group_conflict_overlap_warn_marks_staff_warn_even_with_group_bookings`

### 3. 代约无「与团课时间重合，是否仍要代约？」弹窗

**原因**：选「团课重合」后 `bookGroupOverlapAck` 在提交时直接带 `acknowledgeGroupOverlap: true`，接口一次成功，不走弹窗。

**修改**（`coach-board.vue`）：

- 抽出 `confirmGroupOverlapBook()`
- **确认代约**前先弹窗，用户确定后再带 `acknowledgeGroupOverlap: true`
- `submitBookWithPayload` 不再用选中槽位隐式 ack；仅显式参数为 `true` 时提交
- 接口仍返回 `COACH_PRIVATE_GROUP_OVERLAP` 时作兜底，同样弹窗重试

### 4. A4：修改预约时间对齐代约策略

**现象**：教练看板「修改预约」选团课重合时段无确认弹窗；后端改节未校验团课重合。

**后端**：

- `ScheduleSessionWriteService::update`：私教节改 `startsAt` / `endsAt` / `coachStaffId` 时调用 `assertPrivateCoachScheduleSlot`（与代约同一套 `PrivateCoachAvailabilityService::assertBookableSlot`）
- `UpdateScheduleSessionRequest` + 前端类型：支持 `acknowledgeGroupOverlap`

**前端**（`coach-board.vue`）：

- `retimeGroupOverlapAck`、`pickRetimeSlot` 与代约一致
- `submitRetime` / `submitRetimeWithPayload`：提交前弹窗 + 兜底错误码
- 改约时间槽：`canPickBookSlot`、「团课重合」样式与代约一致

**测试**：`PrivateCoachBookingPolicyTest::test_private_session_retime_requires_group_overlap_acknowledgement`

### 5. 文档与概念澄清（写入本 checkpoint）

- **`acknowledgeGroupOverlap`**：员工请求字段，表示已确认「与团课重合仍代约/改约」；会员端不可用。
- **团课 21:00、槽位 20:15 标重合**：按私教 **[开始, 开始+时长]** 与团课时段相交计算，非开始时间必须相同。
- **A4 含义**：员工改私教时间规则与代约对齐；看板「修改预约」已接；`session-detail` / `session-form` 等入口未统一扫尾。

### 6. 还原点

- 新增本文档目录 `docs/checkpoints/`
- Git commit `b00e930` + 标签 `checkpoint/phase-a-booking-2026-07-29`（未 push 远程）
- 未纳入提交：`.tmp-shot-*.png`、`server/tmp/`

### 当日涉及的主要文件（增量焦点）

```
mijing-next/apps/server/app/Services/Booking/PrivateCoachAvailabilityService.php
mijing-next/apps/server/app/Services/Schedule/ScheduleSessionWriteService.php
mijing-next/apps/server/app/Services/Catalog/CoachPrivateProfileService.php
mijing-next/apps/server/app/Http/Requests/UpdateScheduleSessionRequest.php
mijing-next/apps/server/tests/Feature/PrivateCoachBookingPolicyTest.php
mijing-next/apps/server/tests/Feature/MemberPrivateCoachBookingTest.php
mijing-next/apps/staff-miniapp/src/pages/course/coach-board.vue
mijing-next/apps/staff-miniapp/src/types/scheduling.ts
mijing-next/apps/member-miniapp/src/pages/booking/coach.vue
```

---

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
cd mijing-next/apps/server
php artisan test --filter=PrivateCoachBookingPolicyTest
php artisan test --filter=MemberPrivateCoachBookingTest
php artisan test --filter=ScheduleSessionManagementTest
```

```bash
cd mijing-next/apps/staff-miniapp && pnpm run build:mp-weixin
cd mijing-next/apps/member-miniapp && pnpm run build:mp-weixin
```

## 还原方式

```bash
git checkout checkpoint/phase-a-booking-2026-07-29
# 或
git reset --hard b00e930
```
