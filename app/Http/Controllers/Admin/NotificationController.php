<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Models\AdminNotification;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    /** Appelé toutes les 30 s par la cloche (polling). */
    public function count()
    {
        return response()->json([
            'unread' => AdminNotification::whereNull('read_at')->count(),
        ]);
    }

    public function readAll()
    {
        AdminNotification::whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}
