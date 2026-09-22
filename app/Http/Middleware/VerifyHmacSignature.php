<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyHmacSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Many-Timestamp');
        $signature = $request->header('X-Many-Signature');

        if (!$timestamp || !$signature) {
            return response()->json([
                'error'   => 'AUTH_INVALID_SIGNATURE',
                'message' => 'Headers X-Many-Timestamp ou X-Many-Signature manquants.'
            ], 401);
        }


        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json([
                'error'   => 'AUTH_EXPIRED_TIMESTAMP',
                'message' => 'Horodatage de la requête hors de la fenêtre de tolérance de 5 minutes.'
            ], 401);
        }

        $application = $request->authenticated_application;

        if (!$application) {
            return response()->json([
                'error'   => 'AUTH_INVALID_TOKEN',
                'message' => 'Application non authentifiée.'
            ], 401);
        }

        $rawBody       = $request->getContent();
        $expectedSign  = hash_hmac('sha256', $rawBody, $application->client_secret);

        if (!hash_equals($expectedSign, $signature)) {
            return response()->json([
                'error'   => 'AUTH_INVALID_SIGNATURE',
                'message' => 'Signature HMAC invalide.'
            ], 401);
        }

        return $next($request);
    }
}