<?php

namespace App\Domain\Disbursements\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    use HasUuids;

    protected $table = 'api_disbursements';

    protected $fillable = [
        'disbursement_id',
        'sub_merchant_id',
        'aggregator_id',
        'application_id',
        'sender_id',
        'receiver_id',
        'status',
        'recipient_phone',
        'amount',
        'many_fee',
        'net_amount',
        'currency',
        'reference',
        'idempotency_key',
        'failure_reason',
        'environment',
        'completed_at',
        'confirmed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'many_fee'     => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'completed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];
}