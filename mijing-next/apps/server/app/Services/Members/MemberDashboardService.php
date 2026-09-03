<?php

namespace App\Services\Members;

use App\Enums\AppointmentStatus;
use App\Models\Account;
use App\Models\Appointment;
use App\Models\Member;
use App\Models\MemberLinkRequest;
use App\Models\Site;
use App\Models\SiteCarouselItem;
use App\Models\SiteNotice;
use App\Services\Booking\MemberAppointmentPresenter;
use App\Services\Cards\MemberCardReadService;
use App\Services\Members\MonthlyRankingService;
use App\Services\Points\PointLedgerReadService;
use App\Services\Tenant\MemberExperienceConfigService;
use App\Support\AvatarUrl;
use Illuminate\Support\Str;

class MemberDashboardService
{
    public function __construct(
        private readonly MemberCardReadService $cards,
        private readonly PointLedgerReadService $points,
        private readonly MonthlyRankingService $ranking,
        private readonly MemberExperienceConfigService $memberExperience,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function home(Account $account, Member $member, Site $site): array
    {
        $carouselItems = SiteCarouselItem::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (SiteCarouselItem $item) => [
                'id' => $item->id,
                'imageUrl' => $item->image_url,
                'linkUrl' => $item->link_url,
            ])
            ->values()
            ->all();

        $notices = SiteNotice::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (SiteNotice $notice) => $this->noticeTeaser($notice))
            ->values()
            ->all();

        $upcoming = Appointment::query()
            ->with(['session.course', 'session.coach.account', 'ledgerEntry'])
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::Waitlisted])
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Appointment $appointment) => MemberAppointmentPresenter::toArray($appointment))
            ->values()
            ->all();

        return [
            'carousel' => [
                'items' => $carouselItems,
                'defaultImageUrl' => $carouselItems === []
                    ? ($site->carousel_default_image_url ?? 'https://cdn.example.com/mijing/default-home-banner.jpg')
                    : null,
            ],
            'notices' => $notices,
            'upcomingAppointments' => $upcoming,
            'linkRequestWarning' => $this->linkRequestWarning($account, $member, $site),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mine(Account $account, Member $member): array
    {
        $member->loadMissing('tenant', 'account.memberProfile', 'crmProfile', 'homeSite');
        $tenant = $member->tenant;
        $profile = $account->memberProfile;
        $crmProfile = $member->crmProfile;
        $walletCards = $this->cards->memberWalletQuery($member)->get();
        $now = now();

        $completedQuery = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('status', AppointmentStatus::Completed);

        $monthCompletedQuery = (clone $completedQuery)
            ->whereYear('booked_at', $now->year)
            ->whereMonth('booked_at', $now->month);

        $absenceCount = Appointment::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('status', AppointmentStatus::Absent)
            ->whereYear('booked_at', $now->year)
            ->whereMonth('booked_at', $now->month)
            ->count();

        $pointsEnabled = (bool) $tenant?->points_enabled;

        return [
            'profile' => [
                'displayName' => $profile?->display_name ?? $crmProfile?->name,
                'avatarObjectKey' => $profile?->avatar_object_key,
                'avatarUrl' => $this->avatarUrl($profile?->avatar_object_key),
                'mobileMasked' => $this->maskedMobile($profile?->mobile_last4, $crmProfile?->mobile_last4),
            ],
            'cardCount' => $walletCards->count(),
            'cardList' => $this->cards->memberWalletSummaries($walletCards),
            'helloMessage' => $this->helloMessage(),
            'pointsEnabled' => $pointsEnabled,
            'showMonthRank' => $this->layoutEnabled($member, $tenant),
            'stats' => [
                'appointCount' => (clone $completedQuery)->count(),
                'lastMonthAppointCount' => $monthCompletedQuery->count(),
                'absenceCount' => $pointsEnabled ? null : $absenceCount,
                'totalPoint' => $pointsEnabled ? $this->points->totalPoint($member) : null,
                'monthRankNum' => $tenant?->show_month_rank ? $this->ranking->currentMonthRank($member) : null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function noticeList(Member $member, Site $site): array
    {
        return SiteNotice::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (SiteNotice $notice) => $this->noticeListItem($notice))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function noticeDetail(Member $member, int $noticeId): array
    {
        $notice = SiteNotice::query()
            ->where('tenant_id', $member->tenant_id)
            ->whereKey($noticeId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return [
            'id' => $notice->id,
            'siteId' => $notice->site_id,
            'title' => $notice->title,
            'body' => $notice->body,
            'coverImageUrl' => $notice->cover_image_url,
            'publishedAt' => $notice->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linkRequestWarning(Account $account, Member $member, Site $site): ?array
    {
        $request = MemberLinkRequest::query()
            ->where('account_id', $account->id)
            ->where('tenant_id', $member->tenant_id)
            ->where('site_id', $site->id)
            ->whereNotNull('active_key')
            ->whereIn('status', ['pending_member_confirmation', 'pending_staff_review'])
            ->latest('id')
            ->first();

        if (! $request) {
            return null;
        }

        return [
            'requestId' => $request->public_id,
            'status' => $request->status,
            'message' => match ($request->status) {
                'pending_member_confirmation' => '您有一条待确认的账号关联申请',
                default => '您的账号关联申请正在审核中',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noticeTeaser(SiteNotice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'excerpt' => Str::limit(strip_tags($notice->body), 80),
            'coverImageUrl' => $notice->cover_image_url,
            'publishedAt' => $notice->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noticeListItem(SiteNotice $notice): array
    {
        return [
            ...$this->noticeTeaser($notice),
            'body' => $notice->body,
        ];
    }

    private function helloMessage(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => '早上好',
            $hour < 18 => '下午好',
            default => '晚上好',
        };
    }

    private function maskedMobile(?string $profileLast4, ?string $crmLast4): ?string
    {
        $last4 = $profileLast4 ?: $crmLast4;

        return $last4 ? "*******{$last4}" : null;
    }

    private function avatarUrl(?string $objectKey): ?string
    {
        return AvatarUrl::fromObjectKey($objectKey);
    }

    private function layoutEnabled(Member $member, ?\App\Models\Tenant $tenant): bool
    {
        $site = $member->homeSite;
        if ($site) {
            return $this->memberExperience->layoutEnabled($site, 'showMonthRank');
        }

        return (bool) $tenant?->show_month_rank;
    }
}
