<?php

namespace App\Services\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Appointment;
use App\Services\Members\StaffMemberAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        $query = $this->scopedMembers($staff, $site)
            ->whereNotNull('members.joined_at')
            ->with('crmProfile');

        $this->applyMemberStatusFilter($query, $site, $memberStatus);

        $items = $query->get()
            ->filter(fn (Member $member) => $this->recurringDateWithinDays($member->joined_at, $days, $today))
            ->sortBy([
                fn (Member $member) => $this->nextOccurrence($member->joined_at, $today)->timestamp,
                fn (Member $member) => $member->id,
            ])
            ->values();

        return $this->presentMemberPage($staff, $site, $items, $page, $perPage, [
            'thresholdDays' => $days,
            'memberStatus' => $memberStatus,
        ], fn (Member $member) => [
            'joinedAt' => $member->joined_at?->toDateString(),
            'anniversaryOn' => $this->nextOccurrence($member->joined_at, $today)->toDateString(),
            'daysUntilAnniversary' => $today->diffInDays($this->nextOccurrence($member->joined_at, $today)),
            'lastClassDate' => $this->lastClassDate($staff, $site, $member),
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

        $query = $this->scopedMembers($staff, $site)
            ->with('crmProfile');

        $this->applyValidMemberConstraint($query, $site, $today->toDateString());

        $items = $query->get()
            ->map(function (Member $member) use ($staff, $site) {
                $member->setAttribute('last_class_date', $this->lastClassDate($staff, $site, $member));

                return $member;
            })
            ->filter(function (Member $member) use ($cutoff) {
                $lastClass = $member->getAttribute('last_class_date');

                return $lastClass === null || Carbon::parse($lastClass)->lt($cutoff);
            })
            ->sortByDesc(fn (Member $member) => $member->getAttribute('last_class_date') ?? '0000-00-00')
            ->values();

        return $this->presentMemberPage($staff, $site, $items, $page, $perPage, [
            'thresholdDays' => $days,
        ], fn (Member $member) => [
            'lastClassDate' => $member->getAttribute('last_class_date'),
            'daysSinceLastClass' => $member->getAttribute('last_class_date')
                ? $today->diffInDays(Carbon::parse($member->getAttribute('last_class_date')))
                : null,
        ]);
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
        $query = $this->scopedMembers($staff, $site)
            ->whereHas('crmProfile', fn (Builder $profile) => $profile->whereNotNull('birth_date'))
            ->with('crmProfile');

        $this->applyMemberStatusFilter($query, $site, $memberStatus);

        $items = $query->get()
            ->filter(fn (Member $member) => $this->recurringDateWithinDays($member->crmProfile?->birth_date, $days, $today))
            ->sortBy([
                fn (Member $member) => $this->nextOccurrence($member->crmProfile?->birth_date, $today)->timestamp,
                fn (Member $member) => $member->id,
            ])
            ->values();

        return $this->presentMemberPage($staff, $site, $items, $page, $perPage, [
            'thresholdDays' => $days,
            'memberStatus' => $memberStatus,
        ], fn (Member $member) => [
            'birthDate' => $member->crmProfile?->birth_date?->format('Y-m-d'),
            'birthdayOn' => $this->nextOccurrence($member->crmProfile?->birth_date, $today)->toDateString(),
            'daysUntilBirthday' => $today->diffInDays($this->nextOccurrence($member->crmProfile?->birth_date, $today)),
            'lastClassDate' => $this->lastClassDate($staff, $site, $member),
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

        $query = $this->scopedMembers($staff, $site)
            ->where('members.status', 'lead')
            ->where('members.joined_at', '>=', $since)
            ->whereDoesntHave('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))
            ->with('crmProfile')
            ->orderByDesc('members.joined_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'thresholdDays' => $days,
            'items' => collect($paginator->items())
                ->map(fn (Member $member) => array_merge(
                    $this->memberFields($member, $staff, $site),
                    [
                        'status' => $member->status,
                        'joinedAt' => $member->joined_at?->toIso8601String(),
                        'lastClassDate' => $this->lastClassDate($staff, $site, $member),
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
            ->with(['member.crmProfile'])
            ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(freeze_state, '$.holiday.plannedEndAt')) asc");

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'thresholdDays' => $days,
            'items' => collect($paginator->items())
                ->map(function (MemberCard $card) use ($staff, $site, $today) {
                    $holiday = is_array($card->freeze_state['holiday'] ?? null)
                        ? $card->freeze_state['holiday']
                        : [];
                    $plannedEndAt = $holiday['plannedEndAt'] ?? null;

                    return array_merge(
                        $this->memberFields($card->member, $staff, $site),
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
                            'lastClassDate' => $this->lastClassDate($staff, $site, $card->member),
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

    private function recurringDateWithinDays(?Carbon $referenceDate, int $days, Carbon $today): bool
    {
        if ($referenceDate === null) {
            return false;
        }

        $next = $this->nextOccurrence($referenceDate, $today);

        return $next->lte($today->copy()->addDays($days));
    }

    private function nextOccurrence(?Carbon $referenceDate, Carbon $today): Carbon
    {
        $candidate = Carbon::create($today->year, $referenceDate->month, $referenceDate->day)->startOfDay();
        if ($candidate->lt($today)) {
            $candidate->addYear();
        }

        return $candidate;
    }

    private function lastClassDate(Staff $staff, Site $site, ?Member $member): ?string
    {
        if ($member === null) {
            return null;
        }

        $sessionStartsAt = Appointment::query()
            ->where('appointments.tenant_id', $staff->tenant_id)
            ->where('appointments.site_id', $site->id)
            ->where('appointments.member_id', $member->id)
            ->whereIn('appointments.status', [AppointmentStatus::Completed, AppointmentStatus::Confirmed])
            ->join('schedule_sessions', 'schedule_sessions.id', '=', 'appointments.session_id')
            ->max('schedule_sessions.starts_at');

        return $sessionStartsAt ? Carbon::parse($sessionStartsAt)->toDateString() : null;
    }

    /**
     * @param  Collection<int, Member>  $items
     * @param  array<string, mixed>  $meta
     * @param  callable(Member): array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function presentMemberPage(
        Staff $staff,
        Site $site,
        Collection $items,
        int $page,
        int $perPage,
        array $meta,
        callable $extra,
    ): array {
        $total = $items->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $offset = ($page - 1) * $perPage;

        $pageItems = $items
            ->slice($offset, $perPage)
            ->values()
            ->map(fn (Member $member) => array_merge(
                $this->memberFields($member, $staff, $site),
                $extra($member),
            ))
            ->all();

        return array_merge($meta, [
            'items' => $pageItems,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
            ],
            'computedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{memberId: int, memberNo: string, memberName: ?string, memberAvatarUrl: ?string}
     */
    private function memberFields(?Member $member, Staff $staff, Site $site): array
    {
        if ($member === null) {
            return [
                'memberId' => 0,
                'memberNo' => '',
                'memberName' => null,
                'memberAvatarUrl' => null,
            ];
        }

        $canReadMemberNames = $staff->hasPermission('crm.member.read', $site->id);
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
