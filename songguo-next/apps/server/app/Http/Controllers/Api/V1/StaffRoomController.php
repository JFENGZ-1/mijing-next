<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use App\Models\Staff;
use App\Services\Catalog\RoomWriteService;
use App\Services\Catalog\StaffRoomAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffRoomController extends Controller
{
    public function index(Request $request, int $site, StaffRoomAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'site.rooms.read', $siteModel->id);

        $items = $access->roomQuery($staff, $siteModel)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => $this->roomData($room));

        return ApiResponse::success(['items' => $items]);
    }

    public function show(Request $request, int $site, int $room, StaffRoomAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'site.rooms.read', $siteModel->id);
        $roomModel = $access->room($staff, $siteModel, $room);

        return ApiResponse::success($this->roomData($roomModel));
    }

    public function store(
        StoreRoomRequest $request,
        int $site,
        StaffRoomAccessService $access,
        RoomWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'site.rooms.write', $siteModel->id);
        $room = $writer->create($staff, $siteModel, $request->validated());

        return ApiResponse::success($this->roomData($room), 201);
    }

    public function update(
        UpdateRoomRequest $request,
        int $site,
        int $room,
        StaffRoomAccessService $access,
        RoomWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'site.rooms.write', $siteModel->id);
        $roomModel = $access->room($staff, $siteModel, $room);
        $roomModel = $writer->update($roomModel, $request->validated());

        return ApiResponse::success($this->roomData($roomModel));
    }

    public function archive(
        Request $request,
        int $site,
        int $room,
        StaffRoomAccessService $access,
        RoomWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'site.rooms.write', $siteModel->id);
        $roomModel = $access->room($staff, $siteModel, $room);
        $roomModel = $writer->archive($roomModel);

        return ApiResponse::success($this->roomData($roomModel));
    }

    public function destroy(
        Request $request,
        int $site,
        int $room,
        StaffRoomAccessService $access,
        RoomWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'site.rooms.write', $siteModel->id);
        $roomModel = $access->room($staff, $siteModel, $room);
        $writer->assertPhysicalDeleteForbidden($roomModel);
    }

    private function roomData(Room $room): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'capacity' => $room->capacity,
            'catalogStatus' => $room->catalog_status->value,
            'sortOrder' => $room->sort_order,
            'version' => $room->version,
            'archivedAt' => $room->archived_at?->toIso8601String(),
            'createdAt' => $room->created_at?->toIso8601String(),
            'updatedAt' => $room->updated_at?->toIso8601String(),
        ];
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
