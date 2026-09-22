<?php

namespace App\Jobs;

use App\Domain\BulkPayments\Models\BulkPayment;
use App\Domain\BulkPayments\Models\BulkPaymentRecipient;
use App\Domain\Auth\Models\Aggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ProcessBulkPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        public BulkPayment $bulkPayment,
        public Aggregator  $aggregator,
        public string      $environment,
    ) {}

    public function handle(): void
    {
        $this->bulkPayment->update(['status' => 'PROCESSING']);

        $successCount = 0;
        $failureCount = 0;
        $dbConnection = $this->environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money';
        $senderId     = $this->aggregator->user_id;
        $recipients = BulkPaymentRecipient::on($dbConnection)
                                   ->where('bulk_id', $this->bulkPayment->bulk_id)
                                   ->get();

        foreach ($recipients as $recipient) {
            $totalDebit = $recipient->amount + $recipient->many_fee;

            try {
                DB::connection($dbConnection)->transaction(function ()
                    use ($recipient, $totalDebit, $dbConnection, $senderId, &$successCount, &$failureCount) {

                    // Vérifier solde agrégateur
                    $aggregatorWallet = DB::connection($dbConnection)
                                          ->table('wallets')
                                          ->where('user_id', $senderId)
                                          ->where('status', 'active')
                                          ->lockForUpdate()
                                          ->first();

                    if (!$aggregatorWallet || $aggregatorWallet->balance < $totalDebit) {
                        $recipient->update([
                            'sender_id'      => $senderId,
                            'status'         => 'FAILED',
                            'failure_reason' => 'INSUFFICIENT_FUNDS',
                        ]);
                        $failureCount++;
                        return;
                    }

                    $receiverId = null;

                    // Production : trouver et créditer le destinataire
                    if ($this->environment === 'PRODUCTION') {
                        $normalizedPhone = str_replace(' ', '', $recipient->phone);

    $user = DB::connection('mysql_money')
               ->table('users')
               ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalizedPhone])
               ->whereNull('deleted_at')
               ->first();

                        if (!$user) {
                            $recipient->update([
                                'sender_id'      => $senderId,
                                'status'         => 'FAILED',
                                'failure_reason' => 'RECIPIENT_NOT_FOUND',
                            ]);
                            $failureCount++;
                            return;
                        }

                        $receiverId      = $user->id;
                        $recipientWallet = DB::connection('mysql_money')
                                             ->table('wallets')
                                             ->where('user_id', $receiverId)
                                             ->where('status', 'active')
                                             ->lockForUpdate()
                                             ->first();

                        if (!$recipientWallet) {
                            $recipient->update([
                                'sender_id'      => $senderId,
                                'receiver_id'    => $receiverId,
                                'status'         => 'FAILED',
                                'failure_reason' => 'RECIPIENT_WALLET_NOT_FOUND',
                            ]);
                            $failureCount++;
                            return;
                        }

                        // Créditer destinataire (net_amount)
                        DB::connection('mysql_money')
                          ->table('wallets')
                          ->where('user_id', $receiverId)
                          ->update([
                              'balance'             => DB::raw('balance + ' . $recipient->net_amount),
                              'last_transaction_at' => now(),
                              'version'             => DB::raw('version + 1'),
                              'updated_at'          => now(),
                          ]);

                        // Notifier le destinataire
                        if ($user->fcm_token) {
                            $this->notifyRecipient($user->fcm_token, $recipient);
                        }
                    }

                    // Débiter agrégateur (sender)
                    DB::connection($dbConnection)
                      ->table('wallets')
                      ->where('user_id', $senderId)
                      ->update([
                          'balance'             => DB::raw('balance - ' . $totalDebit),
                          'last_transaction_at' => now(),
                          'version'             => DB::raw('version + 1'),
                          'updated_at'          => now(),
                      ]);

                    // Créditer Many (many_fee)
                    $adminWallet = DB::connection($dbConnection)
                                     ->table('admin_wallets')
                                     ->lockForUpdate()
                                     ->first();

                    if ($adminWallet) {
                        DB::connection($dbConnection)
                          ->table('admin_wallets')
                          ->where('id', $adminWallet->id)
                          ->update([
                              'balance'             => DB::raw('balance + ' . $recipient->many_fee),
                              'last_transaction_at' => now(),
                              'version'             => DB::raw('version + 1'),
                              'updated_at'          => now(),
                          ]);
                    }

                    // Mettre à jour le recipient
                    $recipient->update([
                        'sender_id'   => $senderId,
                        'receiver_id' => $receiverId,
                        'status'      => 'SUCCESS',
                    ]);

                    $successCount++;
                });

            } catch (\Exception $e) {
                Log::error('BULK recipient error: ' . $e->getMessage());
                $recipient->update([
                    'sender_id'      => $senderId,
                    'status'         => 'FAILED',
                    'failure_reason' => 'PROCESSING_ERROR',
                ]);
                $failureCount++;
            }
        }

        // Finaliser le bulk payment
        $this->bulkPayment->update([
            'status'        => 'COMPLETED',
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'completed_at'  => now(),
        ]);

        // Envoyer webhook à l'agrégateur
        dispatch(new SendWebhookJob(
            $this->bulkPayment,
            $failureCount === 0
                ? 'bulk_payment.completed'
                : 'bulk_payment.partially_completed'
        ))->onConnection($this->environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money');

        Log::info("BulkPayment {$this->bulkPayment->bulk_id} terminé : "
                . "{$successCount} succès, {$failureCount} échecs");
    }

    private function notifyRecipient(string $fcmToken, BulkPaymentRecipient $recipient): void
    {
        try {
            $amount = number_format($recipient->net_amount, 0, ',', ' ');
            Http::withHeaders([
                'Authorization' => 'key=' . env('FIREBASE_SERVER_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $fcmToken,
                'notification' => [
                    'title' => '💰 Argent reçu !',
                    'body'  => "Vous avez reçu {$amount} XOF sur votre compte Many.",
                    'sound' => 'default',
                ],
                'data' => [
                    'type'      => 'MONEY_RECEIVED',
                    'bulk_id'   => $recipient->bulk_id,
                    'amount'    => $amount,
                    'reference' => $recipient->reference,
                ],
                'priority' => 'high',
            ]);
        } catch (\Exception $e) {
            Log::warning("FCM bulk recipient notification failed: " . $e->getMessage());
        }
    }
}