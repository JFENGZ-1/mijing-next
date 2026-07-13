<?php

namespace App\Services\Members;

use App\Enums\CardType;
use App\Enums\MemberCardStatus;
use App\Models\Member;
use App\Models\Site;
use App\Models\Staff;
use App\Support\PinyinInitial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffCrmMemberListService
{
    public function __construct(
        private readonly StaffMemberAccessService $access,
        private readonly MobileProtectionService $mobile,
    ) {}

    public function scopedQuery(Staff $staff, Site $site): Builder
    {
        return $this->access->query($staff, $site)
            ->whereNull('members.archived_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(Staff $staff, Site $site): array
    {
        $base = $this->scopedQuery($staff, $site);
        $today = Carbon::today()->toDateString();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $totalCount = (clone $base)->count();
        $monthCount = (clone $base)->whereHas('memberCards', fn (Builder $cards) => $cards
            ->where('member_cards.site_id', $site->id)
            ->whereNull('member_cards.archived_at')
            ->where('member_cards.status', '!=', MemberCardStatus::Voided)
            ->whereBetween('member_cards.issued_at', [$monthStart, $monthEnd]))->count();
        $validUserCount = (clone $base)->where(function (Builder $query) use ($site, $today) {
            $this->applyValidMemberConstraint($query, $site, $today);
        })->count();
        $nocardUserCount = (clone $base)->whereDoesntHave('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))->count();
        $blockedCount = (clone $base)->where('members.app_access_status', '!=', 'allowed')->count();
        $invalidUserCount = (clone $base)
            ->whereHas('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))
            ->where(function (Builder $query) use ($site, $today) {
                $this->applyInvalidMemberConstraint($query, $site, $today);
            })
            ->count();

        $names = (clone $base)
            ->join('member_crm_profiles', 'member_crm_profiles.member_id', '=', 'members.id')
            ->where('member_crm_profiles.tenant_id', $staff->tenant_id)
            ->pluck('member_crm_profiles.name');

        $buckets = [];
        foreach ($names as $name) {
            $initial = PinyinInitial::fromName($name);
            $buckets[$initial] = ($buckets[$initial] ?? 0) + 1;
        }
        ksort($buckets);
        $pinyinIndex = collect($buckets)
            ->map(fn (int $count, string $initial) => [
                'initial' => $initial,
                'count' => $count,
                'pingyinChar' => $initial,
                'ncount' => $count,
            ])
            ->values()
            ->all();

        return [
            'totalCount' => $totalCount,
            'monthCount' => $monthCount,
            'validUserCount' => $validUserCount,
            'invalidUserCount' => $invalidUserCount,
            'nocardUserCount' => $nocardUserCount,
            'nologinUserCount' => $blockedCount,
            'pinyinIndex' => $pinyinIndex,
            'pinyinlist' => $pinyinIndex,
        ];
    }

    public function applyListFilters(Builder $query, Request $request, Staff $staff, Site $site): Builder
    {
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            abort_unless(in_array($status, ['lead', 'active', 'frozen', 'closed'], true), 422, 'INVALID_FILTER');
            $query->where('members.status', $status);
        }

        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where(function (Builder $subquery) use ($term, $staff, $site) {
                $subquery->whereHas('crmProfile', fn (Builder $profile) => $profile
                    ->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'));
                if (preg_match('/^\+?[0-9 -]{7,24}$/', $term) && $staff->hasPermission('crm.member.mobile.search', $site->id)) {
                    $hash = $this->mobile->hashForTenant($this->mobile->normalize($term), $staff->tenant_id);
                    $subquery->orWhereHas('crmProfile', fn (Builder $profile) => $profile->where('mobile_hash', $hash));
                }
            });
        }

        if ($request->has('includeVisitors')) {
            if (! $request->boolean('includeVisitors')) {
                $query->whereHas('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site));
            }
        }

        if ($request->filled('tagIds')) {
            $tagIds = $this->parseCsvInts($request->string('tagIds')->toString());
            abort_if($tagIds === [], 422, 'INVALID_FILTER');
            foreach ($tagIds as $tagId) {
                $query->whereHas('tags', fn (Builder $tags) => $tags
                    ->whereKey($tagId)
                    ->where('member_tags.tenant_id', $staff->tenant_id));
            }
        }

        if ($request->filled('flag')) {
            $flags = $this->parseCsvInts($request->string('flag')->toString());
            abort_if($flags === [], 422, 'INVALID_FILTER');
            $query->whereHas('tags', fn (Builder $tags) => $tags
                ->where('member_tags.tenant_id', $staff->tenant_id)
                ->whereIn('member_tags.id', $flags));
        }

        if ($request->filled('sumMode')) {
            $today = Carbon::today()->toDateString();
            match ($request->string('sumMode')->toString()) {
                'valid' => $query->where(function (Builder $memberQuery) use ($site, $today) {
                    $this->applyValidMemberConstraint($memberQuery, $site, $today);
                }),
                'invalid' => $query
                    ->whereHas('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))
                    ->where(function (Builder $memberQuery) use ($site, $today) {
                        $this->applyInvalidMemberConstraint($memberQuery, $site, $today);
                    }),
                'noCard' => $query->whereDoesntHave('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site)),
                'blocked' => $query->where('members.app_access_status', '!=', 'allowed'),
                'monthNew' => $query->whereHas('memberCards', fn (Builder $cards) => $cards
                    ->where('member_cards.site_id', $site->id)
                    ->whereNull('member_cards.archived_at')
                    ->where('member_cards.status', '!=', MemberCardStatus::Voided)
                    ->whereBetween('member_cards.issued_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])),
                'all' => null,
                default => abort(422, 'INVALID_FILTER'),
            };
        }

        if ($request->filled('pinyinInitial')) {
            $initials = collect(explode(',', $request->string('pinyinInitial')->toString()))
                ->map(fn (string $value) => strtoupper(trim($value)))
                ->filter()
                ->unique()
                ->values()
                ->all();
            abort_if($initials === [], 422, 'INVALID_FILTER');

            $candidateIds = (clone $query)->pluck('members.id');
            if ($candidateIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $matchingIds = Member::query()
                    ->whereIn('id', $candidateIds)
                    ->with('crmProfile')
                    ->get()
                    ->filter(fn (Member $member) => in_array(PinyinInitial::fromName($member->crmProfile?->name), $initials, true))
                    ->pluck('id')
                    ->all();
                $query->whereIn('members.id', $matchingIds === [] ? [-1] : $matchingIds);
            }
        }

        if ($request->filled('runOff')) {
            $runOff = $request->integer('runOff');
            abort_unless($runOff === 1, 422, 'INVALID_FILTER');
            $this->applyRunOffFilter($query, $site, Carbon::today()->toDateString());
        }

        return $query;
    }

    private function applyRunOffFilter(Builder $query, Site $site, string $today): void
    {
        $cutoff = Carbon::now()->subMonths(3)->startOfDay();

        $query
            ->whereHas('memberCards', fn (Builder $cards) => $this->applyCountableCardConstraint($cards, $site))
            ->where(function (Builder $memberQuery) use ($site, $today) {
                $this->applyInvalidMemberConstraint($memberQuery, $site, $today);
            })
            ->whereDoesntHave('memberCards', function (Builder $cards) use ($site, $cutoff) {
                $this->applyCountableCardConstraint($cards, $site);
                $cards->where('member_cards.issued_at', '>=', $cutoff);
            })
            ->whereRaw(
                '(select max(coalesce(member_cards.valid_until, date(member_cards.issued_at))) from member_cards where member_cards.member_id = members.id and member_cards.tenant_id = ? and member_cards.site_id = ? and member_cards.archived_at is null and member_cards.status != ?) < ?',
                [$site->tenant_id, $site->id, MemberCardStatus::Voided->value, $cutoff->toDateString()],
            );
    }

    /**
     * @return list<int>
     */
    private function parseCsvInts(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $part) => (int) trim($part))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function applyCountableCardConstraint(Builder $cards, Site $site): void
    {
        $cards
            ->where('member_cards.tenant_id', $site->tenant_id)
            ->where('member_cards.site_id', $site->id)
            ->whereNull('member_cards.archived_at')
            ->where('member_cards.status', '!=', MemberCardStatus::Voided);
    }

    private function applyValidMemberConstraint(Builder $query, Site $site, string $today): void
    {
        $query->whereHas('memberCards', fn (Builder $cards) => $cards
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
}
