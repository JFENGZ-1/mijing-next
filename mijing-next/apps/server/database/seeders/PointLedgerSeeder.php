<?php

namespace Database\Seeders;

use App\Enums\PointLedgerDirection;
use App\Models\Member;
use App\Models\MemberPointBalance;
use App\Models\PointLedgerEntry;
use Database\Seeders\Support\DemoSeedTarget;
use Illuminate\Database\Seeder;

class PointLedgerSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return;
        }

        $site = DemoSeedTarget::site();
        if (! $site) {
            $this->command?->warn('PointLedgerSeeder skipped: site 1 missing.');

            return;
        }

        $site->tenant()->update(['points_enabled' => true]);

        $member = Member::query()
            ->where('tenant_id', $site->tenant_id)
            ->orderBy('id')
            ->first();

        if (! $member) {
            $this->command?->warn('PointLedgerSeeder skipped: no member for tenant.');

            return;
        }

        $commandKeys = [
            'credit-welcome' => 'a1000001-0000-4000-8000-000000000001',
            'credit-checkin' => 'a1000001-0000-4000-8000-000000000002',
            'debit-redeem' => 'a1000001-0000-4000-8000-000000000003',
        ];

        $entries = [
            ['key' => 'credit-welcome', 'direction' => PointLedgerDirection::Credit, 'amount' => 100, 'reason' => '新会员赠送'],
            ['key' => 'credit-checkin', 'direction' => PointLedgerDirection::Credit, 'amount' => 50, 'reason' => '签到奖励'],
            ['key' => 'debit-redeem', 'direction' => PointLedgerDirection::Debit, 'amount' => 20, 'reason' => '积分兑换'],
        ];

        $balance = 0;
        foreach ($entries as $entry) {
            $signed = $entry['direction'] === PointLedgerDirection::Credit
                ? $entry['amount']
                : -$entry['amount'];

            PointLedgerEntry::updateOrCreate(
                [
                    'tenant_id' => $site->tenant_id,
                    'command_key' => $commandKeys[$entry['key']],
                ],
                [
                    'member_id' => $member->id,
                    'amount_delta' => $entry['amount'],
                    'direction' => $entry['direction'],
                    'reason' => $entry['reason'],
                ],
            );

            $balance += $signed;
        }

        MemberPointBalance::updateOrCreate(
            ['tenant_id' => $site->tenant_id, 'member_id' => $member->id],
            ['balance' => $balance],
        );

        $this->command?->info("PointLedgerSeeder: member {$member->id} balance {$balance}.");
    }
}
