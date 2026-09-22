<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SubMerchantController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\DisbursementController;
use App\Http\Controllers\Api\V1\BulkPaymentController;
use App\Http\Controllers\Api\V1\BalanceController;

// Token (résolution env nécessaire pour choisir la bonne base)
Route::prefix('v1')->middleware(['resolve.env'])->group(function () {
    Route::post('/auth/token', [AuthController::class, 'token']);
});

// Routes protégées token seulement (lecture)
Route::prefix('v1')->middleware(['resolve.env', 'verify.bearer'])->group(function () {
    Route::get('/balance', [BalanceController::class, 'show']);

    Route::get('/sub-merchants',      [SubMerchantController::class, 'index']);
    Route::get('/sub-merchants/{id}', [SubMerchantController::class, 'show']);

    Route::get('/transactions',              [TransactionController::class, 'index']);
    Route::get('/transactions/{id}',         [TransactionController::class, 'show']);

    Route::get('/disbursements',        [DisbursementController::class, 'index']);
    Route::get('/disbursements/{id}',   [DisbursementController::class, 'show']);
    Route::post('/disbursements/{id}/confirm', [DisbursementController::class, 'confirm']);

    Route::get('/bulk-payments',        [BulkPaymentController::class, 'index']);
    Route::get('/bulk-payments/{id}',   [BulkPaymentController::class, 'show']);
    Route::post('/bulk-payments/{id}/confirm',  [BulkPaymentController::class, 'confirm']);
    //hmac
    Route::post('/sub-merchants',               [SubMerchantController::class, 'store']);
    Route::post('/sub-merchants/{id}/activate', [SubMerchantController::class, 'activate']);
    Route::post('/sub-merchants/{id}/suspend',  [SubMerchantController::class, 'suspend']);
    Route::post('/sub-merchants/{id}/close',    [SubMerchantController::class, 'close']);

    Route::post('/transactions',                [TransactionController::class, 'store']);
    Route::post('/transactions/{id}/refund',    [TransactionController::class, 'refund']);

    Route::post('/disbursements',               [DisbursementController::class, 'store']);

    Route::post('/bulk-payments',               [BulkPaymentController::class, 'store']);
});

// Routes protégées token + HMAC (opérations financières)
Route::prefix('v1')->middleware(['resolve.env', 'verify.bearer', 'verify.hmac'])->group(function () {

});
// Route::get('/debug-key', function () {
//     return response()->json(['key' => substr(config('app.key'), 0, 15)]);
// });
// Public — appelé par l'app mobile money
Route::get('/v1/transactions/pending/{phone}',
    [\App\Http\Controllers\Api\V1\TransactionController::class, 'pendingForCustomer']);

Route::post('/v1/transactions/{transactionId}/confirm',
    [\App\Http\Controllers\Api\V1\TransactionController::class, 'confirm']);