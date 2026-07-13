<?php

namespace App\Enums;

enum PayrollRecomputeScope: string
{
    case Site = 'site';
    case Coach = 'coach';
    case Sales = 'sales';
}
