<?php

namespace App\Services\Compensation;

use App\Enums\AppointmentStatus;
use App\Enums\MemberCardOrderStatus;
use App\Models\Appointment;
use App\Models\MemberCard;
use App\Models\MemberCardOrder;
use App\Support\Finance\Money;

class ConsumptionDomainBackfillService
{
    public function __construct(
        private MemberCardValueLotService $valueLots,
        private EntitlementReservationService $reservations,
    ) {}

    /** @return array<string,int> */
    public function run(bool $apply = false, ?int $tenantId = null, int $limit = 1000): array
    {
        $limit = max(1, min($limit, 10000));
        $cards = MemberCard::query()
            ->when($tenantId, fn ($query, $id) => $query->where('tenant_id', $id))
            ->whereDoesntHave('valueLots')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $stats = [
            'cardsScanned' => $cards->count(),
            'actualLots' => 0,
            'derivedLots' => 0,
            'unknownLots' => 0,
            'reservationsScanned' => 0,
            'reservationsCreated' => 0,
            'confirmedReservationsCreated' => 0,
            'completedReservationsImported' => 0,
        ];

        foreach ($cards as $card) {
            $paidOrder = MemberCardOrder::query()
                ->where('tenant_id', $card->tenant_id)
                ->where('member_card_id', $card->id)
                ->where('status', MemberCardOrderStatus::Paid->value)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->first();
            if ($paidOrder !== null) {
                $stats['actualLots']++;
                if ($apply) {
                    $this->valueLots->recordForOrder($paidOrder, $card);
                }

                continue;
            }

            $snapshotPrice = $card->product_snapshot['price'] ?? null;
            $derivedCents = $snapshotPrice !== null ? Money::decimalToCents($snapshotPrice) : null;
            $bucket = $derivedCents === null ? 'unknownLots' : 'derivedLots';
            $stats[$bucket]++;
            if ($apply) {
                $this->valueLots->recordDerivedBackfill(
                    $card,
                    $derivedCents,
                    $derivedCents === null ? 'unknown' : 'derived',
                );
            }
        }

        $appointments = Appointment::query()
            ->when($tenantId, fn ($query, $id) => $query->where('tenant_id', $id))
            ->whereNotNull('member_card_id')
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::Completed->value])
            ->whereDoesntHave('entitlementReservation')
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $stats['reservationsScanned'] = $appointments->count();
        if ($apply) {
            foreach ($appointments as $appointment) {
                $reservation = $this->reservations->reserve($appointment);
                if ($reservation === null) {
                    continue;
                }
                $stats['reservationsCreated']++;
                if ($appointment->status === AppointmentStatus::Completed) {
                    // The old booking ledger already deducted the entitlement. Mark the
                    // reservation as a historical consumed snapshot without creating a
                    // consumption event or commission, so backfill cannot double-charge.
                    $reservation->update([
                        'status' => 'consumed',
                        'consumed_at' => $appointment->session?->ends_at ?? $appointment->updated_at ?? now(),
                        'metadata' => [
                            ...($reservation->metadata ?? []),
                            'legacyImported' => true,
                            'noCommission' => true,
                            'commissionEligibility' => 'disabled_legacy_import',
                            'valueProvenance' => $reservation->metadata['valueProvenance'] ?? 'unknown',
                            'commissionBackfilled' => false,
                        ],
                    ]);
                    $stats['completedReservationsImported']++;
                } else {
                    $reservation->update(['metadata' => [
                        ...($reservation->metadata ?? []),
                        'legacyImported' => true,
                        'commissionEligibility' => 'future_completed_only',
                    ]]);
                    $stats['confirmedReservationsCreated']++;
                }
            }
        }

        return $stats;
    }
}
