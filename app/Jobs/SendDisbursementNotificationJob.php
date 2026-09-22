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

class SendDisbursementNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Disbursement $disbursement,
        public Aggregator   $aggregator,
    ) {}

    public function handle(): void
    {
        try {
            $amount    = number_format($this->disbursement->amount, 0, ',', ' ');
            $recipient = $this->disbursement->recipient_phone;
            $reference = $this->disbursement->disbursement_id;

            Log::info('DISBURSEMENT_CONFIRMATION_REQUIRED', [
                'disbursement_id'  => $reference,
                'aggregator_id'    => $this->aggregator->id,
                'aggregator_name'  => $this->aggregator->legal_name,
                'amount'           => $amount . ' XOF',
                'recipient_phone'  => $recipient,
                'reference'        => $this->disbursement->reference,
            ]);

            // Envoyer notification push à l'agrégateur via FCM
            $aggregatorUser = DB::connection('mysql_money')
                                ->table('users')
                                ->where('id', $this->aggregator->user_id)
                                ->first();

            if ($aggregatorUser && $aggregatorUser->fcm_token) {
                $this->sendFcmNotification(
                    $aggregatorUser->fcm_token,
                    $amount,
                    $recipient,
                    $reference
                );
            }

        } catch (\Exception $e) {
            Log::error('SendDisbursementNotificationJob Error: ' . $e->getMessage());
        }
    }

    private function sendFcmNotification(
        string $fcmToken,
        string $amount,
        string $recipient,
        string $disbursementId
    ): void {
        try {
            \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'key=' . env('FIREBASE_SERVER_KEY'),
                'Content-Type'  => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to'           => $fcmToken,
                'notification' => [
                    'title' => '💸 Confirmation de décaissement',
                    'body'  => "Confirmez l'envoi de {$amount} XOF à {$recipient}",
                    'sound' => 'default',
                ],
                'data' => [
                    'type'            => 'DISBURSEMENT_CONFIRMATION',
                    'disbursement_id' => $disbursementId,
                    'amount'          => $amount,
                    'recipient_phone' => $recipient,
                    'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                ],
                'priority' => 'high',
            ]);
        } catch (\Exception $e) {
            Log::warning('FCM disbursement notification failed: ' . $e->getMessage());
        }
    }
}