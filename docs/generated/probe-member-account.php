<?php

require __DIR__ . '/../../mijing-next/apps/server/vendor/autoload.php';
$app = require __DIR__ . '/../../mijing-next/apps/server/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Member;
use App\Models\WechatIdentity;

$members = Member::query()->select('id', 'member_no', 'account_id', 'tenant_id')->limit(5)->get();
$identity = WechatIdentity::query()->where('openid', 'o6zAJs3_j_Bab0V-xJx3EzNADxvQ')->first();

echo json_encode([
    'members' => $members,
    'devtoolsIdentity' => $identity ? ['account_id' => $identity->account_id, 'appid' => $identity->appid] : null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
