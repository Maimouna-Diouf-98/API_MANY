<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Models\AdminNotification;
use App\Domain\Auth\Models\ProductionRequest;
use App\Domain\Auth\Services\ProductionActivationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProductionRequestController extends Controller
{
    public function __construct(protected ProductionActivationService $service) {}

    public function show(string $id)
    {
        $productionRequest = ProductionRequest::findOrFail($id);
        $aggregator        = $productionRequest->aggregator;

        // Ouvrir la demande = notification lue
        AdminNotification::where('type', 'PRODUCTION_REQUEST')
            ->where('related_id', $productionRequest->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Résumé des tests sandbox pour aider l'admin à décider
        $usage = ['collections' => 0, 'disbursements' => 0, 'bulks' => 0, 'last_activity' => null];

        if ($aggregator) {
            $sb = DB::connection('mysql_sandbox');

            $usage['collections']   = $sb->table('api_transactions')
                ->where('aggregator_id', $aggregator->id)->where('status', 'SUCCESS')->count();
            $usage['disbursements'] = $sb->table('api_disbursements')
                ->where('aggregator_id', $aggregator->id)->where('status', 'SUCCESS')->count();
            $usage['bulks']         = $sb->table('api_bulk_payments')
                ->where('aggregator_id', $aggregator->id)->where('status', 'COMPLETED')->count();
            $usage['apps']          = $sb->table('applications')
                ->where('aggregator_id', $aggregator->id)->count();
            $usage['last_activity'] = $sb->table('api_transactions')
                ->where('aggregator_id', $aggregator->id)->max('created_at');
        }

        $alreadyInProduction = $aggregator
            ? DB::connection('mysql_money')->table('aggregators')
                ->where('email', $aggregator->email)->exists()
            : false;

        $canDecide = auth('admin')->user()->canApproveKyb();

        return view('admin.requests.show', compact(
            'productionRequest', 'aggregator', 'usage', 'alreadyInProduction', 'canDecide'
        ));
    }

    public function approve(Request $request, string $id)
    {
        if ($denied = $this->denyUnlessAllowed()) {
            return $denied;
        }

        $data = $request->validate([
            'commission' => 'required|numeric|min:0|max:100',
        ]);

        $productionRequest = ProductionRequest::findOrFail($id);

        try {
            $credentials = $this->service->approve(
                $productionRequest,
                auth('admin')->user(),
                (float) $data['commission']
            );
        } catch (RuntimeException $e) {
            return back()->with('error', ProductionActivationService::errorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            Log::error('Approbation production échouée : ' . $e->getMessage());
            return back()->with('error', "Échec de l'activation. Aucune donnée n'a été créée côté production.");
        }

        $mailSent = $this->service->sendCredentials($credentials);

        $redirect = redirect()->route('admin.requests.show', $productionRequest->id)
            ->with('success', $mailSent
                ? "Agrégateur activé en production. Les credentials ont été envoyés à {$credentials['email']}."
                : "Agrégateur activé, mais l'email n'a pas pu être envoyé. Transmettez les credentials ci-dessous.");

        // Credentials affichés UNE seule fois, uniquement si l'email a échoué
        if (!$mailSent) {
            $redirect->with('credentials', $credentials);
        }

        return $redirect;
    }

    public function markApproved(Request $request, string $id)
    {
        if ($denied = $this->denyUnlessAllowed()) {
            return $denied;
        }

        $productionRequest = ProductionRequest::findOrFail($id);

        try {
            $this->service->markApprovedIfAlreadyActive($productionRequest, auth('admin')->user());
        } catch (RuntimeException $e) {
            return back()->with('error', ProductionActivationService::errorMessage($e->getMessage()));
        }

        return redirect()->route('admin.requests.show', $productionRequest->id)
            ->with('success', 'Demande marquée comme approuvée (agrégateur déjà actif en production).');
    }

    public function reject(Request $request, string $id)
    {
        if ($denied = $this->denyUnlessAllowed()) {
            return $denied;
        }

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:1000',
        ]);

        $productionRequest = ProductionRequest::findOrFail($id);

        try {
            $this->service->reject($productionRequest, auth('admin')->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', ProductionActivationService::errorMessage($e->getMessage()));
        }

        return redirect()->route('admin.requests.show', $productionRequest->id)
            ->with('success', 'Demande refusée. Le motif est visible par l\'agrégateur.');
    }

    /**
     * Valider une demande de production nécessite la permission approve_kyc
     * (Admin::canApproveKyb). Remplacez par une autre méthode si vous créez
     * une permission dédiée.
     */
    private function denyUnlessAllowed()
    {
        if (!auth('admin')->user()->canApproveKyb()) {
            return back()->with('error',
                "Vous n'avez pas l'autorisation de valider les demandes de production.");
        }

        return null;
    }
}
