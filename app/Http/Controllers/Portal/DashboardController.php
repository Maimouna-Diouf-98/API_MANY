<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Auth\Models\AdminNotification;
use App\Domain\Auth\Models\ProductionRequest;
use App\Domain\Auth\Services\AggregatorStatsService;
use App\Domain\Auth\Services\PortalEnvironmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AggregatorStatsService   $stats,
        protected PortalEnvironmentService $environments,
    ) {}

    public function index()
    {
        $aggregator = auth('aggregator')->user();
        $ctx        = $this->environments->resolve($aggregator);
        $env        = $ctx['env'];

        $data = $this->stats->detail($env, $ctx['aggregator_id']);
        abort_if($data === null, 404);

        $activity = $this->stats->activity($env, $ctx['aggregator_id']);

        $productionRequest = ProductionRequest::where('aggregator_id', $aggregator->id)
                                              ->latest()
                                              ->first();

        return view('portal.dashboard.index', [
            'aggregator'         => $aggregator,                 // compte de connexion (sandbox)
            'envAggregator'      => $data['aggregator'],         // ligne de l'environnement affiché
            'env'                => $env,
            'hasProduction'      => $ctx['has_production'],
            'data'               => $data,
            'activity'           => $activity,
            'activeSubMerchants' => $data['sub_merchants']->where('status', 'ACTIVE')->count(),
            'productionRequest'  => $productionRequest,
        ]);
    }

    /** Bascule Sandbox <-> Production (uniquement si le compte production existe). */
    public function switchEnvironment(Request $request)
    {
        $data = $request->validate(['env' => 'required|in:sandbox,production']);

        if (!$this->environments->switchTo(auth('aggregator')->user(), $data['env'])) {
            return redirect()->route('portal.dashboard')
                ->with('error', "Votre compte n'est pas encore activé en production.");
        }

        return redirect()->route('portal.dashboard');
    }

    public function requestProduction(Request $request)
    {
        $request->validate(['message' => 'nullable|string|max:1000']);

        $aggregator = auth('aggregator')->user();

        if ($aggregator->production_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est déjà activé en production.',
            ], 409);
        }

        $pending = ProductionRequest::where('aggregator_id', $aggregator->id)
                                    ->where('status', 'PENDING')->exists();

        if ($pending) {
            return response()->json([
                'success' => false,
                'message' => 'Une demande est déjà en cours de traitement.',
            ], 409);
        }

        $productionRequest = ProductionRequest::create([
            'aggregator_id' => $aggregator->id,
            'status'        => 'PENDING',
            'message'       => $request->message,
        ]);

        AdminNotification::create([
            'type'       => 'PRODUCTION_REQUEST',
            'title'      => 'Demande de passage en production',
            'body'       => $aggregator->legal_name . ' (' . $aggregator->email . ')',
            'related_id' => $productionRequest->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Demande envoyée. L'équipe Many l'examinera sous 24-48h.",
        ]);
    }
}
