<?php

namespace App\Console\Commands;

use App\Domain\BulkPayments\Models\BulkPayment;
use App\Domain\Disbursements\Models\RealDisbursement;
use App\Domain\Transactions\Models\RealTransaction;
use App\Jobs\SendWebhookJob;
use App\Support\CheckoutLinks;
use Illuminate\Console\Command;

/**
 * Expire les opérations de production restées sans confirmation au-delà du délai
 * (10 minutes, identique à la page de paiement et aux endpoints de confirmation),
 * puis notifie l'agrégateur par webhook.
 *
 * Planifiée chaque minute dans routes/console.php (nécessite `php artisan schedule:run` en cron).
 */
class ExpirePendingTransactions extends Command
{
    protected $signature   = 'transactions:expire-pending';
    protected $description = 'Expire les collections, disbursements et paiements de masse de production non confirmés';

    public function handle(): int
    {
        $deadline = now()->subMinutes(CheckoutLinks::PAYMENT_WINDOW_MINUTES);

        // Collections : le client n'a pas confirmé
        $transactions = $this->expire(
            RealTransaction::where('api_type', 'COLLECTION')->where('status', 'pending')->where('created_at', '<', $deadline),
            ['status' => 'failed', 'failure_reason' => 'EXPIRED'],
            'pending',
            'transaction.failed',
        );

        // Disbursements : l'agrégateur n'a pas confirmé avec son PIN
        $disbursements = $this->expire(
            RealDisbursement::where('status', 'PENDING')->whereNull('confirmed_at')->where('created_at', '<', $deadline),
            ['status' => 'FAILED', 'failure_reason' => 'EXPIRED', 'completed_at' => now()],
            'PENDING',
            'disbursement.failed',
            fn ($q) => $q->whereNull('confirmed_at'),
        );

        // Paiements de masse : même règle
        $bulks = $this->expire(
            BulkPayment::on('mysql_money')->where('status', 'PENDING')->whereNull('confirmed_at')->where('created_at', '<', $deadline),
            ['status' => 'FAILED', 'completed_at' => now()],
            'PENDING',
            'bulk_payment.failed',
            fn ($q) => $q->whereNull('confirmed_at'),
        );

        $this->info("Expirés : {$transactions} collection(s), {$disbursements} disbursement(s), {$bulks} paiement(s) de masse.");

        return self::SUCCESS;
    }

    /**
     * Passe chaque ligne en échec de façon atomique : la mise à jour ne s'applique
     * que si la ligne est toujours en attente (une confirmation concurrente gagne).
     */
    private function expire($query, array $changes, string $pendingStatus, string $event, ?callable $extra = null): int
    {
        $count = 0;

        foreach ($query->get() as $model) {
            $guard = $model->newQuery()->whereKey($model->getKey())->where('status', $pendingStatus);
            if ($extra) {
                $extra($guard);
            }

            if (!$guard->update($changes)) {
                continue;
            }

            $count++;
            $model->refresh();
            dispatch(new SendWebhookJob($model, $event))->onConnection('mysql_money');
            $this->line("  expiré : " . class_basename($model) . ' ' . ($model->transaction_id ?? $model->disbursement_id ?? $model->bulk_id));
        }

        return $count;
    }
}
