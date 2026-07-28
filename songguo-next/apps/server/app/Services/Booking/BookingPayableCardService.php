<?php

namespace App\Services\Booking;

use App\Enums\CardProductCourseScopeKind;
use App\Enums\CardType;
use App\Enums\CourseType;
use App\Enums\MemberCardStatus;
use App\Enums\ScheduleSessionKind;
use App\Models\CardProductCourseScope;
use App\Models\Course;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\ScheduleSession;
use App\Services\Cards\MemberCardReadService;
use Illuminate\Support\Collection;

class BookingPayableCardService
{
    public function __construct(private MemberCardReadService $cards) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function payableCardsForSession(Member $member, ScheduleSession $session): array
    {
        $session->loadMissing('course');
        $cards = $this->cards->memberWalletQuery($member)
            ->where('site_id', $session->site_id)
            ->get()
            ->filter(function (MemberCard $card) use ($session): bool {
                try {
                    return $this->isPayableForSession($card, $session);
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                    if ($e->getMessage() === 'BOOKING_CARD_PRICE_UNKNOWN') {
                        return false;
                    }
                    throw $e;
                }
            });

        return $this->cards->memberWalletSummaries($cards);
    }

    public function resolvePayableCard(Member $member, ScheduleSession $session, int $memberCardId): MemberCard
    {
        $session->loadMissing('course');
        $card = MemberCard::query()
            ->where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id)
            ->where('site_id', $session->site_id)
            ->whereKey($memberCardId)
            ->firstOrFail();

        abort_unless($this->isEligibleForSession($card, $session), 409, 'BOOKING_CARD_NOT_PAYABLE');

        return $card;
    }

    public function isPayableForSession(MemberCard $card, ScheduleSession $session): bool
    {
        return $this->isEligibleForSession($card, $session)
            && $this->hasSufficientEntitlement($card, $session);
    }

    public function isEligibleForSession(MemberCard $card, ScheduleSession $session): bool
    {
        if (in_array($card->status, [MemberCardStatus::Archived, MemberCardStatus::Voided], true)
            || $card->archived_at !== null) {
            return false;
        }

        if (! in_array($card->status, [MemberCardStatus::Active, MemberCardStatus::PendingActivation], true)) {
            return false;
        }

        if ($card->status === MemberCardStatus::Frozen) {
            return false;
        }

        return $this->cardCoversCourse($card, $session->course, $session->session_kind);
    }

    public function deductSpec(MemberCard $card, ScheduleSession $session): array
    {
        $session->loadMissing('course');
        // 私教课扣费实时取 feeList（卡产品 courseScopes.price_override，对标原版 deductAmount）：
        // 计次卡=扣次数（缺省 1），储值卡=扣金额（缺省走卡默认价）
        $privateOverride = $session->course->course_type === CourseType::Private
            ? $this->privatePriceOverride($card, $session->course)
            : null;

        if ($card->card_type === CardType::Count) {
            $count = $privateOverride !== null && $privateOverride >= 1 ? (int) round($privateOverride) : 1;

            return ['type' => CardType::Count, 'count' => $count, 'amount' => null];
        }

        if ($card->card_type === CardType::Period) {
            return ['type' => CardType::Period, 'count' => null, 'amount' => null];
        }

        $amount = $privateOverride !== null
            ? number_format($privateOverride, 2, '.', '')
            : $this->resolveStoredValueAmount($card, $session->course);

        return ['type' => CardType::StoredValue, 'count' => null, 'amount' => $amount];
    }

    private function hasSufficientEntitlement(MemberCard $card, ScheduleSession $session): bool
    {
        $spec = $this->deductSpec($card, $session);

        if ($spec['type'] === CardType::Count) {
            return (int) ($card->cached_remaining_count ?? 0) >= (int) $spec['count'];
        }

        if ($spec['type'] === CardType::Period) {
            return $this->withinValidPeriod($card);
        }

        return (float) ($card->cached_balance ?? 0) >= (float) $spec['amount'];
    }

    private function withinValidPeriod(MemberCard $card): bool
    {
        $today = now()->startOfDay();

        if ($card->valid_from !== null && $today->lt($card->valid_from)) {
            return false;
        }

        if ($card->valid_until !== null && $today->gt($card->valid_until)) {
            return false;
        }

        return true;
    }

    private function resolveStoredValueAmount(MemberCard $card, Course $course): string
    {
        foreach ($card->product_snapshot['courseScopes'] ?? [] as $scope) {
            if ($this->scopeMatchesCourse($scope, $course)) {
                $override = $scope['priceOverride'] ?? null;
                if ($override !== null && $override !== '') {
                    return number_format((float) $override, 2, '.', '');
                }
            }
        }

        $default = $card->product_snapshot['bookingRules']['defaultPrice'] ?? null;
        abort_if($default === null || $default === '', 409, 'BOOKING_CARD_PRICE_UNKNOWN');

        return number_format((float) $default, 2, '.', '');
    }

    private function cardCoversCourse(MemberCard $card, Course $course, ScheduleSessionKind $sessionKind): bool
    {
        // 私教课（对标原版 getCardListForPay 按 courseId 实时查 feeList）：
        // 按卡产品「当前」courseScopes 实时判定，不依赖发卡时拷贝的 product_snapshot——
        // 私教扣费是后配的（applyFees 写卡产品 scopes），快照不会回填，实时查避免失配。
        if ($course->course_type === CourseType::Private) {
            return $this->productCoversPrivateCourse($card, $course);
        }

        /** @var list<array<string, mixed>> $scopes */
        $scopes = $card->product_snapshot['courseScopes'] ?? [];

        if ($scopes === []) {
            return true;
        }

        foreach ($scopes as $scope) {
            if ($this->scopeMatchesCourse($scope, $course)) {
                return true;
            }

            $scopeKind = (string) ($scope['scopeKind'] ?? '');
            if ($scopeKind === 'group' && $sessionKind === ScheduleSessionKind::Group) {
                return true;
            }
        }

        return false;
    }

    /**
     * 私教课卡覆盖判定：卡产品配了该私教课的 single scope 即可约；
     * 卡产品完全无 scope（无限制卡）也可约；无卡产品（手工发卡）回退快照匹配。
     */
    private function productCoversPrivateCourse(MemberCard $card, Course $course): bool
    {
        $productId = (int) ($card->card_product_id ?? 0);
        if ($productId < 1) {
            $scopes = $card->product_snapshot['courseScopes'] ?? [];
            if ($scopes === []) {
                return true;
            }
            foreach ($scopes as $scope) {
                if ($this->scopeMatchesCourse($scope, $course)) {
                    return true;
                }
            }

            return false;
        }

        $hasAnyScope = CardProductCourseScope::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('card_product_id', $productId)
            ->exists();
        if (! $hasAnyScope) {
            return true;
        }

        return CardProductCourseScope::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('card_product_id', $productId)
            ->where('scope_kind', CardProductCourseScopeKind::Single)
            ->where('scope_key', (string) $course->id)
            ->exists();
    }

    /** 私教课实时扣费价（feeList price_override；无配置/无卡产品 → null） */
    private function privatePriceOverride(MemberCard $card, Course $course): ?float
    {
        $productId = (int) ($card->card_product_id ?? 0);
        if ($productId < 1) {
            return null;
        }

        $override = CardProductCourseScope::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('card_product_id', $productId)
            ->where('scope_kind', CardProductCourseScopeKind::Single)
            ->where('scope_key', (string) $course->id)
            ->value('price_override');

        return $override !== null ? (float) $override : null;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function scopeMatchesCourse(array $scope, Course $course): bool
    {
        $scopeKey = (string) ($scope['scopeKey'] ?? '');

        return $scopeKey === (string) $course->id
            || $scopeKey === 'course-'.$course->id;
    }
}
