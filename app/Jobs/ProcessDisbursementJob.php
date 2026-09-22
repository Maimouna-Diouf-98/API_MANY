<?php

namespace App\Jobs;

use App\Domain\Disbursements\Models\Disbursement;
use App\Domain\Auth\Models\Aggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessDisbursementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Disbursement $disbursement,
        public string       $environment,
        public Aggregator   $aggregator,
    ) {}

    public function handle(): void
    {
        $this->environment === 'SANDBOX'
            ? $this->processSandbox()
            : $this->processProduction();
    }

    private function processSandbox(): void
    {
        try {
            DB::connection('mysql_sandbox')->transaction(function () {

                $totalDebit = $this->disbursement->amount + $this->disbursement->many_fee;

                // Vérifier solde agrégateur
                $aggregatorWallet = DB::connection('mysql_sandbox')
                                      ->table('wallets')
                                      ->where('user_id', $this->aggregator->user_id)
                                      ->where('status', 'active')
                                      ->lockForUpdate()
                                      ->first();

                if (!$aggregatorWallet || $aggregatorWallet->balance < $totalDebit) {
                    $this->failDisbursement('INSUFFICIENT_FUNDS');
                    return;
                }

                // Débiter agrégateur (sender)
                DB::connection('mysql_sandbox')
                  ->table('wallets')
                  ->where('user_id', $this->aggregator->user_id)
                  ->update([
                      'balance'             => DB::raw('balance - ' . $totalDebit),
                      'last_transaction_at' => now(),
                      'version'             => DB::raw('version + 1'),
                      'updated_at'          => now(),
                  ]);

                // Créditer Many
                $adminWallet = DB::connection('mysql_sandbox')
                                 ->table('admin_wallets')
                                 ->lockForUpdate()
                                 ->first();

                if ($adminWallet) {
                    DB::connection('mysql_sandbox')
                      ->table('admin_wallets')
                      ->where('id', $adminWallet->id)
                      ->update([
                          'balance'             => DB::raw('balance + ' . $this->disbursement->many_fee),
                          'last_transaction_at' => now(),
                          'version'             => DB::raw('version + 1'),
                          'updated_at'          => now(),
                      ]);
                }

                $this->disbursement->update([
                    'status'       => 'SUCCESS',
                    'completed_at' => now(),
                ]);

                // Webhook à l'agrégateur
                dispatch(new SendWebhookJob(
                    $this->disbursement,
                    'disbursement.succeeded'
                ))->onConnection('mysql_sandbox');
            });

        } catch (\Exception $e) {
            Log::error('DISBURSEMENT SANDBOX Error: ' . $e->getMessage());
            $this->failDisbursement('PROCESSING_ERROR');
        }
    }

    private function processProduction(): void
    {
        try {
            DB::connection('mysql_money')->transaction(function () {

                $totalDebit = $this->disbursement->amount + $this->disbursement->many_fee;

                // Vérifier solde agrégateur (sender)
                $aggregatorWallet = DB::connection('mysql_money')
                                      ->table('wallets')
                                      ->where('user_id', $this->disbursement->sender_id)
                                      ->where('status', 'active')
                                      ->lockForUpdate()
                                      ->first();

                if (!$aggregatorWallet || $aggregatorWallet->balance < $totalDebit) {
                    $this->failDisbursement('INSUFFICIENT_FUNDS');
                    return;
                }

                // Trouver le destinataire (receiver)
                $recipientWallet = null;
                if ($this->disbursement->receiver_id) {
                    $recipientWallet = DB::connection('mysql_money')
                                         ->table('wallets')
                                         ->where('user_id', $this->disbursement->receiver_id)
                                         ->where('status', 'active')
                                         ->lockForUpdate()
                                         ->first();
                } else {
                    // Chercher par phone si receiver_id pas encore défini
                    $recipient = DB::connection('mysql_money')
                                   ->table('users')
                                   ->where('phone', $this->disbursement->recipient_phone)
                                   ->whereNull('deleted_at')
                                   ->first();

                    if ($recipient) {
                        $recipientWallet = DB::connection('mysql_money')
                                             ->table('wallets')
                                             ->where('user_id', $recipient->id)
                                             ->where('status', 'active')
                                             ->lockForUpdate()
                                             ->first();

                        // Mettre à jour receiver_id
                        $this->disbursement->update(['receiver_id' => $recipient->id]);
                    }
                }

                if (!$recipientWallet) {
                    $this->failDisbursement('RECIPIENT_WALLET_NOT_FOUND');
                    return;
                }

                // Débiter l'agrégateur (sender)
                DB::connection('mysql_money')
                  ->table('wallets')
                  ->where('user_id', $this->disbursement->sender_id)
                  ->update([
                      'balance'             => DB::raw('balance - ' . $totalDebit),
                      'last_transaction_at' => now(),
                      'version'             => DB::raw('version + 1'),
                      'updated_at'          => now(),
                  ]);

                // Créditer le destinataire (receiver)
                DB::connection('mysql_money')
                  ->table('wallets')
                  ->where('user_id', $this->disbursement->receiver_id)
                  ->update([
                      'balance'             => DB::raw('balance + ' . $this->disbursement->net_amount),
                      'last_transaction_at' => now(),
                      'version'             => DB::raw('version + 1'),
                      'updated_at'          => now(),
                  ]);

                // Créditer Many
                $adminWallet = DB::connection('mysql_money')
                                 ->table('admin_wallets')
                                 ->lockForUpdate()
                                 ->first();

                if ($adminWallet) {
                    DB::connection('mysql_money')
                      ->table('admin_wallets')
                      ->where('id', $adminWallet->id)
                      ->update([
                          'balance'             => DB::raw('balance + ' . $this->disbursement->many_fee),
                          'last_transaction_at' => now(),
                          'version'             => DB::raw('version + 1'),
                          'updated_at'          => now(),
                      ]);
                }

                // Mettre à jour le disbursement
                $this->disbursement->update([
                    'status'       => 'SUCCESS',
                    'completed_at' => now(),
                ]);

                // Notifier le destinataire qu'il a reçu de l'argent
                dispatch(new SendReceivedNotificationJob(
                    disbursement:    $this->disbursement,
                    receiverId:      $this->disbursement->receiver_id,
                ))->onConnection('mysql_money');

                // Webhook à l'agrégateur
                dispatch(new SendWebhookJob(
                    $this->disbursement,
                    'disbursement.succeeded'
                ))->onConnection('mysql_money');
            });

        } catch (\Exception $e) {
            Log::error('DISBURSEMENT PRODUCTION Error: ' . $e->getMessage());
            $this->failDisbursement('PROCESSING_ERROR');
        }
    }

    private function failDisbursement(string $reason): void
    {
        $this->disbursement->update([
            'status'         => 'FAILED',
            'failure_reason' => $reason,
            'completed_at'   => now(),
        ]);

        $connection = $this->environment === 'SANDBOX'
                      ? 'mysql_sandbox'
                      : 'mysql_money';

        dispatch(new SendWebhookJob(
            $this->disbursement,
            'disbursement.failed'
        ))->onConnection($connection);
    }
}