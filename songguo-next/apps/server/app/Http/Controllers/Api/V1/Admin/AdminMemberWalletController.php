<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\InteractsWithAdminBusinessScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustMemberWalletRequest;
use App\Models\Account;
use App\Models\Member;
use App\Models\MemberWallet;
use App\Models\MemberWalletLedgerEntry;
use App\Models\Site;
use App\Models\Staff;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Services\Wallet\MemberWalletService;
use App\Support\ApiResponse;
use App\Support\DomainActor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMemberWalletController extends Controller
{
    use InteractsWithAdminBusinessScope;

    public function __construct(private readonly MemberWalletService $wallets) {}

    public function index(Request $request, Tenant $tenant, Site $site)
    {
        $site = $this->scopedSite($tenant, $site);
        $filters = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $lastEntries = MemberWalletLedgerEntry::query()
            ->select('member_wallet_id', DB::raw('MAX(occurred_at) as last_entry_at'))
            ->where('tenant_id', $tenant->id)
            ->groupBy('member_wallet_id');
        $paginator = Member::query()
            ->where('members.tenant_id', $tenant->id)
            ->where(function ($query) use ($site) {
                $query->where('members.registration_site_id', $site->id)
                    ->orWhere('members.home_site_id', $site->id)
                    ->orWhereHas('sites', fn ($sites) => $sites->whereKey($site->id));
            })
            ->leftJoin('member_wallets', function ($join) {
                $join->on('member_wallets.member_id', '=', 'members.id')
                    ->on('member_wallets.tenant_id', '=', 'members.tenant_id');
            })
            ->leftJoinSub($lastEntries, 'wallet_last_entries', fn ($join) => $join
                ->on('wallet_last_entries.member_wallet_id', '=', 'member_wallets.id'))
            ->leftJoin('member_crm_profiles', 'member_crm_profiles.member_id', '=', 'members.id')
            ->leftJoin('accounts', 'accounts.id', '=', 'members.account_id')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('members.status', $status))
            ->when($filters['query'] ?? null, fn ($query, $term) => $query->where(function ($nested) use ($term) {
                $nested->where('members.member_no', 'like', "%{$term}%")
                    ->orWhere('member_crm_profiles.name', 'like', "%{$term}%")
                    ->orWhere('accounts.display_name', 'like', "%{$term}%");
            }))
            ->select([
                'members.id as member_id', 'members.member_no', 'members.status',
                'member_crm_profiles.name as profile_name', 'accounts.display_name as account_name',
                'member_wallets.id as wallet_id', 'member_wallets.balance_cents', 'member_wallets.version',
                'wallet_last_entries.last_entry_at',
            ])
            ->orderBy('members.member_no')
            ->paginate($filters['perPage'] ?? 20);

        return ApiResponse::success([
            'items' => collect($paginator->items())->map(fn ($row) => [
                'id' => (int) ($row->wallet_id ?? 0),
                'memberId' => (int) $row->member_id,
                'memberNo' => $row->member_no,
                'memberName' => $row->profile_name ?? $row->account_name ?? $row->member_no,
                'balanceCents' => (int) ($row->balance_cents ?? 0),
                'currency' => 'CNY',
                'version' => (int) ($row->version ?? 1),
                'lastEntryAt' => $row->last_entry_at,
                'status' => $row->status,
            ])->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function ledger(Request $request, Tenant $tenant, Site $site, Member $member)
    {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($this->memberBelongsToSite($member, $site), 404);
        $validated = $request->validate([
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $wallet = MemberWallet::query()
            ->where('tenant_id', $tenant->id)
            ->where('member_id', $member->id)
            ->first();
        if ($wallet === null) {
            return ApiResponse::success([
                'items' => [],
                'pagination' => ['page' => 1, 'perPage' => $validated['perPage'] ?? 20, 'total' => 0, 'lastPage' => 1],
            ]);
        }

        $paginator = MemberWalletLedgerEntry::query()
            ->where('tenant_id', $tenant->id)
            ->where('member_wallet_id', $wallet->id)
            ->where(fn ($query) => $query->where('site_id', $site->id)->orWhereNull('site_id'))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($validated['perPage'] ?? 20);
        $items = collect($paginator->items());
        $staff = Staff::query()->where('tenant_id', $tenant->id)->whereIn('id', $items->pluck('actor_staff_id')->filter())->get()->keyBy('id');
        $accounts = Account::query()->whereIn('id', $items->pluck('actor_account_id')->filter())->get()->keyBy('id');
        $adminIds = $items->map(fn (MemberWalletLedgerEntry $entry) => ($entry->metadata['actorType'] ?? null) === 'super_admin'
            ? $entry->metadata['actorId'] ?? null
            : null)->filter()->unique();
        $admins = SuperAdmin::query()->whereIn('id', $adminIds)->get()->keyBy('id');

        return ApiResponse::success([
            'items' => $items->map(function (MemberWalletLedgerEntry $entry) use ($staff, $accounts, $admins) {
                $adminId = ($entry->metadata['actorType'] ?? null) === 'super_admin'
                    ? $entry->metadata['actorId'] ?? null
                    : null;
                $actorName = $staff->get($entry->actor_staff_id)?->name
                    ?? $accounts->get($entry->actor_account_id)?->display_name
                    ?? $admins->get($adminId)?->name
                    ?? (($entry->metadata['actorType'] ?? null) === 'system' ? '系统' : null);

                return [
                    'id' => $entry->id,
                    'entryType' => $entry->entry_type,
                    'deltaCents' => $entry->direction === 'credit' ? $entry->amount_cents : -$entry->amount_cents,
                    'balanceAfterCents' => $entry->balance_after_cents,
                    'referenceType' => $entry->order_id !== null ? 'member_card_order' : null,
                    'referenceId' => $entry->order_id,
                    'reason' => $entry->reason,
                    'actorName' => $actorName,
                    'commandKey' => $entry->command_key,
                    'reversalOfId' => $entry->reversal_of_id,
                    'occurredAt' => $entry->occurred_at?->toIso8601String(),
                ];
            })->values(),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function adjust(
        AdjustMemberWalletRequest $request,
        Tenant $tenant,
        Site $site,
        Member $member,
    ) {
        $site = $this->scopedSite($tenant, $site);
        abort_unless($this->memberBelongsToSite($member, $site), 404);
        $result = $this->wallets->adjust(
            DomainActor::superAdmin($request->user()),
            $site,
            $member,
            $request->validated(),
        );

        return ApiResponse::success([
            'wallet' => [
                'id' => $result['wallet']->id,
                'memberId' => $member->id,
                'balanceCents' => $result['wallet']->balance_cents,
                'currency' => 'CNY',
                'version' => $result['wallet']->version,
            ],
            'entry' => $this->wallets->presentEntry($result['entry']),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }
}
