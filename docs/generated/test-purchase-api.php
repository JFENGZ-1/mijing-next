<?php

require __DIR__ . '/../../mijing-next/apps/server/vendor/autoload.php';
$app = require __DIR__ . '/../../mijing-next/apps/server/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Account;
use Illuminate\Support\Str;

$account = Account::query()->findOrFail(1);
$token = $account->createToken('purchase-debug', ['api', 'client:member'])->plainTextToken;

$scenarios = [
    ['tenantId' => 1, 'siteId' => 1, 'cardProductId' => 1, 'label' => 'tenant1/site1/product1'],
    ['tenantId' => 2, 'siteId' => 2, 'cardProductId' => 3, 'label' => 'tenant2/site2/product3'],
    ['tenantId' => 1, 'siteId' => 1, 'cardProductId' => 3, 'label' => 'tenant1/site1/wrong-product3'],
];

foreach ($scenarios as $scenario) {
    $commandKey = (string) Str::uuid();
    $url = sprintf(
        'http://127.0.0.1:8010/api/v1/member/card-purchases?tenantId=%d&siteId=%d',
        $scenario['tenantId'],
        $scenario['siteId'],
    );
    $body = json_encode([
        'cardProductId' => $scenario['cardProductId'],
        'commandKey' => $commandKey,
    ], JSON_THROW_ON_ERROR);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo $scenario['label'] . PHP_EOL;
    echo 'HTTP ' . $status . PHP_EOL;
    echo $response . PHP_EOL . PHP_EOL;
}
