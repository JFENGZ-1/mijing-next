<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CardProduct;
use App\Models\Staff;
use App\Services\Cards\CardProductCrossSiteService;
use App\Services\Cards\StaffCardProductAccessService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class StaffCardProductCrossSiteController extends Controller
{
  public function index(
    Request $request,
    int $site,
    StaffMemberAccessService $access,
    CardProductCrossSiteService $crossSite,
  ) {
    $staff = $this->staff($request);
    $siteModel = $access->site($staff, $site);
    $access->assertPermission($staff, 'card-product.catalog.read', $siteModel->id);

    return ApiResponse::success($crossSite->listForSite($staff, $siteModel));
  }

  public function update(
    Request $request,
    int $site,
    int $cardProduct,
    StaffMemberAccessService $access,
    StaffCardProductAccessService $products,
    CardProductCrossSiteService $crossSite,
  ) {
    $staff = $this->staff($request);
    $siteModel = $access->site($staff, $site);
    $access->assertPermission($staff, 'card-product.editor.write', $siteModel->id);
    $product = $products->product($staff, $siteModel, $cardProduct);

    $validated = $request->validate([
      'linkedSiteIds' => ['required', 'array'],
      'linkedSiteIds.*' => ['integer', 'min:1'],
    ]);

    return ApiResponse::success($crossSite->updateLink($staff, $siteModel, $product, $validated));
  }

  private function staff(Request $request): Staff
  {
    return $request->attributes->get('staff_context');
  }
}
