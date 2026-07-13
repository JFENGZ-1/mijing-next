<?php

/**
 * Extended acceptance fixtures for overnight-batch L5 captures.
 * Resolves tenant/site from ADMIN001 (post system:bootstrap).
 * Run via: php artisan tinker --execute="include '.../seed-overnight-batch-fixtures.php';"
 */

use App\Enums\CardProductCatalogStatus;
use App\Enums\CardProductSaleStatus;
use App\Enums\CardType;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Models\Account;
use App\Models\CardProduct;
use App\Models\LegalDocument;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\MemberCrmProfile;
use App\Models\Site;
use App\Models\Staff;
use App\Models\WechatIdentity;
use App\Services\Cards\CardTransferShareTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$staff = Staff::query()->where('employee_no', 'ADMIN001')->orderBy('id')->first();
$site = $staff?->sites()->first() ?? ($staff ? Site::query()->where('tenant_id', $staff->tenant_id)->first() : null);

if (! $site || ! $staff) {
    echo json_encode(['error' => 'ADMIN001 staff or site missing — run system:bootstrap first']);
    exit(1);
}

$tenantId = $staff->tenant_id;
$siteId = $site->id;

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

$memberAccount = Account::firstOrCreate(
    ['display_name' => '验收会员账号'],
    ['status' => 'active', 'mobile' => null],
);

$memberAppId = config('wechat.apps.member.appid');
if ($memberAppId) {
    WechatIdentity::updateOrCreate(
        ['appid' => $memberAppId, 'openid' => 'o6zAJs3_j_Bab0V-xJx3EzNADxvQ'],
        ['account_id' => $memberAccount->id, 'last_authenticated_at' => now()],
    );
}

$member = Member::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'member_no' => 'MEM-ACCEPT-1'],
    [
        'account_id' => $memberAccount->id,
        'status' => 'active',
        'source' => 'staff_create',
        'registration_site_id' => $siteId,
        'home_site_id' => $siteId,
        'app_access_status' => 'allowed',
        'version' => 1,
    ],
);
$member->update(['account_id' => $memberAccount->id]);

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
        'card_no' => 'MC-OVERNIGHT-'.strtoupper(Str::random(4)),
        'status' => MemberCardStatus::Active,
        'cached_balance' => '800.00',
        'valid_until' => now()->addYear()->toDateString(),
        'product_snapshot' => ['name' => $product->name],
        'issued_at' => now(),
        'issued_by_staff_id' => $staff->id,
        'version' => 1,
    ],
);

$order = MemberCardOrder::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'order_no' => 'ORD-OVERNIGHT-1'],
    [
        'site_id' => $siteId,
        'member_id' => $member->id,
        'member_card_id' => $card->id,
        'amount' => 1000,
        'status' => MemberCardOrderStatus::Paid,
        'command_key' => (string) Str::uuid(),
        'metadata' => ['channel' => 'demo_auto_paid', 'cardProductId' => $product->id],
    ],
);

$archivedCard = MemberCard::query()->firstOrCreate(
    ['tenant_id' => $tenantId, 'card_no' => 'MC-ARCH-OVERNIGHT'],
    [
        'site_id' => $siteId,
        'member_id' => $member->id,
        'card_product_id' => $product->id,
        'card_type' => CardType::StoredValue,
        'status' => MemberCardStatus::Archived,
        'archived_at' => now(),
        'product_snapshot' => ['name' => '已归档卡'],
        'issued_at' => now()->subYear(),
        'issued_by_staff_id' => $staff->id,
        'version' => 1,
    ],
);

$transferToken = app(CardTransferShareTokenService::class)->issue($card)['token'];

LegalDocument::query()->firstOrCreate(
    ['scope_key' => 'global', 'type' => 'privacy', 'version' => '1.0'],
    [
        'title' => '隐私政策',
        'content' => '验收演示隐私政策正文。',
        'content_hash' => hash('sha256', '验收演示隐私政策正文。'),
        'status' => 'published',
        'is_required' => true,
        'published_at' => now(),
    ],
);

echo json_encode([
    'tenantId' => $tenantId,
    'siteId' => $siteId,
    'memberId' => $member->id,
    'memberCardId' => $card->id,
    'archivedCardId' => $archivedCard->id,
    'orderId' => $order->id,
    'productId' => $product->id,
    'transferToken' => $transferToken,
    'weekDate' => now()->startOfWeek()->toDateString(),
    'year' => (int) now()->format('Y'),
    'month' => (int) now()->format('n'),
], JSON_UNESCAPED_UNICODE);
