<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Disbursements\Models\Disbursement;
use App\Domain\Disbursements\Services\DisbursementService;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Auth\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisbursementController extends Controller
{
    public function __construct(protected DisbursementService $service) {}

    public function index(Request $request)
    {
        $aggregator    = $request->authenticated_aggregator;
        $disbursements = Disbursement::where('aggregator_id', $aggregator->id)
                                     ->when($request->status, fn($q) =>
                                         $q->where('status', $request->status))
                                     ->latest()
                                     ->paginate($request->get('limit', 20));

        return response()->json($disbursements);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sub_merchant_id' => 'required|string|exists:sub_merchants,id',
            'recipient_phone' => 'required|string',
            'amount'          => 'required|integer|min:100',
            'currency'        => 'required|in:XOF',
            'reference'       => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
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

        // Sandbox : numéro de test uniquement
        if ($environment === 'SANDBOX') {
            $testNumbers = array_map(
                fn($i) => '+221770000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                range(1, 99)
            );
            if (!in_array($validated['recipient_phone'], $testNumbers)) {
                return response()->json([
                    'error'   => 'SANDBOX_INVALID_RECIPIENT',
                    'message' => 'En sandbox, utilisez un numéro de test (+221770000001 à +221770000099).',
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

        try {
            $disbursement = $this->service->create(
                $validated,
                $aggregator,
                $environment,
                $application->id
            );

            return response()->json([
                'disbursement_id' => $disbursement->disbursement_id,
                'sub_merchant_id' => $disbursement->sub_merchant_id,
                'aggregator_id'   => $disbursement->aggregator_id,
                'sender_id'       => $disbursement->sender_id,
                'receiver_id'     => $disbursement->receiver_id,
                'recipient_phone' => $disbursement->recipient_phone,
                'amount'          => $disbursement->amount,
                'fees'            => ['many_fee' => $disbursement->many_fee],
                'net_amount'      => $disbursement->net_amount,
                'currency'        => $disbursement->currency,
                'status'          => $disbursement->status,
                'reference'       => $disbursement->reference,
                'environment'     => $disbursement->environment,
                'message'         => $environment === 'PRODUCTION'
                                     ? 'Disbursement en attente de confirmation. Confirmez avec votre mpin.'
                                     : 'Disbursement en cours de traitement.',
                'created_at'      => $disbursement->created_at,
            ], 201);

        } catch (\Exception $e) {
             Log::error('DISBURSEMENT STORE ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

            $errorMap = [
                'INSUFFICIENT_FUNDS'         => ['code' => 'INSUFFICIENT_FUNDS',         'message' => 'Solde insuffisant.',                       'status' => 422],
                'RECIPIENT_NOT_FOUND'        => ['code' => 'RECIPIENT_NOT_FOUND',        'message' => 'Ce numéro n\'a pas de compte Many.',       'status' => 404],
                'RECIPIENT_WALLET_NOT_FOUND' => ['code' => 'RECIPIENT_WALLET_NOT_FOUND', 'message' => 'Portefeuille destinataire introuvable.',    'status' => 422],
            ];

            $error = $errorMap[$e->getMessage()] ?? [
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Erreur interne.',
                'status'  => 500,
            ];

            return response()->json([
                'error'   => $error['code'],
                'message' => $error['message'],
            ], $error['status']);
        }
    }

    public function confirm(Request $request, string $disbursementId)
    {
        $request->validate([
            'mpin' => 'required|string',
        ]);

        $aggregator = $request->authenticated_aggregator;

        $disbursement = Disbursement::where('disbursement_id', $disbursementId)
                                    ->where('aggregator_id', $aggregator->id)
                                    ->first();

        if (!$disbursement) {
            return response()->json([
                'error'   => 'DISBURSEMENT_NOT_FOUND',
                'message' => 'Disbursement introuvable.',
            ], 404);
        }

        try {
            $disbursement = $this->service->confirmDisbursement(
                $disbursement,
                $request->mpin,
                $aggregator
            );

            return response()->json([
                'disbursement_id' => $disbursement->disbursement_id,
                'status'          => 'PROCESSING',
                'message'         => 'Disbursement confirmé. Traitement en cours.',
            ]);

        } catch (\Exception $e) {
            $errorMap = [
                'DISBURSEMENT_ALREADY_PROCESSED' => ['code' => 'DISBURSEMENT_ALREADY_PROCESSED', 'message' => 'Ce disbursement a déjà été traité.',  'status' => 409],
                'AGGREGATOR_USER_NOT_FOUND'      => ['code' => 'AGGREGATOR_USER_NOT_FOUND',      'message' => 'Utilisateur agrégateur introuvable.', 'status' => 404],
                'AGGREGATOR_MPIN_NOT_SET'        => ['code' => 'AGGREGATOR_MPIN_NOT_SET',        'message' => 'PIN non configuré.',                  'status' => 422],
                'INVALID_MPIN'                   => ['code' => 'INVALID_MPIN',                   'message' => 'PIN incorrect.',                       'status' => 401],
            ];

            $error = $errorMap[$e->getMessage()] ?? [
                'code'    => 'INTERNAL_ERROR',
                'message' => 'Erreur interne.',
                'status'  => 500,
            ];

            return response()->json([
                'error'   => $error['code'],
                'message' => $error['message'],
            ], $error['status']);
        }
    }

    public function show(Request $request, string $disbursementId)
    {
        $aggregator   = $request->authenticated_aggregator;
        $disbursement = Disbursement::where('disbursement_id', $disbursementId)
                                    ->where('aggregator_id', $aggregator->id)
                                    ->firstOrFail();

        return response()->json([
            'disbursement_id' => $disbursement->disbursement_id,
            'sub_merchant_id' => $disbursement->sub_merchant_id,
            'aggregator_id'   => $disbursement->aggregator_id,
            'sender_id'       => $disbursement->sender_id,
            'receiver_id'     => $disbursement->receiver_id,
            'status'          => $disbursement->status,
            'recipient_phone' => $disbursement->recipient_phone,
            'amount'          => $disbursement->amount,
            'fees'            => ['many_fee' => $disbursement->many_fee],
            'net_amount'      => $disbursement->net_amount,
            'currency'        => $disbursement->currency,
            'reference'       => $disbursement->reference,
            'idempotency_key' => $disbursement->idempotency_key,
            'failure_reason'  => $disbursement->failure_reason,
            'environment'     => $disbursement->environment,
            'confirmed_at'    => $disbursement->confirmed_at,
            'created_at'      => $disbursement->created_at,
            'completed_at'    => $disbursement->completed_at,
        ]);
    }
}