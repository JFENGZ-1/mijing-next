<?php

namespace App\Services\Catalog;

use App\Models\Site;
use App\Support\DomainActor;
use Illuminate\Support\Facades\DB;

class CatalogCommandReceiptService
{
    public function payloadHash(array $payload): string
    {
        unset($payload['commandKey']);
        $this->sortRecursive($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function replay(
        Site $site,
        string $resourceType,
        string $action,
        string $commandKey,
        string $payloadHash,
    ): ?object {
        $receipt = DB::table('catalog_change_commands')
            ->where('tenant_id', $site->tenant_id)
            ->where('command_key', $commandKey)
            ->lockForUpdate()
            ->first();
        if ($receipt === null) {
            return null;
        }
        abort_unless(
            (int) $receipt->site_id === $site->id
            && $receipt->resource_type === $resourceType
            && $receipt->action === $action
            && hash_equals($receipt->payload_hash, $payloadHash),
            409,
            'IDEMPOTENCY_KEY_REUSED',
        );

        return $receipt;
    }

    public function record(
        DomainActor $actor,
        Site $site,
        string $resourceType,
        int $resourceId,
        string $action,
        string $commandKey,
        string $payloadHash,
        int $resultVersion,
        ?string $reason,
    ): void {
        DB::table('catalog_change_commands')->insert([
            'tenant_id' => $site->tenant_id,
            'site_id' => $site->id,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'command_key' => $commandKey,
            'payload_hash' => $payloadHash,
            'result_version' => $resultVersion,
            'reason' => $reason,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'created_at' => now(),
        ]);
    }

    private function sortRecursive(array &$value): void
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
    }
}
