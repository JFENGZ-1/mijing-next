<?php

namespace App\Enums;

enum PointLedgerDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
