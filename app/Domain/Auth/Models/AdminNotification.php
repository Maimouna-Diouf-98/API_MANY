<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $connection = 'mysql_sandbox';
    protected $table      = 'admin_notifications';

    protected $fillable = ['type', 'title', 'body', 'related_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
