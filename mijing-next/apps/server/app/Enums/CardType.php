<?php

namespace App\Enums;

enum CardType: string
{
    case StoredValue = 'stored_value';
    case Count = 'count';
    case Period = 'period';
}
