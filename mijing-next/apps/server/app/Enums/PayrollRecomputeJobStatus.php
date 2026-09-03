<?php

namespace App\Enums;

enum PayrollRecomputeJobStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
