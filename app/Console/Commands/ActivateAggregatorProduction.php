<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Domain\Auth\Models\Application;
use App\Mail\ProductionCredentialsMail;
use Illuminate\Support\Facades\Schema;

class ActivateAggregatorProduction extends Command
{
    protected $signature = 'aggregator:activate-production
                        {email : Email de l\'agrégateur sandbox}
                        {--commission=1.50 : Taux de commission}
                        {--fcm= : FCM token de l\'agrégateur (optionnel)}';


    protected $description = 'Active un agrégateur sandbox en production';

    public function handle(): void
    {
        $email      = $this->argument('email');
        $commission = $this->option('commission');

        // 1. Récupérer l'agrégateur sandbox
        $sandboxAgg = DB::connection('mysql_sandbox')
                        ->table('aggregators')
                        ->where('email', $email)
                        ->first();

        if (!$sandboxAgg) {
            $this->error("Agrégateur introuvable en sandbox : $email");
            return;
        }

        $this->info("Agrégateur : {$sandboxAgg->legal_name} ({$email})");

        // 2. Vérifier si déjà en production
        $existingAgg = DB::connection('mysql_money')
                         ->table('aggregators')
                         ->where('email', $email)
                         ->first();

        if ($existingAgg) {
            $this->warn("Cet agrégateur existe déjà en production.");
            if (!$this->confirm("Voulez-vous recréer ses credentials ?")) {
                return;
            }
        }

        // 3. Demander le mot de passe production
        $newPassword     = $this->secret("Nouveau mot de passe production");
        $confirmPassword = $this->secret("Confirmez le mot de passe");

        if ($newPassword !== $confirmPassword) {
            $this->error("Les mots de passe ne correspondent pas.");
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->error("Le mot de passe doit avoir au moins 8 caractères.");
            return;
        }

        // 4. Demander le FCM token (obligatoire pour les notifications)
        $fcmToken = $this->option('fcm');

        if (empty($fcmToken)) {
            $this->warn("⚠ Pas de FCM token fourni. Les notifications push ne fonctionneront pas " .
                       "jusqu'à ce que l'agrégateur appelle POST /v1/device/register.");
        }

        $this->info("Création en cours...");

        $hashedPassword = Hash::make($newPassword);

        // 5. Vérifier que les colonnes mpin et fcm_token existent
        $hasMpin     = Schema::connection('mysql_money')->hasColumn('users', 'mpin');
        $hasFcmToken = Schema::connection('mysql_money')->hasColumn('users', 'fcm_token');

        if (!$hasMpin) {
            $this->error("La colonne 'mpin' n'existe pas dans users. Exécutez d'abord la migration 000003.");
            return;
        }

        if (!$hasFcmToken) {
            $this->error("La colonne 'fcm_token' n'existe pas dans users. Exécutez d'abord la migration 000003.");
            return;
        }

        if (!$existingAgg) {
            // 6. Créer le user production
            $userId = DB::connection('mysql_money')
                        ->table('users')
                        ->insertGetId([
                            'role'                 => 'aggregator',
                            'reference_account_no' => 'AGG' . strtoupper(Str::random(6)),
                            'first_name'           => $sandboxAgg->legal_name,
                            'last_name'            => '',
                            'phone'                => $sandboxAgg->phone ?? '+221770000000',
                            'password'             => $hashedPassword,
                            'mpin'                 => $hashedPassword, // même valeur → pour confirmer disbursements/bulk
                            'fcm_token'            => $fcmToken ?: null,
                            'user_status'          => 0,
                            'verification_status'  => 1,
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);

            $this->info("✓ User créé (id: {$userId}, mpin: ✓, fcm_token: " . ($fcmToken ? '✓' : '⚠ manquant') . ")");

            // 7. Créer le wallet production (0 XOF)
            DB::connection('mysql_money')
              ->table('wallets')
              ->insert([
                  'user_id'    => $userId,
                  'balance'    => 0.00,
                  'currency'   => 'FCFA',
                  'status'     => 'active',
                  'version'    => 1,
                  'created_at' => now(),
                  'updated_at' => now(),
              ]);

            $this->info("✓ Wallet production créé (0 XOF)");

            $aggId = (string) Str::uuid();

            // 8. Créer l'agrégateur production
            DB::connection('mysql_money')
              ->table('aggregators')
              ->insert([
                  'id'                 => $aggId,
                  'user_id'            => $userId,
                  'legal_name'         => $sandboxAgg->legal_name,
                  'trade_name'         => $sandboxAgg->trade_name,
                  'email'              => $sandboxAgg->email,
                  'phone'              => $sandboxAgg->phone,
                  'password'           => $hashedPassword,
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

        } else {
            $aggId  = $existingAgg->id;
            $userId = $existingAgg->user_id;

            // Mettre à jour l'agrégateur existant
            DB::connection('mysql_money')
              ->table('aggregators')
              ->where('email', $email)
              ->update([
                  'password'           => $hashedPassword,
                  'status'             => 'PRODUCTION_ACTIVE',
                  'production_enabled' => true,
                  'commission_rate'    => $commission,
                  'activated_at'       => now(),
                  'updated_at'         => now(),
              ]);

            // Mettre à jour le user existant
            DB::connection('mysql_money')
              ->table('users')
              ->where('id', $userId)
              ->update([
                  'password'   => $hashedPassword,
                  'mpin'       => $hashedPassword,
                  'fcm_token'  => $fcmToken ?: DB::connection('mysql_money')
                                               ->table('users')
                                               ->where('id', $userId)
                                               ->value('fcm_token'),
                  'updated_at' => now(),
              ]);

            // Supprimer les anciennes applications
            DB::connection('mysql_money')
              ->table('applications')
              ->where('aggregator_id', $aggId)
              ->delete();
        }

        // 9. Créer l'application production via Eloquent
        $plainSecret = Str::random(48);
        $clientId    = 'agg_live_' . Str::random(12);

        DB::setDefaultConnection('mysql_money');

        $application = Application::create([
            'aggregator_id' => $aggId,
            'name'          => 'Application Production - ' . $sandboxAgg->legal_name,
            'client_id'     => $clientId,
            'client_secret' => $plainSecret,
            'environment'   => 'PRODUCTION',
            'webhook_url'   => $sandboxAgg->webhook_url,
            'is_active'     => true,
        ]);

        $this->info("✓ Application production créée");

        // 10. Envoyer email à l'agrégateur
        try {
            Mail::to($email)->send(new ProductionCredentialsMail(
                legalName:    $sandboxAgg->legal_name,
                email:        $email,
                clientId:     $clientId,
                clientSecret: $plainSecret,
                password:     $newPassword,
                portalUrl:    url('/portal/login'),
            ));
            $this->info("📧 Email envoyé à {$email}");
        } catch (\Exception $e) {
            $this->warn("⚠ Email non envoyé : " . $e->getMessage());
        }

        // 11. Afficher le résumé
        $this->info('');
        $this->info('✅ Agrégateur activé en production avec succès !');
        $this->info('');
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Entreprise',    $sandboxAgg->legal_name],
                ['Email',         $email],
                ['Mot de passe',  $newPassword],
                ['mpin',          '= mot de passe (hashé en base)'],
                ['fcm_token',     $fcmToken ?: '⚠ non fourni'],
                ['client_id',     $clientId],
                ['client_secret', $plainSecret],
                ['Commission',    $commission . '%'],
                ['Statut',        'PRODUCTION_ACTIVE'],
            ]
        );

        $this->warn('');
        $this->warn('⚠ Notez bien le client_secret, il ne sera plus jamais affiché !');

        if (empty($fcmToken)) {
            $this->warn('⚠ FCM token manquant. L\'agrégateur doit appeler :');
            $this->warn('  POST /api/v1/device/register { "fcm_token": "son_token" }');
        }
    }
}