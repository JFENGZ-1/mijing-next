<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberProfile;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberMonthlyRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_ranking_lists_opted_in_members_by_completed_appointments(): void
    {
        [$account, $tenant, $site, $member, $rival] = $this->seedRankingFixture(showMonthRank: true);
        $year = 2026;
        $month = 7;

        $session = $this->seedSession($site);
        $this->seedCompletedAppointment($member, $session, 3, $year, $month);
        $this->seedCompletedAppointment($rival, $session, 5, $year, $month);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/ranking/monthly?tenantId={$tenant->id}&year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.month', $month)
            ->assertJsonPath('data.viewerOptIn', true)
            ->assertJsonPath('data.items.0.memberId', $rival->id)
            ->assertJsonPath('data.items.0.appointmentCount', 5)
            ->assertJsonPath('data.items.0.rank', 1)
            ->assertJsonPath('data.items.1.memberId', $member->id)
            ->assertJsonPath('data.items.1.isMe', true)
            ->assertJsonPath('data.myRank.rank', 2)
            ->assertJsonPath('data.myRank.appointmentCount', 3);
    }

    public function test_non_opted_in_members_do_not_appear_on_leaderboard(): void
    {
        [$account, $tenant, $site, $member, $hidden] = $this->seedRankingFixture(showMonthRank: true);
        $hidden->update(['ranking_opt_in' => false]);
        $year = 2026;
        $month = 7;
        $session = $this->seedSession($site);
        $this->seedCompletedAppointment($member, $session, 2, $year, $month);
        $this->seedCompletedAppointment($hidden, $session, 10, $year, $month);

        $this->actAsMember($account);

        $response = $this->getJson("/api/v1/member/ranking/monthly?tenantId={$tenant->id}&year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.viewerOptIn', true);

        $memberIds = collect($response->json('data.items'))->pluck('memberId')->all();
        $this->assertSame([$member->id], $memberIds);
        $this->assertSame(1, $response->json('data.myRank.rank'));
        $this->assertNotContains($hidden->id, $memberIds);
    }

    public function test_viewer_opt_in_reflects_member_preference(): void
    {
        [$account, $tenant, $site, $member, $rival] = $this->seedRankingFixture(showMonthRank: true);
        $member->update(['ranking_opt_in' => false]);
        $rival->account?->memberProfile?->update(['avatar_object_key' => 'member-avatars/rival.jpg']);
        $year = 2026;
        $month = 7;
        $session = $this->seedSession($site);
        $this->seedCompletedAppointment($member, $session, 2, $year, $month);
        $this->seedCompletedAppointment($rival, $session, 3, $year, $month);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/ranking/monthly?tenantId={$tenant->id}&year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.viewerOptIn', false)
            ->assertJsonPath('data.myRank.rank', null)
            ->assertJsonPath('data.items.0.avatarUrl', null);
    }

    public function test_opted_in_viewer_receives_avatar_urls_for_ranked_members(): void
    {
        [$account, $tenant, $site, $member, $rival] = $this->seedRankingFixture(showMonthRank: true);
        $avatarKey = 'member-avatars/rival.jpg';
        $rival->account?->memberProfile?->update(['avatar_object_key' => $avatarKey]);
        $year = 2026;
        $month = 7;
        $session = $this->seedSession($site);
        $this->seedCompletedAppointment($member, $session, 1, $year, $month);
        $this->seedCompletedAppointment($rival, $session, 2, $year, $month);

        $this->actAsMember($account);

        $response = $this->getJson("/api/v1/member/ranking/monthly?tenantId={$tenant->id}&year={$year}&month={$month}")
            ->assertOk()
            ->assertJsonPath('data.viewerOptIn', true);

        $avatarUrl = $response->json('data.items.0.avatarUrl');
        $this->assertNotEmpty($avatarUrl);
        $this->assertStringContainsString($avatarKey, (string) $avatarUrl);
    }

    public function test_monthly_ranking_returns_not_found_when_disabled(): void
    {
        [$account, $tenant] = $this->seedRankingFixture(showMonthRank: false);
        $this->actAsMember($account);

        $this->getJson("/api/v1/member/ranking/monthly?tenantId={$tenant->id}")
            ->assertNotFound();
    }

    public function test_cross_tenant_ranking_access_is_denied(): void
    {
        [$account, $tenant] = $this->seedRankingFixture(showMonthRank: true);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-rank', 'show_month_rank' => true]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/ranking/monthly?tenantId={$otherTenant->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Site, 3: Member, 4: Member}
     */
    private function seedRankingFixture(bool $showMonthRank): array
    {
        $tenant = Tenant::create([
            'name' => 'Ranking Tenant',
            'code' => fake()->unique()->slug(1),
            'show_month_rank' => $showMonthRank,
        ]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);

        $account = Account::create(['display_name' => 'Rank Member', 'status' => 'active']);
        MemberProfile::create(['account_id' => $account->id, 'display_name' => 'Rank Member']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-RANK-1',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'ranking_opt_in' => true,
        ]);

        $rivalAccount = Account::create(['display_name' => 'Rival', 'status' => 'active']);
        MemberProfile::create(['account_id' => $rivalAccount->id, 'display_name' => 'Rival']);
        $rival = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $rivalAccount->id,
            'member_no' => 'MEM-RANK-2',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'ranking_opt_in' => true,
        ]);

        return [$account, $tenant, $site, $member, $rival];
    }

    private function seedSession(Site $site): ScheduleSession
    {
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => '教室A',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '教练',
            'status' => 'active',
        ]);
        $startsAt = Carbon::create(2026, 7, 15, 10, 0, 0);

        return ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'session_kind' => ScheduleSessionKind::Group,
            'status' => ScheduleSessionStatus::Scheduled,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 20,
            'booked_count' => 0,
            'version' => 1,
        ]);
    }

    private function seedCompletedAppointment(Member $member, ScheduleSession $session, int $count, int $year, int $month): void
    {
        for ($i = 0; $i < $count; $i++) {
            Appointment::create([
                'tenant_id' => $member->tenant_id,
                'site_id' => $session->site_id,
                'session_id' => $session->id,
                'member_id' => $member->id,
                'status' => AppointmentStatus::Completed,
                'command_key' => (string) Str::uuid(),
                'booked_at' => Carbon::create($year, $month, 1 + $i, 9, 0),
            ]);
        }
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
