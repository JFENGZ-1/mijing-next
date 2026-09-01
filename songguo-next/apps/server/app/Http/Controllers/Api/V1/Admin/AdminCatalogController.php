<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogStatusCommandRequest;
use App\Http\Requests\Admin\StoreAdminCardProductRequest;
use App\Http\Requests\Admin\StoreAdminCourseRequest;
use App\Http\Requests\Admin\UpdateAdminCardProductRequest;
use App\Http\Requests\Admin\UpdateAdminCourseRequest;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\Cards\CardProductWriteService;
use App\Services\Catalog\CourseCatalogWriteService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCatalogController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __construct(
        private readonly CardProductWriteService $cardProducts,
        private readonly CourseCatalogWriteService $courses,
    ) {}

    public function cardProducts(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'archived'])],
            'saleStatus' => ['sometimes', 'nullable', Rule::in(['on_sale', 'stopped'])],
            'cardType' => ['sometimes', 'nullable', Rule::in(['stored_value', 'count', 'period'])],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = CardProduct::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('catalog_status', $status))
            ->when($filters['saleStatus'] ?? null, fn ($query, $status) => $query->where('sale_status', $status))
            ->when($filters['cardType'] ?? null, fn ($query, $cardType) => $query->where('card_type', $cardType))
            ->orderBy('catalog_status')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (CardProduct $product) => $this->presentCardProduct($product))->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function storeCardProduct(
        StoreAdminCardProductRequest $request,
        Tenant $tenant,
        Site $site,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $product = $this->cardProducts->createForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $request->validated(),
        );

        return ApiResponse::success($this->presentCardProduct($product), 201);
    }

    public function updateCardProduct(
        UpdateAdminCardProductRequest $request,
        Tenant $tenant,
        Site $site,
        CardProduct $cardProduct,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $this->assertCardProductScope($tenant, $site, $cardProduct);
        $product = $this->cardProducts->updateForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $cardProduct,
            $request->validated(),
        );

        return ApiResponse::success($this->presentCardProduct($product));
    }

    public function archiveCardProduct(
        CatalogStatusCommandRequest $request,
        Tenant $tenant,
        Site $site,
        CardProduct $cardProduct,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $this->assertCardProductScope($tenant, $site, $cardProduct);
        $validated = $request->validated();
        $product = $this->cardProducts->archiveForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $cardProduct,
            (int) $validated['version'],
            $validated['commandKey'],
            $validated['reason'],
        );

        return ApiResponse::success($this->presentCardProduct($product));
    }

    public function restoreCardProduct(
        CatalogStatusCommandRequest $request,
        Tenant $tenant,
        Site $site,
        CardProduct $cardProduct,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $this->assertCardProductScope($tenant, $site, $cardProduct);
        $validated = $request->validated();
        $product = $this->cardProducts->restoreForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $cardProduct,
            (int) $validated['version'],
            $validated['commandKey'],
            $validated['reason'],
        );

        return ApiResponse::success($this->presentCardProduct($product));
    }

    public function courses(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'archived'])],
            'courseType' => ['sometimes', 'nullable', Rule::in(['group', 'private'])],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = Course::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->with('coach')
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('catalog_status', $status))
            ->when($filters['courseType'] ?? null, fn ($query, $courseType) => $query->where('course_type', $courseType))
            ->orderBy('catalog_status')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (Course $course) => $this->presentCourse($course))->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function storeCourse(
        StoreAdminCourseRequest $request,
        Tenant $tenant,
        Site $site,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $course = $this->courses->createForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $request->validated(),
        );

        return ApiResponse::success($this->presentCourse($course), 201);
    }

    public function updateCourse(
        UpdateAdminCourseRequest $request,
        Tenant $tenant,
        Site $site,
        Course $course,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $this->assertCourseScope($tenant, $site, $course);
        $course = $this->courses->updateForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $course,
            $request->validated(),
        );

        return ApiResponse::success($this->presentCourse($course));
    }

    public function archiveCourse(
        CatalogStatusCommandRequest $request,
        Tenant $tenant,
        Site $site,
        Course $course,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $this->assertCourseScope($tenant, $site, $course);
        $validated = $request->validated();
        $course = $this->courses->archiveForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $course,
            (int) $validated['version'],
            $validated['commandKey'],
            $validated['reason'],
        );

        return ApiResponse::success($this->presentCourse($course));
    }

    public function restoreCourse(
        CatalogStatusCommandRequest $request,
        Tenant $tenant,
        Site $site,
        Course $course,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $this->assertCourseScope($tenant, $site, $course);
        $validated = $request->validated();
        $course = $this->courses->restoreForActor(
            DomainActor::superAdmin($request->user()),
            $site,
            $course,
            (int) $validated['version'],
            $validated['commandKey'],
            $validated['reason'],
        );

        return ApiResponse::success($this->presentCourse($course));
    }

    private function presentCardProduct(CardProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'cardType' => $this->enumValue($product->card_type),
            'priceCents' => Money::decimalToCents($product->price),
            'faceValueCents' => $product->face_value === null ? null : Money::decimalToCents($product->face_value),
            'initialCount' => $product->initial_count,
            'validityDays' => $product->validity_days,
            'saleStatus' => $this->enumValue($product->sale_status),
            'catalogStatus' => $this->enumValue($product->catalog_status),
            'allowedPaymentMethods' => $product->allowed_payment_methods ?? ['online', 'balance'],
            'version' => $product->version,
            'updatedAt' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function presentCourse(Course $course): array
    {
        $course->loadMissing('coach');

        return [
            'id' => $course->id,
            'name' => $course->name,
            'description' => $course->description,
            'courseType' => $this->enumValue($course->course_type),
            'durationMinutes' => $course->duration_minutes,
            'difficulty' => $course->difficulty,
            'minCapacity' => $course->min_capacity,
            'maxCapacity' => $course->max_capacity,
            'coachStaffId' => $course->coach_staff_id,
            'coachName' => $course->coach?->name,
            'catalogStatus' => $this->enumValue($course->catalog_status),
            'version' => $course->version,
            'updatedAt' => $course->updated_at?->toIso8601String(),
        ];
    }

    private function assertCardProductScope(Tenant $tenant, Site $site, CardProduct $product): void
    {
        abort_unless($product->tenant_id === $tenant->id && $product->site_id === $site->id, 404);
    }

    private function assertCourseScope(Tenant $tenant, Site $site, Course $course): void
    {
        abort_unless($course->tenant_id === $tenant->id && $course->site_id === $site->id, 404);
    }
}
