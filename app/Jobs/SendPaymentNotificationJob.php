<?php

namespace App\Jobs;

use App\Domain\Transactions\Models\RealTransaction;
use App\Domain\SubMerchants\Models\SubMerchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPaymentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public RealTransaction $transaction,
        public object          $customer,
        public SubMerchant     $subMerchant,
    ) {}

    public function handle(): void
    {
        try {
            $amount       = number_format($this->transaction->amount, 0, ',', ' ');
            $merchantName = $this->subMerchant->legal_name;
            $reference    = $this->transaction->transaction_id;
            $phone        = $this->transaction->customer_phone;

            // Log pour debug
            Log::info('PAYMENT_REQUEST', [
                'transaction_id'  => $reference,
                'customer_phone'  => $phone,
                'customer_id'     => $this->customer->id,
                'amount'          => $amount . ' XOF',
                'merchant'        => $merchantName,
                'order_reference' => $this->transaction->order_reference,
            ]);

            // Stocker l'OTP dans users.otp pour que l'app
            // mobile money puisse vérifier si nécessaire
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::connection('mysql_money')
              ->table('users')
              ->where('id', $this->customer->id)
              ->update([
                  'otp'        => $otp,
                  'updated_at' => now(),
              ]);

            Log::info("OTP généré pour {$phone}: {$otp} "
                    . "(transaction: {$reference})");

            // Tenter d'envoyer via FCM si fcm_token disponible
            if ($this->customer->fcm_token) {
                $this->sendFcmNotification(
                    $this->customer->fcm_token,
                    $amount,
                    $merchantName,
                    $reference
                );
            } else {
                Log::warning("Pas de FCM token pour user {$this->customer->id} "
                           . "— l'app devra faire du polling.");
            }

        } catch (\Exception $e) {
            Log::error('SendPaymentNotificationJob Error: ' . $e->getMessage());
            // Ne pas faire échouer le job définitivement
            // L'app mobile money peut toujours faire du polling
        }
    }

    private function sendFcmNotification(
        string $fcmToken,
        string $amount,
        string $merchantName,
        string $transactionId
    ): void {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'key=' . env('FIREBASE_SERVER_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $fcmToken,
                'notification' => [
                    'title' => '💳 Demande de paiement Many',
                    'body'  => "Payer {$amount} XOF à {$merchantName} ?",
                    'sound' => 'default',
                    'badge' => 1,
                ],
                'data' => [
                    'type'            => 'PAYMENT_REQUEST',
                    'transaction_id'  => $transactionId,
                    'amount'          => $amount,
                    'merchant_name'   => $merchantName,
                    'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                ],
                'priority'             => 'high',
                'content_available'    => true,
            ]);

            if ($response->successful()) {
                Log::info("FCM notification envoyée pour transaction {$transactionId}");
            } else {
                Log::warning("FCM error: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::warning("FCM failed: " . $e->getMessage());
            // On continue même si FCM échoue
            // L'app peut faire du polling sur /v1/transactions/pending/{phone}
        }
    }
}