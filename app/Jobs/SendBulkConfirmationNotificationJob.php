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

class SendBulkConfirmationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int    $userId,
        public string $bulkId,
        public string $label,
        public int    $totalRecipients,
        public string $totalAmount,
        public string $fee,
    ) {}

    public function handle(): void
    {
        $user = DB::connection('mysql_money')
            ->table('users')
            ->where('id', $this->userId)
            ->first(['id', 'fcm_token']);

        Log::info('BULK CONFIRMATION', [
            'bulk_id'       => $this->bulkId,
            'user_id'       => $this->userId,
            'has_fcm_token' => !empty($user?->fcm_token),
        ]);

        if (!$user || empty($user->fcm_token)) {
            Log::warning('BULK CONFIRMATION: pas de fcm_token', ['user_id' => $this->userId]);
            return;
        }

        $sent = app(FirebaseNotificationService::class)->sendBulkConfirmation(
            $user->fcm_token,
            $this->bulkId,
            $this->label,
            $this->totalRecipients,
            $this->totalAmount,
            $this->fee
        );

        Log::info('FCM bulk confirmation result: ' . ($sent ? 'SUCCESS' : 'FAILED'));

        if (!$sent) {
            throw new \RuntimeException('Échec envoi FCM BULK_PAYMENT_CONFIRMATION');
        }
    }
}