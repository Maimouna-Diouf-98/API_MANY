<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Non connecté : login admin sous /backoffice, login portail ailleurs
        $middleware->redirectGuestsTo(fn (Request $request) =>
            $request->is('backoffice*') ? route('admin.login') : route('portal.login'));

        // Déjà connecté : dashboard correspondant
        $middleware->redirectUsersTo(fn (Request $request) =>
            $request->is('backoffice*') ? route('admin.dashboard') : route('portal.dashboard'));

        $middleware->alias([
            'resolve.env'   => \App\Http\Middleware\ResolveEnvironmentConnection::class,
            'verify.bearer' => \App\Http\Middleware\VerifyBearerToken::class,
            'verify.hmac'   => \App\Http\Middleware\VerifyHmacSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();