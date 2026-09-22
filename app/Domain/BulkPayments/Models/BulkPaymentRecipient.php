<?php

namespace App\Domain\BulkPayments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BulkPaymentRecipient extends Model
{
    use HasUuids;

    protected $table = 'api_bulk_payment_recipients';

    protected $fillable = [
        'bulk_id',
        'sender_id',
        'receiver_id',
        'phone',
        'amount',
        'many_fee',
        'net_amount',
        'reference',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'many_fee'   => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];
}