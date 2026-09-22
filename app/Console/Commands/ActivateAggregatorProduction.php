<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Domain\Auth\Models\Application;

class ActivateAggregatorProduction extends Command
{
    protected $signature   = 'aggregator:activate-production
                              {email : Email de l\'agrégateur sandbox}
                              {--commission=1.50 : Taux de commission}';

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
            if (!$this->confirm("Mettre à jour ses credentials production ?")) {
                return;
            }
        }

        // 3. Demander le nouveau mot de passe production
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

        $this->info("Création en cours...");

        // 4. Créer ou récupérer le user production
        if (!$existingAgg) {
            $userId = DB::connection('mysql_money')
                        ->table('users')
                        ->insertGetId([
                            'role'                 => 'aggregator',
                            'reference_account_no' => 'AGG' . strtoupper(Str::random(6)),
                            'first_name'           => $sandboxAgg->legal_name,
                            'last_name'            => '',
                            'phone'                => $sandboxAgg->phone ?? '+221770000000',
                            'user_status'          => 0,
                            'verification_status'  => 1,
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);

            // Wallet production (0 XOF réel)
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

            $aggId = (string) Str::uuid();

            // Créer l'agrégateur production
            DB::connection('mysql_money')
              ->table('aggregators')
              ->insert([
                  'id'                 => $aggId,
                  'user_id'            => $userId,
                  'legal_name'         => $sandboxAgg->legal_name,
                  'trade_name'         => $sandboxAgg->trade_name,
                  'email'              => $sandboxAgg->email,
                  'phone'              => $sandboxAgg->phone,
                  'password'           => Hash::make($newPassword),
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
                  'password'           => Hash::make($newPassword),
                  'status'             => 'PRODUCTION_ACTIVE',
                  'production_enabled' => true,
                  'commission_rate'    => $commission,
                  'activated_at'       => now(),
                  'updated_at'         => now(),
              ]);

            // Supprimer les anciennes applications
            DB::connection('mysql_money')
              ->table('applications')
              ->where('aggregator_id', $aggId)
              ->delete();
        }

        // 5. Créer l'application production via Eloquent
        // pour que le cast 'encrypted' fonctionne correctement
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

        // 6. Afficher les credentials
        $this->info('');
        $this->info('✅ Agrégateur activé en production avec succès !');
        $this->info('');
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Entreprise',    $sandboxAgg->legal_name],
                ['Email',         $email],
                ['Mot de passe',  '(défini à l\'instant)'],
                ['client_id',     $clientId],
                ['client_secret', $plainSecret],
                ['Commission',    $commission . '%'],
                ['Environnement', 'PRODUCTION'],
                ['Statut',        'PRODUCTION_ACTIVE'],
            ]
        );
        $this->warn('');
        $this->warn('⚠ Notez bien le client_secret, il ne sera plus jamais affiché !');
    }
}