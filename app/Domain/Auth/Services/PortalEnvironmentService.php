<?php

namespace App\Domain\Auth\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Facades\Cache;

/**
 * Détermine si le portail affiche les données sandbox ou production.
 *
 * La connexion au portail se fait toujours sur mysql_sandbox. Une fois activé en production,
 * l'agrégateur a AUSSI une ligne dans mysql_money.aggregators (même email, id différent) :
 * ses applications, sous-marchands et opérations de production sont rattachés à cet id-là.
 */
class PortalEnvironmentService
{
    public const SESSION_KEY = 'portal_env';

    private array $memo = [];

    /** Ligne mysql_money.aggregators correspondant à l'agrégateur connecté, ou null. */
  

public function productionAggregator(object $aggregator): ?object
{
    if (array_key_exists($aggregator->email, $this->memo)) {
        return $this->memo[$aggregator->email];
    }

    // Seul un résultat positif est mis en cache : sinon un agrégateur tout juste
    // activé en production resterait bloqué en sandbox jusqu'à 5 minutes.
    $key = 'portal.prodagg.' . sha1($aggregator->email);
    $row = Cache::get($key);

    if (!$row) {
        try {
            $row = DB::connection('mysql_money')->table('aggregators')
                     ->where('email', $aggregator->email)->first();
        } catch (Throwable $e) {
            Log::error('Portail : lecture agrégateur production impossible : ' . $e->getMessage());
            $row = null;
        }

        if ($row) {
            Cache::put($key, $row, 300);
        }
    }

    return $this->memo[$aggregator->email] = $row ?: null;
}

    /**
     * @return array{env:string, aggregator_id:string, has_production:bool}
     */
    public function resolve(object $aggregator): array
    {
        $production = $this->productionAggregator($aggregator);

        if (!$production) {
            return ['env' => 'sandbox', 'aggregator_id' => $aggregator->id, 'has_production' => false];
        }

        $wanted = session(self::SESSION_KEY);
        $env    = in_array($wanted, ['sandbox', 'production'], true) ? $wanted : 'production';

        return [
            'env'            => $env,
            'aggregator_id'  => $env === 'production' ? $production->id : $aggregator->id,
            'has_production' => true,
        ];
    }

    /** Change d'environnement. Retourne false si la production n'est pas disponible. */
    public function switchTo(object $aggregator, string $env): bool
    {
        if ($env === 'production' && !$this->productionAggregator($aggregator)) {
            return false;
        }

        session([self::SESSION_KEY => $env]);

        return true;
    }
}
