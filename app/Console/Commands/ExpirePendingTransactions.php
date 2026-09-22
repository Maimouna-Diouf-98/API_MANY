<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingTransactions extends Command
{
    protected $signature   = 'transactions:expire-pending';
    protected $description = 'Expire les transactions en attente de confirmation';

    public function handle(): void
    {
        // Expirer les transactions production non confirmées
        $expired = DB::connection('mysql_money')
                     ->table('transactions')
                     ->where('status', 'pending')
                     ->whereNotNull('confirmation_expires_at')
                     ->where('confirmation_expires_at', '<', now())
                     ->get();

        foreach ($expired as $tx) {
            DB::connection('mysql_money')
              ->table('transactions')
              ->where('id', $tx->id)
              ->update([
                  'status'         => 'failed',
                  'failure_reason' => 'CONFIRMATION_EXPIRED',
                  'updated_at'     => now(),
              ]);

            // Envoyer webhook à l'agrégateur
            $transaction = \App\Domain\Transactions\Models\RealTransaction::find($tx->id);
            if ($transaction) {
                dispatch(new \App\Jobs\SendWebhookJob($transaction, 'transaction.failed'));
            }

            $this->info("Transaction expirée : {$tx->transaction_id}");
        }

        $this->info("Total expiré : " . count($expired));
    }
}