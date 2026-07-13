<?php

namespace App\Enums;

enum MemberCardOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Voided = 'voided';
}
