<?php

namespace App\Services\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use App\Services\Members\StaffMemberAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationReminderService
{
    public const DEFAULT_ANNIVERSARY_DAYS = 7;

    public const DEFAULT_NO_CLASS_DAYS = 30;

    public const DEFAULT_BIRTHDAY_DAYS = 7;

    public const DEFAULT_VISITOR_DAYS = 30;

    public const DEFAULT_HOLIDAY_DUE_DAYS = 7;

    public function __construct(
        private readonly StaffMemberAccessService $members,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function anniversary(
        Staff $staff,
        Site $site,
        int $days,
        string $memberStatus,
        int $page,
        int $perPage,
    ): array {
        $today = Carbon::today();
        $query = $this->withLastCompletedClass(
            $this->scopedMembers($staff, $site),
            $staff,
            $site,
        )
            ->whereNotNull('members.joined_at')
            ->with(['crmProfile', 'account']);

        $this->applyMemberStatusFilter($query, $site, $memberStatus);
        $this->applyRecurringDateWindow($query, 'members.joined_at', $today, $days);
        $this->orderByNextOccurrence($query, 'members.joined_at', $today);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->presentMemberPaginator($staff, $site, $paginator, [
            'thresholdDays' => $days,
            'memberStatus' => $memberStatus,
        ], fn (Member $member) => [
            'joinedAt' => $member->joined_at?->toDateString(),
            'anniversaryOn' => $this->nextOccurrence($member->joined_at, $today)->toDateString(),
            'daysUntilAnniversary' => $today->diffInDays($this->nextOccurrence($member->joined_at, $today)),
            'lastClassDate' => $this->formatLastClassDate($member->getAttribute('last_class_at')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function noClass(
        Staff $staff,
        Site $site,
        int $days,
        int $page,
        int $perPage,
    ): array {
        $today = Carbon::today();
        $cutoff = $today->copy()->subDays($days);
        $now = now();

        $query = $this->withLastCompletedClass(
            $this->scopedMembers($staff, $site),
            $staff,
            $site,
        )
            ->with(['crmProfile', 'account'])
            ->whereNotExists(function ($recentClasses) use ($staff, $site, $cutoff, $now) {
                $recentClasses->selectRaw('1')
                    ->from('appointments as recent_appointments')
                    ->join('schedule_sessions as recent_sessions', 'recent_sessions.id', '=', 'recent_appointments.session_id')
                    ->whereColumn('recent_appointments.member_id', 'members.id')
                    ->where('recent_appointments.tenant_id', $staff->tenant_id)
                    ->where('recent_appointments.site_id', $site->id)
                    ->where('recent_appointments.status', AppointmentStatus::Completed->value)
                    ->where('recent_sessions.tenant_id', $staff->tenant_id)
                    ->where('recent_sessions.site_id', $site->id)
                    ->where('recent_sessions.starts_at', '>=', $cutoff)
                    ->where('recent_sessions.starts_at', '<=', $now);
            })
            ->orderByDesc('last_class_at')
            ->orderBy('members.id');

        $this->applyValidMemberConstraint($query, $site, $today->toDateString());

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);

        return [
            'thresholdDays' => $days,
            'items' => collect($paginator->items())
                ->map(function (Member $member) use ($canReadMemberNames, $today) {
                    $lastClassDate = $this->formatLastClassDate($member->getAttribute('last_class_at'));

                    return array_merge($this->memberFields($member, $canReadMemberNames), [
                        'lastClassDate' => $lastClassDate,
                        'daysSinceLastClass' => $lastClassDate
                            ? (int) abs($today->diffInDays(Carbon::parse($lastClassDate)))
                            : null,
                    ]);
                })
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'computedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function birthdays(
        Staff $staff,
        Site $site,
        int $days,
        string $memberStatus,
        int $page,
        int $perPage,
    ): array {
        $today = Carbon::today();
        $query = $this->withLastCompletedClass(
            $this->scopedMembers($staff, $site),
            $staff,
            $site,
        )
            ->join('member_crm_profiles as birthday_profiles', function ($join) {
                $join->on('birthday_profiles.member_id', '=', 'members.id')
                    ->on('birthday_profiles.tenant_id', '=', 'members.tenant_id');
            })
            ->whereNotNull('birthday_profiles.birth_date')
            ->with(['crmProfile', 'account']);

        $this->applyMemberStatusFilter($query, $site, $memberStatus);
        $this->applyRecurringDateWindow($query, 'birthday_profiles.birth_date', $today, $days);
        $this->orderByNextOccurrence($query, 'birthday_profiles.birth_date', $today);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->presentMemberPaginator($staff, $site, $paginator, [
            'thresholdDays' => $days,
            'memberStatus' => $memberStatus,
        ], fn (Member $member) => [
            'birthDate' => $member->crmProfile?->birth_date?->format('Y-m-d'),
            'birthdayOn' => $this->nextOccurrence($member->crmProfile?->birth_date, $today)->toDateString(),
            'daysUntilBirthday' => $today->diffInDays($this->nextOccurrence($member->crmProfile?->birth_date, $today)),
            'lastClassDate' => $this->formatLastClassDate($member->getAttribute('last_class_at')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function visitors(
        Staff $staff,
        Site $site,
        int $days,
        int $page,
        int $perPage,
    ): array {
        $today = Carbon::today();
        $since = $today->copy()->subDays($days);

        $query = $this->withLastCompletedClass(
            $this->scopedMembers($staff, $site),
            $staff,
            $site,
        )
            ->where('members.status', 'lead')
            ->where('members.joined_at', '>=', $since)
            ->whereDoesntHave('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))
            ->with(['crmProfile', 'account'])
            ->orderByDesc('members.joined_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);

        return [
            'thresholdDays' => $days,
            'items' => collect($paginator->items())
                ->map(fn (Member $member) => array_merge(
                    $this->memberFields($member, $canReadMemberNames),
                    [
                        'status' => $member->status,
                        'joinedAt' => $member->joined_at?->toIso8601String(),
                        'lastClassDate' => $this->formatLastClassDate($member->getAttribute('last_class_at')),
                    ],
                ))
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'computedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function holidayDue(
        Staff $staff,
        Site $site,
        int $days,
        int $page,
        int $perPage,
    ): array {
        $today = Carbon::today()->toDateString();
        $until = Carbon::today()->addDays($days)->toDateString();

        $query = MemberCard::query()
            ->select('member_cards.*')
            ->addSelect([
                'last_class_at' => $this->lastCompletedClassSubquery($staff, $site, 'member_cards.member_id'),
            ])
            ->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $site->id)
            ->whereNull('archived_at')
            ->whereNotNull('freeze_state')
            ->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(freeze_state, '$.holiday.plannedEndAt')) >= ?",
                [$today],
            )
            ->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(freeze_state, '$.holiday.plannedEndAt')) <= ?",
                [$until],
            )
            ->with(['member.crmProfile', 'member.account'])
            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(freeze_state, '$.holiday.plannedEndAt')) asc");

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);

        return [
            'thresholdDays' => $days,
            'items' => collect($paginator->items())
                ->map(function (MemberCard $card) use ($canReadMemberNames, $today) {
                    $holiday = is_array($card->freeze_state['holiday'] ?? null)
                        ? $card->freeze_state['holiday']
                        : [];
                    $plannedEndAt = $holiday['plannedEndAt'] ?? null;

                    return array_merge(
                        $this->memberFields($card->member, $canReadMemberNames),
                        [
                            'memberCardId' => $card->id,
                            'cardNo' => $card->card_no,
                            'cardType' => $card->card_type->value,
                            'status' => $card->status->value,
                            'name' => $card->product_snapshot['name'] ?? null,
                            'holidayStartedAt' => $holiday['startedAt'] ?? null,
                            'holidayEndsAt' => $plannedEndAt,
                            'daysUntilHolidayEnds' => $plannedEndAt
                                ? Carbon::parse($today)->diffInDays(Carbon::parse($plannedEndAt))
                                : null,
                            'lastClassDate' => $this->formatLastClassDate($card->getAttribute('last_class_at')),
                        ],
                    );
                })
                ->values()
                ->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'computedAt' => now()->toIso8601String(),
        ];
    }

    private function scopedMembers(Staff $staff, Site $site): Builder
    {
        return $this->members->query($staff, $site)->whereNull('members.archived_at');
    }

    private function applyMemberStatusFilter(Builder $query, Site $site, string $memberStatus): void
    {
        $today = Carbon::today()->toDateString();

        match ($memberStatus) {
            'valid' => $this->applyValidMemberConstraint($query, $site, $today),
            'invalid' => $query
                ->whereHas('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))
                ->where(function (Builder $memberQuery) use ($site, $today) {
                    $this->applyInvalidMemberConstraint($memberQuery, $site, $today);
                }),
            'all' => null,
            default => abort(422, 'INVALID_FILTER'),
        };
    }

    private function applyValidMemberConstraint(Builder $query, Site $site, string $today): void
    {
        $query->where(function (Builder $memberQuery) use ($site, $today) {
            $memberQuery->whereHas('memberCards', fn (Builder $cards) => $cards
                ->where('member_cards.tenant_id', $site->tenant_id)
                ->where('member_cards.site_id', $site->id)
                ->whereNull('member_cards.archived_at')
                ->whereNotIn('member_cards.status', [
                    MemberCardStatus::Voided,
                    MemberCardStatus::Archived,
                    MemberCardStatus::Expired,
                    MemberCardStatus::Exhausted,
                ])
                ->where(function (Builder $validity) use ($today) {
                    $validity
                        ->where(function (Builder $stored) use ($today) {
                            $stored->where('member_cards.card_type', CardType::StoredValue)
                                ->where('member_cards.cached_balance', '>', 0)
                                ->where(function (Builder $until) use ($today) {
                                    $until->whereNull('member_cards.valid_until')
                                        ->orWhere('member_cards.valid_until', '>=', $today);
                                });
                        })
                        ->orWhere(function (Builder $count) use ($today) {
                            $count->where('member_cards.card_type', CardType::Count)
                                ->where('member_cards.cached_remaining_count', '>', 0)
                                ->where(function (Builder $until) use ($today) {
                                    $until->whereNull('member_cards.valid_until')
                                        ->orWhere('member_cards.valid_until', '>=', $today);
                                });
                        })
                        ->orWhere(function (Builder $period) use ($today) {
                            $period->where('member_cards.card_type', CardType::Period)
                                ->where(function (Builder $until) use ($today) {
                                    $until->whereNull('member_cards.valid_until')
                                        ->orWhere('member_cards.valid_until', '>=', $today);
                                });
                        });
                }));
        });
    }

    private function applyInvalidMemberConstraint(Builder $query, Site $site, string $today): void
    {
        $query->whereDoesntHave('memberCards', fn (Builder $cards) => $cards
            ->where('member_cards.tenant_id', $site->tenant_id)
            ->where('member_cards.site_id', $site->id)
            ->whereNull('member_cards.archived_at')
            ->whereNotIn('member_cards.status', [
                MemberCardStatus::Voided,
                MemberCardStatus::Archived,
                MemberCardStatus::Expired,
                MemberCardStatus::Exhausted,
            ])
            ->where(function (Builder $validity) use ($today) {
                $validity
                    ->where(function (Builder $stored) use ($today) {
                        $stored->where('member_cards.card_type', CardType::StoredValue)
                            ->where('member_cards.cached_balance', '>', 0)
                            ->where(function (Builder $until) use ($today) {
                                $until->whereNull('member_cards.valid_until')
                                    ->orWhere('member_cards.valid_until', '>=', $today);
                            });
                    })
                    ->orWhere(function (Builder $count) use ($today) {
                        $count->where('member_cards.card_type', CardType::Count)
                            ->where('member_cards.cached_remaining_count', '>', 0)
                            ->where(function (Builder $until) use ($today) {
                                $until->whereNull('member_cards.valid_until')
                                    ->orWhere('member_cards.valid_until', '>=', $today);
                            });
                    })
                    ->orWhere(function (Builder $period) use ($today) {
                        $period->where('member_cards.card_type', CardType::Period)
                            ->where(function (Builder $until) use ($today) {
                                $until->whereNull('member_cards.valid_until')
                                    ->orWhere('member_cards.valid_until', '>=', $today);
                            });
                    });
            }));
    }

    private function applyCountableCardConstraint(Builder $cards, Site $site): void
    {
        $cards
            ->where('member_cards.tenant_id', $site->tenant_id)
            ->where('member_cards.site_id', $site->id)
            ->whereNull('member_cards.archived_at')
            ->where('member_cards.status', '!=', MemberCardStatus::Voided);
    }

    private function nextOccurrence(?Carbon $referenceDate, Carbon $today): Carbon
    {
        $candidate = Carbon::create($today->year, $referenceDate->month, $referenceDate->day)->startOfDay();
        if ($candidate->lt($today)) {
            $candidate->addYear();
        }

        return $candidate;
    }

    private function withLastCompletedClass(Builder $query, Staff $staff, Site $site): Builder
    {
        return $query
            ->select('members.*')
            ->addSelect([
                'last_class_at' => $this->lastCompletedClassSubquery($staff, $site, 'members.id'),
            ]);
    }

    private function lastCompletedClassSubquery(
        Staff $staff,
        Site $site,
        string $outerMemberColumn,
    ): \Illuminate\Database\Query\Builder {
        return DB::table('appointments as completed_appointments')
            ->join('schedule_sessions as completed_sessions', 'completed_sessions.id', '=', 'completed_appointments.session_id')
            ->selectRaw('MAX(completed_sessions.starts_at)')
            ->whereColumn('completed_appointments.member_id', $outerMemberColumn)
            ->where('completed_appointments.tenant_id', $staff->tenant_id)
            ->where('completed_appointments.site_id', $site->id)
            ->where('completed_appointments.status', AppointmentStatus::Completed->value)
            ->where('completed_sessions.tenant_id', $staff->tenant_id)
            ->where('completed_sessions.site_id', $site->id)
            ->where('completed_sessions.starts_at', '<=', now());
    }

    private function applyRecurringDateWindow(
        Builder $query,
        string $column,
        Carbon $today,
        int $days,
    ): void {
        [$monthDayExpression] = $this->recurringDateExpressions($column);

        $query->whereIn(DB::raw($monthDayExpression), $this->recurringMonthDays($today, $days));
    }

    private function orderByNextOccurrence(Builder $query, string $column, Carbon $today): void
    {
        [$monthDayExpression, $dateKeyExpression] = $this->recurringDateExpressions($column);
        $todayKey = ($today->month * 100) + $today->day;
        $february29 = Carbon::create(2000, 2, 29)->startOfDay();
        $nextLeapBirthday = $this->nextOccurrence($february29, $today);
        $february29SortKey = ($nextLeapBirthday->month * 100) + $nextLeapBirthday->day
            + ($nextLeapBirthday->year > $today->year ? 1200 : 0);

        $query
            ->orderByRaw(<<<SQL
                CASE
                    WHEN {$monthDayExpression} = '02-29' THEN {$february29SortKey}
                    WHEN {$dateKeyExpression} >= {$todayKey} THEN {$dateKeyExpression}
                    ELSE {$dateKeyExpression} + 1200
                END
                SQL)
            ->orderBy('members.id');
    }

    /** @return array{0: string, 1: string} */
    private function recurringDateExpressions(string $column): array
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => [
                "strftime('%m-%d', {$column})",
                "(CAST(strftime('%m', {$column}) AS INTEGER) * 100 + CAST(strftime('%d', {$column}) AS INTEGER))",
            ],
            'pgsql' => [
                "TO_CHAR({$column}, 'MM-DD')",
                "(EXTRACT(MONTH FROM {$column}) * 100 + EXTRACT(DAY FROM {$column}))",
            ],
            default => [
                "DATE_FORMAT({$column}, '%m-%d')",
                "(MONTH({$column}) * 100 + DAYOFMONTH({$column}))",
            ],
        };
    }

    /** @return list<string> */
    private function recurringMonthDays(Carbon $today, int $days): array
    {
        $monthDays = collect(range(0, $days))
            ->map(fn (int $offset) => $today->copy()->addDays($offset)->format('m-d'));

        $february29 = Carbon::create(2000, 2, 29)->startOfDay();
        if ($this->nextOccurrence($february29, $today)->lte($today->copy()->addDays($days))) {
            $monthDays->push('02-29');
        }

        return $monthDays->unique()->values()->all();
    }

    private function formatLastClassDate(mixed $sessionStartsAt): ?string
    {
        return $sessionStartsAt ? Carbon::parse($sessionStartsAt)->toDateString() : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  callable(Member): array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function presentMemberPaginator(
        Staff $staff,
        Site $site,
        LengthAwarePaginator $paginator,
        array $meta,
        callable $extra,
    ): array {
        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);
        $pageItems = collect($paginator->items())
            ->map(fn (Member $member) => array_merge(
                $this->memberFields($member, $canReadMemberNames),
                $extra($member),
            ))
            ->all();

        return array_merge($meta, [
            'items' => $pageItems,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
            'computedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{memberId: int, memberNo: string, memberName: ?string, memberAvatarUrl: ?string}
     */
    private function memberFields(?Member $member, bool $canReadMemberNames): array
    {
        if ($member === null) {
            return [
                'memberId' => 0,
                'memberNo' => '',
                'memberName' => null,
                'memberAvatarUrl' => null,
            ];
        }

        $rawName = $member->crmProfile?->name ?? $member->account?->display_name;

        return [
            'memberId' => $member->id,
            'memberNo' => $member->member_no,
            'memberName' => $canReadMemberNames ? $rawName : $this->maskName($rawName),
            'memberAvatarUrl' => $canReadMemberNames ? $member->account?->avatar_url : null,
        ];
    }

    private function maskName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return mb_substr($name, 0, 1).str_repeat('*', max(mb_strlen($name) - 1, 1));
    }
}
