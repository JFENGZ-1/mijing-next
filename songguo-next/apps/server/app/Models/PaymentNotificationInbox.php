<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentNotificationInbox extends Model
{
    protected $table = 'payment_notification_inbox';

    protected $fillable = [
        'provider',
        'notification_id',
        'event_type',
        'order_no',
        'transaction_id',
        'amount_total',
        'currency',
        'appid',
        'merchant_id',
        'occurred_at',
        'status',
        'attempts',
        'last_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_total' => 'integer',
            'occurred_at' => 'datetime',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
