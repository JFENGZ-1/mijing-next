<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Schedule\ScheduleDisplayConfigService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffScheduleDisplayController extends Controller
{
  public function show(
    Request $request,
    int $site,
    StaffMemberAccessService $access,
    ScheduleDisplayConfigService $display,
  ) {
    $staff = $this->staff($request);
    $siteModel = $access->site($staff, $site);
    $access->assertPermission($staff, 'schedule.session.read', $siteModel->id);

    return ApiResponse::success($display->forSite($staff, $siteModel));
  }

  public function update(
    Request $request,
    int $site,
    StaffMemberAccessService $access,
    ScheduleDisplayConfigService $display,
  ) {
    $staff = $this->staff($request);
    $siteModel = $access->site($staff, $site);
    $access->assertPermission($staff, 'schedule.session.write', $siteModel->id);

    $validated = $request->validate([
      'displayTitle' => ['nullable', 'string', 'max:120'],
      'copyHint' => ['nullable', 'string', 'max:500'],
      'displayTags' => ['nullable', 'array', 'max:20'],
      'displayTags.*.key' => ['required_with:displayTags', 'string', 'max:40'],
      'displayTags.*.label' => ['required_with:displayTags', 'string', 'max:40'],
      'displayTags.*.color' => ['nullable', 'string', 'max:20'],
    ]);

    return ApiResponse::success($display->save($staff, $siteModel, $validated));
  }

  private function staff(Request $request): Staff
  {
    return $request->attributes->get('staff_context');
  }
}
