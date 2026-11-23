<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'nullable|in:android,ios,web',
        ]);

        $aggregator = $request->authenticated_aggregator;
        $environment = $request->environment ?? 'SANDBOX';

        // user_id désigne un utilisateur de la base de l'environnement courant :
        // écrire en sandbox dans mysql_money modifierait un autre utilisateur.
        $dbConnection = $environment === 'PRODUCTION' ? 'mysql_money' : 'mysql_sandbox';

        if ($aggregator->user_id) {
            DB::connection($dbConnection)
              ->table('users')
              ->where('id', $aggregator->user_id)
              ->update([
                  'fcm_token'  => $request->fcm_token,
                  'updated_at' => now(),
              ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token FCM enregistré avec succès.',
        ]);
    }
}