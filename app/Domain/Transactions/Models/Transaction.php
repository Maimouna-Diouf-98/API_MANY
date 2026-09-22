<?php

namespace App\Domain\Transactions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUuids;

    protected $table = 'api_transactions';
   protected $fillable = [
    'transaction_id', 'sub_merchant_id', 'aggregator_id',
    'application_id', 'type', 'status', 'idempotency_key',
    'confirmation_token', 'confirmation_expires_at',
    'order_reference', 'customer_phone', 'amount', 'many_fee',
    'net_to_aggregator', 'currency', 'failure_reason',
    'environment', 'completed_at',
];

protected $casts = [
    'amount'                   => 'decimal:2',
    'many_fee'                 => 'decimal:2',
    'net_to_aggregator'        => 'decimal:2',
    'completed_at'             => 'datetime',
    'confirmation_expires_at'  => 'datetime',
];
}