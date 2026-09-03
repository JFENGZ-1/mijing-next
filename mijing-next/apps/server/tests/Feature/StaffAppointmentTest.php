<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\BookingPolicy;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_lists_confirmed_appointments_for_session(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff([
            'schedule.session.read',
            'crm.member.read',
            'booking.appointment.create',
        ]);

        $appointmentId = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $appointmentId)
            ->assertJsonPath('data.items.0.status', 'confirmed')
            ->assertJsonPath('data.items.0.memberId', $member->id);
    }

    // 对标原版详情页：列表含已取消（划线展示），候补仍走独立 waitlist 接口
    public function test_staff_session_appointments_exclude_waitlisted_but_include_cancelled(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff(['schedule.session.read']);

        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Waitlisted,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);
        Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => AppointmentStatus::Cancelled,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'cancelled');
    }

    public function test_staff_can_book_member_on_session(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff(['booking.appointment.create']);
        $commandKey = (string) Str::uuid();

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => $commandKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.memberId', $member->id)
            ->assertJsonPath('data.createdByStaffId', $staff->id);

        $this->assertSame(1, ScheduleSession::findOrFail($session->id)->booked_count);
    }

    public function test_staff_can_cancel_appointment(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff([
            'booking.appointment.create',
            'booking.appointment.cancel',
        ]);

        $appointmentId = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$appointmentId}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(0, ScheduleSession::findOrFail($session->id)->booked_count);
    }

    // 员工代约不受「开课前 N 分钟停止预约」限制（会员自约仍受限，见 MemberAppointmentTest）
    public function test_staff_proxy_booking_bypasses_booking_cutoff(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff(['booking.appointment.create']);

        // 课 30 分钟后开始，而预约截止为开课前 120 分钟 → 已过截止
        $session->update([
            'starts_at' => now()->addMinutes(30),
            'ends_at' => now()->addMinutes(90),
        ]);
        BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => [
                'group' => ['bookingCutoffMinutesBeforeStart' => 120],
                'private' => [],
            ],
            'rules' => [],
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.createdByStaffId', $staff->id);
    }

    public function test_staff_without_permission_is_denied(): void
    {
        [, $site, $member, $card, $session] = $this->actAsBookingStaff(['schedule.session.read']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_staff_appointment_is_isolated_by_site(): void
    {
        [$staff, $site, $member, $card, $session] = $this->actAsBookingStaff([
            'booking.appointment.create',
            'booking.appointment.cancel',
        ]);

        $branchSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch',
            'code' => 'branch',
            'status' => 'active',
        ]);
        $appointmentId = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/appointments", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'commandKey' => (string) Str::uuid(),
        ])->json('data.id');

        $this->postJson("/api/v1/staff/sites/{$branchSite->id}/appointments/{$appointmentId}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_proxy_booking_per_course_without_subjects_falls_back_to_uniform_course(): void
    {
        // 约私教与约私教课目是两码事：课目模式但未配置课目（脏数据/历史档案）时，
        // 不传 courseId 回退统一隐藏课，仍可约私教（统一时长统一定价），不死锁。
        [$staff, $site] = $this->makeStaff(['booking.appointment.create', 'course-catalog.write']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);

        // 直接 DB 置为「课目模式 + 零课目」非法态（配置接口已拦，这里模拟历史脏数据）
        $profile = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00']],
            'subjectMode' => 'uniform',
            'uniformDurationMinutes' => 60,
        ])->assertCreated()->json('data');
        \App\Models\CoachPrivateProfile::query()->whereKey($profile['id'])->update(['subject_mode' => 'per_course']);

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-PRIVATE-FALLBACK',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-PRIVATE-FALLBACK',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '私教次卡',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $profile['uniformCourseId']]],
            ],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);

        $sessionId = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'start' => '10:00',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'confirmed')
            ->json('data.sessionId');

        // 落到统一隐藏课（回退成功）而非课目
        $this->assertDatabaseHas('schedule_sessions', [
            'id' => $sessionId,
            'course_id' => $profile['uniformCourseId'],
        ]);
    }

    public function test_private_booking_matches_card_by_product_scopes_not_snapshot(): void
    {
        // 对齐原版 feeList 语义：私教课按卡产品「当前」courseScopes 实时判定。
        // 发卡快照（product_snapshot）不含私教隐藏课也可约——feeList 后配不回填快照。
        [$staff, $site] = $this->makeStaff(['booking.appointment.create', 'course-catalog.write']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $profile = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00']],
            'subjectMode' => 'uniform',
            'uniformDurationMinutes' => 60,
        ])->assertCreated()->json('data');

        // 卡产品配置私教 feeList：该隐藏课扣 2 次
        $product = \App\Models\CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::Count,
            'name' => '私教次卡',
            'price' => 1000,
            'initial_count' => 10,
            'activation_mode' => 'immediate',
            'sale_status' => \App\Enums\CardProductSaleStatus::OnSale,
            'catalog_status' => \App\Enums\CardProductCatalogStatus::Active,
        ]);
        \App\Models\CardProductCourseScope::create([
            'tenant_id' => $site->tenant_id,
            'card_product_id' => $product->id,
            'scope_kind' => \App\Enums\CardProductCourseScopeKind::Single,
            'scope_key' => (string) $profile['uniformCourseId'],
            'display_name' => '私教',
            'price_override' => 2,
            'sort_order' => 0,
        ]);

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-PRIVATE-FEE',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        // 快照刻意不含隐藏课 scope（模拟「先开卡、后配私教扣费」的真实时序）
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-PRIVATE-FEE',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '私教次卡',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => '999999']],
            ],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'start' => '10:00',
            'remark' => '会员要求课后拉伸',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.appointment.status', 'confirmed')
            ->assertJsonPath('data.appointment.memberRemark', '会员要求课后拉伸');

        // 计次卡按 feeList price_override 扣 2 次（实时生效）
        $this->assertDatabaseHas('member_cards', [
            'id' => $card->id,
            'cached_remaining_count' => 3,
        ]);
    }

    public function test_private_booking_rejects_card_product_without_fee_scope(): void
    {
        // 卡产品配了其他 scope 但未加入该私教课 feeList → 不可约（对标原版 failPay）
        [$staff, $site] = $this->makeStaff(['booking.appointment.create', 'course-catalog.write']);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $profile = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00']],
            'subjectMode' => 'uniform',
            'uniformDurationMinutes' => 60,
        ])->assertCreated()->json('data');

        $product = \App\Models\CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::Count,
            'name' => '团课次卡',
            'price' => 500,
            'initial_count' => 10,
            'activation_mode' => 'immediate',
            'sale_status' => \App\Enums\CardProductSaleStatus::OnSale,
            'catalog_status' => \App\Enums\CardProductCatalogStatus::Active,
        ]);
        // 只配了别的课 scope，没配私教隐藏课
        \App\Models\CardProductCourseScope::create([
            'tenant_id' => $site->tenant_id,
            'card_product_id' => $product->id,
            'scope_kind' => \App\Enums\CardProductCourseScopeKind::Single,
            'scope_key' => '999999',
            'display_name' => '瑜伽团课',
            'price_override' => null,
            'sort_order' => 0,
        ]);

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-PRIVATE-DENY',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-PRIVATE-DENY',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '团课次卡', 'courseScopes' => []],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);

        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'start' => '10:00',
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_CARD_NOT_PAYABLE');
    }

    public function test_failed_card_check_rolls_back_created_private_session(): void
    {
        // Bug2：无扣费课目代约 409 时，resolveBookableSession 创建的 session 必须整体回滚，
        // 否则孤儿 session 残留导致时间段被占用。
        [$staff, $site, $member, $card, $profile, $coach] = $this->seedPrivateSubjectFixture();
        $noFeeCourseId = collect($profile['courses'])->firstWhere('name', '无扣费课目')['id'];
        $date = now()->addDays(2)->format('Y-m-d');

        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => $date,
            'start' => '10:00',
            'courseId' => $noFeeCourseId,
            'commandKey' => (string) Str::uuid(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'BOOKING_CARD_NOT_PAYABLE');

        // session 已回滚，该教练名下无残留
        $this->assertSame(0, ScheduleSession::query()->where('coach_staff_id', $coach->id)->count());

        // 换有扣费的课目，同时间段可正常约
        $feeCourseId = collect($profile['courses'])->firstWhere('name', '体型调整')['id'];
        $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => $date,
            'start' => '10:00',
            'courseId' => $feeCourseId,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated();
    }

    public function test_cancelled_appointment_releases_private_time_slot(): void
    {
        // Bug1：预约取消后时间段即释放（对齐原版），无有效预约的孤儿 session 不挡新约。
        [$staff, $site, $member, $card, $profile, $coach] = $this->seedPrivateSubjectFixture();
        $courseA = collect($profile['courses'])->firstWhere('name', '体型调整')['id'];
        $courseB = collect($profile['courses'])->firstWhere('name', '产后恢复')['id'];
        $date = now()->addDays(2)->format('Y-m-d');

        $first = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => $date,
            'start' => '10:00',
            'courseId' => $courseA,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$first['appointment']['id']}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])->assertOk();

        // 同时段换课目 B：A 的 session 已无有效预约，不再视为时间冲突
        $second = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => $date,
            'start' => '10:00',
            'courseId' => $courseB,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        // 取消 B 后再约 A 同时段：sameSlot 复用 A 的旧 session（取消记录仍挂其上，对齐原版灰名显示）
        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$second['appointment']['id']}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])->assertOk();
        $third = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => $date,
            'start' => '10:00',
            'courseId' => $courseA,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data');
        $this->assertSame($first['sessionId'], $third['sessionId']);
    }

    public function test_time_slots_reflect_booking_window_and_occupancy(): void
    {
        // 对标原版 getDrainerTimeList：按窗口+课目时长+预约设置时间间隔生成槽，
        // 已占用（有效预约 session 重叠）的槽 unavailable；取消后槽释放。
        [$staff, $site, $member, $card, $profile, $coach] = $this->seedPrivateSubjectFixture();
        $courseA = collect($profile['courses'])->firstWhere('name', '体型调整')['id'];
        $date = now()->addDays(2)->format('Y-m-d');

        // 窗口 08:00~21:00、60 分钟课、默认间隔 30 分钟 → 08:00..20:00 共 25 槽
        $slots = $this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseA}")
            ->assertOk()
            ->assertJsonPath('data.durationMinutes', 60)
            ->json('data.slots');
        $this->assertCount(25, $slots);
        $this->assertSame('08:00', $slots[0]['start']);
        $this->assertSame('20:00', $slots[24]['start']);
        $this->assertTrue(collect($slots)->every(fn ($slot) => $slot['available']));

        // 约 10:00（60 分钟 → 占 10:00~11:00）
        $booked = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/book", [
            'memberId' => $member->id,
            'memberCardId' => $card->id,
            'date' => $date,
            'start' => '10:00',
            'courseId' => $courseA,
            'commandKey' => (string) Str::uuid(),
        ])->assertCreated()->json('data');

        $byStart = collect($this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseA}")
            ->assertOk()->json('data.slots'))->keyBy('start');
        $this->assertFalse($byStart['10:00']['available']); // 重叠（同一时段）
        $this->assertFalse($byStart['10:30']['available']); // 10:30+60=11:30 与 10:00~11:00 重叠
        $this->assertTrue($byStart['09:00']['available']); // 09:00+60=10:00 不重叠
        $this->assertTrue($byStart['11:00']['available']); // 紧邻结束后可约

        // 改约：排除当前 session 后，原时段应释放
        $byStart = collect($this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseA}&excludeSessionId={$booked['sessionId']}")
            ->assertOk()->json('data.slots'))->keyBy('start');
        $this->assertTrue($byStart['10:00']['available']);
        $this->assertTrue($byStart['10:30']['available']);

        // 取消预约 → 槽释放
        $this->postJson("/api/v1/staff/sites/{$site->id}/appointments/{$booked['appointment']['id']}/cancel", [
            'commandKey' => (string) Str::uuid(),
        ])->assertOk();
        $byStart = collect($this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseA}")
            ->assertOk()->json('data.slots'))->keyBy('start');
        $this->assertTrue($byStart['10:00']['available']);
        $this->assertTrue($byStart['10:30']['available']);
    }

    public function test_time_slots_honor_private_slot_interval_from_booking_policy(): void
    {
        [$staff, $site, $member, $card, $profile] = $this->seedPrivateSubjectFixture();
        $courseA = collect($profile['courses'])->firstWhere('name', '体型调整')['id'];
        $date = now()->addDays(2)->format('Y-m-d');

        $defaults = \App\Services\Booking\BookingPolicyService::defaultPolicy();
        $defaults['private']['slotIntervalMinutes'] = 15;
        \App\Models\BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => ['group' => $defaults['group'], 'private' => $defaults['private']],
            'rules' => [],
        ]);

        $slots = $this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseA}")
            ->assertOk()
            ->json('data.slots');
        // 08:00~20:00 每 15 分钟一步：(12*4)+1 = 49
        $this->assertCount(49, $slots);
        $this->assertSame('08:00', $slots[0]['start']);
        $this->assertSame('08:15', $slots[1]['start']);
        $this->assertSame('20:00', $slots[48]['start']);
    }

    /**
     * 私教课目 fixture：per_course 档案 + 课目A/B（各扣1次）+ 课目C（无扣费）+ 次卡。
     *
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard, 4: array<string, mixed>, 5: Staff}
     */
    private function seedPrivateSubjectFixture(): array
    {
        [$staff, $site] = $this->makeStaff([
            'booking.appointment.create',
            'booking.appointment.cancel',
            'course-catalog.write',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);

        $product = \App\Models\CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::Count,
            'name' => '私教次卡',
            'price' => 1000,
            'initial_count' => 20,
            'activation_mode' => 'immediate',
            'sale_status' => \App\Enums\CardProductSaleStatus::OnSale,
            'catalog_status' => \App\Enums\CardProductCatalogStatus::Active,
        ]);

        $profile = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00']],
            'subjectMode' => 'per_course',
            'uniformDurationMinutes' => 60,
            'courses' => [
                ['name' => '体型调整', 'durationMinutes' => 60, 'feeList' => [['cardProductId' => $product->id, 'deductAmount' => 1]]],
                ['name' => '产后恢复', 'durationMinutes' => 60, 'feeList' => [['cardProductId' => $product->id, 'deductAmount' => 1]]],
                ['name' => '无扣费课目', 'durationMinutes' => 60, 'feeList' => []],
            ],
        ])->assertOk()->json('data');

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-PRIVATE-SUBJECT',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-PRIVATE-SUBJECT',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '私教次卡', 'courseScopes' => []],
            'cached_remaining_count' => 20,
            'issued_at' => now(),
        ]);

        return [$staff, $site, $member, $card, $profile, $coach];
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: MemberCard, 4: ScheduleSession}
     */
    private function actAsBookingStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-STAFF-BOOK',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => 'A教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-STAFF-BOOK',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '次卡',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $course->id]],
            ],
            'cached_remaining_count' => 5,
            'issued_at' => now(),
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 0),
            'capacity' => 12,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return [$staff, $site, $member, $card, $session];
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Booking Staff', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Booking Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Booking', 'code' => 'booking', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
