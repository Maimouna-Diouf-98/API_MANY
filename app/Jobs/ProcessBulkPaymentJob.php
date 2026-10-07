<?php

namespace App\Jobs;

use App\Domain\BulkPayments\Models\BulkPayment;
use App\Domain\BulkPayments\Models\BulkPaymentRecipient;
use App\Domain\Auth\Models\Aggregator;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $dbConnection = $this->environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money';
        $senderId     = $this->aggregator->user_id;
        $senderName   = $this->aggregator->trade_name ?: $this->aggregator->legal_name;

        // Seulement les PENDING : un retry ne repaie jamais un destinataire déjà payé
        $recipients = BulkPaymentRecipient::on($dbConnection)
                        ->where('bulk_id', $this->bulkPayment->bulk_id)
                        ->where('status', 'PENDING')
                        ->get();

        foreach ($recipients as $recipient) {
            $totalDebit = $recipient->amount + $recipient->many_fee;

            try {
                $notification = DB::connection($dbConnection)->transaction(
                    function () use ($recipient, $totalDebit, $dbConnection, $senderId) {

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
                            return null;
                        }

                        $receiverId = null;
                        $fcmToken   = null;

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
                                return null;
                            }

                            $receiverId = $user->id;
                            $fcmToken   = $user->fcm_token;

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
                                return null;
                            }

                            DB::connection('mysql_money')
                                ->table('wallets')
                                ->where('user_id', $receiverId)
                                ->update([
                                    'balance'             => DB::raw('balance + ' . $recipient->net_amount),
                                    'last_transaction_at' => now(),
                                    'version'             => DB::raw('version + 1'),
                                    'updated_at'          => now(),
                                ]);
                        }

                        DB::connection($dbConnection)
                            ->table('wallets')
                            ->where('user_id', $senderId)
                            ->update([
                                'balance'             => DB::raw('balance - ' . $totalDebit),
                                'last_transaction_at' => now(),
                                'version'             => DB::raw('version + 1'),
                                'updated_at'          => now(),
                            ]);

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

                        $recipient->update([
                            'sender_id'   => $senderId,
                            'receiver_id' => $receiverId,
                            'status'      => 'SUCCESS',
                        ]);

                        // Renvoyé pour être notifié APRÈS le commit
                        return $fcmToken ? ['token' => $fcmToken] : null;
                    }
                );

                if ($notification) {
                    $this->notifyRecipient($notification['token'], $recipient, $senderName);
                }

            } catch (\Exception $e) {
                Log::error('BULK recipient error: ' . $e->getMessage());
                $recipient->update([
                    'sender_id'      => $senderId,
                    'status'         => 'FAILED',
                    'failure_reason' => 'PROCESSING_ERROR',
                ]);
            }
        }

        // Compteurs recalculés depuis la base (corrects même après un retry)
        $counts = BulkPaymentRecipient::on($dbConnection)
            ->where('bulk_id', $this->bulkPayment->bulk_id)
            ->selectRaw("SUM(status = 'SUCCESS') as ok, SUM(status = 'FAILED') as ko")
            ->first();

        $successCount = (int) ($counts->ok ?? 0);
        $failureCount = (int) ($counts->ko ?? 0);

        $this->bulkPayment->update([
            'status'        => 'COMPLETED',
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'completed_at'  => now(),
        ]);

        dispatch(new SendWebhookJob(
            $this->bulkPayment,
            $failureCount === 0
                ? 'bulk_payment.completed'
                : 'bulk_payment.partially_completed'
        ))->onConnection($this->environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money');

        Log::info("BulkPayment {$this->bulkPayment->bulk_id} terminé : "
                . "{$successCount} succès, {$failureCount} échecs");
    }

    private function notifyRecipient(string $fcmToken, BulkPaymentRecipient $recipient, string $senderName): void
    {
        try {
            app(FirebaseNotificationService::class)->sendBulkMoneyReceived(
                $fcmToken,
                $recipient->bulk_id,
                (string) (int) $recipient->net_amount,
                $senderName,
                $recipient->reference
            );
        } catch (\Throwable $e) {
            Log::warning('FCM bulk recipient notification failed: ' . $e->getMessage());
        }
    }
}