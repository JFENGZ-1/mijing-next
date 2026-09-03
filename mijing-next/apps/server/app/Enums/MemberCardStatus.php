<?php

namespace App\Enums;

enum MemberCardStatus: string
{
    case PendingActivation = 'pending_activation';
    case Active = 'active';
    case Frozen = 'frozen';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
    case Archived = 'archived';
    case Voided = 'voided';
}
