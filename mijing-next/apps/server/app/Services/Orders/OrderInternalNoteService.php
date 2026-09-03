<?php

namespace App\Services\Orders;

use App\Models\MemberCardOrder;
use App\Models\OrderInternalNote;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class OrderInternalNoteService
{
    /**
     * @return array<string, mixed>
     */
    public function append(Staff $staff, Site $site, MemberCardOrder $order, array $payload): array
    {
        $existing = OrderInternalNote::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('command_key', $payload['commandKey'])
            ->first();

        if ($existing !== null) {
            return $this->notePayload($existing);
        }

        $note = DB::transaction(function () use ($staff, $order, $payload) {
            return OrderInternalNote::create([
                'tenant_id' => $staff->tenant_id,
                'order_id' => $order->id,
                'body' => $payload['body'],
                'command_key' => $payload['commandKey'],
                'author_staff_id' => $staff->id,
                'created_at' => now(),
            ]);
        });

        return $this->notePayload($note->fresh(['author']));
    }

    /**
     * @return array<string, mixed>
     */
    private function notePayload(OrderInternalNote $note): array
    {
        return [
            'id' => $note->id,
            'orderId' => $note->order_id,
            'body' => $note->body,
            'commandKey' => $note->command_key,
            'authorStaffId' => $note->author_staff_id,
            'authorStaffName' => $note->author?->name,
            'createdAt' => $note->created_at?->toIso8601String(),
        ];
    }
}
