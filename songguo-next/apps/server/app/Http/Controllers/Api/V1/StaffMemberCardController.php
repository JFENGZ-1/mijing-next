<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveMemberCardRequest;
use App\Http\Requests\BalanceAdjustMemberCardRequest;
use App\Http\Requests\CountAdjustMemberCardRequest;
use App\Http\Requests\FreezeMemberCardRequest;
use App\Http\Requests\HolidayEndMemberCardRequest;
use App\Http\Requests\HolidayStartMemberCardRequest;
use App\Http\Requests\IssueMemberCardRequest;
use App\Http\Requests\ValidityExtensionMemberCardRequest;
use App\Models\MemberCard;
use App\Models\MemberCardShareAssignment;
use App\Models\Staff;
use App\Services\Cards\CardTransferShareTokenService;
use App\Services\Cards\MemberCardAdjustService;
use App\Services\Cards\MemberCardExtrasService;
use App\Services\Cards\MemberCardIssueService;
use App\Services\Cards\MemberCardLifecycleService;
use App\Services\Cards\MemberCardReadService;
use App\Services\Cards\MemberCardStateService;
use App\Services\Compensation\MemberCardShareAssignmentService;
use App\Services\Members\StaffMemberAccessService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use App\Support\Finance\Money;
use Illuminate\Http\Request;

class StaffMemberCardController extends Controller
{
    public function archivedIndex(
        Request $request,
        int $site,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.archive', $siteModel->id);

        $paginator = $reader->staffArchivedCardsQuery($staff, $siteModel)
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => $reader->staffListSummaries(collect($paginator->items())),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function index(
        Request $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);

        $cards = $reader->staffMemberCardsQuery($staff, $siteModel, $memberModel)->get();

        return ApiResponse::success($reader->staffListSummaries($cards));
    }

    public function show(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        return ApiResponse::success($reader->staffDetail($card));
    }

    public function benefits(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        return ApiResponse::success($reader->benefits($card));
    }

    public function shareAssignments(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'compensation.rule.read', $siteModel->id);
        $card = MemberCard::query()->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)->findOrFail($memberCard);
        $today = now()->timezone($siteModel->timezone ?: config('app.timezone'))->toDateString();
        $items = MemberCardShareAssignment::query()
            ->where('tenant_id', $staff->tenant_id)->where('site_id', $siteModel->id)
            ->where('member_card_id', $card->id)->whereIn('status', ['active', 'archived'])
            ->where(fn ($query) => $query->where('status', 'active')->orWhere('effective_until', '>=', $today))
            ->with(['staff', 'role'])->orderBy('compensation_role_id')->orderBy('effective_from')->orderBy('id')->get();

        return ApiResponse::success([
            'memberCardId' => $card->id,
            'version' => (int) $card->share_assignment_version,
            'items' => $items->map(fn (MemberCardShareAssignment $item) => $this->shareAssignmentData($item, $today))->values(),
        ]);
    }

    public function replaceShareAssignments(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardShareAssignmentService $assignments,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'compensation.rule.write', $siteModel->id);
        $card = MemberCard::query()->where('tenant_id', $staff->tenant_id)
            ->where('site_id', $siteModel->id)->findOrFail($memberCard);
        $payload = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.staffId' => ['required', 'integer', 'min:1'],
            'assignments.*.compensationRoleId' => ['required', 'integer', 'min:1'],
            'assignments.*.allocationBps' => ['required', 'integer', 'between:1,10000'],
            'assignments.*.effectiveFrom' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'assignments.*.effectiveUntil' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'expectedVersion' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:500'],
            'commandKey' => ['required', 'uuid'],
        ]);
        $saved = $assignments->replace(
            $card, $siteModel, $payload['assignments'], DomainActor::staff($staff),
            $payload['commandKey'], $payload['reason'], (int) $payload['expectedVersion'],
        );
        $today = now()->timezone($siteModel->timezone ?: config('app.timezone'))->toDateString();

        return ApiResponse::success([
            'memberCardId' => $card->id,
            'version' => (int) $card->fresh()->share_assignment_version,
            'items' => collect($saved)->map(fn (MemberCardShareAssignment $item) => $this->shareAssignmentData($item, $today))->values(),
        ]);
    }

    public function transferShareToken(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        CardTransferShareTokenService $tokens,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $issued = $tokens->issue($card);

        return ApiResponse::success([
            'memberCardId' => $card->id,
            'token' => $issued['token'],
            'expiresAt' => $issued['expiresAt'],
        ]);
    }

    public function balanceAdjust(
        BalanceAdjustMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardAdjustService $adjuster,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.balance.adjust', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $adjuster->adjustBalance($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->adjustmentData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function countAdjust(
        CountAdjustMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardAdjustService $adjuster,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.count.adjust', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $adjuster->adjustCount($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->adjustmentData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function freeze(
        FreezeMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardStateService $state,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.freeze', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $state->freeze($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->stateData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function unfreeze(
        FreezeMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardStateService $state,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.freeze', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $state->unfreeze($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->stateData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function holidayStart(
        HolidayStartMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardStateService $state,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.holiday.manage', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $state->startHoliday($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->stateData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function holidayEnd(
        HolidayEndMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardStateService $state,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.holiday.manage', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $state->endHoliday($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->stateData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function validityExtension(
        ValidityExtensionMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardStateService $state,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.validity.extend', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $result = $state->extendValidity($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->stateData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function archive(
        ArchiveMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardLifecycleService $lifecycle,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.archive', $siteModel->id);
        $card = $reader->staffLifecycleCard($staff, $siteModel, $memberCard);

        $result = $lifecycle->archive($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->lifecycleData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function restore(
        ArchiveMemberCardRequest $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardLifecycleService $lifecycle,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.archive', $siteModel->id);
        $card = $reader->staffLifecycleCard($staff, $siteModel, $memberCard);

        $result = $lifecycle->restore($staff, $siteModel, $card, $request->validated());

        return ApiResponse::success(
            $this->lifecycleData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function ledgerEntries(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $paginator = $reader->ledgerQuery($staff, $siteModel, $card)
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => $reader->staffLedgerEntries($paginator),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(
        IssueMemberCardRequest $request,
        int $site,
        int $member,
        StaffMemberAccessService $access,
        MemberCardIssueService $issuer,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.issue', $siteModel->id);
        $memberModel = $access->member($staff, $siteModel, $member);

        $result = $issuer->issue($staff, $siteModel, $memberModel, $request->validated());

        $data = $this->issuedCardData($result['memberCard']);
        if (($result['order'] ?? null) !== null) {
            $data['order'] = [
                'id' => $result['order']->id,
                'orderNo' => $result['order']->order_no,
                'paymentMethod' => $result['order']->payment_method,
                'actualAmount' => Money::centsToDecimal((int) $result['order']->paid_amount_cents),
                'paidAmountCents' => $result['order']->paid_amount_cents,
                'paidAt' => $result['order']->paid_at?->toIso8601String(),
                'status' => $result['order']->status->value,
                'collectionConfirmation' => $result['order']->metadata['collectionConfirmation'] ?? null,
                'gatewayTransactionId' => $result['order']->metadata['gatewayTransactionId'] ?? null,
            ];
        }

        return ApiResponse::success(
            $data,
            $result['created'] ? 201 : 200,
        );
    }

    public function holidayLast(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        return ApiResponse::success($extras->lastHoliday($staff, $siteModel, $card));
    }

    public function freezeLedgerLast(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        return ApiResponse::success($extras->lastFreezeLedger($staff, $siteModel, $card));
    }

    public function defaultFee(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        return ApiResponse::success($extras->defaultFee($staff, $siteModel, $card));
    }

    public function dynamicFields(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        return ApiResponse::success($extras->dynamicFields($staff, $siteModel, $card));
    }

    public function updateOpeningType(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertPermission($staff, 'member-card.issue', $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $payload = $request->validate([
            'openingType' => ['required', 'string', 'max:40'],
        ]);

        return ApiResponse::success($extras->setOpeningType($staff, $siteModel, $card, $payload));
    }

    public function updateRemark(
        Request $request,
        int $site,
        int $memberCard,
        StaffMemberAccessService $access,
        MemberCardReadService $reader,
        MemberCardExtrasService $extras,
    ) {
        $staff = $this->staff($request);
        $siteModel = $access->site($staff, $site);
        $access->assertMemberCardRead($staff, $siteModel->id);
        $card = $reader->staffCard($staff, $siteModel, $memberCard);

        $payload = $request->validate([
            'remark' => ['required', 'string', 'max:500'],
        ]);

        return ApiResponse::success($extras->updateRemark($staff, $siteModel, $card, $payload));
    }

    private function shareAssignmentData(MemberCardShareAssignment $assignment, string $today): array
    {
        $assignment->loadMissing(['staff', 'role']);
        $from = $assignment->effective_from?->toDateString();
        $until = $assignment->effective_until?->toDateString();
        $effectiveState = $from !== null && $from > $today
            ? 'scheduled'
            : (($until === null || $until >= $today) ? 'current' : 'expired');

        return [
            'id' => $assignment->id,
            'staffId' => $assignment->staff_id,
            'staffName' => $assignment->staff?->name,
            'compensationRoleId' => $assignment->compensation_role_id,
            'roleName' => $assignment->role?->name,
            'roleType' => $assignment->role?->role_type,
            'allocationBps' => $assignment->allocation_bps,
            'effectiveFrom' => $from,
            'effectiveUntil' => $until,
            'effectiveState' => $effectiveState,
            'status' => $assignment->status,
            'version' => $assignment->version,
        ];
    }

    private function adjustmentData(MemberCard $memberCard, array $ledgerEntryIds): array
    {
        return [
            'memberCardId' => $memberCard->id,
            'ledgerEntryIds' => $ledgerEntryIds,
            'cachedBalance' => $this->nullableDecimal($memberCard->cached_balance),
            'cachedRemainingCount' => $memberCard->cached_remaining_count,
        ];
    }

    private function stateData(MemberCard $memberCard, array $ledgerEntryIds): array
    {
        return [
            'memberCardId' => $memberCard->id,
            'ledgerEntryIds' => $ledgerEntryIds,
            'status' => $memberCard->status->value,
            'validFrom' => $memberCard->valid_from?->toDateString(),
            'validUntil' => $memberCard->valid_until?->toDateString(),
            'freezeState' => $memberCard->freeze_state,
        ];
    }

    private function lifecycleData(MemberCard $memberCard, array $ledgerEntryIds): array
    {
        return [
            'memberCardId' => $memberCard->id,
            'ledgerEntryIds' => $ledgerEntryIds,
            'status' => $memberCard->status->value,
            'memberVisibility' => $memberCard->member_visibility->value,
            'archivedAt' => $memberCard->archived_at?->toIso8601String(),
        ];
    }

    private function issuedCardData(MemberCard $memberCard): array
    {
        $snapshot = $memberCard->product_snapshot;

        return [
            'id' => $memberCard->id,
            'cardNo' => $memberCard->card_no,
            'cardType' => $memberCard->card_type->value,
            'status' => $memberCard->status->value,
            'memberId' => $memberCard->member_id,
            'cardProductId' => $memberCard->card_product_id,
            'openingType' => $snapshot['openingType'] ?? null,
            'snapshot' => [
                'name' => $snapshot['name'] ?? null,
                'cardType' => $snapshot['cardType'] ?? $memberCard->card_type->value,
                'faceValue' => $snapshot['faceValue'] ?? null,
                'initialCount' => $snapshot['initialCount'] ?? null,
                'validityDays' => $snapshot['validityDays'] ?? null,
                'activationMode' => $snapshot['activationMode'] ?? null,
                'activationModeOverride' => $snapshot['activationModeOverride'] ?? null,
                'openingType' => $snapshot['openingType'] ?? null,
                'productVersion' => $snapshot['productVersion'] ?? null,
            ],
            'cachedBalance' => $this->nullableDecimal($memberCard->cached_balance),
            'cachedRemainingCount' => $memberCard->cached_remaining_count,
            'validFrom' => $memberCard->valid_from?->toDateString(),
            'validUntil' => $memberCard->valid_until?->toDateString(),
            'issuedAt' => $memberCard->issued_at?->toIso8601String(),
            'issuedByStaffId' => $memberCard->issued_by_staff_id,
        ];
    }

    private function nullableDecimal(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }

    private function staff(Request $request): Staff
    {
        return $request->attributes->get('staff_context');
    }
}
