<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Aggregator;
use App\Mail\AggregatorEmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('portal.auth.login');
    }

    public function login(Request $request)

    {
        DB::setDefaultConnection('mysql_sandbox');
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (auth('aggregator')->attempt([
            'email'    => $request->email,
            'password' => $request->password,
        ])) {
            $aggregator = auth('aggregator')->user();

            if (is_null($aggregator->email_verified_at)) {
                auth('aggregator')->logout();
                return response()->json([
                    'success'    => false,
                    'message'    => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                    'unverified' => true,
                ], 401);
            }

            $request->session()->regenerate();
            return response()->json([
                'success'      => true,
                'redirect_url' => route('portal.dashboard'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Identifiants incorrects.',
        ], 401);
    }

    public function showRegister()
    {
        return view('portal.auth.register');
    }

    public function register(Request $request)
    {
         DB::setDefaultConnection('mysql_sandbox');
        $request->validate([
            'legal_name'  => 'required|string|max:255',
            'trade_name'  => 'nullable|string|max:255',
            'email'       => 'required|email|unique:mysql_money.aggregators,email',
            'phone'       => 'nullable|string|max:20',
            'webhook_url' => 'required|url',
            'password'    => 'required|confirmed|min:8',
            'terms'       => 'accepted',
        ]);

        // Référence unique
        $referenceNo = 'AGG' . strtoupper(Str::random(6));

        // Créer le user Many avec rôle aggregator
        $userId = DB::connection('mysql_sandbox')
                    ->table('users')
                    ->insertGetId([
                        'role'                 => 'aggregator',
                        'reference_account_no' => $referenceNo,
                        'first_name'           => $request->legal_name,
                        'last_name'            => '',
                        'phone'                => $request->phone ?? '+221770000000',
                        'user_status'          => 0,
                        'verification_status'  => 1,
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);

        // Créer le wallet sandbox (1 000 000 XOF fictifs)
        DB::connection('mysql_sandbox')
          ->table('wallets')
          ->insert([
              'user_id'    => $userId,
              'balance'    => 1000000.00,
              'currency'   => 'FCFA',
              'status'     => 'active',
              'version'    => 1,
              'created_at' => now(),
              'updated_at' => now(),
          ]);

        // Créer l'agrégateur
        $aggregator = Aggregator::create([
            'user_id'            => $userId,
            'legal_name'         => $request->legal_name,
            'trade_name'         => $request->trade_name,
            'email'              => $request->email,
            'phone'              => $request->phone,
            'password'           => Hash::make($request->password),
            'webhook_url'        => $request->webhook_url,
            'status'             => 'SANDBOX_ACTIVE',
            'sandbox_enabled'    => true,
            'production_enabled' => false,
            'commission_rate'    => 0.00,
        ]);

        // Envoyer email de vérification
        Mail::to($aggregator->email)
            ->send(new AggregatorEmailVerification($aggregator));

        return response()->json([
            'success'      => true,
            'message'      => 'Compte créé. Vérifiez votre email pour activer votre compte.',
            'redirect_url' => route('portal.login'),
        ]);
    }

   public function verifyEmail(Request $request, string $id)
{
    if (!$request->hasValidSignature()) {
        abort(403, 'Lien de vérification invalide ou expiré.');
    }

    $aggregator = Aggregator::findOrFail($id);

    if ($aggregator->email_verified_at) {
        return redirect()->route('portal.login')
                         ->with('success', 'Email déjà vérifié. Connectez-vous.');
    }

    $aggregator->forceFill(['email_verified_at' => now()])->save();

    return redirect()->route('portal.email.verified');
}

    public function emailVerified()
    {
        return view('portal.auth.email-verified');
    }

    public function resendVerification(Request $request)
    {
        $aggregator = auth('aggregator')->user();

        if ($aggregator->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email déjà vérifié.',
            ]);
        }

        Mail::to($aggregator->email)
            ->send(new AggregatorEmailVerification($aggregator));

        return response()->json([
            'success' => true,
            'message' => 'Email de vérification renvoyé.',
        ]);
    }

    public function logout(Request $request)
    {
        auth('aggregator')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('portal.login');
    }
    // En haut du fichier, vérifiez ces imports :

public function updatePassword(Request $request)
{
    $data = $request->validate([
        'current_password' => 'required|string',
        'password'         => 'required|string|min:8|confirmed|different:current_password',
    ]);

    $aggregator = auth('aggregator')->user();

    if (!Hash::check($data['current_password'], $aggregator->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Mot de passe actuel incorrect.',
        ], 422);
    }

    $hashed = Hash::make($data['password']);

    try {
        DB::connection('mysql_sandbox')->transaction(function () use ($aggregator, $hashed) {

            // 1. Compte sandbox (celui du login portail)
            DB::connection('mysql_sandbox')->table('aggregators')
              ->where('id', $aggregator->id)
              ->update(['password' => $hashed, 'updated_at' => now()]);

            // 2. Compte production, s'il existe déjà
            $production = DB::connection('mysql_money')->table('aggregators')
                            ->where('email', $aggregator->email)
                            ->first();

            if ($production) {
                DB::connection('mysql_money')->transaction(function () use ($production, $hashed) {

                    DB::connection('mysql_money')->table('aggregators')
                      ->where('id', $production->id)
                      ->update(['password' => $hashed, 'updated_at' => now()]);

                    // mpin = mot de passe : sert à confirmer disbursements et bulks
                    DB::connection('mysql_money')->table('users')
                      ->where('id', $production->user_id)
                      ->update([
                          'password'   => $hashed,
                          'mpin'       => $hashed,
                          'updated_at' => now(),
                      ]);
                });
            }
        });
    } catch (\Throwable $e) {
        Log::error('updatePassword échoué : ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Impossible de mettre à jour le mot de passe. Réessayez.',
        ], 500);
    }

    return response()->json([
        'success' => true,
        'message' => 'Mot de passe mis à jour. Il sert aussi de PIN pour confirmer vos décaissements et paiements de masse.',
    ]);
}
}