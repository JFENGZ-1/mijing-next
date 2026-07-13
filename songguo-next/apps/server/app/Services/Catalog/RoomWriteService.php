<?php

namespace App\Services\Catalog;

use App\Enums\CourseCatalogStatus;
use App\Models\Room;
use App\Models\Site;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class RoomWriteService
{
    public function create(Staff $staff, Site $site, array $payload): Room
    {
        return Room::create([
            'tenant_id' => $staff->tenant_id,
            'site_id' => $site->id,
            'name' => $payload['name'],
            'capacity' => $payload['capacity'] ?? null,
            'sort_order' => $payload['sortOrder'] ?? 0,
            'catalog_status' => CourseCatalogStatus::Active,
            'version' => 1,
        ]);
    }

    public function update(Room $room, array $payload): Room
    {
        $updated = Room::query()
            ->whereKey($room->id)
            ->where('tenant_id', $room->tenant_id)
            ->where('version', $payload['version'])
            ->update([
                'name' => $payload['name'] ?? $room->name,
                'capacity' => array_key_exists('capacity', $payload) ? $payload['capacity'] : $room->capacity,
                'sort_order' => $payload['sortOrder'] ?? $room->sort_order,
                'version' => DB::raw('version + 1'),
            ]);

        abort_if($updated !== 1, 409, 'ROOM_VERSION_CONFLICT');

        return $room->fresh();
    }

    public function archive(Room $room): Room
    {
        if ($room->catalog_status === CourseCatalogStatus::Archived) {
            return $room;
        }

        $room->update([
            'catalog_status' => CourseCatalogStatus::Archived,
            'archived_at' => now(),
        ]);

        return $room->fresh();
    }

    public function assertPhysicalDeleteForbidden(Room $room): void
    {
        abort(409, 'ROOM_DELETE_FORBIDDEN');
    }
}
