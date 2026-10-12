<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Application;
use App\Domain\Auth\Models\ProductionRequest;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Disbursements\Models\Disbursement;
use App\Domain\BulkPayments\Models\BulkPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $aggregator = auth('aggregator')->user();

        $applicationIds = Application::where('aggregator_id', $aggregator->id)
                                     ->pluck('id');

        // Applications et sous-marchands
        $totalApplications = Application::where('aggregator_id', $aggregator->id)
                                        ->where('is_active', true)
                                        ->count();

        $totalSubMerchants = SubMerchant::whereIn('application_id', $applicationIds)
                                        ->where('status', 'ACTIVE')
                                        ->count();

        // Collections
        $totalCollections = Transaction::where('aggregator_id', $aggregator->id)
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

        // Bulk payments
        $totalBulk       = BulkPayment::where('aggregator_id', $aggregator->id)
                                       ->where('status', 'COMPLETED')
                                       ->count();

        $totalBulkAmount = BulkPayment::where('aggregator_id', $aggregator->id)
                                      ->where('status', 'COMPLETED')
                                      ->sum('total_amount');

        $totalDecaisse = $totalDisbursed + $totalBulkAmount;

        // Balance wallet
        $wallet = null;
        if ($aggregator->user_id) {
            $wallet = DB::connection('mysql_sandbox')
                        ->table('wallets')
                        ->where('user_id', $aggregator->user_id)
                        ->first();
        }

        $balance = $wallet ? (float) $wallet->balance : 0;

        // Transactions récentes (7 derniers jours)
        $recentTransactions = Transaction::where('aggregator_id', $aggregator->id)
                                         ->latest()
                                         ->take(5)
                                         ->get();

        // Données graphique collections (7 derniers jours)
        $chartData = $this->getChartData($aggregator->id);

        // Statut de la demande de production
        $productionRequest = ProductionRequest::where('aggregator_id', $aggregator->id)
                                              ->latest()
                                              ->first();

        return view('portal.dashboard.index', compact(
            'aggregator',
            'totalApplications',
            'totalSubMerchants',
            'totalCollections',
            'totalVolume',
            'totalDisbursements',
            'totalDisbursed',
            'totalBulk',
            'totalBulkAmount',
            'totalDecaisse',
            'balance',
            'recentTransactions',
            'chartData',
            'productionRequest',
        ));
    }

    private function getChartData(string $aggregatorId): array
    {
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));

        $collections = Transaction::where('aggregator_id', $aggregatorId)
                                   ->where('status', 'SUCCESS')
                                   ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                                   ->selectRaw('DATE(created_at) as date, SUM(amount) as total, COUNT(*) as count')
                                   ->groupBy('date')
                                   ->pluck('total', 'date');

        $disbursements = Disbursement::where('aggregator_id', $aggregatorId)
                                      ->where('status', 'SUCCESS')
                                      ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                                      ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                                      ->groupBy('date')
                                      ->pluck('total', 'date');

        return [
            'labels'       => $days->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))->values()->toArray(),
            'collections'  => $days->map(fn($d) => (float) ($collections[$d] ?? 0))->values()->toArray(),
            'disbursements'=> $days->map(fn($d) => (float) ($disbursements[$d] ?? 0))->values()->toArray(),
        ];
    }

    public function requestProduction(Request $request)
    {
        $aggregator = auth('aggregator')->user();

        // Vérifier si une demande est déjà en cours
        $existing = ProductionRequest::where('aggregator_id', $aggregator->id)
                                     ->where('status', 'PENDING')
                                     ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Une demande est déjà en cours de traitement.',
            ], 409);
        }

        // Créer la demande
        ProductionRequest::create([
            'aggregator_id' => $aggregator->id,
            'status'        => 'PENDING',
            'message'       => $request->message ?? null,
        ]);

        // Envoyer email à l'admin Many
        \Illuminate\Support\Facades\Mail::to(config('mail.admin_email', 'admin@many.sn'))
            ->send(new \App\Mail\ProductionRequestMail($aggregator));

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée. L\'équipe Many vous contactera par email.',
        ]);
    }
}