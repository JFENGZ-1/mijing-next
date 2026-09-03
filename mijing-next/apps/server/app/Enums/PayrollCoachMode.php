<?php

namespace App\Enums;

enum PayrollCoachMode: string
{
    case FixedHours = 'fixed_hours';
    case Headcount = 'headcount';
    case Amount = 'amount';
}
