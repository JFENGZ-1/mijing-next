# 当前对话工作总结（阶段 A 私教约课 · 2026-07-29）

本文档记录**本对话线程**内完成的工作、问题修复与**功能骨架**（便于接手或对照代码）。与还原点文档互补：

- 还原点：`phase-a-booking-2026-07-29.md`（标签 / commit / 验收命令）
- 本文：对话过程 + 架构骨架

---

## 一、对话背景与目标

在「预约设置 P0 + 私教约课对标原版」背景下，用户要求**开始执行阶段 A**，并在前端验收中暴露：

- 会员私教选时段、员工代约/改约
- 团课重合策略 `overlap_warn` 的员工确认体验
- 修改预约时间无确认弹窗、重合槽选不中等问题

对话结束时：**阶段 A 产品验收 OK**，并建立 Git 还原点 `checkpoint/phase-a-booking-2026-07-29`（`b00e930`）。

---

## 二、已定产品规则（贯穿实现）

| 规则 | 行为 |
|------|------|
| 管理端不受会员策略限制 | 员工代约/取消：跳过提前天数、截止预约/取消、会员每日预约上限等；**仍**受教练时间冲突、团课重合策略约束 |
| `overlap_warn` 仅员工可确认 | 会员端**无** `acknowledgeGroupOverlap`；重合槽不可约或仅展示不可选；员工可选「团课重合」槽，**弹窗确认后**再提交 |
| 团课重合判定 | 私教区间 `[开始, 开始+课目时长]` 与团课 `[开始, 结束+课后准备]` **时间相交**即重合，非「开始时刻相同」 |

---

## 三、功能骨架（阶段 A 相关）

### 3.1 后端分层

```
预约策略
  BookingPolicyService
    ├── policyForTenantSite(tenant, site) → group / private 配置
    ├── 团课：提前天数（含日切）、每日预约上限、自动取消最低人数（定时任务）
    └── 私教：slotIntervalMinutes、preparationMinutes、grayOutBookedSlots、groupConflictMode

私教可约时段（核心）
  PrivateCoachAvailabilityService
    ├── buildSlotsForDay() → slots[{ start, startsAt, available, groupOverlapWarn? }]
    ├── evaluateSlot() / sessionBlocksSlot() → none | hard | warn
    └── assertBookableSlot() → 422 COACH_PRIVATE_GROUP_OVERLAP | COACH_PRIVATE_TIME_CONFLICT

私教教练档案
  CoachPrivateProfileService
    ├── timeSlots(site, profile, date, courseId, excludeSessionId?)
    ├── resolveBookableSession(staff?, site, profile, payload)  // 代约动态建 private session
    └── memberPresentation(profile)

会员私教约课
  MemberPrivateCoachBookingService
    ├── profile / timeSlots / payableCards / book
    └── applyMemberSlotRules()  // 去掉 groupOverlapWarn、最少提前、会员策略

员工写预约
  AppointmentWriteService / AppointmentFulfillmentService
    └── createdByStaffId 时跳过会员侧截止/提前/上限等

改私教节时间（A4 看板入口）
  ScheduleSessionWriteService::update
    └── assertPrivateCoachScheduleSlot() + acknowledgeGroupOverlap

定时任务
  GroupSessionAutoCancelService + schedule:auto-cancel-under-min
```

### 3.2 HTTP API 骨架

**会员**（`MemberPrivateCoachController`，前缀 `/api/v1/member/booking/private-coaches`）

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `profile` | 教练私教档案、课目列表 |
| GET | `time-slots` | 日期+课目 → 槽位（会员策略过滤） |
| GET | `payable-cards` | 选定 date+start → 可扣费卡 |
| POST | `book` | 预约（**无** acknowledgeGroupOverlap） |

**员工私教**（`StaffCoachPrivateProfileController`，`/api/v1/staff/sites/{site}/private-coaches/...`）

| 方法 | 路径 | 作用 |
|------|------|------|
| GET/POST/... | `save`、profile CRUD | 档案与课目 |
| GET | `{profile}/time-slots` | 代约/改约选时（含 groupOverlapWarn） |
| POST | `{profile}/book` | 代约，body 可含 `acknowledgeGroupOverlap` |

**改排课时间**（A4）

| 方法 | 路径 | 作用 |
|------|------|------|
| PATCH | `/api/v1/staff/sites/{site}/schedule-sessions/{id}` | 改 startsAt/endsAt 等；私教节校验团课重合；`acknowledgeGroupOverlap` |

**预约策略**

| 方法 | 路径 | 作用 |
|------|------|------|
| GET/PUT | staff booking-policy | `private.groupConflictMode` 等 P0 字段 |

### 3.3 前端页面骨架

**会员端 `member-miniapp`**

```
约课首页 booking/index.vue
  └── 教练入口 → booking/coach.vue
        ├── getMemberPrivateCoachProfile
        ├── getMemberPrivateCoachTimeSlots（按日/课目）
        ├── getMemberPrivateCoachPayableCards（点槽后）
        └── bookMemberPrivateCoach
```

**员工端 `staff-miniapp`**

```
课程 coach-board.vue（私教教练看板）
  ├── 时间线：取消 / 旷课 / 修改预约 / 备注
  ├── 代约弹窗
  │     member-picker → [课目] → 日期条 + time-slots → member-card-picker → 确认
  │     团课重合：槽显示「团课重合」→ confirmGroupOverlapBook → book + acknowledgeGroupOverlap
  └── 修改预约弹窗（与代约共用 time-slots + excludeSessionId）
        pickRetimeSlot + confirmGroupOverlapBook → updateStaffScheduleSession

设置 booking-policy/index.vue
  └── 私教：间隔、课前休息、置灰、与团课重合（block / allow / overlap_warn）
```

### 3.4 团课重合数据流（员工）

```
time-slots API
  → slot.groupOverlapWarn === true
  → canPickBookSlot = available || groupOverlapWarn
  → 用户点「确认代约/确定」
  → confirmGroupOverlapBook()  // 必须先弹窗
  → API acknowledgeGroupOverlap: true
  → assertBookableSlot(..., allowGroupOverlapWarn: true) 通过
```

会员侧同一 `buildSlotsForDay` 结果经 `MemberPrivateCoachBookingService` 剥离 `groupOverlapWarn` 且不可选。

---

## 四、本对话内完成的工作（按主题）

### 4.1 执行与收尾（A1）

- 修复 `CoachPrivateProfileService` 双 `{` 语法错误（会员/员工 time-slots 500）
- 会员 `coach.vue` 选卡字段与类型对齐
- 新增 `MemberPrivateCoachBookingTest`（time-slots 冒烟）
- 会员端、员工端微信小程序构建通过

### 4.2 验收问题修复

| 问题 | 处理 |
|------|------|
| 有人的团课时段无法选「团课重合」 | `overlap_warn` 下团课重叠一律 `warn`，不再因有预约变 `hard` |
| 代约不出现确认弹窗 | 去掉选中槽位自动 ack；提交前 `confirmGroupOverlapBook` |
| 修改预约无弹窗、后端不拦重合 | A4：`ScheduleSessionWriteService` + `coach-board` 改约流程对齐代约 |
| 20:15 与 21:00 团课为何重合 | 文档说明：按时段相交 + 私教时长 |

### 4.3 测试补强

- `PrivateCoachBookingPolicyTest`：有预约团课仍 `groupOverlapWarn`；私教改节需 ack
- 既有 `ScheduleSessionManagementTest`、`StaffAppointmentTest` 等保持通过

### 4.4 工程与备忘

- 文档 `docs/checkpoints/phase-a-booking-2026-07-29.md`（还原点 + 当日变更）
- Git：`b00e930` + tag `checkpoint/phase-a-booking-2026-07-29`
- 概念说明：`acknowledgeGroupOverlap`、A1 vs A4 范围

---

## 五、阶段 A 清单状态（对话结束时）

| 项 | 内容 | 状态 |
|----|------|------|
| A1 | 会员私教 profile / slots / 选卡 / book | ✅ |
| A2 | 员工代约团课重合 UI + API ack | ✅ |
| A3 | 员工代约日期条不绑会员 private 提前天数 | ✅ |
| A4 | 看板「修改预约」与代约同一套槽位 + 改节校验 + 弹窗 | ✅ |

**未纳入 A4 统一（后续可做）**

- `session-detail.vue` 改约私教课（另一套列表 + `rescheduleStaffAppointment`）
- `session-form.vue` 直接改排课时间
- OpenAPI 文档同步

---

## 六、关键文件索引（改动力集中）

| 区域 | 路径 |
|------|------|
| 槽位与重合 | `app/Services/Booking/PrivateCoachAvailabilityService.php` |
| 会员私教 | `MemberPrivateCoachBookingService.php`、`MemberPrivateCoachController.php` |
| 员工档案/代约 | `CoachPrivateProfileService.php`、`StaffCoachPrivateProfileController.php` |
| 改节 | `ScheduleSessionWriteService.php`、`UpdateScheduleSessionRequest.php` |
| 策略 | `BookingPolicyService.php` |
| 员工 UI | `staff-miniapp/.../coach-board.vue`、`settings/booking-policy/index.vue` |
| 会员 UI | `member-miniapp/.../booking/coach.vue`、`api/member.ts` |
| 测试 | `PrivateCoachBookingPolicyTest.php`、`MemberPrivateCoachBookingTest.php` |

---

## 七、建议回归路径（5 分钟）

1. 预约设置 → 私教 → **与团课重合** = 重合时提示  
2. 排一节团课（有人约更好）→ 教练看板代约 → 应见 **团课重合** 可点 → 确认弹窗 → 代约成功  
3. 同教练修改预约到重合时段 → 同样弹窗 → 修改成功  
4. 会员端约同一教练 → 重合时段不可约（无确认）  
5. `php artisan test --filter=PrivateCoachBookingPolicyTest`

---

*文档生成对应当前对话线程；代码以 Git 标签 `checkpoint/phase-a-booking-2026-07-29` 为准。*
