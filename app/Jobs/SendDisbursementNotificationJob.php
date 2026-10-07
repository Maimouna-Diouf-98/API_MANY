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

        // Récupérer le fcm_token de l'agrégateur
        $aggregatorUser = DB::connection('mysql_money')
                            ->table('users')
                            ->where('id', $this->aggregator->user_id)
                            ->first();

        Log::info('AGGREGATOR USER FCM', [
            'user_id'   => $this->aggregator->user_id,
            'has_token' => !empty($aggregatorUser?->fcm_token),
        ]);

        if ($aggregatorUser && $aggregatorUser->fcm_token) {
            $firebase = new \App\Services\FirebaseNotificationService();
            $result   = $firebase->sendDisbursementConfirmation(
                $aggregatorUser->fcm_token,
                $reference,
                $amount,
                $recipient
            );

            Log::info('FCM disbursement result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        } else {
            Log::warning('Agrégateur sans FCM token - user_id: ' . $this->aggregator->user_id);
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
    app(\App\Services\FirebaseNotificationService::class)
        ->sendDisbursementConfirmation($fcmToken, $disbursementId, $amount, $recipient);
}
}