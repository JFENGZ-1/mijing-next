<?php

namespace App\Enums;

enum ScheduleSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
