<?php

namespace App\Enums;

enum TenantSubscriptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Grace = 'grace';
    case Suspended = 'suspended';
}
