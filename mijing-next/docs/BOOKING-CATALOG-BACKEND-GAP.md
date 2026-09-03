# 约课 Catalog 后端字段缺口与修改建议

**生成时间：** 2026-07-13（Asia/Shanghai）
**背景：** 前端 `apps/member-miniapp/src/pages/booking/index.vue` 对标旧 `会员端/pages/appointmentCourse` 视觉还原时，发现 catalog 接口返回字段不足以支撑旧 UI 的课程卡。本文记录缺口与建议改动，**后端尚未修改**，前端已按当前真实契约改诚实（不依赖未暴露字段）。

---

## §1 现状（直验）

### 1.1 当前 catalog 返回字段
`apps/server/app/Services/Booking/MemberBookingBoardService.php::catalogItem()` 返回：

```
id, courseId, courseName, startsAt, endsAt, coachName,
capacity, bookedCount, sessionKind, courseType,
waitlistEnabled, bookable, memberAppointmentStatus
```

### 1.2 当前 eager load
`apps/server/app/Services/Booking/BookingDayBoardQueryService.php::memberCatalogSessions()`：

```php
->with(['course', 'coach'])
```

### 1.3 已确立的隐私契约（不可违反）
`tests/Feature/MemberBookingCatalogTest.php:52`：

```php
$this->assertArrayNotHasKey('coachStaffId', $response->json('data.items.0'));
```

→ **作者刻意不在列表 catalog 暴露 `coachStaffId`**，避免批量枚举教练。staff ID 仅在会话详情 `sessionDetailItem()` 暴露。**任何字段补充方案都不得向 catalog 加 `coachStaffId`。**

---

## §2 缺口：DB 有、API 没吐出来的字段

| 字段 | DB 位置 | 旧 UI 用途 | 暴露建议 | 隐私/作用域考量 |
|---|---|---|---|---|
| `difficulty` | `courses.difficulty` (unsignedTinyInteger, 1-5, nullable) | 难度星级 | ✅ 暴露 | 公开属性，无敏感性 |
| `minCapacity` | `courses.min_capacity` (nullable) | "满 X 人开课" 灰底胶囊 | ✅ 暴露 | 公开属性 |
| `courseTags` | `courses.tags` (array, cast) | 橙色标签 `tagData`（如 "热门"/"初级"） | ✅ 暴露 | seeder 内容 `['瑜伽','初级']` 为公开标签；**建议加白名单/过滤**，避免内部标签泄漏 |
| `displayColor` | `schedule_sessions.display_color` (string 24, nullable) | 课程卡背景色（旧 `courseBacklog` 的等价物，新 schema 用纯色替代图片） | ✅ 暴露 | 无敏感性；nullable，UI 需 fallback |
| `coachAvatarUrl` | `accounts.avatar_url`（经 `staff.account`） | 教练头像 | ⚠️ 暴露但需评估 | 见 §4 |
| `roomName` | `rooms.name`（经 `session.room`） | 课程地址行 | ✅ 暴露 | 已在 `sessionDetailItem` 暴露，catalog 同步暴露一致 |
| `durationMinutes` | `courses.duration_minutes` | （可选）课时长度 | ✅ 暴露 | 已在 `sessionDetailItem` 暴露 |

## §3 DB 确实没有的字段（不可凭空补）

| 旧 UI 元素 | 旧字段 | 新 DB | 处置 |
|---|---|---|---|
| 课程封面图 | `courseBacklog` | 无 `cover_image` 列；新 schema 用 `display_color` 替代 | 用 `displayColor` 做纯色/渐变背景；如需图片封面需新增 migration |
| 已约会员头像堆 | `userlist[].userFaceurl` | 需 join `appointments` + `member_profiles.avatar_object_key` | catalog 不建议加（N+1 + 隐私）；如要，单开 `/member/booking/sessions/{id}/attendees` 端点 |

## §4 `coachAvatarUrl` 隐私评估（第一性原理）

**问题：** `accounts.avatar_url` 是账号级头像（多半来自微信 OAuth），不是 staff 维度的"工作照"。直接当教练公开头像暴露存在两个风险：
1. 教练可能未同意其个人微信头像对会员公开。
2. 旧 UI 的 `staffFace` 是 staff 维度的独立字段，语义不同。

**建议（按优先级）：**
1. **首选**：新增 `staff.avatar_url`（或 `staff.profile_photo_object_key`）字段，由场馆运营上传教练工作照，再暴露为 `coachAvatarUrl`。这语义最干净，与旧 `staffFace` 对齐。
2. **次选**（短期）：临时暴露 `coach.account.avatar_url` 为 `coachAvatarUrl`，但在文档标注"临时方案，待 staff 维度头像字段上线后切换"，并征得产品/教练同意。
3. **保守**：不暴露 `coachAvatarUrl`，UI 用教练名首字占位（**当前前端已采用此方案**，零隐私风险）。

---

## §5 建议的后端改动（待执行）

### 5.1 `BookingDayBoardQueryService::memberCatalogSessions()`
```php
return $this->sessionsForDay($site, $date, ScheduleSessionStatus::Scheduled)
    ->with(['course', 'coach.account', 'room'])   // 新增 coach.account, room
    ->orderBy('starts_at')
    ->orderBy('id')
    ->get();
```

### 5.2 `MemberBookingBoardService::catalogItem()`
在返回数组追加（**不含 `coachStaffId`**，保留隐私门禁）：

```php
'difficulty'      => $session->course?->difficulty,
'minCapacity'     => $session->course?->min_capacity,
'courseTags'      => $session->course?->tags ?? [],
'displayColor'    => $session->display_color,
'coachAvatarUrl'  => $session->coach?->account?->avatar_url,   // 见 §4 评估
'roomName'        => $session->room?->name,
'durationMinutes' => $session->course?->duration_minutes,
```

### 5.3 测试更新（`MemberBookingCatalogTest.php`）
- 第 52 行 `assertArrayNotHasKey('coachStaffId', ...)` **保留**，作为隐私门禁回归断言。
- 新增测试 `test_member_catalog_exposes_course_card_metadata`：seed 带 `difficulty/min_capacity/tags/display_color` 的课程 + session，断言新字段路径；`avatar_url` 为 null 时断言 `coachAvatarUrl === null`。
- 现有 5 个 `assertJsonPath` 子集断言**不会因新增字段而破坏**（Laravel `assertJsonPath` 只校验指定路径）。

### 5.4 OpenAPI 契约（`docs/openapi.yaml:9631`）
`MemberBookingCatalogItem` schema 追加同名 properties（nullable 标注）。`required` 数组不变（新字段全部 nullable）。`MemberBookingSessionDetail` 通过 `allOf` 继承，自动获得新字段，无需重复定义。

### 5.5 验证阶梯目标
- L1：OpenAPI schema 同步
- L2：phpunit happy path + 保留 `coachStaffId` 隔离断言
- L3：前端 `src/api/member.ts` 无需改（`getMemberBookingCatalog` 泛型类型扩展即可）
- L4：前端约课页消费新字段（待后端暴露后切换）

---

## §6 前端已采取的诚实化措施（不依赖后端改动）

- 删除 `MemberBookingCatalogItem.coverImageUrl`（幻影字段，后端从未返回）。
- 课程卡背景：始终用渐变 palette，不引用不存在的 `coverImageUrl`/`displayColor`。
- 删除"难度"文字标签（无 `difficulty` 数据，显示空标签不诚实）。
- 信息行改为 `教练名 | 团课/私教`（`courseTypeLabel` 由 `sessionKind/courseType` 派生，当前 API 已支持）。
- 教练头像用首字占位（无 `coachAvatarUrl`，零隐私风险）。
- "满 X 人开课"改为"剩余 N"（无 `minCapacity`，仅用 `capacity - bookedCount`）。
- 橙色标签由会话状态派生（候补/私教），不依赖 `courseTags`。
- 约私教横滑区由 `sessionKind === private` 会话按教练去重，点击跳该教练首节私教会话详情，**不需要 `coachStaffId`**（与 §1.3 隐私门禁兼容）。

后端按 §5 暴露字段后，前端切换消费即可，UI 结构已预留位置。
