<?php

require __DIR__ . '/../../songguo-next/apps/server/vendor/autoload.php';
$app = require __DIR__ . '/../../songguo-next/apps/server/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Member;
use App\Models\PersonalAccessToken;

$member = Member::query()->where('member_no', 'MEM-ACCEPT-1')->first()
    ?? Member::query()->orderBy('id')->first();

if (!$member) {
    echo json_encode(['ok' => false, 'reason' => 'no member']) . PHP_EOL;
    exit(1);
}

$token = PersonalAccessToken::query()
    ->where('tokenable_type', $member->getMorphClass())
    ->where('tokenable_id', $member->id)
    ->latest('id')
    ->first();

echo json_encode([
    'ok' => true,
    'memberId' => $member->id,
    'memberNo' => $member->member_no,
    'hasToken' => (bool) $token,
    'tokenPreview' => $token ? substr($token->token, 0, 12) . '...' : null,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
