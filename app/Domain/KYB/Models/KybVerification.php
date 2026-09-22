<?php

namespace App\Domain\KYB\Models;

use App\Domain\Auth\Models\Aggregator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KybVerification extends Model
{
    use HasUuids;

    protected $fillable = [
        'aggregator_id',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'expires_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function aggregator(): BelongsTo
    {
        return $this->belongsTo(Aggregator::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KybDocument::class);
    }
}