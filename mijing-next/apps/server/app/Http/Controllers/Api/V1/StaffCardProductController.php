<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CardProductCatalogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCardProductRequest;
use App\Http\Requests\UpdateCardProductRequest;
use App\Models\CardProduct;
use App\Models\CardProductCourseScope;
use App\Models\Staff;
use App\Services\Cards\CardProductExtrasService;
use App\Services\Cards\CardProductWriteService;
use App\Services\Cards\StaffCardProductAccessService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class StaffCardProductController extends Controller
{
    public function index(Request $request, int $site, StaffCardProductAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.catalog.read', $siteModel->id);

        $catalogStatus = match ($request->string('catalogStatus')->toString()) {
            'archived' => CardProductCatalogStatus::Archived,
            default => CardProductCatalogStatus::Active,
        };
        $query = $access->catalogQuery($staff, $siteModel, $catalogStatus);
        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%');
        }

        $paginator = $query
            ->with('courseScopes:id,card_product_id,scope_kind,scope_key')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (CardProduct $product) => $this->catalogItem($product)),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $site, int $cardProduct, StaffCardProductAccessService $access)
    {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.catalog.read', $siteModel->id);
        $product = $access->product($staff, $siteModel, $cardProduct)->load('courseScopes');

        return ApiResponse::success($this->detailData($product));
    }

    public function store(
        StoreCardProductRequest $request,
        int $site,
        StaffCardProductAccessService $access,
        CardProductWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.editor.write', $siteModel->id);
        $product = $writer->create($staff, $siteModel, $request->validated());

        return ApiResponse::success($this->detailData($product), 201);
    }

    public function update(
        UpdateCardProductRequest $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.editor.write', $siteModel->id);
        $product = $access->product($staff, $siteModel, $cardProduct);
        $product = $writer->updateForActor(DomainActor::staff($staff), $siteModel, $product, $request->validated());

        return ApiResponse::success($this->detailData($product));
    }

    public function archive(
        Request $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.archive', $siteModel->id);
        $product = $access->product($staff, $siteModel, $cardProduct);
        $command = $request->validate([
            'version' => ['sometimes', 'integer', 'min:1'],
            'commandKey' => ['sometimes', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $product = $writer->archiveForActor(
            DomainActor::staff($staff), $siteModel, $product,
            (int) ($command['version'] ?? $product->version),
            $command['commandKey'] ?? null, $command['reason'] ?? null,
        )->load('courseScopes');

        return ApiResponse::success($this->detailData($product));
    }

    public function restore(
        Request $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.archive', $siteModel->id);
        $product = $access->product($staff, $siteModel, $cardProduct);
        $command = $request->validate([
            'version' => ['sometimes', 'integer', 'min:1'],
            'commandKey' => ['sometimes', 'uuid'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $product = $writer->restoreForActor(
            DomainActor::staff($staff), $siteModel, $product,
            (int) ($command['version'] ?? $product->version),
            $command['commandKey'] ?? null, $command['reason'] ?? null,
        )->load('courseScopes');

        return ApiResponse::success($this->detailData($product));
    }

    public function destroy(
        Request $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductWriteService $writer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.editor.write', $siteModel->id);
        $product = $access->product($staff, $siteModel, $cardProduct);
        $writer->assertPhysicalDeleteForbidden($product);
    }

    public function groupHistory(
        Request $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'card-product.catalog.read', $siteModel->id);
        $product = $access->product($staff, $siteModel, $cardProduct);

        return ApiResponse::success($extras->groupHistory($staff, $siteModel, $product));
    }

    public function faceLibrary(
        Request $request,
        int $site,
        StaffCardProductAccessService $access,
        CardProductExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);

        return ApiResponse::success($extras->faceLibrary($siteModel));
    }

    public function createExportJob(
        Request $request,
        int $site,
        StaffCardProductAccessService $access,
        CardProductExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'export.job.write', $siteModel->id);

        return ApiResponse::success($extras->createExportJob($staff, $siteModel, $request), 201);
    }

    private function catalogItem(CardProduct $product): array
    {
        $item = [
            'id' => $product->id,
            'cardType' => $product->card_type->value,
            'name' => $product->name,
            'price' => $this->decimal($product->price),
            'faceValue' => $this->nullableDecimal($product->face_value),
            'initialCount' => $product->initial_count,
            'validityDays' => $product->validity_days,
            'saleStatus' => $product->sale_status->value,
            'catalogStatus' => $product->catalog_status->value,
            'sortOrder' => $product->sort_order,
            'version' => $product->version,
            'allowedPaymentMethods' => app(\App\Services\Cards\CardProductPaymentMethodService::class)->methods($product),
            'faceStyle' => (int) ($product->scope_config['faceStyle'] ?? 0),
            'faceGradient' => app(\App\Services\Cards\CardFaceLibraryService::class)
                ->gradientFor((int) ($product->scope_config['faceStyle'] ?? 0)),
        ];

        // 卡·课关联总览需要的可约课程摘要（列表 with courseScopes 时输出）
        if ($product->relationLoaded('courseScopes')) {
            $singleScopes = $product->courseScopes->where('scope_kind', \App\Enums\CardProductCourseScopeKind::Single);
            $item['courseScopeCount'] = $singleScopes->count();
            $item['courseScopeKeys'] = $singleScopes->pluck('scope_key')->map(fn ($key) => (int) $key)->values()->all();
        }

        return $item;
    }

    private function detailData(CardProduct $product): array
    {
        return [
            ...$this->catalogItem($product),
            'description' => $product->description,
            'validityMode' => $product->validity_mode,
            'activationMode' => $product->activation_mode,
            'scopeConfig' => $product->scope_config,
            'bookingRules' => $product->booking_rules,
            'archivedAt' => $product->archived_at?->toIso8601String(),
            'courseScopes' => $product->courseScopes
                ->sortBy('sort_order')
                ->values()
                ->map(fn (CardProductCourseScope $scope) => [
                    'id' => $scope->id,
                    'scopeKind' => $scope->scope_kind->value,
                    'scopeKey' => $scope->scope_key,
                    'displayName' => $scope->display_name,
                    'priceOverride' => $this->nullableDecimal($scope->price_override),
                    'sortOrder' => $scope->sort_order,
                ])
                ->all(),
            'createdAt' => $product->created_at?->toIso8601String(),
            'updatedAt' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null ? null : $this->decimal($value);
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
