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

// Redirection page d'accueil
Route::get('/', fn() => redirect()->route('portal.login'));