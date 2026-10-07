<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\BulkPayments\Models\BulkPayment;
use App\Domain\BulkPayments\Models\BulkPaymentRecipient;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Auth\Models\Application;
use App\Jobs\ProcessBulkPaymentJob;
use App\Jobs\SendBulkConfirmationNotificationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BulkPaymentController extends Controller
{
    const MANY_FEE_RATE = 0.01;

    public function index(Request $request)
    {
        $aggregator   = $request->authenticated_aggregator;
        $bulkPayments = BulkPayment::where('aggregator_id', $aggregator->id)
                                    ->when($request->status, fn($q) =>
                                        $q->where('status', $request->status))
                                    ->latest()
                                    ->paginate(20);

        return response()->json($bulkPayments);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sub_merchant_id'        => 'required|string|exists:sub_merchants,id',
            'label'                  => 'required|string|max:255',
            'currency'               => 'required|in:XOF',
            'webhook_url'            => 'nullable|url',
            'recipients'             => 'required|array|min:1|max:1000',
            'recipients.*.phone'     => 'required|string',
            'recipients.*.amount'    => 'required|integer|min:100',
            'recipients.*.reference' => 'nullable|string|max:255',
        ]);

        $aggregator  = $request->authenticated_aggregator;
        $environment = $request->environment ?? 'SANDBOX';

        // Vérifier autorisation production
        if ($environment === 'PRODUCTION' && !$aggregator->production_enabled) {
            return response()->json([
                'error'   => 'PRODUCTION_NOT_ENABLED',
                'message' => 'Votre compte n\'est pas activé en production.',
            ], 403);
        }

        // Vérifier sous-marchand
        $subMerchant = SubMerchant::where('id', $validated['sub_merchant_id'])
                                  ->whereHas('application', fn($q) =>
                                      $q->where('aggregator_id', $aggregator->id))
                                  ->first();

        if (!$subMerchant) {
            return response()->json([
                'error'   => 'SUB_MERCHANT_NOT_FOUND',
                'message' => 'Sous-marchand introuvable.',
            ], 404);
        }

        if ($subMerchant->status !== 'ACTIVE') {
            return response()->json([
                'error'   => 'SUB_MERCHANT_NOT_ACTIVE',
                'message' => 'Le sous-marchand n\'est pas actif.',
            ], 422);
        }

        // Sandbox : numéros de test uniquement
        if ($environment === 'SANDBOX') {
            $testNumbers = array_map(
                fn($i) => '+221770000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                range(1, 99)
            );
            foreach ($validated['recipients'] as $index => $r) {
                if (!in_array($r['phone'], $testNumbers)) {
                    return response()->json([
                        'error'   => 'SANDBOX_INVALID_RECIPIENT',
                        'message' => "Bénéficiaire #{$index}: {$r['phone']} n'est pas un numéro de test.",
                    ], 422);
                }
            }
        }

        // Calculer totaux
        $totalAmount = array_sum(array_column($validated['recipients'], 'amount'));
        $totalFee    = (int) round($totalAmount * self::MANY_FEE_RATE);
        $totalDebit  = $totalAmount + $totalFee;

        // Vérifier solde agrégateur AVANT de créer
        $dbConnection = $environment === 'SANDBOX' ? 'mysql_sandbox' : 'mysql_money';

        if ($aggregator->user_id) {
            $wallet = DB::connection($dbConnection)
                        ->table('wallets')
                        ->where('user_id', $aggregator->user_id)
                        ->where('status', 'active')
                        ->first();

            if (!$wallet || $wallet->balance < $totalDebit) {
                return response()->json([
                    'error'   => 'INSUFFICIENT_FUNDS',
                    'message' => 'Solde insuffisant. '
                                 . 'Disponible : ' . number_format($wallet->balance ?? 0, 0, ',', ' ') . ' XOF. '
                                 . 'Requis : ' . number_format($totalDebit, 0, ',', ' ') . ' XOF.',
                ], 422);
            }
        }

        // Production : vérifier tous les destinataires
      // Production : vérifier tous les destinataires
if ($environment === 'PRODUCTION') {
    $invalidRecipients = [];
    foreach ($validated['recipients'] as $r) {
        $normalizedPhone = str_replace(' ', '', $r['phone']);

        $user = DB::connection('mysql_money')
                   ->table('users')
                   ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalizedPhone])
                   ->whereNull('deleted_at')
                   ->first();
        if (!$user) $invalidRecipients[] = $r['phone'];
    }
    if (!empty($invalidRecipients)) {
        return response()->json([
            'error'      => 'RECIPIENTS_NOT_FOUND',
            'message'    => 'Certains bénéficiaires n\'ont pas de compte Many.',
            'recipients' => $invalidRecipients,
        ], 422);
    }
}

        // Récupérer application
        $application = Application::where('aggregator_id', $aggregator->id)
                                  ->where('is_active', true)
                                  ->first();

        if (!$application) {
            return response()->json([
                'error'   => 'NO_ACTIVE_APPLICATION',
                'message' => 'Aucune application active trouvée.',
            ], 422);
        }

        // Créer le bulk payment
        $bulkPayment = BulkPayment::create([
            'bulk_id'          => 'BLK-' . strtoupper(Str::random(10)),
            'sub_merchant_id'  => $subMerchant->id,
            'aggregator_id'    => $aggregator->id,
            'application_id'   => $application->id,
            'label'            => $validated['label'],
            'status'           => 'PENDING',
            'total_recipients' => count($validated['recipients']),
            'total_amount'     => $totalAmount,
            'many_fee'         => $totalFee,
            'currency'         => $validated['currency'],
            'environment'      => $environment,
            'webhook_url'      => $validated['webhook_url'] ?? null,
        ]);

        // Créer les recipients
        foreach ($validated['recipients'] as $r) {
            $manyFee   = (int) round($r['amount'] * self::MANY_FEE_RATE);
            $netAmount = $r['amount'] - $manyFee;

            BulkPaymentRecipient::create([
                'bulk_id'    => $bulkPayment->bulk_id,
                'phone'      => $r['phone'],
                'amount'     => $r['amount'],
                'many_fee'   => $manyFee,
                'net_amount' => $netAmount,
                'reference'  => $r['reference'] ?? null,
                'status'     => 'PENDING',
            ]);
        }

        // Sandbox → traitement automatique sans confirmation
        if ($environment === 'SANDBOX') {
            dispatch(new ProcessBulkPaymentJob(
                bulkPayment: $bulkPayment,
                aggregator:  $aggregator,
                environment: 'SANDBOX',
            ))->onConnection('mysql_sandbox');
        }else {
    // Production → attendre confirmation mpin
    Log::info("BulkPayment {$bulkPayment->bulk_id} créé en attente de confirmation", [
        'aggregator'       => $aggregator->legal_name,
        'total_recipients' => $bulkPayment->total_recipients,
        'total_amount'     => $bulkPayment->total_amount . ' XOF',
    ]);

    dispatch(new SendBulkConfirmationNotificationJob(
        userId:          (int) $aggregator->user_id,
        bulkId:          $bulkPayment->bulk_id,
        label:           $bulkPayment->label,
        totalRecipients: (int) $bulkPayment->total_recipients,
        totalAmount:     (string) $bulkPayment->total_amount,
        fee:             (string) $bulkPayment->many_fee,
    ));
}

        return response()->json([
            'bulk_id'          => $bulkPayment->bulk_id,
            'sub_merchant_id'  => $bulkPayment->sub_merchant_id,
            'label'            => $bulkPayment->label,
            'status'           => $bulkPayment->status,
            'total_recipients' => $bulkPayment->total_recipients,
            'total_amount'     => $bulkPayment->total_amount,
            'fees'             => ['many_fee' => $bulkPayment->many_fee],
            'currency'         => $bulkPayment->currency,
            'environment'      => $bulkPayment->environment,
            'message'          => $environment === 'PRODUCTION'
                                  ? 'Paiement de masse créé. Confirmez avec votre mpin.'
                                  : 'Paiement de masse en cours de traitement.',
            'created_at'       => $bulkPayment->created_at,
        ], 202);
    }

 public function confirm(Request $request, string $bulkId)
{
    $request->validate(['mpin' => 'required|string']);

    $aggregator  = $request->authenticated_aggregator;
    $environment = $request->environment ?? 'SANDBOX';

    // La confirmation par PIN n'existe qu'en production
    if ($environment !== 'PRODUCTION') {
        return response()->json([
            'error'   => 'CONFIRMATION_NOT_REQUIRED',
            'message' => 'La confirmation n\'est requise qu\'en production.',
        ], 422);
    }

    $bulkPayment = BulkPayment::where('bulk_id', $bulkId)
                               ->where('aggregator_id', $aggregator->id)
                               ->first();

    if (!$bulkPayment) {
        return response()->json([
            'error'   => 'BULK_PAYMENT_NOT_FOUND',
            'message' => 'Paiement de masse introuvable.',
        ], 404);
    }

    if ($bulkPayment->status !== 'PENDING') {
        return response()->json([
            'error'   => 'BULK_PAYMENT_ALREADY_PROCESSED',
            'message' => 'Ce paiement de masse a déjà été traité.',
            'status'  => $bulkPayment->status,
        ], 409);
    }

    // Expiration : 10 minutes après la création
    if ($bulkPayment->created_at->addMinutes(10)->isPast()) {
        return response()->json([
            'error'   => 'BULK_PAYMENT_EXPIRED',
            'message' => 'Ce paiement de masse a expiré. Créez-en un nouveau.',
        ], 410);
    }

    $aggregatorUser = DB::connection('mysql_money')
                        ->table('users')
                        ->where('id', $aggregator->user_id)
                        ->first(['id', 'mpin']);

    if (!$aggregatorUser) {
        return response()->json([
            'error'   => 'AGGREGATOR_USER_NOT_FOUND',
            'message' => 'Utilisateur agrégateur introuvable.',
        ], 404);
    }

    if (!$aggregatorUser->mpin) {
        return response()->json([
            'error'   => 'AGGREGATOR_MPIN_NOT_SET',
            'message' => 'PIN non configuré. Contactez Many.',
        ], 422);
    }

    if (!Hash::check($request->mpin, $aggregatorUser->mpin)) {
        return response()->json([
            'error'   => 'INVALID_MPIN',
            'message' => 'PIN incorrect.',
        ], 401);
    }

    // Réservation atomique : un seul appel peut passer de PENDING à PROCESSING
    $claimed = BulkPayment::where('bulk_id', $bulkId)
                          ->where('aggregator_id', $aggregator->id)
                          ->where('status', 'PENDING')
                          ->update(['status' => 'PROCESSING', 'confirmed_at' => now()]);

    if (!$claimed) {
        return response()->json([
            'error'   => 'BULK_PAYMENT_ALREADY_PROCESSED',
            'message' => 'Ce paiement de masse est déjà en cours de traitement.',
        ], 409);
    }

    $bulkPayment->refresh();

    dispatch(new ProcessBulkPaymentJob(
        bulkPayment: $bulkPayment,
        aggregator:  $aggregator,
        environment: 'PRODUCTION',
    ))->onConnection('mysql_money');

    return response()->json([
        'bulk_id'          => $bulkPayment->bulk_id,
        'status'           => 'PROCESSING',
        'total_recipients' => $bulkPayment->total_recipients,
        'message'          => 'Paiement de masse confirmé. Traitement en cours.',
    ]);
}

    public function show(Request $request, string $bulkId)
    {
        $aggregator  = $request->authenticated_aggregator;
        $bulkPayment = BulkPayment::where('bulk_id', $bulkId)
                                   ->where('aggregator_id', $aggregator->id)
                                   ->with('recipients')
                                   ->firstOrFail();

        return response()->json([
            'bulk_id'          => $bulkPayment->bulk_id,
            'sub_merchant_id'  => $bulkPayment->sub_merchant_id,
            'label'            => $bulkPayment->label,
            'status'           => $bulkPayment->status,
            'total_recipients' => $bulkPayment->total_recipients,
            'success_count'    => $bulkPayment->success_count,
            'failure_count'    => $bulkPayment->failure_count,
            'total_amount'     => $bulkPayment->total_amount,
            'fees'             => ['many_fee' => $bulkPayment->many_fee],
            'currency'         => $bulkPayment->currency,
            'environment'      => $bulkPayment->environment,
            'confirmed_at'     => $bulkPayment->confirmed_at,
            'completed_at'     => $bulkPayment->completed_at,
            'recipients'       => $bulkPayment->recipients->map(fn($r) => [
                'phone'          => $r->phone,
                'sender_id'      => $r->sender_id,
                'receiver_id'    => $r->receiver_id,
                'amount'         => $r->amount,
                'many_fee'       => $r->many_fee,
                'net_amount'     => $r->net_amount,
                'reference'      => $r->reference,
                'status'         => $r->status,
                'failure_reason' => $r->failure_reason,
            ]),
        ]);
    }
}