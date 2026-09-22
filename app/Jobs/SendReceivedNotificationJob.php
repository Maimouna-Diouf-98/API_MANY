<?php

namespace App\Jobs;

use App\Domain\Disbursements\Models\Disbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendReceivedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Disbursement $disbursement,
        public int          $receiverId,
    ) {}

    public function handle(): void
    {
        try {
            $amount = number_format($this->disbursement->net_amount, 0, ',', ' ');

            // Récupérer le destinataire
            $receiver = DB::connection('mysql_money')
                          ->table('users')
                          ->where('id', $this->receiverId)
                          ->first();

            if (!$receiver) return;

            Log::info('DISBURSEMENT_RECEIVED', [
                'disbursement_id' => $this->disbursement->disbursement_id,
                'receiver_phone'  => $receiver->phone,
                'amount'          => $amount . ' XOF',
            ]);

            // Envoyer notification push au destinataire
            if ($receiver->fcm_token) {
                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'key=' . env('FIREBASE_SERVER_KEY'),
                    'Content-Type'  => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to'           => $receiver->fcm_token,
                    'notification' => [
                        'title' => '💰 Argent reçu !',
                        'body'  => "Vous avez reçu {$amount} XOF sur votre compte Many.",
                        'sound' => 'default',
                    ],
                    'data' => [
                        'type'            => 'MONEY_RECEIVED',
                        'disbursement_id' => $this->disbursement->disbursement_id,
                        'amount'          => $amount,
                        'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                    'priority' => 'high',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('SendReceivedNotificationJob Error: ' . $e->getMessage());
        }
    }
}