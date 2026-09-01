<?php

namespace App\Services\Compensation;

use App\Enums\CardType;
use App\Models\CardProduct;
use App\Models\CardProductCourseRule;
use App\Models\Course;
use App\Models\MemberCard;
use App\Models\Site;
use App\Services\Catalog\CatalogCommandReceiptService;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CardProductCourseRuleService
{
    public function __construct(private readonly CatalogCommandReceiptService $receipts) {}

    public function listForProduct(int $tenantId, int $siteId, int $productId)
    {
        return CardProductCourseRule::query()
            ->where('tenant_id', $tenantId)
            ->where('site_id', $siteId)
            ->where('card_product_id', $productId)
            ->whereIn('status', ['active', 'scheduled'])
            ->with('course:id,name,course_type')
            ->orderBy('course_id')
            ->orderByDesc('version')
            ->get()
            ->unique('course_id')
            ->sortBy('course_id')
            ->values();
    }

    public function replaceRules(
        DomainActor $actor,
        Site $site,
        CardProduct $product,
        array $rules,
        ?string $commandKey = null,
        ?string $reason = null,
        ?int $expectedVersion = null,
    ): array {
        abort_unless(
            $product->tenant_id === $site->tenant_id && $product->site_id === $site->id,
            404,
        );

        $canonicalRules = collect($rules)->sortBy(fn ($rule) => (int) $rule['courseId'])->values()->all();
        $hash = $this->receipts->payloadHash([
            'productId' => $product->id, 'rules' => $canonicalRules, 'reason' => $reason,
            'expectedVersion' => $expectedVersion,
        ]);

        return DB::transaction(function () use ($actor, $site, $product, $canonicalRules, $commandKey, $reason, $expectedVersion, $hash) {
            $locked = CardProduct::query()
                ->where('tenant_id', $site->tenant_id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($commandKey !== null && $this->receipts->replay(
                $site, 'card_product_course_rules', 'replace', $commandKey, $hash,
            ) !== null) {
                return $this->listForProduct($site->tenant_id, $site->id, $locked->id)->all();
            }
            if ($expectedVersion !== null) {
                abort_unless(
                    (int) $locked->course_rule_version === $expectedVersion,
                    409,
                    'CARD_COURSE_RULE_VERSION_CONFLICT',
                );
            }

            $courseIds = collect($canonicalRules)->pluck('courseId')->map(fn ($id) => (int) $id)->unique()->values();
            abort_unless($courseIds->count() === count($canonicalRules), 422, 'CARD_COURSE_RULE_DUPLICATE');
            $courses = Course::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('site_id', $site->id)
                ->whereIn('id', $courseIds)
                ->get()
                ->keyBy('id');
            abort_unless($courses->count() === $courseIds->count(), 422, 'CARD_COURSE_RULE_COURSE_INVALID');

            $current = CardProductCourseRule::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('card_product_id', $locked->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get()
                ->keyBy('course_id');

            CardProductCourseRule::query()
                ->where('tenant_id', $site->tenant_id)
                ->where('card_product_id', $locked->id)
                ->whereIn('status', ['active', 'scheduled'])
                ->whereNotIn('course_id', $courseIds)
                ->update(['status' => 'superseded', 'archived_at' => now()]);

            foreach ($canonicalRules as $rule) {
                $this->assertDeduction((string) $rule['deductionType'], $locked->card_type, $rule);
                $courseId = (int) $rule['courseId'];
                $previous = $current->get($courseId);
                $effectiveAt = isset($rule['effectiveAt']) ? Carbon::parse($rule['effectiveAt']) : now();
                $isFuture = $effectiveAt->isFuture();
                if (! $isFuture) {
                    CardProductCourseRule::query()
                        ->where('tenant_id', $site->tenant_id)
                        ->where('card_product_id', $locked->id)
                        ->where('course_id', $courseId)
                        ->whereIn('status', ['active', 'scheduled'])
                        ->update(['status' => 'superseded', 'archived_at' => now()]);
                }
                $nextVersion = ((int) CardProductCourseRule::query()
                    ->where('tenant_id', $site->tenant_id)
                    ->where('card_product_id', $locked->id)
                    ->where('course_id', $courseId)
                    ->max('version')) + 1;

                CardProductCourseRule::create([
                    'tenant_id' => $site->tenant_id,
                    'site_id' => $site->id,
                    'card_product_id' => $locked->id,
                    'course_id' => $courseId,
                    'deduction_type' => $rule['deductionType'],
                    'amount_cents' => $rule['deductionType'] === 'amount' ? (int) $rule['amountCents'] : null,
                    'count_units' => $rule['deductionType'] === 'count' ? (int) $rule['countUnits'] : null,
                    'version' => $nextVersion,
                    'status' => $isFuture ? 'scheduled' : 'active',
                    'supersedes_id' => $previous?->id,
                    'created_by_staff_id' => $actor->staffId(),
                    'effective_at' => $effectiveAt,
                ]);
            }

            $saved = $this->listForProduct($site->tenant_id, $site->id, $locked->id)->all();
            $locked->course_rule_version = (int) $locked->course_rule_version + 1;
            $locked->save();
            if ($commandKey !== null) {
                $this->receipts->record(
                    $actor, $site, 'card_product_course_rules', $locked->id, 'replace',
                    $commandKey, $hash, (int) $locked->course_rule_version, $reason,
                );
            }

            return $saved;
        });
    }

    public function activeRuleFor(
        MemberCard|CardProduct $card,
        Course $course,
        CarbonInterface|string|null $at = null,
    ): ?CardProductCourseRule {
        $productId = $card instanceof MemberCard ? $card->card_product_id : $card->id;
        if ($productId === null) {
            return null;
        }

        $effectiveAt = $at === null ? now() : Carbon::parse($at);

        return CardProductCourseRule::query()
            ->where('tenant_id', $card->tenant_id)
            ->where('card_product_id', $productId)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'scheduled', 'superseded'])
            ->where('effective_at', '<=', $effectiveAt)
            // superseded/removed versions remain usable for service dates that
            // happened before their archival cutoff, never for future bookings.
            ->where(fn ($query) => $query
                ->whereNull('archived_at')
                ->orWhere('archived_at', '>', $effectiveAt))
            ->orderByDesc('effective_at')
            ->orderByDesc('version')
            ->first();
    }

    public function deductSpec(CardProductCourseRule $rule): array
    {
        return match ($rule->deduction_type) {
            'amount' => [
                'type' => CardType::StoredValue,
                'count' => null,
                'amount' => Money::centsToDecimal((int) $rule->amount_cents),
            ],
            'count' => [
                'type' => CardType::Count,
                'count' => (int) $rule->count_units,
                'amount' => null,
            ],
            default => ['type' => CardType::Period, 'count' => null, 'amount' => null],
        };
    }

    public function present(CardProductCourseRule $rule): array
    {
        return [
            'id' => $rule->id,
            'cardProductId' => $rule->card_product_id,
            'courseId' => $rule->course_id,
            'courseName' => $rule->course?->name,
            'courseType' => $rule->course?->course_type?->value ?? $rule->course?->course_type,
            'deductionType' => $rule->deduction_type,
            'amountCents' => $rule->amount_cents,
            'countUnits' => $rule->count_units,
            'version' => $rule->version,
            'effectiveAt' => $rule->effective_at?->toIso8601String(),
            'status' => $rule->status,
        ];
    }

    private function assertDeduction(string $type, CardType $cardType, array $rule): void
    {
        $expected = match ($cardType) {
            CardType::StoredValue => 'amount',
            CardType::Count => 'count',
            CardType::Period => 'period_auto',
        };
        abort_unless($type === $expected, 422, 'CARD_COURSE_RULE_TYPE_MISMATCH');
        abort_if($type === 'amount' && (int) ($rule['amountCents'] ?? 0) < 1, 422, 'CARD_COURSE_RULE_AMOUNT_INVALID');
        abort_if($type === 'count' && (int) ($rule['countUnits'] ?? 0) < 1, 422, 'CARD_COURSE_RULE_COUNT_INVALID');
    }
}
