<?php

namespace App\Domain\BulkPayments\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkPayment extends Model
{
    use HasUuids;

    protected $table = 'api_bulk_payments';

    protected $fillable = [
        'bulk_id',
        'sub_merchant_id',
        'aggregator_id',
        'application_id',
        'label',
        'status',
        'total_recipients',
        'success_count',
        'failure_count',
        'total_amount',
        'many_fee',
        'currency',
        'environment',
        'webhook_url',
        'completed_at',
        'confirmed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'many_fee'     => 'decimal:2',
        'completed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(BulkPaymentRecipient::class, 'bulk_id', 'bulk_id');
    }
}