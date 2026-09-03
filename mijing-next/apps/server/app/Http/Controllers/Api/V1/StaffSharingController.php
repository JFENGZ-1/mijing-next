<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffSharingController extends Controller
{
  public function createStaffMiniappCode(
    Request $request,
    int $site,
    StaffMemberAccessService $access,
  ) {
    $staff = $this->staff($request);
    $siteModel = $access->site($staff, $site);
    $access->assertPermission($staff, 'tenant.settings.read', $siteModel->id);

    $scene = "site={$siteModel->id}&staff={$staff->id}";
    $pagePath = 'pages/index/index';

    return ApiResponse::success([
      'siteId' => $siteModel->id,
      'siteName' => $siteModel->name,
      'pagePath' => $pagePath,
      'scene' => $scene,
      'shareTitle' => "{$siteModel->name} · 员工工作台",
      'qrImageUrl' => null,
      'hint' => '请在微信开发者工具或已配置的小程序码服务中生成正式二维码；当前返回分享路径与 scene 供保存或转发。',
    ]);
  }

  private function staff(Request $request): Staff
  {
    return $request->attributes->get('staff_context');
  }
}
