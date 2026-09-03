<?php

namespace App\Enums;

enum EntitlementLedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Neutral = 'neutral';
}
