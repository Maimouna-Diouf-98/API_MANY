<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Application;
use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Auth\Services\ApplicationService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(protected ApplicationService $service) {}

    public function index()
    {
        $applications = Application::where('aggregator_id', auth('aggregator')->id())
                                   ->withCount('subMerchants')
                                   ->latest()
                                   ->get();

        return view('portal.applications.index', compact('applications'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'webhook_url' => 'required|url',
        ]);

        $result = $this->service->create(
            aggregatorId: auth('aggregator')->id(),
            name:         $request->name,
            webhookUrl:   $request->webhook_url,
        );

        return redirect()
            ->route('portal.applications.show', $result['application']->id)
            ->with('plain_secret', $result['plain_secret'])
            ->with('success', 'Application créée. Notez bien votre client_secret.');
    }

    public function show(string $id)
    {
        $application = Application::where('id', $id)
                                  ->where('aggregator_id', auth('aggregator')->id())
                                  ->firstOrFail();

        $subMerchants = SubMerchant::where('application_id', $id)
                                   ->latest()
                                   ->get();

        $plainSecret = session('plain_secret');

        return view('portal.applications.show',
                    compact('application', 'subMerchants', 'plainSecret'));
    }

    public function destroy(string $id)
    {
        $application = Application::where('id', $id)
                                  ->where('aggregator_id', auth('aggregator')->id())
                                  ->firstOrFail();

        $this->service->delete($application);

        return redirect()
            ->route('portal.applications.index')
            ->with('success', 'Application supprimée.');
    }
}