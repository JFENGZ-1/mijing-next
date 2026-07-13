<?php

use App\Models\Staff;
use App\Models\WechatIdentity;

$openid = 'o6zAJs3_j_Bab0V-xJx3EzNADxvQ';
$staff = Staff::query()->where('employee_no', 'ADMIN001')->orderBy('id')->first();
$identities = WechatIdentity::query()
    ->where('openid', $openid)
    ->get(['id', 'appid', 'account_id', 'openid']);

$staffBound = $staff && $identities->contains(fn ($i) => (int) $i->account_id === (int) $staff->account_id);

echo json_encode([
    'staffId' => $staff?->id,
    'staffAccountId' => $staff?->account_id,
    'staffEmployeeNo' => $staff?->employee_no,
    'identities' => $identities->toArray(),
    'staffBound' => $staffBound,
], JSON_UNESCAPED_UNICODE);
