<?php

namespace App\Domain\Transactions\Services;

use App\Domain\Transactions\Models\Transaction;
use App\Domain\Transactions\Models\RealTransaction;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Auth\Models\Aggregator;
use App\Jobs\ProcessTransactionJob;
use App\Jobs\SendWebhookJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TransactionService
{
    public function __construct(
        protected FeeCalculatorService $feeCalculator
    ) {}

    public function collection(
        array      $data,
        Aggregator $aggregator,
        string     $environment
    ): array {

        if (!empty($data['idempotency_key'])) {
            $model    = $environment === 'SANDBOX' ? Transaction::class : RealTransaction::class;
            $existing = $model::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return [
                    'transaction' => $existing,
                    'qr_code'     => $this->generateQrCode($existing),
                ];
            }
        }

        $subMerchant = SubMerchant::where('id', $data['sub_merchant_id'])
                                  ->whereHas('application', fn($q) =>
                                      $q->where('aggregator_id', $aggregator->id))
                                  ->first();

        if (!$subMerchant) throw new \Exception('SUB_MERCHANT_NOT_FOUND');
        if ($subMerchant->status !== 'ACTIVE') throw new \Exception('SUB_MERCHANT_NOT_ACTIVE');

        $fees          = $this->feeCalculator->calculate((int) $data['amount']);
        $transactionId = (string) rand(1000000000, 9999999999);

        if ($environment === 'SANDBOX') {
            $transaction = Transaction::create([
                'transaction_id'    => $transactionId,
                'sub_merchant_id'   => $subMerchant->id,
                'aggregator_id'     => $aggregator->id,
                'application_id'    => $subMerchant->application_id,
                'type'              => 'COLLECTION',
                'status'            => 'PENDING',
                'idempotency_key'   => $data['idempotency_key'] ?? null,
                'order_reference'   => $data['order_reference'],
                'customer_phone'    => $data['customer_phone'],
                'amount'            => $data['amount'],
                'many_fee'          => $fees['many_fee'],
                'net_to_aggregator' => $fees['net_to_aggregator'],
                'currency'          => $data['currency'] ?? 'XOF',
                'environment'       => 'SANDBOX',
            ]);

            dispatch(new ProcessTransactionJob(
                transaction:   $transaction,
                environment:   'SANDBOX',
                customerPhone: $data['customer_phone'],
                aggregator:    $aggregator,
            ));

            return [
                'transaction' => $transaction,
                'qr_code'     => $this->generateQrCode($transaction),
            ];
        }

        // Production → vérifications réelles
        $normalizedPhone = str_replace(' ', '', $data['customer_phone']);

        $customer = DB::connection('mysql_money')
              ->table('users')
              ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalizedPhone])
              ->whereNull('deleted_at')
              ->first();

        if (!$customer) throw new \Exception('CUSTOMER_NOT_FOUND');

        $customerWallet = DB::connection('mysql_money')
                            ->table('wallets')
                            ->where('user_id', $customer->id)
                            ->where('status', 'active')
                            ->first();

        if (!$customerWallet) throw new \Exception('CUSTOMER_WALLET_NOT_FOUND');
        if ($customerWallet->balance < $data['amount']) throw new \Exception('INSUFFICIENT_FUNDS');
        if (!$customer->mpin) throw new \Exception('CUSTOMER_MPIN_NOT_SET');

        $transaction = RealTransaction::create([
    'sender_id'         => $customer->id,
    'receiver_id'       => $aggregator->user_id,
    'transaction_id'    => $transactionId,
    'reference_no'      => strtoupper(Str::random(16)),
    'sub_merchant_id'   => $subMerchant->id,
    'aggregator_id'     => $aggregator->id,
    'application_id'    => $subMerchant->application_id,
    'api_type'          => 'COLLECTION',
    'idempotency_key'   => $data['idempotency_key'] ?? null,
    'order_reference'   => $data['order_reference'],
    'customer_phone'    => $data['customer_phone'],
    'amount'            => $data['amount'],
    'transaction_fee'   => $fees['many_fee'],
    'many_fee'          => $fees['many_fee'],
    'net_to_aggregator' => $fees['net_to_aggregator'], // ← corrigé
    'currency'          => $data['currency'] ?? 'XOF',
    'status'            => 'pending',
    'transaction_date'  => now()->format('d M Y'),
    'transaction_time'  => now()->format('h:i A'),
    'transaction_mode'  => 'normal',
]);

        return [
            'transaction' => $transaction,
            'qr_code'     => $this->generateQrCode($transaction),
        ];
    }

    // Appelé par l'app mobile Many après scan du QR + saisie du mpin
    public function confirmPayment(
        RealTransaction $transaction,
        string          $mpin
    ): RealTransaction {

        if ($transaction->status !== 'pending') {
            throw new \Exception('TRANSACTION_ALREADY_PROCESSED');
        }

        $customer = DB::connection('mysql_money')
                      ->table('users')
                      ->where('id', $transaction->sender_id)
                      ->first();

        if (!$customer) throw new \Exception('CUSTOMER_NOT_FOUND');
        if (!$customer->mpin) throw new \Exception('CUSTOMER_MPIN_NOT_SET');

        if (!Hash::check($mpin, $customer->mpin)) {
            throw new \Exception('INVALID_MPIN');
        }

        $aggregator = Aggregator::on('mysql_money')->find($transaction->aggregator_id);

        dispatch(new ProcessTransactionJob(
            transaction:   $transaction,
            environment:   'PRODUCTION',
            customerPhone: $transaction->customer_phone,
            aggregator:    $aggregator,
        ));

        return $transaction;
    }

    public function refund(
        Transaction|RealTransaction $transaction,
        int                         $amount,
        string                      $reason
    ): Transaction|RealTransaction {

        $successStatus = $transaction instanceof Transaction ? 'SUCCESS' : 'success';
        if ($transaction->status !== $successStatus) throw new \Exception('TRANSACTION_NOT_REFUNDABLE');
        if ($amount > $transaction->amount) throw new \Exception('REFUND_AMOUNT_EXCEEDS_ORIGINAL');

        $transaction->update([
            'status'         => $transaction instanceof Transaction ? 'REFUNDED' : 'refunded',
            'failure_reason' => $reason,
        ]);

        return $transaction;
    }

   private function generateQrCode(Transaction|RealTransaction $transaction): string
{
    $payload = json_encode([
        'type'            => 'MANY_PAYMENT',
        'transaction_id'  => $transaction->transaction_id,
        'amount'          => $transaction->amount,
        'currency'        => $transaction->currency,
        'sub_merchant_id' => $transaction->sub_merchant_id,
        'expires_at'      => now()->addMinutes(10)->timestamp,
    ]);

    $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(300)
                ->errorCorrection('H')
                ->generate($payload);

    return base64_encode($qrCode);
}
}