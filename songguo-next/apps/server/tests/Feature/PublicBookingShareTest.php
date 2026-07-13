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
use App\Services\Booking\BookingShareTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PublicBookingShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_share_read_works_without_auth_and_excludes_pii(): void
    {
        [$session, $site] = $this->seedSession();
        $token = app(BookingShareTokenService::class)->issue($session)['token'];

        $this->getJson("/api/v1/public/booking/share/sessions/{$token}")
            ->assertOk()
            ->assertJsonPath('data.sessionId', $session->id)
            ->assertJsonPath('data.courseName', '瑜伽团课')
            ->assertJsonPath('data.siteName', $site->name)
            ->assertJsonStructure(['data' => ['shareNotice', 'shareExpiresAt']])
            ->assertJsonMissingPath('data.memberId')
            ->assertJsonMissingPath('data.memberNo')
            ->assertJsonMissingPath('data.mobile')
            ->assertJsonMissingPath('data.staffNotes');
    }

    public function test_invalid_share_token_is_rejected(): void
    {
        $this->getJson('/api/v1/public/booking/share/sessions/not-a-valid-token.signature')
            ->assertNotFound()
            ->assertJsonPath('code', 'BOOKING_SHARE_TOKEN_INVALID');
    }

    public function test_expired_share_token_is_rejected(): void
    {
        [$session] = $this->seedSession();
        $token = app(BookingShareTokenService::class)->issue($session)['token'];

        $this->travel(8)->days();

        $this->getJson("/api/v1/public/booking/share/sessions/{$token}")
            ->assertStatus(410)
            ->assertJsonPath('code', 'BOOKING_SHARE_TOKEN_EXPIRED');
    }

    public function test_share_token_does_not_authenticate_member_endpoints(): void
    {
        [$session] = $this->seedSession();
        $token = app(BookingShareTokenService::class)->issue($session)['token'];

        $this->getJson("/api/v1/member/booking/appointments?tenantId={$session->tenant_id}&sign={$token}")
            ->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/member/booking/appointments?tenantId={$session->tenant_id}")
            ->assertUnauthorized();
    }

    public function test_staff_can_create_share_link_with_permission(): void
    {
        [, $site, $session] = $this->actAsShareStaff(['booking.share.create']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/share-link")
            ->assertOk()
            ->assertJsonPath('data.sessionId', $session->id)
            ->assertJsonStructure(['data' => ['token', 'expiresAt', 'publicUrl', 'shareNotice']]);

        $token = $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/share-link")
            ->json('data.token');

        $this->getJson("/api/v1/public/booking/share/sessions/{$token}")
            ->assertOk()
            ->assertJsonPath('data.sessionId', $session->id);
    }

    public function test_staff_without_share_permission_is_denied(): void
    {
        [, $site, $session] = $this->actAsShareStaff(['schedule.session.read']);

        $this->postJson("/api/v1/staff/sites/{$site->id}/schedule-sessions/{$session->id}/share-link")
            ->assertForbidden()
            ->assertJsonPath('code', 'PERMISSION_DENIED');
    }

    public function test_share_link_is_isolated_by_site(): void
    {
        [$staff, $site, $session] = $this->actAsShareStaff(['booking.share.create']);
        $branchSite = Site::create([
            'tenant_id' => $staff->tenant_id,
            'name' => 'Branch',
            'code' => 'branch',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/staff/sites/{$branchSite->id}/schedule-sessions/{$session->id}/share-link")
            ->assertNotFound();
    }

    /**
     * @return array{0: ScheduleSession, 1: Site}
     */
    private function seedSession(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $room = Room::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'A教室',
            'catalog_status' => CourseCatalogStatus::Active,
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
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
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

        return [$session, $site];
    }

    /**
     * @return array{0: Staff, 1: Site, 2: ScheduleSession}
     */
    private function actAsShareStaff(array $permissions): array
    {
        [$session, $site] = $this->seedSession();
        $tenant = Tenant::findOrFail($site->tenant_id);
        $account = Account::create(['display_name' => 'Share Staff', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Share Staff',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Share', 'code' => 'share', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'booking']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);
        Sanctum::actingAs($account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$tenant->id}"]);

        return [$staff, $site, $session];
    }
}
