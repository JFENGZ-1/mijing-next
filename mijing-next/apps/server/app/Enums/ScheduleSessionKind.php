<?php

namespace App\Enums;

enum ScheduleSessionKind: string
{
    case Group = 'group';
    case Private = 'private';
}
