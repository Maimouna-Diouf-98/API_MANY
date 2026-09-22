<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Application;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Disbursements\Models\Disbursement;
use Illuminate\Support\Facades\DB;
use App\Domain\BulkPayments\Models\BulkPayment;

class DashboardController extends Controller
{
    public function index()
    {
        $aggregator = auth('aggregator')->user();

        $applicationIds = Application::where('aggregator_id', $aggregator->id)
                                     ->pluck('id');

        $totalApplications = Application::where('aggregator_id', $aggregator->id)
                                        ->where('is_active', true)
                                        ->count();

        $totalSubMerchants = SubMerchant::whereIn('application_id', $applicationIds)
                                        ->where('status', 'ACTIVE')
                                        ->count();

        // Collections
        $totalTransactions = Transaction::where('aggregator_id', $aggregator->id)
                                        ->where('status', 'SUCCESS')
                                        ->count();

        $totalVolume = Transaction::where('aggregator_id', $aggregator->id)
                                  ->where('status', 'SUCCESS')
                                  ->sum('amount');

        // Disbursements
        $totalDisbursements = Disbursement::where('aggregator_id', $aggregator->id)
                                          ->where('status', 'SUCCESS')
                                          ->count();

        $totalDisbursed = Disbursement::where('aggregator_id', $aggregator->id)
                                      ->where('status', 'SUCCESS')
                                      ->sum('amount');
  
                    

        // Balance wallet
        $wallet = null;
        if ($aggregator->user_id) {
            $wallet = DB::connection('mysql_sandbox')
                        ->table('wallets')
                        ->where('user_id', $aggregator->user_id)
                        ->first();
        }

        $balance = $wallet ? $wallet->balance : 0;

// Transactions récentes (collections + disbursements + bulk)
$recentCollections = Transaction::where('aggregator_id', $aggregator->id)
    ->latest()
    ->take(5)
    ->get()
    ->map(fn($t) => [
        'type'       => 'COLLECTION',
        'id'         => $t->transaction_id,
        'reference'  => $t->order_reference,
        'amount'     => $t->amount,
        'net'        => $t->net_to_aggregator,
        'status'     => $t->status,
        'created_at' => $t->created_at,
    ]);

$recentDisbursements = Disbursement::where('aggregator_id', $aggregator->id)
    ->latest()
    ->take(5)
    ->get()
    ->map(fn($d) => [
        'type'       => 'DISBURSEMENT',
        'id'         => $d->disbursement_id,
        'reference'  => $d->reference,
        'amount'     => $d->amount,
        'net'        => $d->net_amount,
        'status'     => $d->status,
        'created_at' => $d->created_at,
    ]);

$recentBulkPayments = BulkPayment::where('aggregator_id', $aggregator->id)
    ->latest()
    ->take(5)
    ->get()
    ->map(fn($b) => [
        'type'       => 'BULK_PAYMENT',
        'id'         => $b->bulk_id,
        'reference'  => $b->label,
        'amount'     => $b->total_amount,
        'net'        => $b->total_amount - $b->many_fee,
        'status'     => $b->status,
        'created_at' => $b->created_at,
    ]);

$recentTransactions = $recentCollections
    ->concat($recentDisbursements)
    ->concat($recentBulkPayments)
    ->sortByDesc('created_at')
    ->take(5)
    ->values();

        $applications = Application::where('aggregator_id', $aggregator->id)
                                   ->withCount('subMerchants')
                                   ->latest()
                                   ->take(5)
                                   ->get();
        $totalBulk       = BulkPayment::where('aggregator_id', $aggregator->id)
                               ->where('status', 'COMPLETED')->count();
        $totalBulkAmount = BulkPayment::where('aggregator_id', $aggregator->id)
                               ->where('status', 'COMPLETED')->sum('total_amount');
        $totalDecaisse   = $totalDisbursed + $totalBulkAmount;

        return view('portal.dashboard.index', compact(
            'aggregator',
            'totalApplications',
            'totalSubMerchants',
            'totalTransactions',
            'totalVolume',
            'totalDisbursements',
            'totalDisbursed',
            'balance',
            'recentTransactions',
            'applications',
            'totalBulk',
            'totalBulkAmount',
            'totalDecaisse',
        ));
    }
}