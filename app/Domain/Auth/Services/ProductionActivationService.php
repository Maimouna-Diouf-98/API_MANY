<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Admin;
use App\Domain\Auth\Models\Application;
use App\Domain\Auth\Models\ProductionRequest;
use App\Mail\ProductionCredentialsMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Logique unique d'activation en production, utilisée par :
 *  - l'espace admin (bouton "Approuver")
 *  - la commande artisan aggregator:activate-production
 */
class ProductionActivationService
{
    /** Approuve une demande (verrouillée) et active l'agrégateur en production. */
    public function approve(ProductionRequest $request, Admin $admin, float $commission): array
    {
        return DB::connection('mysql_sandbox')->transaction(function () use ($request, $admin, $commission) {

            $locked = ProductionRequest::lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== 'PENDING') {
                throw new RuntimeException('REQUEST_ALREADY_REVIEWED');
            }

            $sandboxAgg = DB::connection('mysql_sandbox')
                            ->table('aggregators')
                            ->where('id', $locked->aggregator_id)
                            ->first();

            if (!$sandboxAgg) {
                throw new RuntimeException('AGGREGATOR_NOT_FOUND');
            }

            $credentials = $this->activate($sandboxAgg, $commission);

            $locked->update([
                'status'      => 'APPROVED',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            return $credentials;
        });
    }

    /**
     * Régularise une demande dont l'agrégateur est DÉJÀ actif en production
     * (activé par commande artisan avant l'espace admin) : marque la demande
     * APPROVED sans rien créer côté production.
     */
    public function markApprovedIfAlreadyActive(ProductionRequest $request, Admin $admin): void
    {
        DB::connection('mysql_sandbox')->transaction(function () use ($request, $admin) {

            $locked = ProductionRequest::lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== 'PENDING') {
                throw new RuntimeException('REQUEST_ALREADY_REVIEWED');
            }

            $sandboxAgg = DB::connection('mysql_sandbox')
                            ->table('aggregators')
                            ->where('id', $locked->aggregator_id)
                            ->first();

            if (!$sandboxAgg) {
                throw new RuntimeException('AGGREGATOR_NOT_FOUND');
            }

            $active = DB::connection('mysql_money')
                        ->table('aggregators')
                        ->where('email', $sandboxAgg->email)
                        ->where('production_enabled', true)
                        ->exists();

            if (!$active) {
                throw new RuntimeException('NOT_ACTIVE_IN_PRODUCTION');
            }

            $locked->update([
                'status'      => 'APPROVED',
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);

            // Aligne le compte sandbox (ne touche pas au mot de passe)
            DB::connection('mysql_sandbox')
              ->table('aggregators')
              ->where('id', $sandboxAgg->id)
              ->update(['production_enabled' => true, 'updated_at' => now()]);
        });
    }

    /** Refuse une demande avec un motif visible par l'agrégateur. */
    public function reject(ProductionRequest $request, Admin $admin, string $reason): void
    {
        DB::connection('mysql_sandbox')->transaction(function () use ($request, $admin, $reason) {
            $locked = ProductionRequest::lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== 'PENDING') {
                throw new RuntimeException('REQUEST_ALREADY_REVIEWED');
            }

            $locked->update([
                'status'      => 'REJECTED',
                'admin_note'  => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $admin->id,
            ]);
        });
    }

    /**
     * Crée dans mysql_money : user (mpin = mot de passe), wallet à 0,
     * agrégateur PRODUCTION_ACTIVE et application agg_live_xxx.
     * Synchronise ensuite le mot de passe sur le compte sandbox pour que
     * l'agrégateur se connecte au portail avec le nouveau mot de passe.
     *
     * @param  object $sandboxAgg  ligne de mysql_sandbox.aggregators
     * @return array  credentials en clair (à transmettre à l'agrégateur)
     */
    public function activate(object $sandboxAgg, float $commission): array
    {
        if (empty($sandboxAgg->email_verified_at)) {
            throw new RuntimeException('EMAIL_NOT_VERIFIED');
        }
        if (empty($sandboxAgg->phone)) {
            throw new RuntimeException('AGGREGATOR_PHONE_MISSING');
        }

        $money = DB::connection('mysql_money');

        if ($money->table('aggregators')->where('email', $sandboxAgg->email)->exists()) {
            throw new RuntimeException('ALREADY_ACTIVE');
        }

        $normalizedPhone = str_replace(' ', '', $sandboxAgg->phone);
        $phoneUsed = $money->table('users')
                           ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalizedPhone])
                           ->whereNull('deleted_at')
                           ->exists();
        if ($phoneUsed) {
            throw new RuntimeException('PHONE_ALREADY_USED');
        }

        // FCM token copié depuis le sandbox si l'agrégateur en a déjà un
        $fcmToken = DB::connection('mysql_sandbox')
                      ->table('users')
                      ->where('id', $sandboxAgg->user_id)
                      ->value('fcm_token');

        $password     = Str::random(12);
        $hashed       = Hash::make($password);
        $clientId     = 'agg_live_' . Str::random(12);
        $clientSecret = Str::random(48);
        $aggId        = (string) Str::uuid();

        $money->transaction(function () use (
            $money, $sandboxAgg, $hashed, $fcmToken, $aggId, $clientId, $clientSecret, $commission
        ) {
            $userId = $money->table('users')->insertGetId([
                'role'                 => 'aggregator',
                'reference_account_no' => 'AGG' . strtoupper(Str::random(6)),
                'first_name'           => $sandboxAgg->legal_name,
                'last_name'            => '',
                'phone'                => $sandboxAgg->phone,
                'password'             => $hashed,
                'mpin'                 => $hashed,      // mpin = mot de passe
                'fcm_token'            => $fcmToken,
                'user_status'          => 0,
                'verification_status'  => 1,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            $money->table('wallets')->insert([
                'user_id'    => $userId,
                'balance'    => 0.00,
                'currency'   => 'FCFA',
                'status'     => 'active',
                'version'    => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $money->table('aggregators')->insert([
                'id'                 => $aggId,
                'user_id'            => $userId,
                'legal_name'         => $sandboxAgg->legal_name,
                'trade_name'         => $sandboxAgg->trade_name,
                'email'              => $sandboxAgg->email,
                'phone'              => $sandboxAgg->phone,
                'password'           => $hashed,
                'webhook_url'        => $sandboxAgg->webhook_url,
                'status'             => 'PRODUCTION_ACTIVE',
                'sandbox_enabled'    => false,
                'production_enabled' => true,
                'commission_rate'    => $commission,
                'email_verified_at'  => now(),
                'activated_at'       => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Via Eloquent : le cast 'encrypted' chiffre correctement le secret
            Application::on('mysql_money')->create([
                'aggregator_id' => $aggId,
                'name'          => 'Application Production - ' . $sandboxAgg->legal_name,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'environment'   => 'PRODUCTION',
                'webhook_url'   => $sandboxAgg->webhook_url,
                'is_active'     => true,
            ]);
        });

        // Même mot de passe côté portail (login sur mysql_sandbox)
        DB::connection('mysql_sandbox')
          ->table('aggregators')
          ->where('id', $sandboxAgg->id)
          ->update([
              'password'           => $hashed,
              'production_enabled' => true,
              'activated_at'       => now(),
              'updated_at'         => now(),
          ]);

        return [
            'legal_name'    => $sandboxAgg->legal_name,
            'email'         => $sandboxAgg->email,
            'password'      => $password,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'commission'    => $commission,
            'has_fcm_token' => !empty($fcmToken),
        ];
    }

    /** Marque APPROVED les demandes en attente (utilisé par la commande artisan). */
    public function closePendingRequests(string $aggregatorId): void
    {
        ProductionRequest::where('aggregator_id', $aggregatorId)
            ->where('status', 'PENDING')
            ->update(['status' => 'APPROVED', 'reviewed_at' => now()]);
    }

    /** Envoie les credentials à l'agrégateur. Retourne false si l'email échoue. */
    public function sendCredentials(array $c): bool
    {
        try {
            Mail::to($c['email'])->send(new ProductionCredentialsMail(
                legalName:    $c['legal_name'],
                email:        $c['email'],
                clientId:     $c['client_id'],
                clientSecret: $c['client_secret'],
                password:     $c['password'],
                portalUrl:    url('/portal/login'),
            ));
            return true;
        } catch (Throwable $e) {
            Log::error('Envoi credentials production échoué : ' . $e->getMessage());
            return false;
        }
    }

    /** Messages lisibles pour les erreurs métier. */
    public static function errorMessage(string $code): string
    {
        return match ($code) {
            'REQUEST_ALREADY_REVIEWED' => 'Cette demande a déjà été traitée.',
            'AGGREGATOR_NOT_FOUND'     => 'Agrégateur sandbox introuvable.',
            'EMAIL_NOT_VERIFIED'       => "L'email de l'agrégateur n'est pas vérifié.",
            'AGGREGATOR_PHONE_MISSING' => "L'agrégateur n'a pas de numéro de téléphone.",
            'ALREADY_ACTIVE'           => 'Cet agrégateur existe déjà en production.',
            'PHONE_ALREADY_USED'       => 'Ce numéro de téléphone est déjà utilisé par un compte Many.',
            'NOT_ACTIVE_IN_PRODUCTION' => "Cet agrégateur n'est pas actif en production : utilisez « Activer en production ».",
            default                    => 'Erreur interne : ' . $code,
        };
    }
}
