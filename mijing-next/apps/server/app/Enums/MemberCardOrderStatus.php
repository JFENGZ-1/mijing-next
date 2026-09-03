<?php

namespace App\Enums;

enum MemberCardOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Closing = 'closing';
    case Paid = 'paid';
    case Closed = 'closed';
    case Voided = 'voided';
}
