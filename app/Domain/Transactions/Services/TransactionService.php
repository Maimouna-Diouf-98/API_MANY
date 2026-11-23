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
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Jobs\SendPaymentNotificationJob;

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
            if ($existing && $existing->aggregator_id !== $aggregator->id) {
                // Clé unique globalement : ne jamais renvoyer la transaction d'un autre agrégateur
                throw new \Exception('IDEMPOTENCY_KEY_CONFLICT');
            }
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
dispatch(new SendPaymentNotificationJob(
    customerId:   (int) $customer->id,
    transactionId: $transaction->transaction_id,
    amount:       (string) $transaction->amount,
    merchantName: $subMerchant->legal_name ?? 'Marchand',
));
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

        $isSandbox     = $transaction instanceof Transaction;
        $conn          = $isSandbox ? 'mysql_sandbox' : 'mysql_money';
        $table         = $isSandbox ? 'api_transactions' : 'transactions';
        $successStatus = $isSandbox ? 'SUCCESS' : 'success';

        if ($amount > $transaction->amount) throw new \Exception('REFUND_AMOUNT_EXCEEDS_ORIGINAL');

        // Règle : le client récupère $amount, débité du wallet de l'agrégateur.
        // La commission Many reste acquise. Un seul remboursement par transaction.
        DB::connection($conn)->transaction(function () use ($transaction, $amount, $reason, $isSandbox, $conn, $table, $successStatus) {

            $current = DB::connection($conn)->table($table)
                         ->where('id', $transaction->id)
                         ->lockForUpdate()
                         ->first();

            if (!$current || $current->status !== $successStatus) {
                throw new \Exception('TRANSACTION_NOT_REFUNDABLE');
            }

            $aggregatorUserId = Aggregator::on($conn)->find($transaction->aggregator_id)?->user_id;

            $aggregatorWallet = $aggregatorUserId
                ? DB::connection($conn)->table('wallets')
                    ->where('user_id', $aggregatorUserId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first()
                : null;

            if (!$aggregatorWallet || $aggregatorWallet->balance < $amount) {
                throw new \Exception('AGGREGATOR_INSUFFICIENT_FUNDS');
            }

            // Client : sender_id en production, recherche par téléphone en sandbox
            $customerId = $isSandbox
                ? DB::connection($conn)->table('users')
                    ->whereRaw("REPLACE(phone, ' ', '') = ?", [str_replace(' ', '', $transaction->customer_phone)])
                    ->value('id')
                : $transaction->sender_id;

            $customerWallet = $customerId
                ? DB::connection($conn)->table('wallets')
                    ->where('user_id', $customerId)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first()
                : null;

            // En production, l'argent doit arriver quelque part
            if (!$customerWallet && !$isSandbox) {
                throw new \Exception('CUSTOMER_WALLET_NOT_FOUND');
            }

            $move = fn (int $userId, string $sign) => DB::connection($conn)->table('wallets')
                ->where('user_id', $userId)
                ->update([
                    'balance'             => DB::raw("balance {$sign} " . $amount),
                    'last_transaction_at' => now(),
                    'version'             => DB::raw('version + 1'),
                    'updated_at'          => now(),
                ]);

            $move((int) $aggregatorUserId, '-');
            if ($customerWallet) {
                $move((int) $customerId, '+');
            }

            $transaction->update([
                'status'         => $isSandbox ? 'REFUNDED' : 'refunded',
                'failure_reason' => $reason,
            ]);
        });

        try {
            dispatch(new SendWebhookJob($transaction, 'transaction.refunded'))->onConnection($conn);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Webhook refund non envoyé : ' . $e->getMessage());
        }

        return $transaction;
    }

/**
 * QR code d'une collection, utilisé par la page de paiement (checkout).
 * L'expiration est figée sur created_at + 10 min : recharger la page ne la prolonge pas.
 */
public function qrCodeFor(Transaction|RealTransaction $transaction): string
{
    return $this->generateQrCode($transaction);
}

private function generateQrCode(Transaction|RealTransaction $transaction): string
{
    // Garde : une transaction sans agrégateur ne doit pas produire de QR
    if (!$transaction->aggregator_id) {
        throw new \DomainException('Transaction sans agrégateur : QR impossible.');
    }

    // RealTransaction => 'mysql_money', Transaction => connexion par défaut
    $conn = $transaction->getConnectionName() ?? config('database.default');

    $aggregator = Aggregator::on($conn)->find($transaction->aggregator_id);

    $user = $aggregator?->user_id
        ? DB::connection($conn)->table('users')
            ->where('id', $aggregator->user_id)
            ->first(['id', 'role', 'first_name', 'last_name', 'phone'])
        : null;

    if (!$user) {
        throw new \DomainException('Utilisateur de l\'agrégateur introuvable : QR impossible.');
    }

    $expiresAt = $transaction instanceof Transaction && $transaction->confirmation_expires_at
        ? $transaction->confirmation_expires_at->timestamp
        : $transaction->created_at->copy()->addMinutes(10)->timestamp;

    $payload = json_encode([
        // champs existants : inchangés
        'type'            => 'MANY_PAYMENT',
        'transaction_id'  => $transaction->transaction_id,
        'amount'          => $transaction->amount,
        'currency'        => $transaction->currency,
        'sub_merchant_id' => $transaction->sub_merchant_id,
        'expires_at'      => $expiresAt,

        // champs ajoutés pour la compatibilité avec l'app
        'user_id'         => (int) $user->id,
        'name'            => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
        'phone'           => $user->phone ?? $aggregator->phone,
        'user_type'       => $user->role,
    ]);


    $qrCode = QrCode::format('svg')
       ->size(350)
       ->margin(2)
       ->errorCorrection('M')
       ->generate($payload);
    return base64_encode($qrCode);
}
}