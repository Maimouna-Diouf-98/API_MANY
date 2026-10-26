<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Auth\Services\AggregatorStatsService;
use App\Domain\Auth\Services\SandboxAggregatorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AggregatorController extends Controller
{
    public function __construct(
        protected AggregatorStatsService   $stats,
        protected SandboxAggregatorService $sandboxService,
    ) {}

    public function index(string $env)
    {
        $data = $this->stats->list($env);

        return view('admin.aggregators.index', [
            'env'    => $env,
            'rows'   => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    public function show(string $env, string $id)
    {
        $data = $this->stats->detail($env, $id);

        abort_if($data === null, 404);

        return view('admin.aggregators.show', array_merge($data, ['env' => $env]));
    }

    public function create()
    {
        return view('admin.aggregators.create', [
            'canCreate' => auth('admin')->user()->canApproveKyb(),
        ]);
    }

    public function store(Request $request)
    {
        // Même permission que la validation des demandes de production
        if (!auth('admin')->user()->canApproveKyb()) {
            return back()->with('error', "Vous n'avez pas l'autorisation de créer un agrégateur.");
        }

        $data = $request->validate([
            'legal_name'  => 'required|string|max:255',
            'trade_name'  => 'nullable|string|max:255',
            'email'       => 'required|email|max:255|unique:mysql_sandbox.aggregators,email',
            'phone'       => ['required', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'webhook_url' => 'required|url|max:255',
        ], [
            'email.unique' => 'Un agrégateur existe déjà avec cet email.',
            'phone.regex'  => 'Numéro invalide (ex : +221 77 123 45 67).',
        ]);

        try {
            $credentials = $this->sandboxService->create($data, auth('admin')->user());
        } catch (RuntimeException $e) {
            return back()->withInput()
                         ->with('error', SandboxAggregatorService::errorMessage($e->getMessage()));
        } catch (\Throwable $e) {
            Log::error('Création agrégateur sandbox échouée : ' . $e->getMessage());
            return back()->withInput()
                         ->with('error', "Échec de la création. Aucune donnée n'a été enregistrée.");
        }

        $mailSent = $this->sandboxService->sendCredentials($credentials);

        $redirect = redirect()->route('admin.aggregators.index', 'sandbox')
            ->with('success', $mailSent
                ? "Agrégateur créé. Un email avec ses accès et un lien de vérification a été envoyé à {$credentials['email']}."
                : "Agrégateur créé, mais l'email n'a pas pu être envoyé. Transmettez-lui les accès et le lien ci-dessous.");

        // Mot de passe affiché UNE seule fois, uniquement si l'email a échoué
        if (!$mailSent) {
            $redirect->with('credentials', $credentials);
        }

        return $redirect;
    }

    /** Renvoie les accès (nouveau mot de passe + nouveau lien) tant que l'email n'est pas vérifié. */
    public function resendAccess(string $id)
    {
        if (!auth('admin')->user()->canApproveKyb()) {
            return back()->with('error', "Vous n'avez pas l'autorisation de renvoyer des accès.");
        }

        try {
            $credentials = $this->sandboxService->resendAccess($id, auth('admin')->user());
        } catch (RuntimeException $e) {
            return back()->with('error', SandboxAggregatorService::errorMessage($e->getMessage()));
        }

        $mailSent = $this->sandboxService->sendCredentials($credentials);

        $redirect = redirect()->route('admin.aggregators.show', ['sandbox', $id])
            ->with('success', $mailSent
                ? "Nouveaux accès envoyés à {$credentials['email']}. L'ancien mot de passe ne fonctionne plus."
                : "Nouveaux accès générés, mais l'email n'a pas pu être envoyé. Transmettez-les ci-dessous.");

        if (!$mailSent) {
            $redirect->with('credentials', $credentials);
        }

        return $redirect;
    }
}
