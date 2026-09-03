<?php

namespace App\Services\Members;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardOrderStatus;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Cards\MemberCardReadService;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Support\Carbon;

class StaffCrmMemberMetricsService
{
    public function __construct(
        private readonly MemberCardReadService $cards,
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * @return array{
     *   totalPayAmount: string|null,
     *   groupMonthCount: int|null,
     *   groupTotalCount: int|null,
     *   privateMonthCount: int|null,
     *   privateTotalCount: int|null,
     *   absenceMonthCount: int|null,
     *   absenceTotalCount: int|null,
     *   consumedAmount: string|null,
     *   residualValue: string|null,
     *   noClassDays: int|null
     * }
     */
    public function summarize(Staff $staff, Site $site, Member $member): array
    {
        $booking = $staff->hasPermission('booking.member-history.list', $site->id)
            ? $this->bookingMetrics($staff, $site, $member)
            : $this->emptyBookingMetrics();

        $totalPayAmount = $staff->hasPermission('order.read', $site->id)
            ? $this->totalPaidAmount($staff, $site, $member)
            : null;

        $canReadCards = $staff->hasPermission('member-card.read', $site->id)
            || $staff->hasPermission('crm.member.card.read', $site->id);
        $cardValues = $canReadCards
            ? $this->cardValueMetrics($staff, $site, $member)
            : ['consumedAmount' => null, 'residualValue' => null];

        return [
            'totalPayAmount' => $totalPayAmount,
            ...$booking,
            ...$cardValues,
        ];
    }

    /**
     * @return array{
     *   groupMonthCount: int,
     *   groupTotalCount: int,
     *   privateMonthCount: int,
     *   privateTotalCount: int,
     *   absenceMonthCount: int,
     *   absenceTotalCount: int,
     *   noClassDays: int
     * }
     */
    private function bookingMetrics(Staff $staff, Site $site, Member $member): array
    {
        $monthStart = now()->startOfMonth();
        $attended = Appointment::query()
            ->join('schedule_sessions', 'schedule_sessions.id', '=', 'appointments.session_id')
            ->where('appointments.tenant_id', $staff->tenant_id)
            ->where('appointments.site_id', $site->id)
            ->where('appointments.member_id', $member->id)
            ->where('schedule_sessions.tenant_id', $staff->tenant_id)
            ->where('schedule_sessions.site_id', $site->id)
            ->where('schedule_sessions.starts_at', '<', now())
            ->whereIn('appointments.status', [
                AppointmentStatus::Completed->value,
                AppointmentStatus::Confirmed->value,
            ]);

        $counts = (clone $attended)
            ->selectRaw(
                'schedule_sessions.session_kind as session_kind, COUNT(*) as total_count, '
                .'SUM(CASE WHEN schedule_sessions.starts_at >= ? THEN 1 ELSE 0 END) as month_count',
                [$monthStart],
            )
            ->groupBy('schedule_sessions.session_kind')
            ->get()
            ->keyBy('session_kind');

        $absences = Appointment::query()
            ->join('schedule_sessions', 'schedule_sessions.id', '=', 'appointments.session_id')
            ->where('appointments.tenant_id', $staff->tenant_id)
            ->where('appointments.site_id', $site->id)
            ->where('appointments.member_id', $member->id)
            ->where('schedule_sessions.tenant_id', $staff->tenant_id)
            ->where('schedule_sessions.site_id', $site->id)
            ->where('appointments.status', AppointmentStatus::Absent->value)
            ->selectRaw(
                'COUNT(*) as total_count, '
                .'SUM(CASE WHEN schedule_sessions.starts_at >= ? THEN 1 ELSE 0 END) as month_count',
                [$monthStart],
            )
            ->first();

        $lastClassAt = (clone $attended)->max('schedule_sessions.starts_at');
        $noClassDays = $lastClassAt === null
            ? 0
            : (int) Carbon::parse($lastClassAt)->startOfDay()->diffInDays(now()->startOfDay());

        $group = $counts->get(ScheduleSessionKind::Group->value);
        $private = $counts->get(ScheduleSessionKind::Private->value);

        return [
            'groupMonthCount' => (int) ($group?->month_count ?? 0),
            'groupTotalCount' => (int) ($group?->total_count ?? 0),
            'privateMonthCount' => (int) ($private?->month_count ?? 0),
            'privateTotalCount' => (int) ($private?->total_count ?? 0),
            'absenceMonthCount' => (int) ($absences?->month_count ?? 0),
            'absenceTotalCount' => (int) ($absences?->total_count ?? 0),
            'noClassDays' => max(0, $noClassDays),
        ];
    }

    /**
     * @return array{
     *   groupMonthCount: null,
     *   groupTotalCount: null,
     *   privateMonthCount: null,
     *   privateTotalCount: null,
     *   absenceMonthCount: null,
     *   absenceTotalCount: null,
     *   noClassDays: null
     * }
     */
    private function emptyBookingMetrics(): array
    {
        return [
            'groupMonthCount' => null,
            'groupTotalCount' => null,
            'privateMonthCount' => null,
            'privateTotalCount' => null,
            'absenceMonthCount' => null,
            'absenceTotalCount' => null,
            'noClassDays' => null,
        ];
    }

    private function totalPaidAmount(Staff $staff, Site $site, Member $member): string
    {
        $orders = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_id', $member->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->with('amountCorrections')
            ->get();

        $total = $orders->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order));

        return $this->decimal($total);
    }

    /**
     * @return array{consumedAmount: string|null, residualValue: string|null}
     */
    private function cardValueMetrics(Staff $staff, Site $site, Member $member): array
    {
        $cards = MemberCard::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('member_id', $member->id)
            ->where('status', '!=', MemberCardStatus::Voided)
            ->get();

        if ($cards->isEmpty()) {
            return ['consumedAmount' => '0.00', 'residualValue' => '0.00'];
        }

        $consumed = 0.0;
        $residual = 0.0;
        $hasConsumed = false;
        $hasResidual = false;

        foreach ($cards as $card) {
            $metrics = $this->cards->staffValueMetrics($card);
            if ($metrics['consumedAmount'] !== null) {
                $consumed += (float) $metrics['consumedAmount'];
                $hasConsumed = true;
            }

            if (
                $card->status !== MemberCardStatus::Archived
                && $card->archived_at === null
                && $metrics['residualValue'] !== null
            ) {
                $residual += (float) $metrics['residualValue'];
                $hasResidual = true;
            }
        }

        return [
            'consumedAmount' => $hasConsumed ? $this->decimal($consumed) : null,
            'residualValue' => $hasResidual ? $this->decimal($residual) : null,
        ];
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
