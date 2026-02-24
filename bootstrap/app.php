<?php

use App\Http\Middleware\EnsureAccountIsNotSuspended;
use App\Providers\RoleRouteServiceProvider;
use App\Providers\SuspendedRouteServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'ensure.not.suspended' => EnsureAccountIsNotSuspended::class
        ]);
    })
    ->withProviders([
        RoleRouteServiceProvider::class,
        SuspendedRouteServiceProvider::class
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
