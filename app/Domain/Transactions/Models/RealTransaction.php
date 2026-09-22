<?php

namespace App\Domain\Transactions\Models;

use Illuminate\Database\Eloquent\Model;

class RealTransaction extends Model
{
    protected $connection = 'mysql_money';
    protected $table      = 'transactions';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'transaction_id',
        'reference_no',
        'sub_merchant_id',
        'aggregator_id',
        'application_id',
        'api_type',
        'idempotency_key',
        'order_reference',
        'customer_phone',
        'amount',
        'transaction_fee',
        'many_fee',
        'net_to_aggregator',  // ← correct
        'currency',
        'status',
        'failure_reason',
        'transaction_date',
        'transaction_time',
        'transaction_mode',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'many_fee'          => 'decimal:2',
        'net_to_aggregator' => 'decimal:2',
    ];
}