<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Transactions\Models\Transaction;
use App\Domain\Transactions\Models\RealTransaction;
use App\Domain\Transactions\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function __construct(protected TransactionService $service) {}

    public function index(Request $request)
    {
        $aggregator  = $request->authenticated_aggregator;
        $environment = $request->environment ?? 'SANDBOX';

        $model        = $environment === 'SANDBOX' ? Transaction::class : RealTransaction::class;
        $transactions = $model::where('aggregator_id', $aggregator->id)
                              ->when($request->status, fn($q) => $q->where('status', $request->status))
                              ->when($request->sub_merchant_id, fn($q) =>
                                  $q->where('sub_merchant_id', $request->sub_merchant_id))
                              ->latest()
                              ->paginate($request->get('limit', 20));

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $environment = $request->environment ?? 'SANDBOX';

        $rules = [
            'sub_merchant_id' => 'required|string|exists:sub_merchants,id',
            'amount'          => 'required|integer|min:100',
            'currency'        => 'required|in:XOF',
            'customer_phone'  => ['required', 'string'],
            'order_reference' => 'required|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ];

        if ($environment === 'SANDBOX') {
            $testNumbers = array_map(
                fn($i) => '+221770000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                range(1, 99)
            );
            $rules['customer_phone'][] = function ($attr, $value, $fail) use ($testNumbers) {
                if (!in_array($value, $testNumbers)) {
                    $fail('En sandbox, utilisez un numéro de test (+221770000001 à +221770000099).');
                }
            };
        }

        $validated = $request->validate($rules);

        try {
            $aggregator = $request->authenticated_aggregator;

            if ($environment === 'PRODUCTION' && !$aggregator->production_enabled) {
                return response()->json([
                    'error'   => 'PRODUCTION_NOT_ENABLED',
                    'message' => 'Votre compte n\'est pas activé en production.',
                ], 403);
            }

            $result      = $this->service->collection($validated, $aggregator, $environment);
            $transaction = $result['transaction'];
            $qrCode      = $result['qr_code'];

            $netAmount = $environment === 'SANDBOX'
                         ? $transaction->net_to_aggregator
                         : $transaction->net_to_aggregator;

            return response()->json([
                'transaction_id'    => $transaction->transaction_id,
                'sub_merchant_id'   => $transaction->sub_merchant_id,
                'aggregator_id'     => $transaction->aggregator_id,
                'type'              => 'COLLECTION',
                'status'            => $environment === 'SANDBOX'
                                       ? $transaction->status
                                       : strtoupper($transaction->status),
                'amount'            => $transaction->amount,
                'fees'              => ['many_fee' => $transaction->many_fee],
                'net_to_aggregator' => $netAmount,
                'currency'          => $transaction->currency,
                'environment'       => $environment,
                'qr_code'           => $qrCode,
                'qr_code_format'    => 'base64/svg+xml',
                'qr_expires_in'     => 600,
                'created_at'        => $transaction->created_at,
            ], 201);

        } catch (\Exception $e) {
            Log::error('DEBUG TRANSACTION ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

            $errorMap = [
                'SUB_MERCHANT_NOT_FOUND'    => ['code' => 'SUB_MERCHANT_NOT_FOUND',    'message' => 'Sous-marchand introuvable.',              'status' => 404],
                'SUB_MERCHANT_NOT_ACTIVE'   => ['code' => 'SUB_MERCHANT_NOT_ACTIVE',   'message' => 'Le sous-marchand n\'est pas actif.',      'status' => 422],
                'CUSTOMER_NOT_FOUND'        => ['code' => 'CUSTOMER_NOT_FOUND',        'message' => 'Ce numéro n\'a pas de compte Many.',      'status' => 404],
                'CUSTOMER_WALLET_NOT_FOUND' => ['code' => 'CUSTOMER_WALLET_NOT_FOUND', 'message' => 'Portefeuille client introuvable.',         'status' => 422],
                'INSUFFICIENT_FUNDS'        => ['code' => 'INSUFFICIENT_FUNDS',        'message' => 'Solde insuffisant.',                      'status' => 422],
                'CUSTOMER_MPIN_NOT_SET'     => ['code' => 'CUSTOMER_MPIN_NOT_SET',     'message' => 'Le client n\'a pas configuré son PIN.',   'status' => 422],
            ];

            $error = $errorMap[$e->getMessage()] ?? [
                'code' => 'INTERNAL_ERROR', 'message' => 'Erreur interne.', 'status' => 500
            ];

            return response()->json(['error' => $error['code'], 'message' => $error['message']],
                                    $error['status']);
        }
    }

    public function show(Request $request, string $transactionId)
    {
        $aggregator  = $request->authenticated_aggregator;
        $environment = $request->environment ?? 'SANDBOX';

        if ($environment === 'SANDBOX') {
            $transaction = Transaction::where('transaction_id', $transactionId)
                                      ->where('aggregator_id', $aggregator->id)
                                      ->firstOrFail();
            $netAmount = $transaction->net_to_aggregator;
        } else {
            $transaction = RealTransaction::where('transaction_id', $transactionId)
                                          ->where('aggregator_id', $aggregator->id)
                                          ->firstOrFail();
            $netAmount = $transaction->net_to_aggregator;
        }

        return response()->json([
            'transaction_id'    => $transaction->transaction_id,
            'sub_merchant_id'   => $transaction->sub_merchant_id,
            'aggregator_id'     => $transaction->aggregator_id,
            'type'              => $environment === 'SANDBOX' ? $transaction->type : $transaction->api_type,
            'status'            => $environment === 'SANDBOX'
                                   ? $transaction->status
                                   : strtoupper($transaction->status),
            'amount'            => $transaction->amount,
            'fees'              => ['many_fee' => $transaction->many_fee],
            'net_to_aggregator' => $netAmount,
            'customer_phone'    => $transaction->customer_phone,
            'order_reference'   => $transaction->order_reference,
            'idempotency_key'   => $transaction->idempotency_key,
            'failure_reason'    => $transaction->failure_reason,
            'environment'       => $environment,
            'created_at'        => $transaction->created_at,
            'completed_at'      => $environment === 'SANDBOX'
                                   ? $transaction->completed_at
                                   : $transaction->updated_at,
        ]);
    }

    public function refund(Request $request, string $transactionId)
    {
        $validated   = $request->validate([
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);
        $aggregator  = $request->authenticated_aggregator;
        $environment = $request->environment ?? 'SANDBOX';

        $transaction = $environment === 'SANDBOX'
            ? Transaction::where('transaction_id', $transactionId)
                         ->where('aggregator_id', $aggregator->id)->firstOrFail()
            : RealTransaction::where('transaction_id', $transactionId)
                             ->where('aggregator_id', $aggregator->id)->firstOrFail();

        try {
            $transaction = $this->service->refund($transaction, $validated['amount'], $validated['reason']);
            return response()->json([
                'transaction_id' => $transaction->transaction_id,
                'status'         => $environment === 'SANDBOX'
                                    ? $transaction->status
                                    : strtoupper($transaction->status),
                'refunded_at'    => $transaction->updated_at,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'message' => 'Remboursement impossible.'], 422);
        }
    }

    // Appelé par l'app mobile Many après scan du QR code + saisie du mpin
    public function confirm(Request $request, string $transactionId)
    {
        $request->validate([
            'customer_phone' => 'required|string',
            'mpin'           => 'required|string',
        ]);

        $normalizedPhone = str_replace(' ', '', $request->customer_phone);

        $transaction = RealTransaction::where('transaction_id', $transactionId)
                                      ->whereRaw("REPLACE(customer_phone, ' ', '') = ?", [$normalizedPhone])
                                      ->first();

        if (!$transaction) {
            return response()->json([
                'error'   => 'TRANSACTION_NOT_FOUND',
                'message' => 'Transaction introuvable.',
            ], 404);
        }

        if ($transaction->status !== 'pending') {
            return response()->json([
                'error'   => 'TRANSACTION_ALREADY_PROCESSED',
                'message' => 'Cette transaction a déjà été traitée.',
                'status'  => strtoupper($transaction->status),
            ], 409);
        }
        if ($transaction->created_at->addMinutes(10)->isPast()) {
    $transaction->update([
        'status'         => 'failed',
        'failure_reason' => 'EXPIRED',
    ]);

    return response()->json([
        'error'   => 'TRANSACTION_EXPIRED',
        'message' => 'Cette demande de paiement a expiré.',
    ], 410);
}
        

        try {
            $transaction = $this->service->confirmPayment($transaction, $request->mpin);

            return response()->json([
                'transaction_id' => $transaction->transaction_id,
                'status'         => 'PROCESSING',
                'message'        => 'Paiement en cours de traitement.',
            ]);

        } catch (\Exception $e) {
            Log::error('CONFIRM ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            $errorMap = [
                'TRANSACTION_ALREADY_PROCESSED' => ['code' => 'TRANSACTION_ALREADY_PROCESSED', 'message' => 'Transaction déjà traitée.', 'status' => 409],
                'CUSTOMER_NOT_FOUND'            => ['code' => 'CUSTOMER_NOT_FOUND',            'message' => 'Client introuvable.',        'status' => 404],
                'CUSTOMER_MPIN_NOT_SET'         => ['code' => 'CUSTOMER_MPIN_NOT_SET',         'message' => 'PIN non configuré.',         'status' => 422],
                'INVALID_MPIN'                  => ['code' => 'INVALID_MPIN',                  'message' => 'PIN incorrect.',             'status' => 401],
            ];

            $error = $errorMap[$e->getMessage()] ?? [
                'code' => 'INTERNAL_ERROR', 'message' => 'Erreur interne.', 'status' => 500
            ];
            

            return response()->json(['error' => $error['code'], 'message' => $error['message']],
                                    $error['status']);
        }
    }
    // Appelé par l'app mobile money pour voir
// les demandes de paiement en attente
public function pendingForCustomer(Request $request, string $phone)
{
    $normalizedPhone = str_replace(' ', '', $phone);

    $transactions = RealTransaction::whereRaw(
                        "REPLACE(customer_phone, ' ', '') = ?",
                        [$normalizedPhone]
                    )
                    ->where('status', 'pending')
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->whereNotNull('api_type')
                    ->where('api_type', 'COLLECTION')
                    ->latest()
                    ->get()
                    ->map(function ($tx) {
                        $subMerchant = \App\Domain\SubMerchants\Models\SubMerchant::on('mysql_money')
                                        ->find($tx->sub_merchant_id);
                        return [
                            'transaction_id'  => $tx->transaction_id,
                            'amount'          => $tx->amount,
                            'currency'        => $tx->currency,
                            'merchant_name'   => $subMerchant?->legal_name ?? 'Marchand',
                            'order_reference' => $tx->order_reference,
                            'created_at'      => $tx->created_at,
                            'expires_at'      => $tx->created_at->addMinutes(10),
                        ];
                    });

    return response()->json([
        'pending_count' => $transactions->count(),
        'transactions'  => $transactions,
    ]);
}
}
