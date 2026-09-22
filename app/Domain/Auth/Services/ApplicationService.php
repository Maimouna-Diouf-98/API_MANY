<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Application;
use Illuminate\Support\Str;

class ApplicationService
{
    public function create(
        string $aggregatorId,
        string $name,
        string $webhookUrl
    ): array {
        $plainSecret = Str::random(48);
        $clientId    = 'agg_sandbox_' . Str::random(12);

        // Utiliser le modèle Eloquent pour que le cast 'encrypted'
        // fonctionne correctement (pas de double sérialisation)
        $application = Application::create([
            'aggregator_id' => $aggregatorId,
            'name'          => $name,
            'client_id'     => $clientId,
            'client_secret' => $plainSecret,
            'environment'   => 'SANDBOX',
            'webhook_url'   => $webhookUrl,
            'is_active'     => true,
        ]);

        return [
            'application'  => $application,
            'plain_secret' => $plainSecret,
        ];
    }

    public function delete(Application $application): void
    {
        $application->tokens()->delete();
        $application->delete();
    }
}