<?php

namespace App\Console\Commands;

use App\Services\Cards\MemberCardAutoActivationService;
use Illuminate\Console\Command;

class ActivateDueMemberCards extends Command
{
    protected $signature = 'cards:activate-due';

    protected $description = '激活所有「购卡X天后自动开卡」已到期的会员卡';

    public function handle(MemberCardAutoActivationService $activation): int
    {
        $result = $activation->activateDueDelayedCards();

        $this->info("已激活 {$result['activated']} 张，失败 {$result['failed']} 张。");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
