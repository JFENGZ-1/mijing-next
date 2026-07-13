<?php

namespace App\Services\Dashboard;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardOrderStatus;
use App\Enums\ScheduleSessionKind;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\MemberCardOrder;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use App\Services\Orders\MemberCardOrderService;
use Illuminate\Support\Carbon;

class StaffDashboardService
{
    public function __construct(
        private readonly StaffMemberAccessService $members,
        private readonly MemberCardOrderService $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Staff $staff, Site $site): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $paidToday = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->with('amountCorrections')
            ->get();

        $todayRevenue = $paidToday
            ->sum(fn (MemberCardOrder $order) => (float) $this->orders->effectiveAmount($order));

        $appointmentBase = Appointment::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereIn('status', [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Waitlisted,
                AppointmentStatus::Completed,
            ])
            ->whereHas('session', fn ($query) => $query
                ->whereBetween('starts_at', [$todayStart, $todayEnd]));

        $groupAppointmentCount = (clone $appointmentBase)
            ->whereHas('session', fn ($query) => $query->where('session_kind', ScheduleSessionKind::Group))
            ->count();

        $privateAppointmentCount = (clone $appointmentBase)
            ->whereHas('session', fn ($query) => $query->where('session_kind', ScheduleSessionKind::Private))
            ->count();

        $newMemberCount = $this->members->query($staff, $site)
            ->whereBetween('joined_at', [$todayStart, $todayEnd])
            ->count();

        return [
            'greeting' => $this->greeting($staff),
            'kpis' => [
                'todayRevenue' => $this->decimalString($todayRevenue),
                'groupAppointmentCount' => $groupAppointmentCount,
                'privateAppointmentCount' => $privateAppointmentCount,
                'saleCardCount' => $paidToday->count(),
                'newMemberCount' => $newMemberCount,
            ],
            'asOf' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function salesFeed(Staff $staff, Site $site, int $page, int $perPage): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $paginator = MemberCardOrder::query()
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', MemberCardOrderStatus::Paid)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->with(['member.crmProfile', 'member.account', 'memberCard', 'amountCorrections'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => collect($paginator->items())
                ->map(fn (MemberCardOrder $order) => $this->salesFeedItem($order))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function appointmentFeed(Staff $staff, Site $site, int $page, int $perPage): array
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);

        $paginator = Appointment::query()
            ->join('schedule_sessions as dashboard_sessions', 'appointments.session_id', '=', 'dashboard_sessions.id')
            ->where('appointments.tenant_id', $staff->tenant_id)
            ->where('appointments.site_id', $site->id)
            ->where('appointments.status', AppointmentStatus::Confirmed)
            ->whereBetween('dashboard_sessions.starts_at', [$todayStart, $todayEnd])
            ->with([
                'member.crmProfile',
                'member.account',
                'session.course',
                'session.coach',
            ])
            ->orderBy('dashboard_sessions.starts_at')
            ->orderBy('appointments.id')
            ->select('appointments.*')
            ->paginate($perPage, ['appointments.*'], 'page', $page);

        return [
            'items' => collect($paginator->items())
                ->map(fn (Appointment $appointment) => $this->appointmentFeedItem($appointment, $canReadMemberNames))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentFeedItem(Appointment $appointment, bool $canReadMemberNames): array
    {
        /** @var Member|null $member */
        $member = $appointment->member;
        $session = $appointment->relationLoaded('session') ? $appointment->session : null;
        $rawName = $member?->crmProfile?->name ?? $member?->account?->display_name;

        return [
            'id' => $appointment->id,
            'memberId' => $appointment->member_id,
            'memberName' => $canReadMemberNames ? $rawName : $this->maskName($rawName),
            'memberAvatarUrl' => $canReadMemberNames ? $member?->account?->avatar_url : null,
            'courseName' => $session?->course?->name,
            'courseType' => $session?->course?->course_type->value,
            'sessionKind' => $session?->session_kind->value,
            'startsAt' => $session?->starts_at?->toIso8601String(),
            'endsAt' => $session?->ends_at?->toIso8601String(),
            'coachName' => $session?->coach?->name,
            'status' => $appointment->status->value,
            'bookedAt' => $appointment->booked_at?->toIso8601String(),
        ];
    }

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }

    /**
     * @return array{headline: string, hint: string}
     */
    private function greeting(Staff $staff): array
    {
        $hour = now()->hour;
        $timeGreeting = match (true) {
            $hour < 6 => '夜深了',
            $hour < 12 => '早上好',
            $hour < 14 => '中午好',
            $hour < 18 => '下午好',
            default => '晚上好',
        };

        return [
            'headline' => $staff->name,
            'hint' => "{$timeGreeting}，祝您工作愉快",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesFeedItem(MemberCardOrder $order): array
    {
        /** @var Member|null $member */
        $member = $order->member;
        $metadata = $order->metadata ?? [];

        return [
            'id' => $order->id,
            'orderNo' => $order->order_no,
            'memberId' => $order->member_id,
            'memberName' => $member?->crmProfile?->name ?? $member?->account?->display_name,
            'memberAvatarUrl' => $member?->account?->avatar_url,
            'soldAt' => $order->created_at?->toIso8601String(),
            'isNewMember' => $this->isNewMemberToday($member, $order->created_at),
            'cardName' => $this->cardName($order),
            'amount' => $this->orders->effectiveAmount($order),
            'paymentChannel' => $metadata['paymentChannel'] ?? null,
            'remark' => $metadata['remark'] ?? null,
        ];
    }

    private function isNewMemberToday(?Member $member, ?Carbon $soldAt): bool
    {
        if (! $member?->joined_at || ! $soldAt) {
            return false;
        }

        return $member->joined_at->toDateString() === $soldAt->toDateString();
    }

    private function cardName(MemberCardOrder $order): ?string
    {
        $snapshot = $order->memberCard?->product_snapshot;

        if (is_array($snapshot) && ! empty($snapshot['name'])) {
            return (string) $snapshot['name'];
        }

        $metadata = $order->metadata ?? [];

        return isset($metadata['cardName']) ? (string) $metadata['cardName'] : null;
    }

    private function decimalString(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
