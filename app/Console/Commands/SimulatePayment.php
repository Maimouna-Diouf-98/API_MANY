<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SimulatePayment extends Command
{
    protected $signature = 'many:simulate-payment 
                            {phone? : Numéro du client Many (ex: +221774825025)}
                            {amount=5000 : Montant en XOF}
                            {merchant=Supermarché Maya : Nom du marchand}';

    protected $description = 'Simule une demande de paiement marchand (Collection Many API) pour tester l\'app mobile';

    public function handle(): int
    {
        $rawPhone = $this->argument('phone') ?: '+221774825025';
        $amount = (int) $this->argument('amount');
        $merchantName = $this->argument('merchant');

        $normalizedPhone = str_replace([' ', '-', '.'], '', $rawPhone);
        if (!str_starts_with($normalizedPhone, '+')) {
            $normalizedPhone = '+221' . ltrim($normalizedPhone, '0');
        }

        $this->info("--------------------------------------------------");
        $this->info("  Simulation de Paiement Many API (Collection)");
        $this->info("--------------------------------------------------");
        $this->line("Client   : <comment>{$normalizedPhone}</comment>");
        $this->line("Montant  : <comment>{$amount} XOF</comment>");
        $this->line("Marchand : <comment>{$merchantName}</comment>");

        // 1. Trouver le client dans la base money
        $customer = DB::connection('mysql_money')->table('users')
            ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalizedPhone])
            ->first();

        if (!$customer) {
            $this->error("Aucun utilisateur trouvé avec le numéro {$normalizedPhone} dans la base de données.");
            $this->warn("Voici quelques numéros existants dans votre base :");
            $users = DB::connection('mysql_money')->table('users')
                ->where('role', 'user')
                ->select('phone', 'first_name', 'last_name')
                ->limit(5)
                ->get();
            foreach ($users as $u) {
                $this->line(" - {$u->phone} ({$u->first_name} {$u->last_name})");
            }
            return 1;
        }

        // 2. Trouver ou créer un agrégateur de test
        $aggregator = DB::connection('mysql_money')->table('aggregators')->first();
        if (!$aggregator) {
            $aggregatorId = (string) Str::uuid();
            DB::connection('mysql_money')->table('aggregators')->insert([
                'id'                 => $aggregatorId,
                'user_id'            => $customer->id,
                'legal_name'         => 'Many Aggregator SARL',
                'trade_name'         => 'Many Aggregator',
                'email'              => 'contact@manyaggregator.sn',
                'phone'              => '+221330000000',
                'password'           => bcrypt('password'),
                'status'             => 'PRODUCTION_ACTIVE',
                'sandbox_enabled'    => 1,
                'production_enabled' => 1,
                'webhook_url'        => 'https://api.many.sn/webhook',
                'ip_whitelist'       => json_encode([]),
                'commission_rate'    => 0.01,
                'activated_at'       => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
            $aggregator = DB::connection('mysql_money')->table('aggregators')->where('id', $aggregatorId)->first();
        }

        // 3. Trouver ou créer une application
        $app = DB::connection('mysql_money')->table('applications')->where('aggregator_id', $aggregator->id)->first();
        if (!$app) {
            $appId = (string) Str::uuid();
            DB::connection('mysql_money')->table('applications')->insert([
                'id'            => $appId,
                'aggregator_id' => $aggregator->id,
                'name'          => 'Default POS',
                'client_id'     => 'agg_live_' . Str::random(16),
                'client_secret' => encrypt(Str::random(48)),
                'environment'   => 'PRODUCTION',
                'webhook_url'   => 'https://api.many.sn/webhook',
                'is_active'     => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $app = DB::connection('mysql_money')->table('applications')->where('id', $appId)->first();
        }

        // 4. Trouver ou créer le sous-marchand
        $subMerchant = DB::connection('mysql_money')->table('sub_merchants')
            ->where('application_id', $app->id)
            ->where('legal_name', $merchantName)
            ->first();

        if (!$subMerchant) {
            $subMerchantId = (string) Str::uuid();
            DB::connection('mysql_money')->table('sub_merchants')->insert([
                'id'             => $subMerchantId,
                'application_id' => $app->id,
                'legal_name'     => $merchantName,
                'business_type'  => 'Commerce',
                'address'            => 'Dakar, Sénégal',
                'webhook_url'        => 'https://api.many.sn/webhook',
                'external_reference' => 'EXT-' . Str::random(8),
                'status'             => 'ACTIVE',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $subMerchant = DB::connection('mysql_money')->table('sub_merchants')->where('id', $subMerchantId)->first();
        }

        // 5. Créer la transaction PENDING
        $transactionId = (string) rand(1000000000, 9999999999);
        $orderRef = 'CMD-' . rand(1000, 9999);
        $fee = (int) round($amount * 0.01);
        $net = $amount - $fee;

        DB::connection('mysql_money')->table('transactions')->insert([
            'sender_id'         => $customer->id,
            'receiver_id'       => $aggregator->user_id ?: $customer->id,
            'transaction_id'    => $transactionId,
            'reference_no'      => strtoupper(Str::random(16)),
            'sub_merchant_id'   => $subMerchant->id,
            'aggregator_id'     => $aggregator->id,
            'application_id'    => $app->id,
            'api_type'          => 'COLLECTION',
            'idempotency_key'   => 'SIM-' . Str::random(10),
            'order_reference'   => $orderRef,
            'customer_phone'    => $normalizedPhone,
            'amount'            => $amount,
            'transaction_fee'   => $fee,
            'many_fee'          => $fee,
            'net_to_aggregator' => $net,
            'currency'          => 'XOF',
            'status'            => 'pending',
            'transaction_date'  => now()->format('d M Y'),
            'transaction_time'  => now()->format('h:i A'),
            'transaction_mode'  => 'normal',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // 6. Générer le payload QR Code
        $qrPayload = [
            'type'            => 'MANY_PAYMENT',
            'transaction_id'  => $transactionId,
            'amount'          => $amount,
            'currency'        => 'XOF',
            'sub_merchant_id' => $subMerchant->id,
            'name'            => $merchantName,
            'order_reference' => $orderRef,
            'expires_at'      => now()->addMinutes(10)->timestamp,
        ];

        $jsonPayload = json_encode($qrPayload);
        $svgQr = QrCode::format('svg')->size(280)->margin(2)->generate($jsonPayload);

        // 7. Générer une page web test-qr.html pour affichage facile du QR Code
        $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Many - {$merchantName}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f7fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); padding: 32px; max-width: 380px; width: 100%; text-align: center; }
        .tag { display: inline-block; background: #FDE8F3; color: #B13A7E; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; margin-bottom: 12px; }
        .merchant { font-size: 22px; font-weight: 800; color: #1a202c; margin: 0 0 4px; }
        .ref { font-size: 13px; color: #718096; margin: 0 0 16px; }
        .amount { font-size: 32px; font-weight: 800; color: #B13A7E; margin: 0 0 20px; }
        .qr-wrapper { background: white; padding: 12px; border-radius: 16px; border: 2px dashed #E2E8F0; display: inline-block; margin-bottom: 20px; }
        .instructions { font-size: 13px; color: #4a5568; line-height: 1.5; background: #edf2f7; padding: 12px; border-radius: 12px; }
        .timer { font-size: 12px; color: #e53e3e; font-weight: 600; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="tag">Demande de Paiement Many</span>
        <h1 class="merchant">{$merchantName}</h1>
        <p class="ref">Réf: {$orderRef}</p>
        <div class="amount">{$amount} XOF</div>

        <div class="qr-wrapper">
            {$svgQr}
        </div>

        <div class="instructions">
            Ouvrez l'application <strong>Many</strong> sur votre téléphone, allez dans <strong>Scanner</strong> et visez ce QR Code.
        </div>
        <div class="timer">Valable 10 minutes</div>
    </div>
</body>
</html>
HTML;

        $publicHtmlPath = public_path('test-qr.html');
        file_put_contents($publicHtmlPath, $htmlContent);

        $this->newLine();
        $this->info("Demande de paiement créée avec succès !");
        $this->line("  ID Transaction : <info>{$transactionId}</info>");
        $this->line("  Référence      : <info>{$orderRef}</info>");
        $this->line("  Expire dans    : 10 minutes");
        $this->newLine();

        $this->comment("==================================================");
        $this->comment("           COMMENT TESTER SUR L'APPLICATION        ");
        $this->comment("==================================================");
        $this->line("1. TEST PAR BANDEAU D'ACCUEIL (Polling) :");
        $this->line("   - Connectez-vous sur l'app avec le numéro : <info>{$normalizedPhone}</info>");
        $this->line("   - Restez sur la page d'accueil ou tirez pour actualiser.");
        $this->line("   - Le bandeau rose/orange 'Paiement en attente' apparaît.");
        $this->line("   - Cliquez sur 'Régler' et saisissez votre code secret.");
        $this->newLine();
        $this->line("2. TEST PAR SCAN DU QR CODE :");
        $this->line("   - Ouvrez dans votre navigateur : <info>http://localhost:8000/test-qr.html</info>");
        $this->line("   - Ouvrez l'app mobile Many, allez dans l'onglet 'Scanner'.");
        $this->line("   - Visez le QR Code affiché sur votre écran d'ordinateur !");
        $this->comment("==================================================");

        return 0;
    }
}
