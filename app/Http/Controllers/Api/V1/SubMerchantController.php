<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\SubMerchants\Services\SubMerchantService;
use App\Domain\Auth\Models\Application;
use Illuminate\Http\Request;

class SubMerchantController extends Controller
{
    public function __construct(protected SubMerchantService $service) {}

    private function getApplicationIds($aggregator): \Illuminate\Support\Collection
    {
        return Application::where('aggregator_id', $aggregator->id)->pluck('id');
    }

    public function index(Request $request)
    {
        $aggregator     = $request->authenticated_aggregator;
        $applicationIds = $this->getApplicationIds($aggregator);

        $subMerchants = SubMerchant::whereIn('application_id', $applicationIds)
                                   ->when($request->status, fn($q) =>
                                       $q->where('status', $request->status))
                                   ->latest()
                                   ->paginate($request->get('limit', 20));

        return response()->json($subMerchants);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|uuid|exists:applications,id',
            'legal_name'     => 'required|string|max:255',
            'business_type'  => 'required|string|max:255',
            'address'        => 'required|string|max:500',
            'webhook_url'    => 'required|url',
        ]);

        $aggregator  = $request->authenticated_aggregator;

        $application = Application::where('id', $validated['application_id'])
                                  ->where('aggregator_id', $aggregator->id)
                                  ->first();

        if (!$application) {
            return response()->json([
                'error'   => 'APPLICATION_NOT_FOUND',
                'message' => 'Cette application n\'appartient pas à votre compte.',
            ], 404);
        }

        $subMerchant = $this->service->create($validated, $validated['application_id']);

        return response()->json([
            'sub_merchant_id'    => $subMerchant->id,
            'application_id'     => $subMerchant->application_id,
            'legal_name'         => $subMerchant->legal_name,
            'business_type'      => $subMerchant->business_type,
            'address'            => $subMerchant->address,
            'external_reference' => $subMerchant->external_reference,
            'status'             => $subMerchant->status,
            'created_at'         => $subMerchant->created_at,
        ], 201);
    }

    public function show(Request $request, string $id)
    {
        $aggregator     = $request->authenticated_aggregator;
        $applicationIds = $this->getApplicationIds($aggregator);

        $subMerchant = SubMerchant::whereIn('application_id', $applicationIds)
                                  ->where('id', $id)
                                  ->firstOrFail();

        return response()->json($subMerchant);
    }

    public function activate(Request $request, string $id)
    {
        $subMerchant = $this->findSubMerchant($request, $id);
        if (in_array($subMerchant->status, ['CLOSED'])) {
            return response()->json([
                'error'   => 'INVALID_STATUS',
                'message' => 'Un sous-marchand clôturé ne peut pas être réactivé.',
            ], 422);
        }
        $this->service->activate($subMerchant);
        return response()->json(['sub_merchant_id' => $subMerchant->id, 'status' => 'ACTIVE']);
    }

    public function suspend(Request $request, string $id)
    {
        $subMerchant = $this->findSubMerchant($request, $id);
        if ($subMerchant->status !== 'ACTIVE') {
            return response()->json([
                'error'   => 'INVALID_STATUS',
                'message' => 'Seul un sous-marchand actif peut être suspendu.',
            ], 422);
        }
        $this->service->suspend($subMerchant);
        return response()->json(['sub_merchant_id' => $subMerchant->id, 'status' => 'SUSPENDED']);
    }

    public function close(Request $request, string $id)
    {
        $subMerchant = $this->findSubMerchant($request, $id);
        if ($subMerchant->status === 'CLOSED') {
            return response()->json([
                'error'   => 'INVALID_STATUS',
                'message' => 'Ce sous-marchand est déjà clôturé.',
            ], 422);
        }
        $this->service->close($subMerchant);
        return response()->json(['sub_merchant_id' => $subMerchant->id, 'status' => 'CLOSED']);
    }

    private function findSubMerchant(Request $request, string $id): SubMerchant
    {
        $applicationIds = $this->getApplicationIds($request->authenticated_aggregator);
        return SubMerchant::whereIn('application_id', $applicationIds)
                          ->where('id', $id)
                          ->firstOrFail();
    }
}