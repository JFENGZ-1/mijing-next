<?php

namespace App\Enums;

enum PayrollSalesMode: string
{
    case FlatRate = 'flat_rate';
    case Tiered = 'tiered';
}
