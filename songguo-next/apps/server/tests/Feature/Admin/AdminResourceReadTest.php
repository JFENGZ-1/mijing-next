<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Appointment;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\Room;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminResourceReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = SuperAdmin::query()->create([
            'username' => 'resource.admin',
            'name' => '数据管理员',
            'password' => 'Correct-Horse-2026',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin, ['api', 'client:admin', 'admin:platform']);
    }

    public function test_platform_resource_endpoint_returns_real_rows_for_all_supported_resources(): void
    {
        $fixture = $this->createFixture();

        $expectations = [
            'staff' => ['name', '顾晨'],
            'courses' => ['name', '燃脂搏击'],
            'schedules' => ['course', '燃脂搏击'],
            'appointments' => ['member', '周雨晴'],
            'cards' => ['product', '团课十次卡'],
            'orders' => ['orderNo', 'ADMIN-ORDER-001'],
        ];

        foreach ($expectations as $resource => [$path, $value]) {
            $this->getJson("/api/v1/admin/resources/{$resource}?tenantId={$fixture['tenant']->id}")
                ->assertOk()
                ->assertJsonPath('data.resource', $resource)
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath("data.items.0.{$path}", $value);
        }
    }

    public function test_resource_filters_are_server_side_and_unknown_resources_are_rejected(): void
    {
        $fixture = $this->createFixture();

        $this->getJson("/api/v1/admin/resources/courses?siteId={$fixture['site']->id}&query=搏击&status=active")
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->getJson('/api/v1/admin/resources/courses?query=不存在')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->getJson('/api/v1/admin/resources/accounts')->assertNotFound();
    }

    private function createFixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => '觅境运动',
            'code' => 'songguo',
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '滨江旗舰店',
            'code' => 'binjiang',
            'status' => 'active',
            'timezone' => 'Asia/Shanghai',
        ]);
        $staffAccount = Account::query()->create([
            'display_name' => '顾晨',
            'mobile' => '13800000001',
            'status' => 'active',
        ]);
        $staff = Staff::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $staffAccount->id,
            'employee_no' => 'COACH-001',
            'name' => '顾晨',
            'status' => 'active',
            'joined_on' => now()->toDateString(),
        ]);
        $memberAccount = Account::query()->create([
            'display_name' => '周雨晴',
            'mobile' => '13800002064',
            'status' => 'active',
        ]);
        $member = Member::query()->create([
            'tenant_id' => $tenant->id,
            'account_id' => $memberAccount->id,
            'member_no' => 'M-10001',
            'status' => 'active',
            'source' => 'admin-test',
            'registration_site_id' => $site->id,
            'home_site_id' => $site->id,
            'joined_at' => now(),
        ]);
        MemberCrmProfile::query()->create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'name' => '周雨晴',
            'mobile_last4' => '2064',
        ]);
        $room = Room::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'A 教室',
            'capacity' => 20,
            'catalog_status' => 'active',
        ]);
        $course = Course::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_type' => 'group',
            'name' => '燃脂搏击',
            'duration_minutes' => 60,
            'min_capacity' => 1,
            'max_capacity' => 20,
            'default_room_id' => $room->id,
            'coach_staff_id' => $staff->id,
            'catalog_status' => 'active',
        ]);
        $session = ScheduleSession::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'coach_staff_id' => $staff->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'capacity' => 20,
            'booked_count' => 1,
            'status' => 'scheduled',
            'session_kind' => 'group',
            'created_by_staff_id' => $staff->id,
        ]);
        $product = CardProduct::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'card_type' => 'count',
            'name' => '团课十次卡',
            'price' => '999.00',
            'initial_count' => 10,
            'validity_days' => 365,
            'validity_mode' => 'days',
            'activation_mode' => 'immediate',
            'sale_status' => 'on_sale',
            'catalog_status' => 'active',
            'created_by_staff_id' => $staff->id,
        ]);
        $card = MemberCard::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'card_product_id' => $product->id,
            'card_type' => 'count',
            'card_no' => 'MC-ADMIN-001',
            'status' => 'active',
            'product_snapshot' => ['name' => '团课十次卡'],
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'cached_remaining_count' => 9,
            'issued_at' => now(),
            'issued_by_staff_id' => $staff->id,
        ]);
        Appointment::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'session_id' => $session->id,
            'member_id' => $member->id,
            'status' => 'confirmed',
            'command_key' => (string) Str::uuid(),
            'member_card_id' => $card->id,
            'booked_at' => now(),
        ]);
        MemberCardOrder::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'member_id' => $member->id,
            'member_card_id' => $card->id,
            'order_no' => 'ADMIN-ORDER-001',
            'amount' => '999.00',
            'status' => 'paid',
        ]);

        return compact('tenant', 'site');
    }
}
