<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserHasRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands(__DIR__.'/../app/Console/Commands')
    ->withMiddleware(function (Middleware $middleware): void {
        // Authentication uses Sanctum personal access tokens (Bearer); browser stateful
        // middleware is intentionally disabled to avoid CSRF/session mismatch on API login.
        $middleware->alias(['role' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep Laravel's standard exception handling; API validation returns JSON.
    })->create();
