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
        'status',
        'message',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function aggregator()
    {
        return $this->belongsTo(Aggregator::class);
    }
}