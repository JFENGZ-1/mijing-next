<?php

namespace Tests\Feature;

use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Models\Account;
use App\Models\BookingPolicy;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Permission;
use App\Models\Role;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\CardProduct;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Booking\BookingPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateCoachBookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_minutes_extends_busy_window_in_time_slots(): void
    {
        [$staff, $site, $profile, $courseId, $date] = $this->seedCoachProfile();
        $this->persistPrivatePolicy($site, ['preparationMinutes' => 30]);

        $start = now()->parse($date)->setTime(10, 0);
        $this->createPrivateSession($site, $profile['coachStaffId'], $courseId, $start, 60, withAppointment: true);

        $slots = collect($this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseId}")
            ->assertOk()->json('data.slots'))->keyBy('start');

        $this->assertFalse($slots['11:00']['available']);
        $this->assertTrue($slots['11:30']['available']);
    }

    public function test_group_conflict_block_marks_slot_unavailable_without_bookings(): void
    {
        [$staff, $site, $profile, $courseId, $date] = $this->seedCoachProfile();
        $this->persistPrivatePolicy($site, ['groupConflictMode' => 'block']);

        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => '团课',
            'course_type' => CourseType::Group,
            'coach_staff_id' => $profile['coachStaffId'],
            'catalog_status' => CourseCatalogStatus::Active,
            'duration_minutes' => 60,
        ]);

        ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $groupCourse->id,
            'coach_staff_id' => $profile['coachStaffId'],
            'starts_at' => now()->parse($date)->setTime(10, 0),
            'ends_at' => now()->parse($date)->setTime(11, 0),
            'capacity' => 10,
            'booked_count' => 0,
            'status' => 'scheduled',
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        $slots = collect($this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseId}")
            ->assertOk()->json('data.slots'))->keyBy('start');

        $this->assertFalse($slots['10:00']['available']);
    }

    public function test_group_conflict_overlap_warn_marks_staff_warn_even_with_group_bookings(): void
    {
        [$staff, $site, $profile, $courseId, $date] = $this->seedCoachProfile();
        $this->persistPrivatePolicy($site, ['groupConflictMode' => 'overlap_warn']);

        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => '团课',
            'course_type' => CourseType::Group,
            'coach_staff_id' => $profile['coachStaffId'],
            'catalog_status' => CourseCatalogStatus::Active,
            'duration_minutes' => 60,
        ]);

        $groupSession = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $groupCourse->id,
            'coach_staff_id' => $profile['coachStaffId'],
            'starts_at' => now()->parse($date)->setTime(10, 0),
            'ends_at' => now()->parse($date)->setTime(11, 0),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => 'scheduled',
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'GM1',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $product = CardProduct::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'card_type' => CardType::Count,
            'name' => '次卡',
            'price' => 100,
            'initial_count' => 10,
            'activation_mode' => 'immediate',
            'sale_status' => \App\Enums\CardProductSaleStatus::OnSale,
            'catalog_status' => \App\Enums\CardProductCatalogStatus::Active,
        ]);
        $card = MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::Count,
            'card_no' => 'GC1',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '次卡'],
            'cached_remaining_count' => 10,
            'issued_at' => now(),
        ]);
        \App\Models\Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $groupSession->id,
            'member_id' => $member->id,
            'status' => 'confirmed',
            'command_key' => (string) \Illuminate\Support\Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);

        $slots = collect($this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseId}")
            ->assertOk()->json('data.slots'))->keyBy('start');

        $this->assertFalse($slots['10:00']['available']);
        $this->assertTrue($slots['10:00']['groupOverlapWarn']);
    }

    public function test_private_session_retime_requires_group_overlap_acknowledgement(): void
    {
        [$staff, $site, $profile, $courseId, $date] = $this->seedCoachProfile();
        $this->persistPrivatePolicy($site, ['groupConflictMode' => 'overlap_warn']);

        $start = now()->parse($date)->setTime(9, 0);
        $privateSession = $this->createPrivateSession($site, $profile['coachStaffId'], $courseId, $start, 60, withAppointment: true);

        $groupCourse = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => '团课',
            'course_type' => CourseType::Group,
            'coach_staff_id' => $profile['coachStaffId'],
            'catalog_status' => CourseCatalogStatus::Active,
            'duration_minutes' => 60,
        ]);
        ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $groupCourse->id,
            'coach_staff_id' => $profile['coachStaffId'],
            'starts_at' => now()->parse($date)->setTime(10, 0),
            'ends_at' => now()->parse($date)->setTime(11, 0),
            'capacity' => 10,
            'booked_count' => 0,
            'status' => 'scheduled',
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        $newStart = now()->parse($date)->setTime(10, 0);
        $payload = [
            'version' => 1,
            'startsAt' => $newStart->toIso8601String(),
            'endsAt' => $newStart->copy()->addHour()->toIso8601String(),
        ];

        $this->patchJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$privateSession->id}", $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'COACH_PRIVATE_GROUP_OVERLAP');

        $this->patchJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$privateSession->id}", [
            ...$payload,
            'acknowledgeGroupOverlap' => true,
        ])->assertOk();
    }

    public function test_gray_out_disabled_hides_unavailable_slots(): void
    {
        [$staff, $site, $profile, $courseId, $date] = $this->seedCoachProfile();
        $this->persistPrivatePolicy($site, ['grayOutBookedSlots' => false]);

        $start = now()->parse($date)->setTime(10, 0);
        $this->createPrivateSession($site, $profile['coachStaffId'], $courseId, $start, 60, withAppointment: true);

        $data = $this->getJson("/api/v1/staff/sites/{$site->id}/private-coaches/{$profile['id']}/time-slots?date={$date}&courseId={$courseId}")
            ->assertOk()
            ->json('data');

        $this->assertFalse($data['grayOutBookedSlots']);
        $this->assertFalse(collect($data['slots'])->contains(fn ($slot) => $slot['start'] === '10:00'));
    }

    public function test_effective_group_advance_respects_daily_cutoff(): void
    {
        $service = new BookingPolicyService;
        $tenant = Tenant::create(['name' => 'T', 'code' => 'tz']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'S', 'code' => 's', 'status' => 'active', 'timezone' => 'Asia/Shanghai']);
        $policy = BookingPolicyService::defaultPolicy();
        $policy['group']['advanceBookingDays'] = 7;
        $policy['group']['advanceBookingDailyCutoffHour'] = 23;
        $policy['group']['advanceBookingDailyCutoffMinute'] = 0;

        $this->travelTo(now('Asia/Shanghai')->setTime(10, 0));
        $this->assertSame(6, $service->effectiveAdvanceBookingDays($site, 7, ScheduleSessionKind::Group->value, $policy));

        $this->travelTo(now('Asia/Shanghai')->setTime(23, 30));
        $this->assertSame(7, $service->effectiveAdvanceBookingDays($site, 7, ScheduleSessionKind::Group->value, $policy));
    }

    /**
     * @return array{0: Staff, 1: Site, 2: array<string, mixed>, 3: int, 4: string}
     */
    private function seedCoachProfile(): array
    {
        [$staff, $site] = $this->makeStaff([
            'booking.appointment.create',
            'course-catalog.write',
            'schedule.session.write',
        ]);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => 'C1',
            'name' => 'Coach',
            'status' => 'active',
        ]);

        $profile = $this->postJson("/api/v1/staff/sites/{$site->id}/private-coaches/save", [
            'coachStaffId' => $coach->id,
            'bookingWindows' => [['days' => [1, 2, 3, 4, 5, 6, 7], 'start' => '08:00', 'end' => '21:00']],
            'subjectMode' => 'per_course',
            'courses' => [['name' => '私教', 'durationMinutes' => 60, 'feeList' => []]],
        ])->assertOk()->json('data');

        $courseId = $profile['courses'][0]['id'];
        $date = now()->addDays(2)->format('Y-m-d');

        return [$staff, $site, $profile, $courseId, $date];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'T', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'S', 'code' => 's', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => 'E1',
            'name' => 'Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'R', 'code' => 'r', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

  /**
     * @param  array<string, mixed>  $privateOverrides
     */
    private function persistPrivatePolicy(Site $site, array $privateOverrides): void
    {
        $defaults = BookingPolicyService::defaultPolicy();
        $defaults['private'] = array_merge($defaults['private'], $privateOverrides);
        BookingPolicy::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'version' => 1,
            'policy' => ['group' => $defaults['group'], 'private' => $defaults['private']],
            'rules' => [],
        ]);
    }

    private function createPrivateSession(
        Site $site,
        int $coachStaffId,
        int $courseId,
        \Carbon\Carbon $start,
        int $duration,
        bool $withAppointment,
    ): ScheduleSession {
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $courseId,
            'coach_staff_id' => $coachStaffId,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes($duration),
            'capacity' => 1,
            'booked_count' => $withAppointment ? 1 : 0,
            'status' => 'scheduled',
            'session_kind' => ScheduleSessionKind::Private,
            'version' => 1,
        ]);

        if ($withAppointment) {
            $member = Member::create([
                'tenant_id' => $site->tenant_id,
                'member_no' => 'M1',
                'registration_site_id' => $site->id,
                'home_site_id' => $site->id,
                'status' => 'active',
            ]);
            $product = CardProduct::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'card_type' => CardType::Count,
                'name' => '次卡',
                'price' => 100,
                'initial_count' => 10,
                'activation_mode' => 'immediate',
                'sale_status' => \App\Enums\CardProductSaleStatus::OnSale,
                'catalog_status' => \App\Enums\CardProductCatalogStatus::Active,
            ]);
            $card = MemberCard::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'member_id' => $member->id,
                'card_product_id' => $product->id,
                'card_type' => CardType::Count,
                'card_no' => 'C1',
                'status' => MemberCardStatus::Active,
                'product_snapshot' => ['name' => '次卡'],
                'cached_remaining_count' => 10,
                'issued_at' => now(),
            ]);
            \App\Models\Appointment::create([
                'tenant_id' => $site->tenant_id,
                'site_id' => $site->id,
                'session_id' => $session->id,
                'member_id' => $member->id,
                'status' => 'confirmed',
                'command_key' => (string) \Illuminate\Support\Str::uuid(),
                'member_card_id' => $card->id,
                'booked_at' => now(),
            ]);
        }

        return $session;
    }
}
