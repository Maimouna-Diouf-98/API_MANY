<?php

namespace App\Jobs;

use App\Services\FirebaseNotificationService;
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
        public int     $customerId,
        public string  $transactionId,
        public string  $amount,
        public string  $merchantName,
        public ?string $orderReference = null,
    ) {}

    public function handle(): void
    {
        $customer = DB::connection('mysql_money')
            ->table('users')
            ->where('id', $this->customerId)
            ->first(['id', 'fcm_token']);

        Log::info('PAYMENT_REQUEST', [
            'transaction_id' => $this->transactionId,
            'customer_id'    => $this->customerId,
            'has_fcm_token'  => !empty($customer?->fcm_token),
            'amount'         => $this->amount . ' XOF',
        ]);

        if (!$customer || empty($customer->fcm_token)) {
            Log::warning('PAYMENT_REQUEST: pas de fcm_token', ['customer_id' => $this->customerId]);
            return;
        }

        $sent = app(FirebaseNotificationService::class)->sendPaymentRequest(
            $customer->fcm_token,
            $this->transactionId,
            $this->amount,          // montant brut : le formatage est fait dans le service
            $this->merchantName,
            'XOF',
            $this->orderReference
        );

        Log::info('FCM payment request result: ' . ($sent ? 'SUCCESS' : 'FAILED'));

        if (!$sent) {
            throw new \RuntimeException('Échec envoi FCM PAYMENT_REQUEST');
        }
    }
}