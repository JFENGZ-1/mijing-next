<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberLinkRequest;
use App\Models\MemberProfile;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\SiteCarouselItem;
use App\Models\SiteNotice;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberHomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_home_returns_carousel_notices_upcoming_and_link_warning(): void
    {
        [$account, $tenant, $member, $site, $card, $session] = $this->seedFixture();
        $this->seedCarousel($tenant, $site);
        $notice = $this->seedNotice($tenant, $site, '开馆通知', '今日正常营业');
        $this->seedPendingLinkRequest($account, $tenant, $site);

        Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_by_account_id' => $account->id,
            'booked_at' => now(),
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/home?tenantId={$tenant->id}&siteId={$site->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.carousel.items')
            ->assertJsonPath('data.notices.0.id', $notice->id)
            ->assertJsonPath('data.notices.0.title', '开馆通知')
            ->assertJsonCount(1, 'data.upcomingAppointments')
            ->assertJsonPath('data.upcomingAppointments.0.courseName', '哈他瑜伽')
            ->assertJsonPath('data.linkRequestWarning.status', 'pending_staff_review')
            ->assertJsonPath('data.linkRequestWarning.message', '您的账号关联申请正在审核中');
    }

    public function test_member_mine_returns_profile_summary_and_stats(): void
    {
        [$account, $tenant, $member, $site, $card, $session] = $this->seedFixture();
        MemberProfile::create([
            'account_id' => $account->id,
            'display_name' => '演示会员',
            'mobile_last4' => '8000',
            'mobile_verified_at' => now(),
            'version' => 1,
        ]);

        Appointment::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => AppointmentStatus::Completed,
            'command_key' => (string) Str::uuid(),
            'booked_by_account_id' => $account->id,
            'booked_at' => now(),
        ]);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/mine?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.profile.displayName', '演示会员')
            ->assertJsonPath('data.profile.mobileMasked', '*******8000')
            ->assertJsonPath('data.cardCount', 1)
            ->assertJsonCount(1, 'data.cardList')
            ->assertJsonPath('data.stats.appointCount', 1)
            ->assertJsonPath('data.pointsEnabled', false)
            ->assertJsonMissingPath('data.profile.mobile');
    }

    public function test_member_notice_list_and_detail(): void
    {
        [$account, $tenant, , $site] = $this->seedFixture(withSession: false);
        $notice = $this->seedNotice($tenant, $site, '停课通知', '因设备维护暂停团课');

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/notices?tenantId={$tenant->id}&siteId={$site->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $notice->id)
            ->assertJsonPath('data.items.0.body', '因设备维护暂停团课');

        $this->getJson("/api/v1/member/notices/{$notice->id}?tenantId={$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.title', '停课通知')
            ->assertJsonPath('data.body', '因设备维护暂停团课');
    }

    public function test_cross_tenant_access_is_denied_for_home_and_mine(): void
    {
        [$account, $tenant, , $site] = $this->seedFixture(withSession: false);
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-home']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/home?tenantId={$otherTenant->id}&siteId={$site->id}")
            ->assertNotFound();

        $this->getJson("/api/v1/member/mine?tenantId={$otherTenant->id}")
            ->assertNotFound();
    }

    public function test_notice_detail_returns_not_found_for_other_tenant(): void
    {
        [$account, $tenant, , $site] = $this->seedFixture(withSession: false);
        $notice = $this->seedNotice($tenant, $site, '租户 A 通知', '仅租户 A 可见');
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other-notice']);

        $this->actAsMember($account);

        $this->getJson("/api/v1/member/notices/{$notice->id}?tenantId={$otherTenant->id}")
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: Tenant, 2: Member, 3: Site, 4: MemberCard, 5?: ScheduleSession}
     */
    private function seedFixture(bool $withSession = true): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Home Member', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'member_no' => 'MEM-HOME',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        $product = CardProduct::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => CardType::Count,
            'name' => '测试卡',
            'price' => 100,
            'initial_count' => 5,
            'sale_status' => CardProductSaleStatus::OnSale,
            'catalog_status' => CardProductCatalogStatus::Active,
        ]);

        $card = MemberCard::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => CardType::Count,
            'card_no' => 'MC-HOME',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '测试卡'],
            'cached_remaining_count' => 3,
            'issued_at' => now(),
        ]);

        if (! $withSession) {
            return [$account, $tenant, $member, $site, $card];
        }

        $course = Course::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '哈他瑜伽',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $room = Room::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'A 教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $coach = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => Account::create(['display_name' => 'Coach', 'status' => 'active'])->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => '教练',
            'status' => 'active',
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 12,
            'booked_count' => 0,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return [$account, $tenant, $member, $site, $card, $session];
    }

    private function seedCarousel(Tenant $tenant, Site $site): void
    {
        SiteCarouselItem::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'image_url' => 'https://cdn.example.com/banner-1.jpg',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        SiteCarouselItem::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'image_url' => 'https://cdn.example.com/banner-2.jpg',
            'status' => 'published',
            'sort_order' => 2,
        ]);
    }

    private function seedNotice(Tenant $tenant, Site $site, string $title, string $body): SiteNotice
    {
        return SiteNotice::create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'title' => $title,
            'body' => $body,
            'status' => 'published',
            'published_at' => now()->subHour(),
            'sort_order' => 1,
        ]);
    }

    private function seedPendingLinkRequest(Account $account, Tenant $tenant, Site $site): void
    {
        $lead = Member::create([
            'tenant_id' => $tenant->id,
            'member_no' => 'MEM-LEAD',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'lead',
        ]);

        MemberLinkRequest::create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'lead_member_id' => $lead->id,
            'account_id' => $account->id,
            'status' => 'pending_staff_review',
            'member_decision' => 'link',
            'evidence_type' => 'verified_mobile',
            'evidence_hash' => hash('sha256', 'demo'),
            'member_profile_version' => 1,
            'active_key' => hash('sha256', "tenant:{$tenant->id}|lead:{$lead->id}"),
            'member_decided_at' => now(),
            'expires_at' => now()->addDays(7),
            'version' => 1,
        ]);
    }

    private function actAsMember(Account $account): void
    {
        Sanctum::actingAs($account, ['api', 'client:member']);
    }
}
