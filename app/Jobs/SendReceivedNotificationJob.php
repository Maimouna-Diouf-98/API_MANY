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
            app(\App\Services\FirebaseNotificationService::class)->sendMoneyReceived(
            $receiver->fcm_token,
          $amount,
         (string) $this->disbursement->disbursement_id
    );
}

        } catch (\Exception $e) {
            Log::error('SendReceivedNotificationJob Error: ' . $e->getMessage());
        }
    }
}