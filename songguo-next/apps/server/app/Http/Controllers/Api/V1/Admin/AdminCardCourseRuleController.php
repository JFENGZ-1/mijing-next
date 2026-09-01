<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplaceCardCourseRulesRequest;
use App\Http\Requests\Admin\SaveCourseCompensationRuleRequest;
use App\Http\Requests\Admin\UpdateCardProductPaymentMethodsRequest;
use App\Models\CardProduct;
use App\Models\CardProductCourseRule;
use App\Models\Course;
use App\Models\CourseCompensationRule;
use App\Models\Site;
use App\Models\Tenant;
use App\Services\Cards\CardProductPaymentMethodService;
use App\Services\Compensation\CardProductCourseRuleService;
use App\Services\Compensation\CourseCompensationRuleService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCardCourseRuleController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __construct(
        private readonly CardProductPaymentMethodService $paymentMethods,
        private readonly CardProductCourseRuleService $cardRules,
        private readonly CourseCompensationRuleService $courseRules,
    ) {}

    public function paymentMethods(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = CardProduct::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('catalog_status', $status))
            ->orderBy('name')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (CardProduct $product) => $this->presentPaymentMethods($product))->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function updatePaymentMethods(
        UpdateCardProductPaymentMethodsRequest $request,
        Tenant $tenant,
        Site $site,
        CardProduct $cardProduct,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validated();
        $product = $this->paymentMethods->update(
            $this->actor($request),
            $site,
            $cardProduct,
            $validated['allowedPaymentMethods'],
            (int) $validated['version'],
            $validated['commandKey'],
            $validated['reason'],
        );

        return ApiResponse::success($this->presentPaymentMethods($product));
    }

    public function cardCourseRules(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = CardProduct::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->with(['courseRules' => fn ($query) => $query
                ->whereIn('status', ['active', 'scheduled'])
                ->with('course:id,name,course_type')
                ->orderBy('course_id')
                ->orderByDesc('version')])
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('name', 'like', "%{$term}%")
                    ->orWhereHas('courseRules.course', fn ($courses) => $courses->where('name', 'like', "%{$term}%"));
            }))
            ->orderBy('name')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (CardProduct $product) => [
                'cardProductId' => $product->id,
                'cardProductName' => $product->name,
                'cardType' => $this->enumValue($product->card_type),
                'catalogStatus' => $this->enumValue($product->catalog_status),
                'rulesVersion' => (int) $product->course_rule_version,
                'rules' => $product->courseRules
                    ->unique('course_id')
                    ->map(fn (CardProductCourseRule $rule) => $this->presentCardRule($rule))
                    ->values(),
                'updatedAt' => $product->courseRules->max('updated_at')?->toIso8601String(),
            ])->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function replaceCardCourseRules(
        ReplaceCardCourseRulesRequest $request,
        Tenant $tenant,
        Site $site,
        CardProduct $cardProduct,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless(
            $cardProduct->tenant_id === $tenant->id && $cardProduct->site_id === $site->id,
            404,
        );
        $validated = $request->validated();
        $rules = collect($validated['rules'])->map(fn (array $rule) => array_filter([
            'courseId' => (int) $rule['courseId'],
            'deductionType' => $rule['deductionKind'],
            'amountCents' => $rule['deductionAmountCents'] ?? null,
            'countUnits' => $rule['deductionCount'] ?? null,
            'effectiveAt' => $rule['effectiveAt'] ?? null,
        ], fn ($value, $key) => $key !== 'effectiveAt' || $value !== null, ARRAY_FILTER_USE_BOTH))->all();
        $saved = $this->cardRules->replaceRules(
            $this->actor($request),
            $site,
            $cardProduct,
            $rules,
            $validated['commandKey'],
            $validated['reason'],
            (int) $validated['expectedVersion'],
        );

        return ApiResponse::success([
            'cardProductId' => $cardProduct->id,
            'rulesVersion' => (int) $cardProduct->refresh()->course_rule_version,
            'rules' => collect($saved)->map(fn (CardProductCourseRule $rule) => $this->presentCardRule($rule))->values(),
        ]);
    }

    public function courseCompensationRules(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'in:active,scheduled'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $latest = DB::table('course_compensation_rules')
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->whereIn('status', ['active', 'scheduled'])
            ->groupBy('course_id')
            ->select(['course_id', DB::raw('MAX(version) as latest_version')]);
        $query = CourseCompensationRule::query()
            ->joinSub($latest, 'latest_course_rules', fn ($join) => $join
                ->on('latest_course_rules.course_id', '=', 'course_compensation_rules.course_id')
                ->on('latest_course_rules.latest_version', '=', 'course_compensation_rules.version'))
            ->join('courses', 'courses.id', '=', 'course_compensation_rules.course_id')
            ->with('roleRates.role')
            ->when(
                $filters['status'] ?? null,
                fn ($builder, $status) => $builder->where('course_compensation_rules.status', $status),
            )
            ->when($filters['query'] ?? null, fn ($builder, $term) => $builder->where(function ($nested) use ($term) {
                $nested->where('courses.name', 'like', "%{$term}%")
                    ->orWhereHas('roleRates.role', fn ($roles) => $roles->where('name', 'like', "%{$term}%"));
            }))
            ->select([
                'course_compensation_rules.*',
                'courses.name as course_name',
            ])
            ->orderBy('courses.name');
        $paginator = $query->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(function (CourseCompensationRule $rule) {
                $presented = $this->courseRules->present($rule);

                return [
                    ...$presented,
                    'courseName' => $rule->course_name,
                    'formulaVersion' => 'course-rule-v'.$rule->version,
                    'updatedAt' => $rule->updated_at?->toIso8601String(),
                ];
            })->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function saveCourseCompensationRule(
        SaveCourseCompensationRuleRequest $request,
        Tenant $tenant,
        Site $site,
    ) {
        $site = $this->scopedSite($tenant, $site);
        $validated = $request->validated();
        $course = Course::query()
            ->where('tenant_id', $tenant->id)
            ->where('site_id', $site->id)
            ->findOrFail($validated['courseId']);
        if (($validated['id'] ?? null) !== null) {
            CourseCompensationRule::query()
                ->where('tenant_id', $tenant->id)
                ->where('site_id', $site->id)
                ->where('course_id', $course->id)
                ->findOrFail($validated['id']);
        }
        $payload = [
            'sessionFeeCents' => (int) $validated['sessionFeeCents'],
            'roleRates' => collect($validated['roleRates'])->map(fn (array $rate) => [
                'compensationRoleId' => (int) $rate['compensationRoleId'],
                'rateBps' => (int) $rate['rateBps'],
            ])->all(),
        ];
        if (($validated['effectiveAt'] ?? null) !== null) {
            $payload['effectiveAt'] = $validated['effectiveAt'];
        }
        $rule = $this->courseRules->saveVersion(
            $this->actor($request),
            $site,
            $course,
            $payload,
            $validated['commandKey'],
            (int) $validated['version'],
            $validated['reason'],
        );

        return ApiResponse::success([
            ...$this->courseRules->present($rule),
            'courseName' => $course->name,
            'formulaVersion' => 'course-rule-v'.$rule->version,
            'updatedAt' => $rule->updated_at?->toIso8601String(),
        ]);
    }

    private function actor(Request $request): DomainActor
    {
        return DomainActor::superAdmin($request->user());
    }

    private function presentPaymentMethods(CardProduct $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'cardType' => $this->enumValue($product->card_type),
            'priceCents' => Money::decimalToCents($product->price),
            'allowedPaymentMethods' => $this->paymentMethods->methods($product),
            'version' => (int) $product->version,
            'status' => $this->enumValue($product->catalog_status),
            'updatedAt' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function presentCardRule(CardProductCourseRule $rule): array
    {
        $rule->loadMissing(['cardProduct', 'course']);

        return [
            'id' => $rule->id,
            'cardProductId' => $rule->card_product_id,
            'cardProductName' => $rule->cardProduct?->name,
            'cardType' => $this->enumValue($rule->cardProduct?->card_type),
            'courseId' => $rule->course_id,
            'courseName' => $rule->course?->name,
            'deductionKind' => $rule->deduction_type,
            'deductionAmountCents' => $rule->amount_cents,
            'deductionCount' => $rule->count_units,
            'version' => $rule->version,
            'status' => $rule->status,
            'effectiveAt' => $rule->effective_at?->toIso8601String(),
            'updatedAt' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
