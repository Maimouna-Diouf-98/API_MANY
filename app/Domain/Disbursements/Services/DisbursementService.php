<?php

namespace App\Domain\Disbursements\Services;

use App\Domain\Disbursements\Models\Disbursement;
use App\Domain\Auth\Models\Aggregator;
use App\Jobs\ProcessDisbursementJob;
use App\Jobs\SendDisbursementNotificationJob;
use Illuminate\Support\Facades\DB;

class DisbursementService
{
    const MANY_FEE_RATE = 0.01;

    public function create(
        array      $data,
        Aggregator $aggregator,
        string     $environment,
        string     $applicationId
    ): Disbursement {

        // Vérifier idempotence
        if (!empty($data['idempotency_key'])) {
            $existing = Disbursement::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) return $existing;
        }

        $manyFee    = (int) round($data['amount'] * self::MANY_FEE_RATE);
        $netAmount  = $data['amount'] - $manyFee;
        $totalDebit = $data['amount'] + $manyFee;

        // Vérifier solde agrégateur AVANT de créer
        $dbConnection = $environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money';

        $aggregatorWallet = DB::connection($dbConnection)
                              ->table('wallets')
                              ->where('user_id', $aggregator->user_id)
                              ->where('status', 'active')
                              ->first();

        if (!$aggregatorWallet || $aggregatorWallet->balance < $totalDebit) {
            throw new \Exception('INSUFFICIENT_FUNDS');
        }

        // En production : vérifier que le destinataire existe
        $receiverId = null;
        if ($environment === 'PRODUCTION') {
            $normalizedPhone = str_replace(' ', '', $data['recipient_phone']);

            $recipient = DB::connection('mysql_money')
                   ->table('users')
                   ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalizedPhone])
                   ->whereNull('deleted_at')
                   ->first();

            if (!$recipient) throw new \Exception('RECIPIENT_NOT_FOUND');

            $recipientWallet = DB::connection('mysql_money')
                                 ->table('wallets')
                                 ->where('user_id', $recipient->id)
                                 ->where('status', 'active')
                                 ->first();

            if (!$recipientWallet) throw new \Exception('RECIPIENT_WALLET_NOT_FOUND');

            $receiverId = $recipient->id;
        }

        // Créer le disbursement
        $disbursement = Disbursement::create([
            'disbursement_id'  => (string) rand(1000000000, 9999999999),
            'sub_merchant_id'  => $data['sub_merchant_id'],
            'aggregator_id'    => $aggregator->id,
            'application_id'   => $applicationId,
            'sender_id'        => $aggregator->user_id,
            'receiver_id'      => $receiverId,
            'status'           => 'PENDING',
            'recipient_phone'  => $data['recipient_phone'],
            'amount'           => $data['amount'],
            'many_fee'         => $manyFee,
            'net_amount'       => $netAmount,
            'currency'         => $data['currency'] ?? 'XOF',
            'reference'        => $data['reference'] ?? null,
            'idempotency_key'  => $data['idempotency_key'] ?? null,
            'environment'      => $environment,
        ]);

        // Notifier l'agrégateur pour confirmation
        if ($environment === 'PRODUCTION') {
            dispatch(new SendDisbursementNotificationJob(
                disbursement: $disbursement,
                aggregator:   $aggregator,
            ))->onConnection('mysql_money');
        } else {
            // Sandbox → traitement automatique
            dispatch(new ProcessDisbursementJob(
                disbursement: $disbursement,
                environment:  $environment,
                aggregator:   $aggregator,
            ))->onConnection('mysql_sandbox');
        }

        return $disbursement;
    }

    public function confirmDisbursement(
        Disbursement $disbursement,
        string       $mpin,
        Aggregator   $aggregator
    ): Disbursement {

        // Vérifier que le disbursement est PENDING
        if ($disbursement->status !== 'PENDING') {
            throw new \Exception('DISBURSEMENT_ALREADY_PROCESSED');
        }

        // Vérifier le mpin de l'agrégateur via son user_id
        $aggregatorUser = DB::connection('mysql_money')
                            ->table('users')
                            ->where('id', $aggregator->user_id)
                            ->first();

        if (!$aggregatorUser) {
            throw new \Exception('AGGREGATOR_USER_NOT_FOUND');
        }

        if (!$aggregatorUser->mpin) {
            throw new \Exception('AGGREGATOR_MPIN_NOT_SET');
        }

        if (!\Illuminate\Support\Facades\Hash::check($mpin, $aggregatorUser->mpin)) {
            throw new \Exception('INVALID_MPIN');
        }

        // Marquer comme confirmé
        $disbursement->update(['confirmed_at' => now()]);

        // Dispatcher le job de traitement production
        dispatch(new ProcessDisbursementJob(
            disbursement: $disbursement,
            environment:  'PRODUCTION',
            aggregator:   $aggregator,
        ))->onConnection('mysql_money');

        return $disbursement;
    }
}