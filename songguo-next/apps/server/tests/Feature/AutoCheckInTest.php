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
use App\Models\AppointmentEvent;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 自动签到（对标原版「下课后，将由系统5分钟内自动签到」）：
 * appointments:auto-check-in 每5分钟把已下课且仍 confirmed 的预约批量转 completed。
 */
class AutoCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_ended_confirmed_appointment_is_auto_checked_in_and_first_class_card_activates(): void
    {
        [$tenant, $site, , $card, $session] = $this->seedCheckInFixture(endsAt: now()->subMinutes(2));

        $appointment = $this->makeAppointment($tenant, $site, $card, $session);

        $this->artisan('appointments:auto-check-in')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
        // first-class 卡随上课自动激活（与手动签到一致）
        $this->assertSame(MemberCardStatus::Active, $card->fresh()->status);

        $event = AppointmentEvent::query()
            ->where('appointment_id', $appointment->id)
            ->where('event_type', 'checked_in')
            ->sole();
        $this->assertSame('auto-check-in:'.$appointment->id, $event->command_key);
        $this->assertNull($event->actor_staff_id);
        $this->assertTrue((bool) ($event->payload['auto'] ?? false));
    }

    public function test_upcoming_appointment_is_not_touched(): void
    {
        [$tenant, $site, , $card, $session] = $this->seedCheckInFixture(endsAt: now()->addHour());

        $appointment = $this->makeAppointment($tenant, $site, $card, $session);

        $this->artisan('appointments:auto-check-in')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertSame(0, AppointmentEvent::query()->where('appointment_id', $appointment->id)->count());
    }

    public function test_ended_outside_post_class_window_is_not_auto_checked_in(): void
    {
        [$tenant, $site, , $card, $session] = $this->seedCheckInFixture(endsAt: now()->subMinutes(10));

        $appointment = $this->makeAppointment($tenant, $site, $card, $session);

        $this->artisan('appointments:auto-check-in')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_suspended_or_cancelled_session_is_not_auto_checked_in(): void
    {
        // 停课（suspended）：会员没来上课，不自动签到（等恢复或人工处理）
        [$tenant, $site, , $card, $session] = $this->seedCheckInFixture(
            endsAt: now()->subMinutes(2),
            sessionStatus: ScheduleSessionStatus::Suspended,
        );
        $appointment = $this->makeAppointment($tenant, $site, $card, $session);

        $this->artisan('appointments:auto-check-in')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_rerun_is_idempotent(): void
    {
        [$tenant, $site, , $card, $session] = $this->seedCheckInFixture(endsAt: now()->subMinutes(2));
        $appointment = $this->makeAppointment($tenant, $site, $card, $session);

        $this->artisan('appointments:auto-check-in')->assertSuccessful();
        $this->artisan('appointments:auto-check-in')->assertSuccessful();

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
        $this->assertSame(
            1,
            AppointmentEvent::query()
                ->where('appointment_id', $appointment->id)
                ->where('event_type', 'checked_in')
                ->count(),
        );
    }

    // ================= fixture =================

    /**
     * @return array{0: Tenant, 1: Site, 2: Member, 3: MemberCard, 4: ScheduleSession}
     */
    private function seedCheckInFixture($endsAt, ScheduleSessionStatus $sessionStatus = ScheduleSessionStatus::Scheduled): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(2)]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => fake()->unique()->slug(2), 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-'.fake()->unique()->numerify('####'),
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);
        $course = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '瑜伽团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '李教练',
            'status' => 'active',
        ]);
        $product = CardProduct::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => CardType::Count,
            'name' => '首次上课开卡次卡',
            'price' => 100,
            'initial_count' => 10,
            'activation_mode' => 'first-class',
            'sale_status' => 'on_sale',
            'catalog_status' => 'active',
        ]);
        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-'.fake()->unique()->numerify('####'),
            'status' => MemberCardStatus::PendingActivation,
            'product_snapshot' => [
                'name' => $product->name,
                'cardType' => CardType::Count->value,
                'activationMode' => 'first-class',
                'courseScopes' => [['scopeKind' => 'single', 'scopeKey' => (string) $course->id]],
            ],
            'cached_remaining_count' => 10,
            'issued_at' => now(),
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => $endsAt->copy()->subMinutes(60),
            'ends_at' => $endsAt,
            'capacity' => 12,
            'booked_count' => 1,
            'status' => $sessionStatus,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return [$tenant, $site, $member, $card, $session];
    }

    private function makeAppointment(Tenant $tenant, Site $site, MemberCard $card, ScheduleSession $session): Appointment
    {
        return Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $card->member_id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);
    }
}
