<?php

namespace App\Domain\Auth\Models;

use App\Domain\SubMerchants\Models\SubMerchant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Application extends Model
{
    use HasUuids, HasApiTokens;

    protected $fillable = [
        'aggregator_id', 'name', 'client_id', 'client_secret',
        'environment', 'webhook_url', 'is_active', 'last_used_at',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_used_at'  => 'datetime',
        'client_secret' => 'encrypted',
    ];

    protected $hidden = ['client_secret'];

    public function aggregator(): BelongsTo
    {
        return $this->belongsTo(Aggregator::class);
    }

    public function subMerchants(): HasMany
    {
        return $this->hasMany(SubMerchant::class);
    }
}