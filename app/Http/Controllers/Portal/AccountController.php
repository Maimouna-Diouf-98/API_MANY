<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Auth\Services\PortalEnvironmentService;
use App\Http\Controllers\Controller;

class AccountController extends Controller
{
    /** Affiche la page. Le changement lui-même passe par portal.update.password (AuthController@updatePassword). */
    public function showPassword(PortalEnvironmentService $environments)
    {
        $aggregator = auth('aggregator')->user();

        return view('portal.account.password', [
            'aggregator'    => $aggregator,
            'hasProduction' => $environments->productionAggregator($aggregator) !== null,
        ]);
    }
}
