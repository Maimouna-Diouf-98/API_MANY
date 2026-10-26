<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Admin;
use App\Mail\SandboxCredentialsMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Création d'un agrégateur en phase test (sandbox) par un admin.
 * Même résultat que /portal/register, avec un mot de passe généré
 * et un email déjà considéré comme vérifié.
 */
class SandboxAggregatorService
{
    /** Durée de validité du lien de vérification d'email. */
    private const VERIFICATION_HOURS = 72;

    public function create(array $data, ?Admin $admin = null): array
    {
        $sandbox = DB::connection('mysql_sandbox');
        $money   = DB::connection('mysql_money');

        $email = trim($data['email']);
        $phone = trim($data['phone']);

        // Email déjà pris (sandbox ou production)
        if ($sandbox->table('aggregators')->where('email', $email)->exists()
            || $money->table('aggregators')->where('email', $email)->exists()) {
            throw new RuntimeException('EMAIL_ALREADY_USED');
        }

        // Téléphone : doit rester libre pour pouvoir activer l'agrégateur en production plus tard
        $normalized = str_replace(' ', '', $phone);

        $phoneUsed = $money->table('users')
                           ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalized])
                           ->whereNull('deleted_at')->exists()
                  || $sandbox->table('aggregators')
                             ->whereRaw("REPLACE(phone, ' ', '') = ?", [$normalized])->exists();

        if ($phoneUsed) {
            throw new RuntimeException('PHONE_ALREADY_USED');
        }

        $password     = Str::random(12);
        $hashed       = Hash::make($password);
        $aggregatorId = (string) Str::uuid();

        $sandbox->transaction(function () use ($sandbox, $data, $email, $phone, $hashed, $aggregatorId) {

            $userId = $sandbox->table('users')->insertGetId([
                'role'                 => 'aggregator',
                'reference_account_no' => 'AGG' . strtoupper(Str::random(6)),
                'first_name'           => $data['legal_name'],
                'last_name'            => '',
                'phone'                => $phone,
                'user_status'          => 0,
                'verification_status'  => 1,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            // Wallet sandbox : 1 000 000 XOF fictifs
            $sandbox->table('wallets')->insert([
                'user_id'    => $userId,
                'balance'    => 1000000.00,
                'currency'   => 'FCFA',
                'status'     => 'active',
                'version'    => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sandbox->table('aggregators')->insert([
                'id'                 => $aggregatorId,
                'user_id'            => $userId,
                'legal_name'         => $data['legal_name'],
                'trade_name'         => $data['trade_name'] ?? null,
                'email'              => $email,
                'phone'              => $phone,
                'password'           => $hashed,
                'webhook_url'        => $data['webhook_url'],
                'status'             => 'SANDBOX_ACTIVE',
                'sandbox_enabled'    => true,
                'production_enabled' => false,
                'commission_rate'    => 0.00,
                'email_verified_at'  => null,    // l'agrégateur doit vérifier son email avant de se connecter
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        });

        Log::info('Agrégateur sandbox créé par un admin', [
            'admin_id'      => $admin?->id,
            'aggregator_id' => $aggregatorId,
            'email'         => $email,
        ]);

        return [
            'aggregator_id'     => $aggregatorId,
            'legal_name'        => $data['legal_name'],
            'email'             => $email,
            'password'          => $password,
            'verification_url'  => $this->verificationUrl($aggregatorId),
            'verification_hours' => self::VERIFICATION_HOURS,
        ];
    }

    /**
     * Renvoie les accès tant que l'email n'est pas vérifié : le mot de passe d'origine
     * n'est pas récupérable (il est haché), donc un nouveau est généré avec un nouveau lien.
     */
    public function resendAccess(string $aggregatorId, ?Admin $admin = null): array
    {
        return DB::connection('mysql_sandbox')->transaction(function () use ($aggregatorId, $admin) {

            $agg = DB::connection('mysql_sandbox')->table('aggregators')
                     ->where('id', $aggregatorId)
                     ->lockForUpdate()
                     ->first();

            if (!$agg) {
                throw new RuntimeException('AGGREGATOR_NOT_FOUND');
            }
            if (!empty($agg->email_verified_at)) {
                throw new RuntimeException('ALREADY_VERIFIED');
            }

            $password = Str::random(12);

            DB::connection('mysql_sandbox')->table('aggregators')
              ->where('id', $agg->id)
              ->update(['password' => Hash::make($password), 'updated_at' => now()]);

            Log::info('Accès sandbox renvoyés par un admin', [
                'admin_id'      => $admin?->id,
                'aggregator_id' => $agg->id,
            ]);

            return [
                'aggregator_id'      => $agg->id,
                'legal_name'         => $agg->legal_name,
                'email'              => $agg->email,
                'password'           => $password,
                'verification_url'   => $this->verificationUrl($agg->id),
                'verification_hours' => self::VERIFICATION_HOURS,
            ];
        });
    }

    /** Lien signé vers la route portal.verify.email (la même que celle de l'inscription). */
    public function verificationUrl(string $aggregatorId): string
    {
        return URL::temporarySignedRoute(
            'portal.verify.email',
            now()->addHours(self::VERIFICATION_HOURS),
            ['id' => $aggregatorId]
        );
    }

    /** Envoie les accès à l'agrégateur. Retourne false si l'email échoue. */
    public function sendCredentials(array $c): bool
    {
        try {
            Mail::to($c['email'])->send(new SandboxCredentialsMail(
                legalName:       $c['legal_name'],
                email:           $c['email'],
                password:        $c['password'],
                portalUrl:       url('/portal/login'),
                verificationUrl: $c['verification_url'],
                validHours:      $c['verification_hours'],
            ));
            return true;
        } catch (Throwable $e) {
            Log::error('Envoi accès sandbox échoué : ' . $e->getMessage());
            return false;
        }
    }

    public static function errorMessage(string $code): string
    {
        return match ($code) {
            'EMAIL_ALREADY_USED' => 'Un agrégateur existe déjà avec cet email (sandbox ou production).',
            'PHONE_ALREADY_USED' => 'Ce numéro de téléphone est déjà utilisé : il bloquerait le passage en production.',
            'AGGREGATOR_NOT_FOUND' => 'Agrégateur introuvable.',
            'ALREADY_VERIFIED'   => "Cet agrégateur a déjà vérifié son email : ses accès ne peuvent plus être renvoyés par ce moyen.",
            default              => 'Erreur interne : ' . $code,
        };
    }
}
