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
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\MemberProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\Cards\CardTransferShareTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberCardTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_issue_transfer_share_token(): void
    {
        [$staff, $site, $member] = $this->actAsStaff(['member-card.read']);
        $card = $this->createCard($site, $member);

        $this->postJson("/api/v1/staff/sites/{$site->id}/member-cards/{$card->id}/transfer-share-token")
            ->assertOk()
            ->assertJsonStructure(['data' => ['memberCardId', 'token', 'expiresAt']]);
    }

    public function test_member_can_preview_and_claim_transfer_token(): void
    {
        [$staff, $site, $fromMember, $fromAccount] = $this->seedTransferMembers();
        $toAccount = Account::create(['display_name' => '领取人', 'status' => 'active']);
        $toMember = Member::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => $toAccount->id,
            'member_no' => 'MEM-CLAIM',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberProfile::create(['account_id' => $toAccount->id, 'display_name' => '领取人', 'version' => 1]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $toMember->id,
            'name' => '领取人',
            'version' => 1,
        ]);
        $this->publishPrivacyDocument($toAccount);

        $card = $this->createCard($site, $fromMember);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $token = app(CardTransferShareTokenService::class)->issue($card)['token'];

        Sanctum::actingAs($toAccount, ['api', 'client:member']);

        $this->getJson("/api/v1/member/card-transfers/{$token}")
            ->assertOk()
            ->assertJsonPath('data.claimable', true)
            ->assertJsonPath('data.card.name', '转赠卡');

        $commandKey = (string) Str::uuid();
        $this->postJson("/api/v1/member/card-transfers/{$token}/claim", ['commandKey' => $commandKey])
            ->assertCreated()
            ->assertJsonPath('data.memberCardId', $card->id);

        $card->refresh();
        $this->assertSame($toMember->id, $card->member_id);

        $this->postJson("/api/v1/member/card-transfers/{$token}/claim", ['commandKey' => $commandKey])
            ->assertOk();
    }

    public function test_expired_transfer_token_is_rejected(): void
    {
        [, $site, $member, $account] = $this->seedTransferMembers();
        $card = $this->createCard($site, $member);
        $this->travel(-8)->days();
        $issued = app(CardTransferShareTokenService::class)->issue($card);
        $this->travelBack();

        Sanctum::actingAs($account, ['api', 'client:member']);

        $this->getJson("/api/v1/member/card-transfers/{$issued['token']}")
            ->assertStatus(410);
    }

    public function test_card_with_active_booking_cannot_be_transferred(): void
    {
        [$staff, $site, $fromMember] = $this->seedTransferMembers();
        $toAccount = $this->createClaimant($site, 'MEM-ACTIVE-CLAIM');
        $card = $this->createCard($site, $fromMember);
        $this->createActiveAppointment($staff, $site, $fromMember, $card);
        $token = app(CardTransferShareTokenService::class)->issue($card)['token'];

        Sanctum::actingAs($toAccount, ['api', 'client:member']);
        $this->getJson("/api/v1/member/card-transfers/{$token}")
            ->assertOk()
            ->assertJsonPath('data.claimable', false);
        $this->postJson("/api/v1/member/card-transfers/{$token}/claim", [
            'commandKey' => (string) Str::uuid(),
        ])->assertStatus(409);

        $this->assertSame($fromMember->id, $card->fresh()->member_id);
    }

    public function test_transfer_command_key_is_bound_to_card_token_and_claimant(): void
    {
        [$staff, $site, $fromMember] = $this->seedTransferMembers();
        $firstAccount = $this->createClaimant($site, 'MEM-FIRST-CLAIM');
        $secondAccount = $this->createClaimant($site, 'MEM-SECOND-CLAIM');
        $card = $this->createCard($site, $fromMember);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $token = app(CardTransferShareTokenService::class)->issue($card)['token'];
        $commandKey = (string) Str::uuid();

        Sanctum::actingAs($firstAccount, ['api', 'client:member']);
        $this->postJson("/api/v1/member/card-transfers/{$token}/claim", ['commandKey' => $commandKey])
            ->assertCreated();

        Sanctum::actingAs($secondAccount, ['api', 'client:member']);
        $this->postJson("/api/v1/member/card-transfers/{$token}/claim", ['commandKey' => $commandKey])
            ->assertStatus(409);
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member, 3: Account}
     */
    private function seedTransferMembers(): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $fromAccount = Account::create(['display_name' => '赠卡人', 'status' => 'active']);
        $fromMember = Member::create([
            'tenant_id' => $tenant->id,
            'account_id' => $fromAccount->id,
            'member_no' => 'MEM-GIVE',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberProfile::create(['account_id' => $fromAccount->id, 'display_name' => '赠卡人', 'version' => 1]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $fromMember->id,
            'name' => '赠卡人',
            'version' => 1,
        ]);
        $this->publishPrivacyDocument($fromAccount);

        [$staff] = $this->actAsStaff(['member-card.read'], $tenant, $site);

        return [$staff, $site, $fromMember, $fromAccount];
    }

    /**
     * @return array{0: Staff, 1: Site, 2: Member}
     */
    private function actAsStaff(array $permissions, ?Tenant $tenant = null, ?Site $site = null): array
    {
        [$staff, $site] = $this->makeStaff($permissions, $tenant, $site);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'member_no' => 'MEM-STAFF',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
        ]);

        return [$staff, $site, $member];
    }

    /**
     * @return array{0: Staff, 1: Site}
     */
    private function makeStaff(array $permissions, ?Tenant $tenant = null, ?Site $site = null): array
    {
        $tenant ??= Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'Card Reader', 'status' => 'active']);
        $site ??= Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'Card Reader',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Cards', 'code' => 'cards', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'card']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }

    private function createCard(Site $site, Member $member): MemberCard
    {
        return MemberCard::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-TRANSFER',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => ['name' => '转赠卡'],
            'cached_balance' => 300,
            'issued_at' => now(),
        ]);
    }

    private function createClaimant(Site $site, string $memberNo): Account
    {
        $account = Account::create(['display_name' => $memberNo, 'status' => 'active']);
        $member = Member::create([
            'tenant_id' => $site->tenant_id,
            'account_id' => $account->id,
            'member_no' => $memberNo,
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'status' => 'active',
            'app_access_status' => 'allowed',
        ]);
        MemberProfile::create(['account_id' => $account->id, 'display_name' => $memberNo, 'version' => 1]);
        MemberCrmProfile::create([
            'tenant_id' => $site->tenant_id,
            'member_id' => $member->id,
            'name' => $memberNo,
            'version' => 1,
        ]);
        $this->publishPrivacyDocument($account);

        return $account;
    }

    private function createActiveAppointment(
        Staff $coach,
        Site $site,
        Member $member,
        MemberCard $card,
    ): Appointment {
        $room = Room::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'name' => '转赠测试教室',
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $course = Course::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '转赠测试课程',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $session = ScheduleSession::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $coach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 10,
            'booked_count' => 1,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => ScheduleSessionKind::Group,
            'version' => 1,
        ]);

        return Appointment::create([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'status' => AppointmentStatus::Confirmed,
            'command_key' => (string) Str::uuid(),
            'booked_at' => now(),
        ]);
    }

    private function publishPrivacyDocument(Account $account): void
    {
        $document = LegalDocument::query()->firstOrCreate(
            ['scope_key' => 'global', 'type' => 'privacy', 'version' => '1.0'],
            [
                'title' => '隐私政策',
                'content' => 'privacy',
                'content_hash' => hash('sha256', 'privacy'),
                'status' => 'published',
                'is_required' => true,
                'published_at' => now(),
            ],
        );
        LegalConsent::firstOrCreate(
            ['account_id' => $account->id, 'legal_document_id' => $document->id, 'action' => 'accepted'],
            ['source' => 'test', 'occurred_at' => now()],
        );
    }
}
