<?php

require __DIR__ . '/../../mijing-next/apps/server/vendor/autoload.php';
$app = require __DIR__ . '/../../mijing-next/apps/server/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Account;
use App\Models\CardProduct;
use App\Models\Member;
use App\Models\Site;
use App\Services\Cards\MemberCardPurchaseService;
use Illuminate\Support\Str;

$member = Member::query()->where('member_no', 'MEM-POLISH-1')->firstOrFail();
$account = Account::query()->findOrFail($member->account_id);
$site = Site::query()->where('tenant_id', $member->tenant_id)->orderBy('id')->firstOrFail();
$product = CardProduct::query()
    ->where('tenant_id', $member->tenant_id)
    ->where('site_id', $site->id)
    ->where('sale_status', 'on_sale')
    ->orderBy('id')
    ->firstOrFail();

$result = app(MemberCardPurchaseService::class)->submit($account, $member, $site, [
    'cardProductId' => $product->id,
    'commandKey' => 'shot-' . time(),
]);

$card = $result['memberCard'];
$payload = [
    'ok' => true,
    'name' => $card?->name ?? $product->name,
    'status' => $card?->status?->value ?? $card?->status,
    'cardId' => $card?->id,
];

$out = __DIR__ . '/member-purchase-shot-payload.json';
file_put_contents($out, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
