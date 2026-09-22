<?php

namespace App\Domain\KYB\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KybDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'kyb_verification_id',
        'type',
        'file_path',
        'file_name',
        'status',
        'rejection_reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function kybVerification(): BelongsTo
    {
        return $this->belongsTo(KybVerification::class);
    }
}