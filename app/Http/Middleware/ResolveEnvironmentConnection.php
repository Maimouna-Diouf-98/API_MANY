<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveEnvironmentConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->header('X-Many-Client-Id', '');

        if (str_starts_with($clientId, 'agg_sandbox_')) {
            DB::setDefaultConnection('mysql_sandbox');
            $request->merge(['environment' => 'SANDBOX']);
        } elseif (str_starts_with($clientId, 'agg_live_')) {
            DB::setDefaultConnection('mysql_money');
            $request->merge(['environment' => 'PRODUCTION']);
        } else {
            return response()->json([
                'error'   => 'AUTH_INVALID_CLIENT',
                'message' => 'Header X-Many-Client-Id manquant ou invalide.',
            ], 401);
        }

        return $next($request);
    }
}