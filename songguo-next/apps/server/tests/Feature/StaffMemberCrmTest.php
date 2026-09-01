<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\EntitlementLedgerDirection;
use App\Enums\EntitlementLedgerEntryType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Course;
use App\Models\EntitlementLedgerEntry;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\MemberTag;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffMemberCrmTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_unlinked_lead_and_only_receives_masked_mobile(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());

        $response = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '潜客张三',
            'mobile' => '13800138000',
            'assignToMe' => true,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'lead')
            ->assertJsonPath('data.mobileMasked', '*******8000')
            ->assertJsonPath('data.owner.id', $staff->id)
            ->assertJsonMissing(['mobile' => '13800138000']);

        $memberId = $response->json('data.id');
        $this->assertDatabaseHas('members', ['id' => $memberId, 'account_id' => null, 'tenant_id' => $staff->tenant_id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.created', 'subject_id' => $memberId]);
        $this->assertDatabaseHas('member_status_events', ['member_id' => $memberId, 'to_status' => 'lead']);
    }

    public function test_same_tenant_mobile_conflict_never_merges_members(): void
    {
        [, $site] = $this->actAsStaff($this->allCrmPermissions());
        $payload = ['name' => 'First', 'mobile' => '13800138000'];
        $this->postJson("/api/v1/staff/sites/{$site->id}/members", $payload)->assertCreated();

        $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'Second', 'mobile' => '13800138000'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CRM_MOBILE_CONFLICT');

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_staff_can_manage_complete_profile_with_masked_national_id_and_site_owner(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());
        $ownerAccount = Account::create(['display_name' => '顾问李四', 'status' => 'active']);
        $owner = Staff::create([
            'tenant_id' => $staff->tenant_id,
            'account_id' => $ownerAccount->id,
            'employee_no' => 'OWNER001',
            'name' => '顾问李四',
            'status' => 'active',
        ]);
        $owner->sites()->attach($site->id, ['tenant_id' => $staff->tenant_id, 'is_primary' => false]);

        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '完整档案会员',
            'nationalId' => '11010119900101123X',
            'heightCm' => 168.5,
            'weightKg' => 52.25,
            'ownerStaffId' => $owner->id,
        ])->assertCreated()
            ->assertJsonPath('data.nationalIdMasked', '**************123X')
            ->assertJsonPath('data.heightCm', 168.5)
            ->assertJsonPath('data.weightKg', 52.25)
            ->assertJsonPath('data.owner.id', $owner->id)
            ->assertJsonMissing(['nationalId' => '11010119900101123X']);

        $profile = MemberCrmProfile::where('member_id', $created->json('data.id'))->firstOrFail();
        $this->assertNotSame('11010119900101123X', $profile->getRawOriginal('national_id_ciphertext'));
        $this->assertSame('11010119900101123X', Crypt::decryptString($profile->getRawOriginal('national_id_ciphertext')));

        $this->patchJson("/api/v1/staff/sites/{$site->id}/members/{$created->json('data.id')}", [
            'version' => $created->json('data.version'),
            'heightCm' => 170,
            'weightKg' => null,
            'ownerStaffId' => null,
        ])->assertOk()
            ->assertJsonPath('data.nationalIdMasked', '**************123X')
            ->assertJsonPath('data.heightCm', 170)
            ->assertJsonPath('data.weightKg', null)
            ->assertJsonPath('data.owner', null);
    }

    public function test_closed_member_moves_to_deleted_list_and_restore_recovers_previous_status(): void
    {
        [, $site] = $this->actAsStaff($this->allCrmPermissions());
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => '待归档会员'])
            ->assertCreated();
        $memberId = $created->json('data.id');

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/status-transitions", [
            'version' => $created->json('data.version'),
            'targetStatus' => 'closed',
            'reason' => '员工端归档会员',
        ])->assertOk()->assertJsonPath('data.status', 'closed');

        $this->assertNotNull(Member::findOrFail($memberId)->archived_at);
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/deleted")
            ->assertOk()->assertJsonPath('data.items.0.id', $memberId);

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/restore")
            ->assertOk()->assertJsonPath('data.status', 'lead');
        $this->assertNull(Member::findOrFail($memberId)->archived_at);
        $this->assertDatabaseHas('member_status_events', [
            'member_id' => $memberId,
            'from_status' => 'closed',
            'to_status' => 'lead',
        ]);
    }

    public function test_member_detail_returns_complete_authorized_operational_metrics(): void
    {
        [$staff, $site] = $this->actAsStaff([
            ...$this->allCrmPermissions(),
            'booking.member-history.list',
            'order.read',
            'member-card.read',
        ]);
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '指标会员',
        ])->assertCreated();
        $member = Member::findOrFail($created->json('data.id'));

        $groupCourse = Course::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Group,
            'name' => '指标团课',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);
        $privateCourse = Course::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'course_type' => CourseType::Private,
            'name' => '指标私教',
            'duration_minutes' => 60,
            'catalog_status' => CourseCatalogStatus::Active,
        ]);

        $groupSession = $this->createMetricSession(
            $staff,
            $site,
            $groupCourse,
            ScheduleSessionKind::Group,
            now()->startOfDay(),
        );
        $privateSession = $this->createMetricSession(
            $staff,
            $site,
            $privateCourse,
            ScheduleSessionKind::Private,
            now()->startOfMonth()->subDay(),
        );
        $absentSession = $this->createMetricSession(
            $staff,
            $site,
            $groupCourse,
            ScheduleSessionKind::Group,
            now()->startOfDay(),
        );

        $this->createMetricAppointment($staff, $site, $member, $groupSession, AppointmentStatus::Completed);
        $this->createMetricAppointment($staff, $site, $member, $privateSession, AppointmentStatus::Completed);
        $this->createMetricAppointment($staff, $site, $member, $absentSession, AppointmentStatus::Absent);

        $card = MemberCard::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_type' => CardType::StoredValue,
            'card_no' => 'MC-MEMBER-METRICS',
            'status' => MemberCardStatus::Active,
            'product_snapshot' => [
                'name' => '指标储值卡',
                'cardType' => CardType::StoredValue->value,
                'price' => '100.00',
                'faceValue' => '100.00',
            ],
            'cached_balance' => 40,
            'issued_at' => now(),
        ]);
        MemberCardOrder::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ORD-MEMBER-METRICS',
            'amount' => 100,
            'status' => MemberCardOrderStatus::Paid,
            'command_key' => (string) Str::uuid(),
        ]);
        EntitlementLedgerEntry::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'member_card_id' => $card->id,
            'member_id' => $member->id,
            'entry_type' => EntitlementLedgerEntryType::BalanceAdjust,
            'direction' => EntitlementLedgerDirection::Debit,
            'amount_delta' => 60,
            'reason' => '课程消费',
            'occurred_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.metrics.totalPayAmount', '100.00')
            ->assertJsonPath('data.metrics.groupMonthCount', 1)
            ->assertJsonPath('data.metrics.groupTotalCount', 1)
            ->assertJsonPath('data.metrics.privateMonthCount', 0)
            ->assertJsonPath('data.metrics.privateTotalCount', 1)
            ->assertJsonPath('data.metrics.absenceMonthCount', 1)
            ->assertJsonPath('data.metrics.absenceTotalCount', 1)
            ->assertJsonPath('data.metrics.consumedAmount', '60.00')
            ->assertJsonPath('data.metrics.residualValue', '40.00')
            ->assertJsonPath('data.metrics.noClassDays', 0);
    }

    public function test_member_detail_hides_metrics_without_domain_permissions(): void
    {
        [, $site] = $this->actAsStaff($this->allCrmPermissions());
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", [
            'name' => '权限受限会员',
        ])->assertCreated();

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$created->json('data.id')}")
            ->assertOk()
            ->assertJsonPath('data.metrics.totalPayAmount', null)
            ->assertJsonPath('data.metrics.groupTotalCount', null)
            ->assertJsonPath('data.metrics.consumedAmount', null)
            ->assertJsonPath('data.metrics.residualValue', null)
            ->assertJsonPath('data.metrics.noClassDays', null);
    }

    public function test_staff_cannot_access_member_outside_assigned_site_or_tenant(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other', 'status' => 'active']);
        $otherMember = Member::create([
            'tenant_id' => $otherTenant->id,
            'member_no' => 'OTHER001',
            'status' => 'lead',
            'registration_site_id' => $otherSite->id,
            'home_site_id' => $otherSite->id,
        ]);
        MemberCrmProfile::create(['tenant_id' => $otherTenant->id, 'member_id' => $otherMember->id, 'name' => 'Other Member']);
        DB::table('member_sites')->insert([
            'tenant_id' => $otherTenant->id, 'member_id' => $otherMember->id, 'site_id' => $otherSite->id,
            'relationship_type' => 'home', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$otherMember->id}")->assertNotFound();
        $this->getJson("/api/v1/staff/sites/{$otherSite->id}/members")->assertNotFound();
        $this->assertSame($staff->tenant_id, $site->tenant_id);
    }

    public function test_member_token_cannot_use_staff_crm_even_for_same_account(): void
    {
        [$staff, $site] = $this->makeStaff($this->allCrmPermissions());
        Sanctum::actingAs($staff->account, ['api', 'client:member']);

        $this->getJson("/api/v1/staff/sites/{$site->id}/members")
            ->assertForbidden()
            ->assertJsonPath('code', 'TOKEN_AUDIENCE_INVALID');
    }

    public function test_status_transition_is_explicit_versioned_and_audited(): void
    {
        [, $site] = $this->actAsStaff($this->allCrmPermissions());
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'Lead'])->assertCreated();
        $memberId = $created->json('data.id');
        $version = $created->json('data.version');

        $activated = $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/status-transitions", [
            'version' => $version,
            'targetStatus' => 'active',
            'reason' => '完成首次到店登记',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/status-transitions", [
            'version' => $version,
            'targetStatus' => 'frozen',
            'reason' => 'stale request',
        ])->assertStatus(409);

        $this->assertDatabaseHas('member_status_events', ['member_id' => $memberId, 'from_status' => 'lead', 'to_status' => 'active']);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.status_changed', 'subject_id' => $memberId]);
        $this->assertGreaterThan($version, $activated->json('data.version'));
    }

    public function test_notes_are_append_only_and_tags_are_tenant_scoped(): void
    {
        [$staff, $site] = $this->actAsStaff($this->allCrmPermissions());
        $created = $this->postJson("/api/v1/staff/sites/{$site->id}/members", ['name' => 'Member'])->assertCreated();
        $memberId = $created->json('data.id');
        $version = $created->json('data.version');

        $this->postJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/notes", ['body' => '首次沟通，会员不可见'])
            ->assertCreated();
        $this->getJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/notes")
            ->assertOk()->assertJsonPath('data.0.author', $staff->name);

        $tag = MemberTag::create([
            'tenant_id' => $staff->tenant_id, 'name' => '重点跟进', 'normalized_name' => '重点跟进', 'color' => '#D92D20',
        ]);
        $this->putJson("/api/v1/staff/sites/{$site->id}/members/{$memberId}/tags", [
            'version' => $version,
            'tagIds' => [$tag->id],
        ])->assertOk()->assertJsonPath('data.tags.0.id', $tag->id);

        $this->assertDatabaseCount('member_notes', 1);
        $this->assertDatabaseHas('audit_events', ['action' => 'crm.member.note_added', 'subject_id' => $memberId]);
        $this->getJson("/api/v1/staff/member-tags?siteId={$site->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $tag->id);
        $this->getJson('/api/v1/staff/member-tags')->assertUnprocessable();
    }

    public function test_database_rejects_cross_tenant_staff_site_pivot(): void
    {
        [$staff] = $this->makeStaff($this->allCrmPermissions());
        $otherTenant = Tenant::create(['name' => 'Other', 'code' => 'other']);
        $otherSite = Site::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'other']);

        $this->expectException(QueryException::class);
        DB::table('site_staff')->insert([
            'site_id' => $otherSite->id,
            'tenant_id' => $staff->tenant_id,
            'staff_id' => $staff->id,
            'is_primary' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function allCrmPermissions(): array
    {
        return [
            'crm.member.read', 'crm.member.mobile.search', 'crm.member.create', 'crm.member.update',
            'crm.member.status.manage', 'crm.member.owner.claim', 'crm.member.note.read',
            'crm.member.note.add', 'crm.member.tag.assign', 'crm.tag.manage', 'crm.member.app_access.manage',
            'crm.member.deleted.read', 'crm.member.restore',
        ];
    }

    private function createMetricSession(
        Staff $staff,
        Site $site,
        Course $course,
        ScheduleSessionKind $kind,
        $startsAt,
    ): ScheduleSession {
        return ScheduleSession::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'capacity' => 10,
            'status' => ScheduleSessionStatus::Scheduled,
            'session_kind' => $kind,
            'version' => 1,
        ]);
    }

    private function createMetricAppointment(
        Staff $staff,
        Site $site,
        Member $member,
        ScheduleSession $session,
        AppointmentStatus $status,
    ): Appointment {
        return Appointment::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => $status,
            'command_key' => (string) Str::uuid(),
            'booked_at' => $session->starts_at->copy()->subDay(),
            'absent_marked_at' => $status === AppointmentStatus::Absent ? $session->ends_at : null,
        ]);
    }

    private function actAsStaff(array $permissions): array
    {
        [$staff, $site] = $this->makeStaff($permissions);
        Sanctum::actingAs($staff->account, ['api', 'client:staff', "staff:{$staff->id}", "tenant:{$staff->tenant_id}"]);

        return [$staff, $site];
    }

    private function makeStaff(array $permissions): array
    {
        $tenant = Tenant::create(['name' => 'Tenant', 'code' => fake()->unique()->slug(1)]);
        $account = Account::create(['display_name' => 'CRM Admin', 'status' => 'active']);
        $site = Site::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'status' => 'active']);
        $staff = Staff::create([
            'tenant_id' => $tenant->id,
            'account_id' => $account->id,
            'employee_no' => fake()->unique()->numerify('EMP####'),
            'name' => 'CRM Admin',
            'status' => 'active',
        ]);
        $staff->sites()->attach($site->id, ['tenant_id' => $tenant->id, 'is_primary' => true]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'CRM', 'code' => 'crm', 'status' => 'active']);
        foreach ($permissions as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => 'crm']);
            $role->permissions()->attach($permission->id);
        }
        $staff->roles()->attach($role->id, ['tenant_id' => $tenant->id, 'site_id' => null]);

        return [$staff, $site];
    }
}
