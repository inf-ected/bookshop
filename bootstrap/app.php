<?php

declare(strict_types=1);

use App\Http\Middleware\CheckNotBanned;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'check.not.banned' => CheckNotBanned::class,
        ]);

        $middleware->appendToGroup('web', CheckNotBanned::class);

        // Exempt Stripe webhook from CSRF verification (Rule 35 — signature verified inside controller)
        $middleware->validateCsrfTokens(except: [
            '/webhooks/stripe',
        ]);
    })
    ->withCommands([
        __DIR__.'/../app/Features/Pages/Commands',
        __DIR__.'/../app/Features/Cart/Commands',
        __DIR__.'/../app/Features/Checkout/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
