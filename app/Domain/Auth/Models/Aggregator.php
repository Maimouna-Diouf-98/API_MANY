<?php

namespace App\Domain\Auth\Models;

use App\Domain\KYB\Models\KybVerification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Aggregator extends Authenticatable
{
    use HasUuids, Notifiable;

    // Pas de connexion fixe → définie par le middleware

    protected $fillable = [
        'user_id', 'legal_name', 'trade_name', 'email', 'phone',
        'password', 'status', 'sandbox_enabled', 'production_enabled',
        'webhook_url', 'ip_whitelist', 'commission_rate', 'activated_at',
    ];

    protected $casts = [
        'ip_whitelist'       => 'array',
        'sandbox_enabled'    => 'boolean',
        'production_enabled' => 'boolean',
        'activated_at'       => 'datetime',
        'email_verified_at'  => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}