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
        $middleware->redirectGuestsTo(fn () => route('portal.login'));

        $middleware->validateCsrfTokens(except: [
            'test-pos/*',
        ]);

        $middleware->alias([
            'resolve.env'    => \App\Http\Middleware\ResolveEnvironmentConnection::class,
            'verify.bearer'  => \App\Http\Middleware\VerifyBearerToken::class,
            'verify.hmac'    => \App\Http\Middleware\VerifyHmacSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

