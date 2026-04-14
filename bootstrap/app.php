<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust Caddy / Nginx in front of us so Laravel sees the original
        // scheme (https), host, and client IP from X-Forwarded-* headers.
        // We trust any upstream — our Caddy + Nginx live in the docker
        // network in front of PHP-FPM and terminate TLS.
        $middleware->trustProxies(at: ['0.0.0.0/0', '::/0']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
