<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\SubMerchants\Services\SubMerchantService;
use App\Domain\Auth\Models\Application;
use Illuminate\Http\Request;

class SubMerchantController extends Controller
{
    public function __construct(protected SubMerchantService $service) {}

    public function show(string $appId, string $smId)
    {
        // Vérifier que l'application appartient à l'agrégateur
        $application = Application::where('id', $appId)
                                  ->where('aggregator_id', auth('aggregator')->id())
                                  ->firstOrFail();

        // Vérifier que le sous-marchand appartient à cette application
        $subMerchant = SubMerchant::where('id', $smId)
                                  ->where('application_id', $appId)
                                  ->firstOrFail();

        return view('portal.sub-merchants.show',
                    compact('application', 'subMerchant'));
    }

  public function store(Request $request, string $appId)
{
    $application = Application::where('id', $appId)
                              ->where('aggregator_id', auth('aggregator')->id())
                              ->firstOrFail();

    $request->validate([
        'legal_name'    => 'required|string|max:255',
        'business_type' => 'required|string|max:255',
        'address'       => 'required|string|max:500',
        'webhook_url'   => 'required|url',
    ]);

    $this->service->create($request->all(), $appId);

    return redirect()
        ->route('portal.applications.show', $appId)
        ->with('success', 'Sous-marchand créé avec succès.');
}

    public function activate(string $appId, string $smId)
    {
        $subMerchant = $this->findSubMerchant($appId, $smId);
        $this->service->activate($subMerchant);
        return back()->with('success', 'Sous-marchand activé.');
    }

    public function suspend(string $appId, string $smId)
    {
        $subMerchant = $this->findSubMerchant($appId, $smId);
        $this->service->suspend($subMerchant);
        return back()->with('success', 'Sous-marchand suspendu.');
    }

    public function close(string $appId, string $smId)
    {
        $subMerchant = $this->findSubMerchant($appId, $smId);
        $this->service->close($subMerchant);
        return back()->with('success', 'Sous-marchand clôturé.');
    }

    private function findSubMerchant(string $appId, string $smId): SubMerchant
    {
        Application::where('id', $appId)
                   ->where('aggregator_id', auth('aggregator')->id())
                   ->firstOrFail();

        return SubMerchant::where('id', $smId)
                          ->where('application_id', $appId)
                          ->firstOrFail();
    }
}