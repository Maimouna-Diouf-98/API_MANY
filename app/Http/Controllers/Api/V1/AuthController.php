<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
   public function token(Request $request)

{
    Log::info('APP_KEY utilisée: ' . substr(config('app.key'), 0, 15));
    $request->validate([
        'client_id'     => 'required|string',
        'client_secret' => 'required|string',
        'grant_type'    => 'required|in:client_credentials',
    ]);

    // La connexion est déjà définie par ResolveEnvironmentConnection
    $application = \App\Domain\Auth\Models\Application::where('client_id', $request->client_id)
                                                       ->where('is_active', true)
                                                       ->first();

    if (!$application) {
        return response()->json([
            'error'   => 'AUTH_INVALID_CLIENT',
            'message' => 'client_id invalide ou application inactive.',
        ], 401);
    }

    // Comparer le secret (gérer les deux cas : sérialisé et non sérialisé)
    $storedSecret = $application->client_secret;

    // Si le secret stocké est sérialisé, le désérialiser
    if (str_starts_with($storedSecret, 's:')) {
        $storedSecret = unserialize($storedSecret);
    }

    if ($request->client_secret !== $storedSecret) {
        return response()->json([
            'error'   => 'AUTH_INVALID_CLIENT',
            'message' => 'client_secret invalide.',
        ], 401);
    }

    $application->tokens()->delete();

    $token = $application->createToken(
        'api_token',
        ['*'],
        now()->addHour()
    );

    $application->update(['last_used_at' => now()]);

    return response()->json([
        'access_token' => $token->plainTextToken,
        'token_type'   => 'Bearer',
        'expires_in'   => 3600,
        'environment'  => $application->environment,
    ]);
}
}