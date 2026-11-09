<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Auth\Services\PortalDataService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RuntimeException;

class SubMerchantController extends Controller
{
    public function __construct(protected PortalDataService $data) {}

    private function ctx(): array
    {
        return $this->data->context(auth('aggregator')->user());
    }

    public function store(Request $request, string $appId)
    {
        $validated = $request->validate([
            'legal_name'    => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'address'       => 'required|string|max:500',
            'webhook_url'   => 'required|url|max:255',
        ]);

        try {
            $id = $this->data->createSubMerchant($this->ctx(), $appId, $validated);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', PortalDataService::errorMessage($e->getMessage()));
        }

        return redirect()->route('portal.sub-merchants.show', [$appId, $id])
            ->with('success', 'Sous-marchand créé. Activez-le pour pouvoir recevoir des opérations.');
    }

    public function show(string $appId, string $smId)
    {
        $ctx = $this->ctx();
        $sm  = $this->data->subMerchant($ctx, $appId, $smId);

        abort_if(!$sm, 404);

        return view('portal.sub-merchants.show', [
            'env'        => $ctx['env'],
            'appId'      => $appId,
            'sm'         => $sm,
            'totals'     => $this->data->subMerchantTotals($ctx, $smId),
            'operations' => $this->data->subMerchantOperations($ctx, $smId),
        ]);
    }

    public function activate(string $appId, string $smId)
    {
        return $this->change($appId, $smId, 'activate', 'Sous-marchand activé.');
    }

    public function suspend(string $appId, string $smId)
    {
        return $this->change($appId, $smId, 'suspend', 'Sous-marchand suspendu.');
    }

    public function close(string $appId, string $smId)
    {
        return $this->change($appId, $smId, 'close', 'Sous-marchand clôturé définitivement.');
    }

    private function change(string $appId, string $smId, string $action, string $message)
    {
        try {
            $this->data->changeSubMerchantStatus($this->ctx(), $appId, $smId, $action);
        } catch (RuntimeException $e) {
            return back()->with('error', PortalDataService::errorMessage($e->getMessage()));
        }

        return back()->with('success', $message);
    }
}
