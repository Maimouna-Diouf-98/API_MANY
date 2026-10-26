<?php

namespace App\Console\Commands;

use App\Domain\Auth\Services\ProductionActivationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ActivateAggregatorProduction extends Command
{
    protected $signature = 'aggregator:activate-production
                            {email : Email de l\'agrégateur inscrit en sandbox}
                            {--commission=0.00 : Taux de commission}
                            {--no-mail : Ne pas envoyer l\'email de credentials}';

    protected $description = 'Active un agrégateur sandbox en production (alternative à l\'espace admin)';

    public function handle(ProductionActivationService $service): int
    {
        $email = $this->argument('email');

        $sandboxAgg = DB::connection('mysql_sandbox')
                        ->table('aggregators')
                        ->where('email', $email)
                        ->first();

        if (!$sandboxAgg) {
            $this->error("Agrégateur introuvable en sandbox : {$email}");
            return self::FAILURE;
        }

        try {
            $c = $service->activate($sandboxAgg, (float) $this->option('commission'));
        } catch (RuntimeException $e) {
            $this->error(ProductionActivationService::errorMessage($e->getMessage()));
            return self::FAILURE;
        }

        $service->closePendingRequests($sandboxAgg->id);

        if (!$this->option('no-mail')) {
            $this->line($service->sendCredentials($c)
                ? "📧 Credentials envoyés à {$email}"
                : '⚠ Email non envoyé (voir laravel.log)');
        }

        $this->info('✅ Agrégateur activé en production.');
        $this->table(['Champ', 'Valeur'], [
            ['Entreprise',    $c['legal_name']],
            ['Email',         $c['email']],
            ['Mot de passe',  $c['password'] . '  (= mpin)'],
            ['client_id',     $c['client_id']],
            ['client_secret', $c['client_secret']],
            ['Commission',    $c['commission'] . '%'],
            ['fcm_token',     $c['has_fcm_token'] ? 'copié depuis le sandbox' : '⚠ absent'],
        ]);
        $this->warn('Notez le client_secret : il ne sera plus affiché.');

        return self::SUCCESS;
    }
}
