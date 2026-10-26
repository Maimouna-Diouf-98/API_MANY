<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Portal\ApplicationController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\SubMerchantController as PortalSubMerchantController;
use App\Http\Controllers\Portal\TransactionController as PortalTransactionController;


// ─── Portail Agrégateur ───────────────────────────────
Route::prefix('portal')->name('portal.')->group(function () {

    // Pages publiques (non connecté)
    Route::get('/login', [PortalAuthController::class, 'showLogin'])
         ->name('login');
    Route::post('/login', [PortalAuthController::class, 'login']);

    Route::get('/register', [PortalAuthController::class, 'showRegister'])
     ->name('register');
    Route::post('/register', [PortalAuthController::class, 'register']);

    Route::post('/logout', [PortalAuthController::class, 'logout'])
         ->name('logout');

Route::middleware('auth:aggregator')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
         ->name('dashboard');
     Route::post('/dashboard/request-production',
         [DashboardController::class, 'requestProduction'])
         ->name('dashboard.request.production');

    // Applications
    Route::get('/applications', [ApplicationController::class, 'index'])
         ->name('applications.index');
    Route::post('/applications', [ApplicationController::class, 'store'])
         ->name('applications.store');
    Route::get('/applications/{id}', [ApplicationController::class, 'show'])
         ->name('applications.show');
    Route::delete('/applications/{id}', [ApplicationController::class, 'destroy'])
         ->name('applications.destroy');

    // Sous-marchands imbriqués dans applications
    Route::prefix('applications/{appId}/sub-merchants')
         ->name('sub-merchants.')
         ->group(function () {
             Route::post('/', [PortalSubMerchantController::class, 'store'])
                  ->name('store');
             Route::get('/{smId}', [PortalSubMerchantController::class, 'show'])
                  ->name('show');
             Route::post('/{smId}/activate', [PortalSubMerchantController::class, 'activate'])
                  ->name('activate');
             Route::post('/{smId}/suspend', [PortalSubMerchantController::class, 'suspend'])
                  ->name('suspend');
             Route::post('/{smId}/close', [PortalSubMerchantController::class, 'close'])
                  ->name('close');
         });

    // Transactions
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [PortalTransactionController::class, 'index'])
             ->name('index');
        Route::get('/{transactionId}', [PortalTransactionController::class, 'show'])
             ->name('show');
    });


}); // fin auth:aggregator



    // Vérification email
    Route::get('/verify-email/{id}', [PortalAuthController::class, 'verifyEmail'])
         ->name('verify.email')
         ->middleware('signed');

    Route::get('/email-verified', [PortalAuthController::class, 'emailVerified'])
         ->name('email.verified');

    Route::post('/resend-verification', [PortalAuthController::class, 'resendVerification'])
         ->name('resend.verification')
         ->middleware('auth:aggregator');
});

// Redirection page d'accueil et alias de secours pour la route login
Route::get('/', fn() => redirect()->route('portal.login'));

// ─── Test POS / Paiement Marchand pour l'Application Mobile ───
Route::post('/test-pos/create', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\DB::setDefaultConnection('mysql_money');
    $aggregator = \App\Domain\Auth\Models\Aggregator::on('mysql_money')->first();
    $subMerchant = \App\Domain\SubMerchants\Models\SubMerchant::on('mysql_money')->first();

    if (!$aggregator || !$subMerchant) {
        return response()->json(['error' => 'Marchand ou agrégateur introuvable.'], 500);
    }

    $amount = (int) $request->input('amount', 5000);
    $phone = (string) $request->input('phone', '+221774874628');
    $ref = $request->input('order_reference') ?: ('CMD-' . rand(1000, 9999));

    $service = app(\App\Domain\Transactions\Services\TransactionService::class);
    $result = $service->collection([
        'sub_merchant_id' => $subMerchant->id,
        'amount'          => $amount,
        'currency'        => 'XOF',
        'customer_phone'  => $phone,
        'order_reference' => $ref,
    ], $aggregator, 'PRODUCTION');

    return response()->json([
        'transaction_id'  => $result['transaction']->transaction_id,
        'order_reference' => $ref,
        'merchant_name'   => $subMerchant->legal_name ?? 'Maya Store SARL',
        'amount'          => $amount,
        'currency'        => 'XOF',
        'qr_code'         => $result['qr_code'],
        'status'          => strtoupper($result['transaction']->status),
    ]);
});

Route::get('/test-pos/status/{id}', function (string $id) {
    $tx = \App\Domain\Transactions\Models\RealTransaction::where('transaction_id', $id)->first();
    return response()->json([
        'transaction_id' => $id,
        'status'         => strtoupper($tx?->status ?? 'NOT_FOUND'),
        'updated_at'     => $tx?->updated_at,
    ]);
});
