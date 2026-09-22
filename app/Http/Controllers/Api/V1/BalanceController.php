<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Transactions\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceController extends Controller
{
    public function show(Request $request)
    {
        $aggregator   = $request->authenticated_aggregator;
        $environment  = $request->environment ?? 'SANDBOX';
        $dbConnection = $environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money';

        $wallet = null;
        if ($aggregator->user_id) {
            $wallet = DB::connection($dbConnection)
                        ->table('wallets')
                        ->where('user_id', $aggregator->user_id)
                        ->first();
        }

        $pendingBalance = Transaction::where('aggregator_id', $aggregator->id)
                                     ->where('status', 'PENDING')
                                     ->sum('net_to_aggregator');

        return response()->json([
            'aggregator_id'   => $aggregator->id,
            'balance'         => $wallet ? (float) $wallet->balance : 0,
            'pending_balance' => (float) $pendingBalance,
            'currency'        => 'XOF',
            'environment'     => $environment,
            'last_updated_at' => $wallet?->last_transaction_at,
        ]);
    }
}