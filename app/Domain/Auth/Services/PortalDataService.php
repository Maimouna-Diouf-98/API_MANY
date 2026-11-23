<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Application;
use App\Domain\SubMerchants\Models\SubMerchant;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données du portail agrégateur (applications, sous-marchands, opérations).
 *
 * Toutes les lectures et écritures passent par un contexte issu de PortalEnvironmentService :
 *   env            'sandbox' | 'production'
 *   aggregator_id  id de l'agrégateur DANS la base de l'environnement affiché
 *   conn           'mysql_sandbox' | 'mysql_money'
 *
 * Règle de sécurité : chaque requête est limitée à ctx['aggregator_id'] — un agrégateur
 * ne voit ni ne modifie jamais les données d'un autre.
 *
 * Les erreurs métier sont levées en RuntimeException avec un code (voir errorMessage()).
 */
class PortalDataService
{
    private const PER_PAGE          = 20;
    private const RECENT_OPERATIONS = 10;

    public function __construct(protected PortalEnvironmentService $environments) {}

    // ─────────────────────────────────────────────────────────────────────
    // Contexte
    // ─────────────────────────────────────────────────────────────────────

    /** @return array{env:string, aggregator_id:string, has_production:bool, conn:string} */
    public function context(object $aggregator): array
    {
        $ctx = $this->environments->resolve($aggregator);
        $ctx['conn'] = $ctx['env'] === 'production' ? 'mysql_money' : 'mysql_sandbox';

        return $ctx;
    }

    private function isProd(array $ctx): bool
    {
        return $ctx['env'] === 'production';
    }

    private function db(array $ctx)
    {
        return DB::connection($ctx['conn']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Applications
    // ─────────────────────────────────────────────────────────────────────

    public function applications(array $ctx): Collection
    {
        return Application::on($ctx['conn'])
            ->where('aggregator_id', $ctx['aggregator_id'])
            ->withCount('subMerchants')          // → sub_merchants_count
            ->latest()
            ->get();
    }

    public function application(array $ctx, string $id): ?Application
    {
        return Application::on($ctx['conn'])
            ->where('aggregator_id', $ctx['aggregator_id'])
            ->where('id', $id)
            ->first();
    }

    private function applicationOrFail(array $ctx, string $id): Application
    {
        return $this->application($ctx, $id) ?? throw new RuntimeException('APPLICATION_NOT_FOUND');
    }

    /** @return array{application: Application, secret: string} secret en clair, affiché une seule fois */
    public function createApplication(array $ctx, string $name, string $webhookUrl): array
    {
        $secret = Str::random(48);

        $application = Application::on($ctx['conn'])->create([
            'aggregator_id' => $ctx['aggregator_id'],
            'name'          => $name,
            'client_id'     => ($this->isProd($ctx) ? 'agg_live_' : 'agg_sandbox_') . Str::random(12),
            'client_secret' => $secret,                     // chiffré par le cast 'encrypted'
            'environment'   => $this->isProd($ctx) ? 'PRODUCTION' : 'SANDBOX',
            'webhook_url'   => $webhookUrl,
            'is_active'     => true,
        ]);

        return ['application' => $application, 'secret' => $secret];
    }

    /** Nouveau secret ; l'ancien ne marche plus et les tokens en cours sont révoqués. */
    public function regenerateSecret(array $ctx, string $id): string
    {
        $application = $this->applicationOrFail($ctx, $id);
        $secret      = Str::random(48);

        DB::connection($ctx['conn'])->transaction(function () use ($application, $secret) {
            $application->update(['client_secret' => $secret]);
            $application->tokens()->delete();
        });

        return $secret;
    }

    /**
     * Production, ou application ayant déjà des opérations : désactivation (historique conservé).
     * Sandbox sans opération : suppression de l'application et de ses sous-marchands.
     *
     * @return 'deactivated'|'deleted'
     */
    public function removeApplication(array $ctx, string $id): string
    {
        $application = $this->applicationOrFail($ctx, $id);

        if ($this->isProd($ctx) || $this->applicationHasOperations($ctx, $application->id)) {
            DB::connection($ctx['conn'])->transaction(function () use ($application) {
                $application->update(['is_active' => false]);
                $application->tokens()->delete();
            });

            return 'deactivated';
        }

        DB::connection($ctx['conn'])->transaction(function () use ($ctx, $application) {
            $application->tokens()->delete();
            $this->db($ctx)->table('sub_merchants')->where('application_id', $application->id)->delete();
            $application->delete();
        });

        return 'deleted';
    }

    public function reactivateApplication(array $ctx, string $id): void
    {
        $application = $this->applicationOrFail($ctx, $id);

        if ($application->is_active) {
            throw new RuntimeException('APPLICATION_ALREADY_ACTIVE');
        }

        $application->update(['is_active' => true]);
    }

    private function applicationHasOperations(array $ctx, string $applicationId): bool
    {
        foreach ([$this->collectionsTable($ctx), 'api_disbursements', 'api_bulk_payments'] as $table) {
            if ($this->db($ctx)->table($table)->where('application_id', $applicationId)->exists()) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Sous-marchands
    // ─────────────────────────────────────────────────────────────────────

    /** Sous-marchands d'une application, avec montants encaissés / décaissés (tableaux). */
    public function subMerchantsOfApplication(array $ctx, string $appId): Collection
    {
        if (!$this->application($ctx, $appId)) {
            return collect();
        }

        $subMerchants = $this->db($ctx)->table('sub_merchants')
            ->where('application_id', $appId)
            ->orderByDesc('created_at')
            ->get();

        $collected = $this->successfulCollections($ctx)
            ->whereIn('sub_merchant_id', $subMerchants->pluck('id'))
            ->groupBy('sub_merchant_id')
            ->selectRaw('sub_merchant_id, SUM(amount) as total')
            ->pluck('total', 'sub_merchant_id');

        $disbursed = $this->disbursedBySubMerchant($ctx, $subMerchants->pluck('id'));

        return $subMerchants->map(fn ($sm) => [
            'id'                 => $sm->id,
            'legal_name'         => $sm->legal_name,
            'business_type'      => $sm->business_type,
            'external_reference' => $sm->external_reference,
            'status'             => $sm->status,
            'collected'          => (float) ($collected[$sm->id] ?? 0),
            'disbursed'          => (float) ($disbursed[$sm->id] ?? 0),
        ]);
    }

    /** @return string id du sous-marchand créé */
    public function createSubMerchant(array $ctx, string $appId, array $data): string
    {
        $application = $this->applicationOrFail($ctx, $appId);

        if (!$application->is_active) {
            throw new RuntimeException('APPLICATION_INACTIVE');
        }

        $prefix = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->aggregatorName($ctx))), 0, 6) ?: 'AGG';

        $subMerchant = SubMerchant::on($ctx['conn'])->create([
            'application_id'     => $application->id,
            'legal_name'         => $data['legal_name'],
            'business_type'      => $data['business_type'],
            'address'            => $data['address'],
            'webhook_url'        => $data['webhook_url'],
            'external_reference' => 'SM-' . $prefix . '-' . strtoupper(Str::random(6)),
            'status'             => 'PENDING',
        ]);

        return $subMerchant->id;
    }

    /** Sous-marchand (avec application_name), seulement s'il appartient à cette application de l'agrégateur. */
    public function subMerchant(array $ctx, string $appId, string $smId): ?object
    {
        return $this->db($ctx)->table('sub_merchants as sm')
            ->join('applications as a', 'a.id', '=', 'sm.application_id')
            ->where('a.aggregator_id', $ctx['aggregator_id'])
            ->where('a.id', $appId)
            ->where('sm.id', $smId)
            ->select('sm.*', 'a.name as application_name')
            ->first();
    }

    /** @return array{collected: float, disbursed: float} */
    public function subMerchantTotals(array $ctx, string $smId): array
    {
        return [
            'collected' => (float) $this->successfulCollections($ctx)->where('sub_merchant_id', $smId)->sum('amount'),
            'disbursed' => (float) ($this->disbursedBySubMerchant($ctx, collect([$smId]))[$smId] ?? 0),
        ];
    }

    /** Dernières opérations (tous types confondus), les plus récentes d'abord. */
    public function subMerchantOperations(array $ctx, string $smId): Collection
    {
        $ops = collect();

        foreach (['collections', 'disbursements', 'bulks'] as $type) {
            $rows = $this->baseQuery($ctx, $type)
                ->where('sub_merchant_id', $smId)
                ->orderByDesc('created_at')
                ->limit(self::RECENT_OPERATIONS)
                ->get();

            foreach ($rows as $row) {
                $ops->push([
                    'type'       => $type,
                    'id'         => $row->{$this->idColumn($type)},
                    'status'     => $row->status,
                    'label'      => match ($type) {
                        'collections'   => $row->order_reference,
                        'disbursements' => $row->reference,
                        default         => $row->label,
                    },
                    'amount'     => $type === 'bulks' ? $row->total_amount : $row->amount,
                    'created_at' => Carbon::parse($row->created_at),
                ]);
            }
        }

        return $ops->sortByDesc('created_at')->take(self::RECENT_OPERATIONS)->values();
    }

    /** @param 'activate'|'suspend'|'close' $action */
    public function changeSubMerchantStatus(array $ctx, string $appId, string $smId, string $action): void
    {
        $sm = $this->subMerchant($ctx, $appId, $smId) ?? throw new RuntimeException('SUB_MERCHANT_NOT_FOUND');

        $allowed = match ($action) {
            'activate' => in_array($sm->status, ['PENDING', 'SUSPENDED'], true),
            'suspend'  => $sm->status === 'ACTIVE',
            'close'    => $sm->status !== 'CLOSED',
            default    => false,
        };

        if (!$allowed) {
            throw new RuntimeException('INVALID_STATUS_TRANSITION');
        }

        $this->db($ctx)->table('sub_merchants')->where('id', $sm->id)->update([
            'status'     => ['activate' => 'ACTIVE', 'suspend' => 'SUSPENDED', 'close' => 'CLOSED'][$action],
            'updated_at' => now(),
        ]);
    }

    /** id => legal_name, limité aux sous-marchands de l'agrégateur. */
    public function subMerchantNames(array $ctx, iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return $this->ownedSubMerchants($ctx)
            ->whereIn('sm.id', $ids)
            ->pluck('sm.legal_name', 'sm.id')
            ->all();
    }

    /** Pour le filtre « marchand » de la liste des opérations. */
    public function allSubMerchants(array $ctx): Collection
    {
        return $this->ownedSubMerchants($ctx)
            ->orderBy('sm.legal_name')
            ->get(['sm.id', 'sm.legal_name']);
    }

    private function ownedSubMerchants(array $ctx): Builder
    {
        return $this->db($ctx)->table('sub_merchants as sm')
            ->join('applications as a', 'a.id', '=', 'sm.application_id')
            ->where('a.aggregator_id', $ctx['aggregator_id']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Opérations (collections, disbursements, paiements de masse)
    // ─────────────────────────────────────────────────────────────────────

    public function transactions(array $ctx, string $type, array $filters): LengthAwarePaginator
    {
        return $this->transactionsQuery($ctx, $type, $filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** Requête filtrée (utilisée par la liste et par l'export CSV). */
    public function transactionsQuery(array $ctx, string $type, array $filters): Builder
    {
        $query = $this->baseQuery($ctx, $type);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);   // collation insensible à la casse : 'SUCCESS' = 'success'
        }
        if (!empty($filters['sub_merchant_id'])) {
            $query->where('sub_merchant_id', $filters['sub_merchant_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }
        if (!empty($filters['q'])) {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $query->where(function ($q) use ($type, $like) {
                foreach ($this->searchColumns($type) as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        return $query->orderByDesc('created_at');
    }

    /** Colonnes de l'export CSV. */
    public function columns(array $ctx, string $type): array
    {
        return match ($type) {
            'collections' => [
                'transaction_id', 'order_reference', 'customer_phone', 'amount', 'many_fee', 'net_to_aggregator',
                'currency', 'status', 'failure_reason', 'sub_merchant_id', 'idempotency_key', 'created_at',
                $this->isProd($ctx) ? 'updated_at' : 'completed_at',
            ],
            'disbursements' => [
                'disbursement_id', 'reference', 'recipient_phone', 'amount', 'many_fee', 'net_amount',
                'currency', 'status', 'failure_reason', 'sub_merchant_id', 'idempotency_key', 'created_at', 'completed_at',
            ],
            default => [
                'bulk_id', 'label', 'total_recipients', 'success_count', 'failure_count', 'total_amount',
                'many_fee', 'currency', 'status', 'sub_merchant_id', 'created_at', 'completed_at',
            ],
        };
    }

    /**
     * Recherche une opération de l'agrégateur par son identifiant public.
     * Sans type (ou type invalide), cherche dans les trois.
     *
     * @return array{type:string, row:object}|null
     */
    public function findTransaction(array $ctx, string $id, ?string $type = null): ?array
    {
        $types = in_array($type, ['collections', 'disbursements', 'bulks'], true)
            ? [$type]
            : ['collections', 'disbursements', 'bulks'];

        foreach ($types as $t) {
            $row = $this->baseQuery($ctx, $t)->where($this->idColumn($t), $id)->first();
            if ($row) {
                return ['type' => $t, 'row' => $row];
            }
        }

        return null;
    }

    /** Bénéficiaires d'un paiement de masse de l'agrégateur. */
    public function bulkRecipients(array $ctx, string $bulkId): Collection
    {
        $owned = $this->baseQuery($ctx, 'bulks')->where('bulk_id', $bulkId)->exists();

        if (!$owned) {
            return collect();
        }

        return $this->db($ctx)->table('api_bulk_payment_recipients')
            ->where('bulk_id', $bulkId)
            ->orderBy('created_at')
            ->get();
    }

    /** Requête de base d'un type d'opération, déjà limitée à l'agrégateur. */
    private function baseQuery(array $ctx, string $type): Builder
    {
        $query = $this->db($ctx)->table(match ($type) {
            'collections'   => $this->collectionsTable($ctx),
            'disbursements' => 'api_disbursements',
            'bulks'         => 'api_bulk_payments',
            default         => throw new RuntimeException('INVALID_TYPE'),
        })->where('aggregator_id', $ctx['aggregator_id']);

        // En production, les collections partagent la table transactions de l'app mobile money
        if ($type === 'collections' && $this->isProd($ctx)) {
            $query->where('api_type', 'COLLECTION')->whereNull('deleted_at');
        }

        return $query;
    }

    private function collectionsTable(array $ctx): string
    {
        return $this->isProd($ctx) ? 'transactions' : 'api_transactions';
    }

    private function idColumn(string $type): string
    {
        return ['collections' => 'transaction_id', 'disbursements' => 'disbursement_id', 'bulks' => 'bulk_id'][$type];
    }

    private function searchColumns(string $type): array
    {
        return match ($type) {
            'collections'   => ['transaction_id', 'order_reference', 'customer_phone', 'idempotency_key'],
            'disbursements' => ['disbursement_id', 'reference', 'recipient_phone', 'idempotency_key'],
            default         => ['bulk_id', 'label'],
        };
    }

    private function successfulCollections(array $ctx): Builder
    {
        return $this->baseQuery($ctx, 'collections')->where('status', 'SUCCESS');
    }

    /** Décaissé par sous-marchand : disbursements réussis + bénéficiaires payés des paiements de masse. */
    private function disbursedBySubMerchant(array $ctx, Collection $subMerchantIds): array
    {
        if ($subMerchantIds->isEmpty()) {
            return [];
        }

        $totals = $this->baseQuery($ctx, 'disbursements')
            ->where('status', 'SUCCESS')
            ->whereIn('sub_merchant_id', $subMerchantIds)
            ->groupBy('sub_merchant_id')
            ->selectRaw('sub_merchant_id, SUM(amount) as total')
            ->pluck('total', 'sub_merchant_id')
            ->all();

        $bulk = $this->db($ctx)->table('api_bulk_payment_recipients as r')
            ->join('api_bulk_payments as b', 'b.bulk_id', '=', 'r.bulk_id')
            ->where('b.aggregator_id', $ctx['aggregator_id'])
            ->where('r.status', 'SUCCESS')
            ->whereIn('b.sub_merchant_id', $subMerchantIds)
            ->groupBy('b.sub_merchant_id')
            ->selectRaw('b.sub_merchant_id as sm_id, SUM(r.amount) as total')
            ->pluck('total', 'sm_id');

        foreach ($bulk as $smId => $amount) {
            $totals[$smId] = ($totals[$smId] ?? 0) + $amount;
        }

        return $totals;
    }

    private function aggregatorName(array $ctx): ?string
    {
        return $this->db($ctx)->table('aggregators')->where('id', $ctx['aggregator_id'])->value('legal_name');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Messages d'erreur
    // ─────────────────────────────────────────────────────────────────────

    public static function errorMessage(string $code): string
    {
        return match ($code) {
            'APPLICATION_NOT_FOUND'      => 'Application introuvable.',
            'APPLICATION_INACTIVE'       => "Cette application est désactivée : réactivez-la avant d'y ajouter un sous-marchand.",
            'APPLICATION_ALREADY_ACTIVE' => 'Cette application est déjà active.',
            'SUB_MERCHANT_NOT_FOUND'     => 'Sous-marchand introuvable.',
            'INVALID_STATUS_TRANSITION'  => "Cette action n'est pas possible dans l'état actuel du sous-marchand.",
            default                      => 'Une erreur est survenue. Réessayez ou contactez Many.',
        };
    }
}
