<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Auth\Services\PortalDataService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RuntimeException;

class ApplicationController extends Controller
{
    public function __construct(protected PortalDataService $data) {}

    private function ctx(): array
    {
        return $this->data->context(auth('aggregator')->user());
    }

    public function index()
    {
        $ctx = $this->ctx();

        return view('portal.applications.index', [
            'env'          => $ctx['env'],
            'applications' => $this->data->applications($ctx),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'webhook_url' => 'required|url|max:255',
        ]);

        try {
            $result = $this->data->createApplication($this->ctx(), $validated['name'], $validated['webhook_url']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', PortalDataService::errorMessage($e->getMessage()));
        }

        // Le secret n'est affiché qu'une seule fois, sur la page de l'application
        return redirect()->route('portal.applications.show', $result['application']->id)
            ->with('success', 'Application créée. Copiez votre client_secret maintenant.')
            ->with('plain_secret', $result['secret']);
    }

    public function show(string $id)
    {
        $ctx = $this->ctx();
        $app = $this->data->application($ctx, $id);

        abort_if(!$app, 404);

        return view('portal.applications.show', [
            'env'          => $ctx['env'],
            'application'  => $app,
            'subMerchants' => $this->data->subMerchantsOfApplication($ctx, $id),
        ]);
    }

    public function regenerateSecret(string $id)
    {
        try {
            $secret = $this->data->regenerateSecret($this->ctx(), $id);
        } catch (RuntimeException $e) {
            return back()->with('error', PortalDataService::errorMessage($e->getMessage()));
        }

        return redirect()->route('portal.applications.show', $id)
            ->with('success', "Nouveau client_secret généré. L'ancien ne fonctionne plus et les tokens en cours sont révoqués.")
            ->with('plain_secret', $secret);
    }

    public function destroy(string $id)
    {
        try {
            $result = $this->data->removeApplication($this->ctx(), $id);
        } catch (RuntimeException $e) {
            return back()->with('error', PortalDataService::errorMessage($e->getMessage()));
        }

        if ($result === 'deactivated') {
            return redirect()->route('portal.applications.show', $id)
                ->with('success', "Application désactivée : elle ne peut plus s'authentifier. Son historique est conservé.");
        }

        return redirect()->route('portal.applications.index')->with('success', 'Application supprimée.');
    }

    public function reactivate(string $id)
    {
        try {
            $this->data->reactivateApplication($this->ctx(), $id);
        } catch (RuntimeException $e) {
            return back()->with('error', PortalDataService::errorMessage($e->getMessage()));
        }

        return back()->with('success', 'Application réactivée.');
    }
}
