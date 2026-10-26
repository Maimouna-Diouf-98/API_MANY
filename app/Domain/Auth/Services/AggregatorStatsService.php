<?php

namespace App\Domain\Auth\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Statistiques des agrégateurs pour l'espace admin.
 *
 * Montants "réussis" uniquement :
 *   collecté  = collections SUCCESS
 *   décaissé  = décaissements SUCCESS + bénéficiaires de bulk SUCCESS
 *   volume    = collecté + décaissé
 *   solde     = wallet de l'agrégateur
 */
class AggregatorStatsService
{
    private const ENVS = [
        'sandbox' => [
            'conn' => 'mysql_sandbox', 'tx_table' => 'api_transactions',
            'tx_ok' => 'SUCCESS', 'tx_filter' => false,
        ],
        'production' => [
            'conn' => 'mysql_money', 'tx_table' => 'transactions',
            'tx_ok' => 'success', 'tx_filter' => true,   // transactions mobile money partagées : on filtre api_type
        ],
    ];

    private const GROUPS = ['aggregator_id', 'sub_merchant_id'];

    /** Liste de tous les agrégateurs d'un environnement avec leurs compteurs et montants. */
    public function list(string $env): array
    {
        $db = $this->db($env);

        $aggregators = $db->table('aggregators')->orderByDesc('created_at')->get();

        $apps = $db->table('applications')
                   ->selectRaw('aggregator_id as k, COUNT(*) as c')
                   ->groupBy('aggregator_id')->pluck('c', 'k');

        $merchants = $db->table('sub_merchants as s')
                        ->join('applications as a', 'a.id', '=', 's.application_id')
                        ->selectRaw('a.aggregator_id as k, COUNT(*) as c')
                        ->groupBy('a.aggregator_id')->pluck('c', 'k');

        $collected = $this->collections($env, 'aggregator_id');
        $disbursed = $this->disbursements($env, 'aggregator_id');
        $bulks     = $this->bulks($env, 'aggregator_id');

        $balances = $db->table('wallets')
                       ->whereIn('user_id', $aggregators->pluck('user_id')->filter()->all())
                       ->pluck('balance', 'user_id');

        $rows = $aggregators->map(function ($a) use ($apps, $merchants, $collected, $disbursed, $bulks, $balances) {
            $in  = (float) ($collected[$a->id] ?? 0);
            $out = (float) ($disbursed[$a->id] ?? 0) + (float) ($bulks[$a->id] ?? 0);

            return [
                'id'                 => $a->id,
                'legal_name'         => $a->legal_name,
                'trade_name'         => $a->trade_name,
                'email'              => $a->email,
                'status'             => $a->status,
                'production_enabled' => (bool) $a->production_enabled,
                'email_verified'     => !empty($a->email_verified_at),
                'created_at'         => $a->created_at,
                'apps'               => (int) ($apps[$a->id] ?? 0),
                'sub_merchants'      => (int) ($merchants[$a->id] ?? 0),
                'balance'            => (float) ($balances[$a->user_id] ?? 0),
                'collected'          => $in,
                'disbursed'          => $out,
                'volume'             => $in + $out,
            ];
        });

        return ['rows' => $rows, 'totals' => $this->totals($rows)];
    }

    /** Détail d'un agrégateur : applications, sous-marchands et montants par sous-marchand. */
    public function detail(string $env, string $aggregatorId): ?array
    {
        $db  = $this->db($env);
        $agg = $db->table('aggregators')->where('id', $aggregatorId)->first();

        if (!$agg) {
            return null;
        }

        $applications = $db->table('applications')
                           ->where('aggregator_id', $agg->id)
                           ->orderByDesc('created_at')
                           ->get(['id', 'name', 'client_id', 'is_active', 'created_at']);

        $appNames = $applications->pluck('name', 'id');

        $subMerchants = $db->table('sub_merchants')
                           ->whereIn('application_id', $applications->pluck('id')->all())
                           ->orderByDesc('created_at')
                           ->get();

        $collectedBySm = $this->collections($env, 'sub_merchant_id', $agg->id);
        $disbursedBySm = $this->disbursements($env, 'sub_merchant_id', $agg->id);
        $bulksBySm     = $this->bulks($env, 'sub_merchant_id', $agg->id);

        $merchantRows = $subMerchants->map(function ($s) use ($appNames, $collectedBySm, $disbursedBySm, $bulksBySm) {
            $in  = (float) ($collectedBySm[$s->id] ?? 0);
            $out = (float) ($disbursedBySm[$s->id] ?? 0) + (float) ($bulksBySm[$s->id] ?? 0);

            return [
                'id'                 => $s->id,
                'legal_name'         => $s->legal_name,
                'business_type'      => $s->business_type,
                'external_reference' => $s->external_reference,
                'status'             => $s->status,
                'application'        => $appNames[$s->application_id] ?? '—',
                'collected'          => $in,
                'disbursed'          => $out,
            ];
        });

        $collected = (float) ($this->collections($env, 'aggregator_id', $agg->id)[$agg->id] ?? 0);
        $disbursed = (float) ($this->disbursements($env, 'aggregator_id', $agg->id)[$agg->id] ?? 0)
                   + (float) ($this->bulks($env, 'aggregator_id', $agg->id)[$agg->id] ?? 0);

        return [
            'aggregator'    => $agg,
            'applications'  => $applications,
            'sub_merchants' => $merchantRows,
            'balance'       => (float) $db->table('wallets')->where('user_id', $agg->user_id)->value('balance'),
            'collected'     => $collected,
            'disbursed'     => $disbursed,
            'volume'        => $collected + $disbursed,
        ];
    }

    // ───────────── Requêtes ─────────────

    private function collections(string $env, string $group, ?string $aggregatorId = null): Collection
    {
        $c = $this->cfg($env);
        $q = $this->db($env)->table($c['tx_table'])->where('status', $c['tx_ok']);

        if ($c['tx_filter']) {
            $q->where('api_type', 'COLLECTION');
        }
        if ($aggregatorId) {
            $q->where('aggregator_id', $aggregatorId);
        }

        return $q->whereNotNull($this->group($group))
                 ->selectRaw($this->group($group) . ' as k, SUM(amount) as total')
                 ->groupBy($this->group($group))
                 ->pluck('total', 'k');
    }

    private function disbursements(string $env, string $group, ?string $aggregatorId = null): Collection
    {
        $q = $this->db($env)->table('api_disbursements')->where('status', 'SUCCESS');

        if ($aggregatorId) {
            $q->where('aggregator_id', $aggregatorId);
        }

        return $q->selectRaw($this->group($group) . ' as k, SUM(amount) as total')
                 ->groupBy($this->group($group))
                 ->pluck('total', 'k');
    }

    private function bulks(string $env, string $group, ?string $aggregatorId = null): Collection
    {
        $g = $this->group($group);
        $q = $this->db($env)->table('api_bulk_payment_recipients as r')
                  ->join('api_bulk_payments as b', 'b.bulk_id', '=', 'r.bulk_id')
                  ->where('r.status', 'SUCCESS');

        if ($aggregatorId) {
            $q->where('b.aggregator_id', $aggregatorId);
        }

        return $q->selectRaw("b.{$g} as k, SUM(r.amount) as total")
                 ->groupBy("b.{$g}")
                 ->pluck('total', 'k');
    }

    private function totals(Collection $rows): array
    {
        return [
            'aggregators'   => $rows->count(),
            'apps'          => $rows->sum('apps'),
            'sub_merchants' => $rows->sum('sub_merchants'),
            'balance'       => $rows->sum('balance'),
            'collected'     => $rows->sum('collected'),
            'disbursed'     => $rows->sum('disbursed'),
            'volume'        => $rows->sum('volume'),
        ];
    }

    private function cfg(string $env): array
    {
        return self::ENVS[$env] ?? throw new InvalidArgumentException("Environnement inconnu : {$env}");
    }

    private function db(string $env)
    {
        return DB::connection($this->cfg($env)['conn']);
    }

    /** Liste blanche : ces noms de colonnes sont insérés dans du SQL brut. */
    private function group(string $column): string
    {
        if (!in_array($column, self::GROUPS, true)) {
            throw new InvalidArgumentException("Colonne de regroupement invalide : {$column}");
        }
        return $column;
    }
}
