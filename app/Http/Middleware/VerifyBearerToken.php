<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Domain\Auth\Models\Application;
use App\Domain\Auth\Models\Aggregator;
use Symfony\Component\HttpFoundation\Response;

class VerifyBearerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error'   => 'AUTH_INVALID_TOKEN',
                'message' => 'Token Bearer manquant.',
            ], 401);
        }

        $token = substr($authHeader, 7);

        // Chercher le token sur la connexion active (sandbox ou money)
        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken || $accessToken->expires_at?->isPast()) {
            return response()->json([
                'error'   => 'AUTH_INVALID_TOKEN',
                'message' => 'Token invalide ou expiré.',
            ], 401);
        }

        // Récupérer l'application
        $application = Application::find($accessToken->tokenable_id);

        if (!$application || !$application->is_active) {
            return response()->json([
                'error'   => 'AUTH_INVALID_TOKEN',
                'message' => 'Application inactive ou introuvable.',
            ], 401);
        }

        // Récupérer l'agrégateur
        $aggregator = Aggregator::find($application->aggregator_id);

        if (!$aggregator) {
            return response()->json([
                'error'   => 'AUTH_INVALID_TOKEN',
                'message' => 'Agrégateur introuvable.',
            ], 401);
        }

        if (in_array($aggregator->status, ['SUSPENDED', 'CLOSED'])) {
            return response()->json([
                'error'   => 'ACCOUNT_SUSPENDED',
                'message' => 'Compte suspendu ou clôturé.',
            ], 403);
        }

        // Mettre à jour last_used_at
        $application->update(['last_used_at' => now()]);

        // Injecter dans la requête
        $request->merge([
            'authenticated_application' => $application,
            'authenticated_aggregator'  => $aggregator,
        ]);

        return $next($request);
    }
}