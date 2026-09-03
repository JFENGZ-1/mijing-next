<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\MemberProfile;
use App\Services\Members\MobileProtectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SetTestMemberMobile extends Command
{
    protected $signature = 'member:set-test-mobile
        {mobile : Local test mobile number}
        {--account-id= : Target account ID}
        {--confirm : Confirm the local-only override}';

    protected $description = 'Set a verified member mobile for local frontend testing only';

    public function handle(MobileProtectionService $protection): int
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            $this->error('This command is disabled outside local/testing environments.');
            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->error('Pass --confirm to acknowledge the local test override.');
            return self::FAILURE;
        }
        $accountId = filter_var($this->option('account-id'), FILTER_VALIDATE_INT);
        $account = $accountId ? Account::find($accountId) : null;
        if (! $account) {
            $this->error('A valid --account-id is required.');
            return self::FAILURE;
        }

        try {
            $normalized = $protection->normalize($this->argument('mobile'));
        } catch (InvalidArgumentException) {
            $this->error('The test mobile format is invalid.');
            return self::FAILURE;
        }
        $hash = $protection->hash($normalized);
        if (MemberProfile::where('mobile_hash', $hash)->where('account_id', '!=', $account->id)->exists()) {
            $this->error('That mobile is already bound to another account. Accounts were not merged.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($account, $normalized, $hash, $protection) {
            $profile = MemberProfile::firstOrCreate(['account_id' => $account->id]);
            $profile->forceFill([
                'mobile_ciphertext' => $protection->encrypt($normalized),
                'mobile_hash' => $hash,
                'mobile_last4' => substr($normalized, -4),
                'mobile_verified_at' => now(),
                'mobile_verification_method' => 'local_test_override',
                'version' => $profile->version + 1,
            ])->save();
        });

        $this->info("Account #{$account->id} test mobile ending ".substr($normalized, -4).' is ready.');
        return self::SUCCESS;
    }
}
