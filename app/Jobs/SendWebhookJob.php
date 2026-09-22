<?php

namespace App\Jobs;

use App\Domain\Transactions\Models\Transaction;
use App\Domain\Transactions\Models\RealTransaction;
use App\Domain\Disbursements\Models\Disbursement;
use App\Domain\BulkPayments\Models\BulkPayment;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Auth\Models\Application;
use App\Domain\Auth\Models\Aggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $backoff = 60;

    public function __construct(
        public Transaction|RealTransaction|Disbursement|BulkPayment $transaction,
        public string                                                $eventType,
    ) {}

    public function handle(): void
    {
        Log::info('=== SendWebhookJob START ===', ['event' => $this->eventType]);

        $isDisbursement = $this->transaction instanceof Disbursement;
        $isBulkPayment  = $this->transaction instanceof BulkPayment;

        // Transaction/RealTransaction se distinguent par leur classe.
        // Disbursement et BulkPayment partagent le même modèle pour les deux
        // environnements, donc on lit leur champ `environment` directement.
        $isSandbox = match (true) {
            $this->transaction instanceof Transaction     => true,
            $this->transaction instanceof RealTransaction  => false,
            default => ($this->transaction->environment ?? 'PRODUCTION') === 'SANDBOX',
        };

        $connection = $isSandbox ? 'mysql_sandbox' : 'mysql_money';

        Log::info('SendWebhookJob context', [
            'is_disbursement'  => $isDisbursement,
            'is_bulk_payment'  => $isBulkPayment,
            'is_sandbox'       => $isSandbox,
            'connection'       => $connection,
            'sub_merchant_id'  => $this->transaction->sub_merchant_id,
        ]);

        $payload = [
            'event_id'   => (string) Str::uuid(),
            'event_type' => $this->eventType,
            'created_at' => now()->toIso8601String(),
            'data'       => match (true) {
                $isDisbursement => $this->buildDisbursementPayload(),
                $isBulkPayment  => $this->buildBulkPaymentPayload(),
                default         => $this->buildTransactionPayload($isSandbox),
            },
        ];

        $subMerchant = SubMerchant::on($connection)->find($this->transaction->sub_merchant_id);

        if (!$subMerchant) {
            Log::warning('SendWebhookJob: sub_merchant introuvable', [
                'sub_merchant_id' => $this->transaction->sub_merchant_id,
                'connection'      => $connection,
            ]);
            return;
        }

        $application = Application::on($connection)->find($subMerchant->application_id);
        $aggregator  = $application
                       ? Aggregator::on($connection)->find($application->aggregator_id)
                       : null;

        Log::info('SendWebhookJob aggregator lookup', [
            'application_found'        => $application !== null,
            'aggregator_found'         => $aggregator !== null,
            'aggregator_webhook_url'   => $aggregator->webhook_url ?? 'N/A',
            'sub_merchant_webhook_url' => $subMerchant->webhook_url ?? 'N/A',
        ]);

        if ($aggregator) {
            $this->sendWebhook(
                $aggregator->webhook_url,
                $payload,
                $aggregator->client_secret ?? '',
                $isSandbox
            );
        }

        // Un bulk payment permet aussi de préciser sa propre webhook_url
        // à la création (voir BulkPaymentController::store), en plus de
        // celle du sous-marchand.
        if ($isBulkPayment && !empty($this->transaction->webhook_url)) {
            $this->sendWebhook(
                $this->transaction->webhook_url,
                $payload,
                '',
                $isSandbox
            );
        }

        $this->sendWebhook(
            $subMerchant->webhook_url,
            $payload,
            '',
            $isSandbox
        );

        Log::info('=== SendWebhookJob END ===');
    }

    private function buildTransactionPayload(bool $isSandbox): array
    {
        return [
            'transaction_id'    => $this->transaction->transaction_id,
            'sub_merchant_id'   => $this->transaction->sub_merchant_id,
            'aggregator_id'     => $this->transaction->aggregator_id,
            'type'              => 'COLLECTION',
            'amount'            => $this->transaction->amount,
            'currency'          => $this->transaction->currency,
            'status'            => $isSandbox
                                   ? $this->transaction->status
                                   : strtoupper($this->transaction->status),
            'fees'              => ['many_fee' => $this->transaction->many_fee],
            'net_to_aggregator' => $this->transaction->net_to_aggregator,
        ];
    }

    private function buildDisbursementPayload(): array
    {
        return [
            'disbursement_id' => $this->transaction->disbursement_id,
            'sub_merchant_id' => $this->transaction->sub_merchant_id,
            'aggregator_id'   => $this->transaction->aggregator_id,
            'type'            => 'DISBURSEMENT',
            'amount'          => $this->transaction->amount,
            'currency'        => $this->transaction->currency,
            'status'          => $this->transaction->status,
            'fees'            => ['many_fee' => $this->transaction->many_fee],
            'net_amount'      => $this->transaction->net_amount,
            'recipient_phone' => $this->transaction->recipient_phone,
            'reference'       => $this->transaction->reference,
        ];
    }

    private function buildBulkPaymentPayload(): array
    {
        return [
            'bulk_id'          => $this->transaction->bulk_id,
            'sub_merchant_id'  => $this->transaction->sub_merchant_id,
            'aggregator_id'    => $this->transaction->aggregator_id,
            'type'             => 'BULK_PAYMENT',
            'label'            => $this->transaction->label,
            'status'           => $this->transaction->status,
            'total_recipients' => $this->transaction->total_recipients,
            'success_count'    => $this->transaction->success_count,
            'failure_count'    => $this->transaction->failure_count,
            'total_amount'     => $this->transaction->total_amount,
            'fees'             => ['many_fee' => $this->transaction->many_fee],
            'currency'         => $this->transaction->currency,
        ];
    }

    private function sendWebhook(?string $url, array $payload, string $secret, bool $isSandbox): void
    {
        if (empty($url)) {
            Log::info('sendWebhook: URL vide, envoi ignoré');
            return;
        }

        $body      = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $body, $secret);

        $request = Http::withHeaders([
            'Content-Type'             => 'application/json',
            'X-Many-Webhook-Signature' => $signature,
            'X-Many-Webhook-Timestamp' => $timestamp,
            'X-Many-Event-Id'          => $payload['event_id'],
        ])->timeout(10);

        if ($isSandbox) {
            try {
                $response = $request->post($url, $payload);
                Log::info("Webhook sandbox envoyé à {$url} — statut: " . $response->status());
            } catch (\Exception $e) {
                Log::info("Webhook sandbox ignoré (URL probablement fictive): " . $e->getMessage());
            }
            return;
        }

        try {
            $response = $request->throw()->post($url, $payload);
            Log::info("Webhook production envoyé à {$url} — statut: " . $response->status());
        } catch (\Exception $e) {
            Log::error("Webhook production échoué vers {$url}: " . $e->getMessage());
            throw $e;
        }
    }
}