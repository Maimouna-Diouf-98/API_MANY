<?php

namespace App\Domain\SubMerchants\Models;

use App\Domain\Auth\Models\Application;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubMerchant extends Model
{
    use HasUuids;

    protected $fillable = [
        'application_id', 'legal_name', 'business_type',
        'address', 'webhook_url', 'external_reference', 'status',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}