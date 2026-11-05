<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'cek.visibilitas'    => \App\Http\Middleware\CekVisibilitasDokumen::class,
            'password.changed'   => \App\Http\Middleware\EnsurePasswordIsChanged::class,
        ]);
        $middleware->web([
            HandleCors::class,
            TrustProxies::class,
            \App\Http\Middleware\ApplyActiveRole::class,
            \App\Http\Middleware\CacheUserPermissions::class,
        ]);
         // Trust proxy headers (X-Forwarded-Proto, etc.) so Livewire signed upload URLs match request URL
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
