<?php

namespace App\Http\Controllers;

use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Transactions\Models\RealTransaction;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Transactions\Services\TransactionService;
use App\Support\CheckoutLinks;
use Illuminate\Support\Facades\Log;

/**
 * Page de paiement hébergée : affiche le QR code d'une collection que le client scanne
 * avec l'application Many, puis suit le statut jusqu'au résultat.
 * Routes publiques protégées par un lien signé (middleware « signed »).
 */
class CheckoutController extends Controller
{
    public function __construct(protected TransactionService $transactions) {}

    public function show(string $env, string $transactionId)
    {
        $tx = $this->find($env, $transactionId);
        abort_if(!$tx, 404);

        $state = $this->state($tx);
        $qr    = null;

        if ($state === 'PENDING') {
            try {
                $qr = $this->transactions->qrCodeFor($tx);
            } catch (\Throwable $e) {
                Log::error('Checkout : génération du QR impossible : ' . $e->getMessage());
            }
        }

        return response()
            ->view('checkout.show', [
                'env'       => $env,
                'tx'        => $tx,
                'state'     => $state,
                'qr'        => $qr,
                'merchant'  => $this->merchantName($env, $tx),
                'remaining' => $this->remainingSeconds($tx),
                'statusUrl' => CheckoutLinks::statusUrl($env, $transactionId, $tx->created_at),
                'failure'   => $this->failureMessage($tx->failure_reason ?? null),
            ])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');   // ne pas divulguer le lien signé
    }

    /** Interrogée toutes les 3 secondes par la page. */
    public function status(string $env, string $transactionId)
    {
        $tx = $this->find($env, $transactionId);
        abort_if(!$tx, 404);

        $state = $this->state($tx);

        return response()
            ->json([
                'status'    => $state,
                'remaining' => $this->remainingSeconds($tx),
                'message'   => $state === 'FAILED' ? $this->failureMessage($tx->failure_reason ?? null) : null,
            ])
            ->header('Cache-Control', 'no-store');
    }

    private function find(string $env, string $id): Transaction|RealTransaction|null
    {
        return $env === 'production'
            ? RealTransaction::where('transaction_id', $id)->where('api_type', 'COLLECTION')->first()
            : Transaction::on('mysql_sandbox')->where('transaction_id', $id)->first();
    }

    /** PENDING | SUCCESS | FAILED | REFUNDED | EXPIRED (en attente et délai dépassé). */
    private function state(Transaction|RealTransaction $tx): string
    {
        $status = strtoupper($tx->status);

        return $status === 'PENDING' && $this->remainingSeconds($tx) <= 0 ? 'EXPIRED' : $status;
    }

    private function remainingSeconds(Transaction|RealTransaction $tx): int
    {
        $deadline = $tx->created_at->copy()->addMinutes(CheckoutLinks::PAYMENT_WINDOW_MINUTES);

        return max(0, $deadline->getTimestamp() - now()->getTimestamp());
    }

    private function merchantName(string $env, Transaction|RealTransaction $tx): string
    {
        $conn = $env === 'production' ? 'mysql_money' : 'mysql_sandbox';

        return SubMerchant::on($conn)->find($tx->sub_merchant_id)?->legal_name ?? 'Marchand';
    }

    /** Message lisible : les codes internes ne sont jamais exposés au client final. */
    private function failureMessage(?string $reason): string
    {
        return match ($reason) {
            'INSUFFICIENT_FUNDS'                          => 'Solde insuffisant sur votre compte Many.',
            'EXPIRED', 'CONFIRMATION_EXPIRED', 'OTP_EXPIRED' => 'La demande de paiement a expiré.',
            default                                       => "Le paiement n'a pas pu être effectué.",
        };
    }
}
