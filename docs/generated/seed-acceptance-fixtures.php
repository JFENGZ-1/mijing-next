<?php

use App\Enums\AppointmentStatus;
use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\CourseCatalogStatus;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Enums\ScheduleSessionStatus;
use App\Models\Appointment;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCrmProfile;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use App\Models\ScheduleSession;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Str;

$staff = Staff::query()->where('employee_no', 'ADMIN001')->orderBy('id')->first();
$site = $staff?->sites()->first() ?? ($staff ? Site::query()->where('tenant_id', $staff->tenant_id)->first() : null);

if (! $site || ! $staff) {
    echo json_encode(['error' => 'ADMIN001 staff or site missing — run system:bootstrap first']);
    exit(1);
}

$tenantId = $staff->tenant_id;
$siteId = $site->id;

$room = Room::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'site_id' => $siteId, 'name' => 'A教室'],
    ['capacity' => 20, 'catalog_status' => CourseCatalogStatus::Active, 'sort_order' => 10, 'version' => 1],
);

$group = Course::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'site_id' => $siteId, 'name' => '瑜伽团课'],
    [
        'course_type' => CourseType::Group,
        'description' => '验收演示',
        'duration_minutes' => 60,
        'min_capacity' => 3,
        'max_capacity' => 12,
        'default_room_id' => $room->id,
        'catalog_status' => CourseCatalogStatus::Active,
        'sort_order' => 10,
        'version' => 1,
    ],
);

$session = ScheduleSession::query()->firstOrCreate(
    [
        'tenant_id' => $tenantId,
        'site_id' => $siteId,
        'course_id' => $group->id,
        'starts_at' => now()->setTime(18, 0, 0),
    ],
    [
        'room_id' => $room->id,
        'coach_staff_id' => $staff->id,
        'ends_at' => now()->setTime(19, 0, 0),
        'capacity' => 12,
        'booked_count' => 2,
        'session_kind' => ScheduleSessionKind::Group,
        'status' => ScheduleSessionStatus::Scheduled,
        'version' => 1,
    ],
);

$product = CardProduct::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'site_id' => $siteId, 'name' => '储值卡 1000'],
    [
        'card_type' => CardType::StoredValue,
        'description' => '验收演示储值卡',
        'price' => 1000,
        'face_value' => 1000,
        'sale_status' => CardProductSaleStatus::OnSale,
        'catalog_status' => CardProductCatalogStatus::Active,
        'sort_order' => 10,
        'version' => 1,
    ],
);

$member = Member::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_no' => 'MEM-ACCEPT-1'],
    [
        'status' => 'active',
        'source' => 'staff_create',
        'registration_site_id' => $siteId,
        'home_site_id' => $siteId,
        'app_access_status' => 'allowed',
        'version' => 1,
    ],
);

DB::table('member_sites')->updateOrInsert(
    ['member_id' => $member->id, 'site_id' => $siteId],
    ['tenant_id' => $tenantId, 'relationship_type' => 'registered', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
);

MemberCrmProfile::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_id' => $member->id],
    ['name' => '验收会员', 'mobile_last4' => '9999', 'version' => 1],
);

$card = MemberCard::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_id' => $member->id, 'card_product_id' => $product->id],
    [
        'site_id' => $siteId,
        'card_type' => CardType::StoredValue,
        'card_no' => 'MC-ACC-'.strtoupper(Str::random(6)),
        'status' => MemberCardStatus::Active,
        'cached_balance' => '800.00',
        'valid_until' => now()->addYear()->toDateString(),
        'product_snapshot' => ['name' => $product->name],
        'issued_at' => now(),
        'issued_by_staff_id' => $staff->id,
        'version' => 1,
    ],
);

$member2 = Member::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_no' => 'MEM-ACCEPT-2'],
    [
        'status' => 'active',
        'source' => 'staff_create',
        'registration_site_id' => $siteId,
        'home_site_id' => $siteId,
        'app_access_status' => 'allowed',
        'version' => 1,
    ],
);

DB::table('member_sites')->updateOrInsert(
    ['member_id' => $member2->id, 'site_id' => $siteId],
    ['tenant_id' => $tenantId, 'relationship_type' => 'registered', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
);

MemberCrmProfile::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_id' => $member2->id],
    ['name' => '验收会员二', 'mobile_last4' => '8888', 'version' => 1],
);

$card2 = MemberCard::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_id' => $member2->id, 'card_product_id' => $product->id],
    [
        'site_id' => $siteId,
        'card_type' => CardType::StoredValue,
        'card_no' => 'MC-ACC2-'.strtoupper(Str::random(6)),
        'status' => MemberCardStatus::Active,
        'cached_balance' => '500.00',
        'valid_until' => now()->addYear()->toDateString(),
        'product_snapshot' => ['name' => $product->name],
        'issued_at' => now(),
        'issued_by_staff_id' => $staff->id,
        'version' => 1,
    ],
);

$appointment1 = Appointment::query()->firstOrCreate(
    [
        'tenant_id' => $tenantId,
        'site_id' => $siteId,
        'session_id' => $session->id,
        'member_id' => $member->id,
        'status' => AppointmentStatus::Confirmed,
    ],
    [
        'command_key' => (string) Str::uuid(),
        'member_card_id' => $card->id,
        'created_by_staff_id' => $staff->id,
        'booked_at' => now()->subHours(2),
        'staff_notes' => '验收演示预约',
    ],
);

$appointment2 = Appointment::query()->firstOrCreate(
    [
        'tenant_id' => $tenantId,
        'site_id' => $siteId,
        'session_id' => $session->id,
        'member_id' => $member2->id,
        'status' => AppointmentStatus::Confirmed,
    ],
    [
        'command_key' => (string) Str::uuid(),
        'member_card_id' => $card2->id,
        'created_by_staff_id' => $staff->id,
        'booked_at' => now()->subHour(),
    ],
);

$session->update(['booked_count' => 2]);

echo json_encode([
    'sessionId' => $session->id,
    'productId' => $product->id,
    'memberId' => $member->id,
    'memberCardId' => $card->id,
    'appointmentIds' => [$appointment1->id, $appointment2->id],
    'courseId' => $group->id,
    'date' => now()->toDateString(),
], JSON_UNESCAPED_UNICODE);
