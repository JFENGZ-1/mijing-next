<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CardProduct;
use App\Models\Course;
use App\Models\Staff;
use App\Services\Cards\StaffCardProductAccessService;
use App\Services\Compensation\CardProductCourseRuleService;
use App\Services\Compensation\CourseCompensationRuleService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class StaffCompensationRuleController extends Controller
{
    public function cardRules(
        Request $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductCourseRuleService $rules,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.rule.read');
        $product = $this->product($staff, $siteModel->id, $cardProduct);

        return ApiResponse::success([
            'cardProductId' => $product->id,
            'cardProductName' => $product->name,
            'cardType' => $product->card_type->value,
            'rulesVersion' => (int) $product->course_rule_version,
            'items' => $rules->listForProduct($staff->tenant_id, $siteModel->id, $product->id)
                ->map(fn ($rule) => $rules->present($rule))->values(),
        ]);
    }

    public function replaceCardRules(
        Request $request,
        int $site,
        int $cardProduct,
        StaffCardProductAccessService $access,
        CardProductCourseRuleService $rules,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.rule.write');
        $product = $this->product($staff, $siteModel->id, $cardProduct);
        $payload = $request->validate([
            'rules' => ['required', 'array'],
            'rules.*.courseId' => ['required', 'integer', 'min:1', 'distinct'],
            'rules.*.deductionType' => ['required', 'in:amount,count,period_auto'],
            'rules.*.amountCents' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.*.countUnits' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rules.*.effectiveAt' => ['sometimes', 'nullable', 'date'],
            'commandKey' => ['required', 'uuid'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ]);
        $saved = $rules->replaceRules(
            DomainActor::staff($staff), $siteModel, $product, $payload['rules'],
            $payload['commandKey'], $payload['reason'], (int) $payload['expectedVersion'],
        );
        $product->refresh();

        return ApiResponse::success([
            'cardProductId' => $product->id,
            'cardProductName' => $product->name,
            'cardType' => $product->card_type->value,
            'rulesVersion' => (int) $product->course_rule_version,
            'items' => collect($saved)->map(fn ($rule) => $rules->present($rule))->values(),
        ]);
    }

    public function courseRule(
        Request $request,
        int $site,
        int $course,
        StaffCardProductAccessService $access,
        CourseCompensationRuleService $rules,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.rule.read');
        $courseModel = $this->course($staff, $siteModel->id, $course);

        return ApiResponse::success([
            'courseId' => $courseModel->id,
            'rule' => $rules->present($rules->latestForCourse($staff->tenant_id, $siteModel->id, $courseModel->id)),
        ]);
    }

    public function saveCourseRule(
        Request $request,
        int $site,
        int $course,
        StaffCardProductAccessService $access,
        CourseCompensationRuleService $rules,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'compensation.rule.write');
        $courseModel = $this->course($staff, $siteModel->id, $course);
        $payload = $request->validate([
            'sessionFeeCents' => ['required', 'integer', 'min:0'],
            'effectiveAt' => ['sometimes', 'nullable', 'date'],
            'roleRates' => ['required', 'array'],
            'roleRates.*.compensationRoleId' => ['required', 'integer', 'min:1', 'distinct'],
            'roleRates.*.rateBps' => ['required', 'integer', 'between:0,10000'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'commandKey' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ]);
        $saved = $rules->saveVersion(
            DomainActor::staff($staff), $siteModel, $courseModel, $payload,
            $payload['commandKey'], (int) $payload['expectedVersion'], $payload['reason'],
        );

        return ApiResponse::success(['courseId' => $courseModel->id, 'rule' => $rules->present($saved)]);
    }

    private function context(Request $request, int $site, StaffCardProductAccessService $access, string $permission): array
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff_context');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, $permission, $siteModel->id);

        return [$staff, $siteModel];
    }

    private function product(Staff $staff, int $siteId, int $id): CardProduct
    {
        return CardProduct::query()->where('tenant_id', $staff->tenant_id)->where('site_id', $siteId)->findOrFail($id);
    }

    private function course(Staff $staff, int $siteId, int $id): Course
    {
        return Course::query()->where('tenant_id', $staff->tenant_id)->where('site_id', $siteId)->findOrFail($id);
    }
}
