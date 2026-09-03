<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateMemberCardRequest;
use App\Http\Requests\MemberCardVisibilityRequest;
use App\Models\MemberCard;
use App\Services\Cards\MemberCardIssueService;
use App\Services\Cards\MemberCardLifecycleService;
use App\Services\Cards\MemberCardReadService;
use App\Services\Members\TenantMemberAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MemberMemberCardController extends Controller
{
    public function hiddenIndex(
        Request $request,
        TenantMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $account = $request->user();
        $member = $access->member($account, $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        $cards = $reader->memberHiddenCardsQuery($member)->get();

        return ApiResponse::success($reader->memberWalletSummaries($cards));
    }

    public function index(
        Request $request,
        TenantMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $account = $request->user();
        $member = $access->member($account, $request->integer('tenantId'));
        abort_unless($member, 404);
        $access->assertAppAccess($member);

        $cards = $reader->memberWalletQuery($member)->get();

        return ApiResponse::success($reader->memberWalletSummaries($cards));
    }

    public function show(
        Request $request,
        int $memberCard,
        TenantMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        abort_unless($request->filled('tenantId'), 422, 'TENANT_ID_REQUIRED');
        $account = $request->user();
        $card = MemberCard::query()->whereKey($memberCard)->firstOrFail();
        abort_unless($card->tenant_id === $request->integer('tenantId'), 404);
        $member = $access->member($account, $card->tenant_id);
        abort_unless($member && $member->id === $card->member_id, 404);
        $access->assertAppAccess($member);

        return ApiResponse::success($reader->memberWalletSummary($card));
    }

    public function benefits(
        Request $request,
        int $memberCard,
        TenantMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $account = $request->user();
        $card = MemberCard::query()->whereKey($memberCard)->firstOrFail();
        $member = $access->member($account, $card->tenant_id);
        abort_unless($member && $member->id === $card->member_id, 404);
        $access->assertAppAccess($member);

        return ApiResponse::success($reader->benefits($card));
    }

    public function ledgerEntries(
        Request $request,
        int $memberCard,
        TenantMemberAccessService $access,
        MemberCardReadService $reader,
    ) {
        $account = $request->user();
        $card = MemberCard::query()->whereKey($memberCard)->firstOrFail();
        $member = $access->member($account, $card->tenant_id);
        abort_unless($member && $member->id === $card->member_id, 404);
        $access->assertAppAccess($member);

        $paginator = $reader->memberLedgerQuery($member, $card)
            ->paginate(min(max($request->integer('perPage', 20), 1), 50));

        return ApiResponse::success([
            'items' => $reader->memberLedgerEntries($paginator),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function activate(
        ActivateMemberCardRequest $request,
        int $memberCard,
        TenantMemberAccessService $access,
        MemberCardIssueService $issuer,
    ) {
        $account = $request->user();
        $card = MemberCard::query()->whereKey($memberCard)->firstOrFail();
        $member = $access->member($account, $card->tenant_id);
        abort_unless($member && $member->id === $card->member_id, 404);
        $access->assertAppAccess($member);

        $result = $issuer->activate($account, $card, $request->input('commandKey'));

        return ApiResponse::success($this->activatedCardData($result['memberCard']));
    }

    public function hide(
        MemberCardVisibilityRequest $request,
        int $memberCard,
        TenantMemberAccessService $access,
        MemberCardLifecycleService $lifecycle,
    ) {
        $account = $request->user();
        $card = MemberCard::query()->whereKey($memberCard)->firstOrFail();
        $member = $access->member($account, $card->tenant_id);
        abort_unless($member && $member->id === $card->member_id, 404);
        $access->assertAppAccess($member);

        $result = $lifecycle->hide($member, $account, $card, $request->validated());

        return ApiResponse::success(
            $this->visibilityData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    public function restoreVisibility(
        MemberCardVisibilityRequest $request,
        int $memberCard,
        TenantMemberAccessService $access,
        MemberCardLifecycleService $lifecycle,
    ) {
        $account = $request->user();
        $card = MemberCard::query()->whereKey($memberCard)->firstOrFail();
        $member = $access->member($account, $card->tenant_id);
        abort_unless($member && $member->id === $card->member_id, 404);
        $access->assertAppAccess($member);

        $result = $lifecycle->restoreVisibility($member, $account, $card, $request->validated());

        return ApiResponse::success(
            $this->visibilityData($result['memberCard'], $result['ledgerEntryIds']),
            $result['created'] ? 201 : 200,
        );
    }

    private function activatedCardData(MemberCard $memberCard): array
    {
        $snapshot = $memberCard->product_snapshot;

        return [
            'id' => $memberCard->id,
            'cardNo' => $memberCard->card_no,
            'cardType' => $memberCard->card_type->value,
            'status' => $memberCard->status->value,
            'snapshot' => [
                'name' => $snapshot['name'] ?? null,
                'activationMode' => $snapshot['activationMode'] ?? null,
            ],
            'validFrom' => $memberCard->valid_from?->toDateString(),
            'validUntil' => $memberCard->valid_until?->toDateString(),
        ];
    }

    private function visibilityData(MemberCard $memberCard, array $ledgerEntryIds): array
    {
        return [
            'memberCardId' => $memberCard->id,
            'ledgerEntryIds' => $ledgerEntryIds,
            'memberVisibility' => $memberCard->member_visibility->value,
        ];
    }
}
