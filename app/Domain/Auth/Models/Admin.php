<?php

namespace App\Domain\Auth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $connection = 'mysql_money';
    protected $table      = 'admins';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 0;
    }

    public function canAccessDashboard(): bool
    {
        return $this->view_dashboard === 1;
    }

    public function canApproveKyb(): bool
    {
        return $this->approve_kyc === 1;
    }
}