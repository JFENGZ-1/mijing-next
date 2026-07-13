<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Staff;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BindStaffAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:bind-account {account-id : Verified account ID} {--employee-no=ADMIN001}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Explicitly bind a verified account to an existing staff record';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $account = Account::find($this->argument('account-id'));
        $staff = Staff::where('employee_no', $this->option('employee-no'))->first();

        if (! $account || ! $staff) {
            $this->error('Account or staff record was not found.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($account, $staff) {
            $previousAccountId = $staff->account_id;
            $staff->update(['account_id' => $account->id, 'version' => $staff->version + 1]);
            Account::find($previousAccountId)?->tokens()->delete();
        });

        $this->info("Account #{$account->id} is now bound to staff #{$staff->id}.");
        return self::SUCCESS;
    }
}
