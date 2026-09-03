<?php

namespace App\Services\Members;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MonthlyRankingService
{
    public function assertEnabled(Tenant $tenant): void
    {
        abort_unless($tenant->show_month_rank, 404);
    }

    /**
     * @return array<string, mixed>
     */
    public function list(Member $member, int $year, int $month): array
    {
        $viewerOptIn = (bool) $member->ranking_opt_in;
        $ranked = $this->rankedMembers($member->tenant_id, $year, $month);
        $list = $ranked->take(15)->values()->map(function (array $row, int $index) use ($member, $viewerOptIn) {
            return [
                'rank' => $index + 1,
                'memberId' => $row['memberId'],
                'displayName' => $row['displayName'],
                'avatarObjectKey' => $row['avatarObjectKey'],
                'avatarUrl' => $viewerOptIn ? $this->avatarUrl($row['avatarObjectKey']) : null,
                'appointmentCount' => $row['appointmentCount'],
                'isMe' => $row['memberId'] === $member->id,
            ];
        })->all();

        $myRow = $ranked->firstWhere('memberId', $member->id);
        $myRank = null;
        if ($member->ranking_opt_in && $myRow) {
            $myRank = $ranked->search(fn (array $row) => $row['memberId'] === $member->id) + 1;
        }

        $member->loadMissing('account.memberProfile', 'crmProfile');
        $profile = $member->account?->memberProfile;

        $avatarObjectKey = $profile?->avatar_object_key;

        return [
            'tenantId' => $member->tenant_id,
            'year' => $year,
            'month' => $month,
            'viewerOptIn' => $viewerOptIn,
            'items' => $list,
            'myRank' => [
                'rank' => $myRank,
                'displayName' => $profile?->display_name ?? $member->crmProfile?->name,
                'avatarObjectKey' => $avatarObjectKey,
                'avatarUrl' => $viewerOptIn ? $this->avatarUrl($avatarObjectKey) : null,
                'appointmentCount' => $this->appointmentCount($member, $year, $month),
            ],
        ];
    }

    public function currentMonthRank(Member $member): ?int
    {
        if (! $member->ranking_opt_in) {
            return null;
        }

        $now = now();
        $ranked = $this->rankedMembers($member->tenant_id, (int) $now->year, (int) $now->month);
        $position = $ranked->search(fn (array $row) => $row['memberId'] === $member->id);

        return $position === false ? null : $position + 1;
    }

    /**
     * @return Collection<int, array{memberId: int, displayName: ?string, avatarObjectKey: ?string, appointmentCount: int}>
     */
    private function rankedMembers(int $tenantId, int $year, int $month): Collection
    {
        $counts = Appointment::query()
            ->selectRaw('member_id, COUNT(*) as appointment_count')
            ->where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::Completed)
            ->whereYear('booked_at', $year)
            ->whereMonth('booked_at', $month)
            ->groupBy('member_id')
            ->orderByDesc('appointment_count')
            ->orderBy('member_id')
            ->get();

        if ($counts->isEmpty()) {
            return collect();
        }

        $members = Member::query()
            ->with(['account.memberProfile', 'crmProfile'])
            ->where('tenant_id', $tenantId)
            ->where('ranking_opt_in', true)
            ->whereIn('id', $counts->pluck('member_id'))
            ->get()
            ->keyBy('id');

        return $counts
            ->filter(fn ($row) => $members->has($row->member_id))
            ->map(function ($row) use ($members) {
                $member = $members->get($row->member_id);
                $profile = $member->account?->memberProfile;

                return [
                    'memberId' => $member->id,
                    'displayName' => $profile?->display_name ?? $member->crmProfile?->name,
                    'avatarObjectKey' => $profile?->avatar_object_key,
                    'appointmentCount' => (int) $row->appointment_count,
                ];
            })
            ->values();
    }

    private function appointmentCount(Member $member, int $year, int $month): int
    {
        return Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('status', AppointmentStatus::Completed)
            ->whereYear('booked_at', $year)
            ->whereMonth('booked_at', $month)
            ->count();
    }

    private function avatarUrl(?string $objectKey): ?string
    {
        if (! $objectKey) {
            return null;
        }
        if (str_starts_with($objectKey, 'http://') || str_starts_with($objectKey, 'https://')) {
            return $objectKey;
        }

        return Storage::disk('public')->url($objectKey);
    }
}
