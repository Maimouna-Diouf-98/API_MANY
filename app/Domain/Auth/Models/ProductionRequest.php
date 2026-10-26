<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductionRequest extends Model
{
    use HasUuids;

    protected $connection = 'mysql_sandbox';
    protected $table      = 'production_requests';

    protected $fillable = [
        'aggregator_id',
        'status',        // PENDING | APPROVED | REJECTED
        'message',       // message de l'agrégateur
        'reviewed_at',
        'reviewed_by',   // admins.id
        'admin_note',    // motif de refus
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    /** Agrégateur sandbox lié (accesseur : évite toute ambiguïté de connexion). */
    public function getAggregatorAttribute(): ?Aggregator
    {
        return Aggregator::on('mysql_sandbox')->find($this->aggregator_id);
    }
}
