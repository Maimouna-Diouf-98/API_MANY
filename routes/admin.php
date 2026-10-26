<?php

use App\Http\Controllers\Admin\AggregatorController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProductionRequestController;
use App\Http\Middleware\EnsureAdminIsActive;
use Illuminate\Support\Facades\Route;

// URL en /backoffice (le dossier public/Admin bloquait /admin).
// Les noms de routes restent admin.* : vues et contrôleurs inchangés.
Route::prefix('backoffice')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])
             ->middleware('throttle:5,1')->name('login.submit');
    });

    Route::middleware(['auth:admin', EnsureAdminIsActive::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Demandes de passage en production
        Route::get('/requests/{id}',                 [ProductionRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{id}/approve',        [ProductionRequestController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{id}/reject',         [ProductionRequestController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{id}/mark-approved',  [ProductionRequestController::class, 'markApproved'])->name('requests.markApproved');

        // Création d'un agrégateur en phase test
        Route::get('/aggregators/create', [AggregatorController::class, 'create'])->name('aggregators.create');
        Route::post('/aggregators',       [AggregatorController::class, 'store'])->name('aggregators.store');
        Route::post('/aggregators/{id}/resend-access', [AggregatorController::class, 'resendAccess'])->name('aggregators.resendAccess');

        // Onglets agrégateurs (production / sandbox)
        Route::get('/aggregators/{env}', [AggregatorController::class, 'index'])
             ->whereIn('env', ['production', 'sandbox'])->name('aggregators.index');
        Route::get('/aggregators/{env}/{id}', [AggregatorController::class, 'show'])
             ->whereIn('env', ['production', 'sandbox'])->name('aggregators.show');

        // Notifications
        Route::get('/notifications/count',     [NotificationController::class, 'count'])->name('notifications.count');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    });
});
