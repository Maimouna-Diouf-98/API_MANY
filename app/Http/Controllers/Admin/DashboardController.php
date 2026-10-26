<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Models\AdminNotification;
use App\Domain\Auth\Models\ProductionRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $requests = ProductionRequest::query()
            ->when(in_array($status, ['PENDING', 'APPROVED', 'REJECTED'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'pending'    => ProductionRequest::where('status', 'PENDING')->count(),
            'approved'   => ProductionRequest::where('status', 'APPROVED')->count(),
            'rejected'   => ProductionRequest::where('status', 'REJECTED')->count(),
            'sandbox'    => DB::connection('mysql_sandbox')->table('aggregators')->count(),
            'production' => DB::connection('mysql_money')->table('aggregators')
                              ->where('production_enabled', true)->count(),
        ];

        $notifications = AdminNotification::latest()->take(8)->get();

        return view('admin.dashboard', compact('requests', 'stats', 'notifications', 'status'));
    }
}
