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

        // Mettre à jour le fcm_token du user dans mysql_money
        if ($aggregator->user_id) {
            DB::connection('mysql_money')
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