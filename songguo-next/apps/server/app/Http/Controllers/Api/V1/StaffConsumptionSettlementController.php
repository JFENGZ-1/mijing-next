<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\ConsumptionEvent;
use App\Models\PayrollPeriod;
use App\Models\Staff;
use App\Services\Cards\StaffCardProductAccessService;
use App\Services\Compensation\ConsumptionReportQueryService;
use App\Services\Compensation\ConsumptionSettlementService;
use App\Services\Compensation\PayrollPeriodService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;

class StaffConsumptionSettlementController extends Controller
{
    public function preview(Request $request, int $site, int $appointment, StaffCardProductAccessService $access, ConsumptionSettlementService $settlements)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'consumption.read');
        $appointmentModel = $this->appointment($staff, $siteModel->id, $appointment);

        return ApiResponse::success($settlements->preview($appointmentModel));
    }

    public function settle(Request $request, int $site, int $appointment, StaffCardProductAccessService $access, ConsumptionSettlementService $settlements)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'consumption.adjust');
        $appointmentModel = $this->appointment($staff, $siteModel->id, $appointment);
        $event = $settlements->settle($appointmentModel, 'manual', $staff->id);

        return ApiResponse::success($event === null ? null : $settlements->present($event));
    }

    public function index(
        Request $request,
        int $site,
        StaffCardProductAccessService $access,
        ConsumptionSettlementService $settlements,
        ConsumptionReportQueryService $reports,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'consumption.read');
        $filters = $request->validate([
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'memberId' => ['sometimes', 'integer', 'min:1'],
            'courseId' => ['sometimes', 'integer', 'min:1'],
            'memberCardId' => ['sometimes', 'integer', 'min:1'],
            'coachStaffId' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'in:provisional,final,reversed,adjusted'],
            'dimension' => ['sometimes', 'nullable', 'in:coach,share,member,course,card'],
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if (($filters['dimension'] ?? null) !== null) {
            $paginator = $reports->paginate($staff->tenant_id, $siteModel->id, $filters, $filters['dimension'], $filters['perPage'] ?? 20);

            return ApiResponse::success([
                'dimension' => $filters['dimension'],
                'items' => collect($paginator->items())->map(fn ($row) => $reports->present($row))->values(),
                'summary' => $reports->totals($staff->tenant_id, $siteModel->id, $filters),
                'pagination' => $this->pagination($paginator),
            ]);
        }

        $query = $settlements->queryForSite($staff->tenant_id, $siteModel->id, $filters);
        $paginator = $query->paginate($filters['perPage'] ?? 20);
        $summary = $reports->totals($staff->tenant_id, $siteModel->id, $filters);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (ConsumptionEvent $event) => $settlements->present($event))->values(),
            'summary' => $summary,
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function show(Request $request, int $site, int $event, StaffCardProductAccessService $access, ConsumptionSettlementService $settlements)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'consumption.read');
        $eventModel = ConsumptionEvent::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)->findOrFail($event);

        return ApiResponse::success($settlements->present($eventModel));
    }

    public function appointmentSettlement(
        Request $request,
        int $site,
        int $appointment,
        StaffCardProductAccessService $access,
        ConsumptionSettlementService $settlements,
    ) {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'consumption.read');
        $appointmentModel = $this->appointment($staff, $siteModel->id, $appointment);
        $event = ConsumptionEvent::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)
            ->where('appointment_id', $appointmentModel->id)->first();

        return ApiResponse::success($event === null ? null : $settlements->present($event));
    }

    public function reverse(Request $request, int $site, int $event, StaffCardProductAccessService $access, ConsumptionSettlementService $settlements)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'consumption.adjust');
        $payload = $request->validate([
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $eventModel = ConsumptionEvent::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)->findOrFail($event);
        $reversed = $settlements->reverse($eventModel, $payload['reason'], $payload['commandKey'], DomainActor::staff($staff));

        return ApiResponse::success($settlements->present($reversed));
    }

    public function payrollIndex(Request $request, int $site, StaffCardProductAccessService $access, PayrollPeriodService $periods)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'payroll.period.close');
        $filters = $request->validate([
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2200'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = $periods->queryForSite($staff->tenant_id, $siteModel->id)
            ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('starts_on', $year))
            ->when($filters['month'] ?? null, fn ($query, $month) => $query->whereMonth('starts_on', $month))
            ->paginate(min(max($request->integer('perPage', 20), 1), 100));

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn (PayrollPeriod $period) => $this->presentPeriod($periods, $siteModel, $period))->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function payrollStore(Request $request, int $site, StaffCardProductAccessService $access, PayrollPeriodService $periods)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'payroll.period.close');
        $payload = $request->validate([
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $period = $periods->create(DomainActor::staff($staff), $siteModel, $payload);

        return ApiResponse::success($this->presentPeriod($periods, $siteModel, $period), 201);
    }

    public function payrollClose(Request $request, int $site, int $period, StaffCardProductAccessService $access, PayrollPeriodService $periods)
    {
        [$staff, $siteModel] = $this->context($request, $site, $access, 'payroll.period.close');
        $payload = $request->validate([
            'expectedVersion' => ['required_without:version', 'integer', 'min:1'],
            'version' => ['required_without:expectedVersion', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:4', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $periodModel = PayrollPeriod::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)->findOrFail($period);
        $closed = $periods->close(
            DomainActor::staff($staff),
            $siteModel,
            $periodModel,
            (int) ($payload['expectedVersion'] ?? $payload['version']),
            $payload,
        );

        return ApiResponse::success($this->presentPeriod($periods, $siteModel, $closed));
    }

    private function context(Request $request, int $site, StaffCardProductAccessService $access, string $permission): array
    {
        /** @var Staff $staff */
        $staff = $request->attributes->get('staff_context');
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, $permission, $siteModel->id);

        return [$staff, $siteModel];
    }

    private function appointment(Staff $staff, int $siteId, int $id): Appointment
    {
        return Appointment::query()->where('tenant_id', $staff->tenant_id)->where('site_id', $siteId)->findOrFail($id);
    }

    private function presentPeriod(PayrollPeriodService $periods, $site, PayrollPeriod $period): array
    {
        return [
            ...$periods->present($period),
            ...$periods->metrics($period),
            ...$periods->closeState($site, $period),
        ];
    }

    private function pagination($paginator): array
    {
        return [
            'page' => $paginator->currentPage(), 'perPage' => $paginator->perPage(),
            'total' => $paginator->total(), 'lastPage' => $paginator->lastPage(),
        ];
    }
}
