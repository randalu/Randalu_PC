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
    ->withMiddleware(function (Middleware $middleware): void {
        // Only trust forwarded headers when TRUSTED_PROXIES is configured
        // (e.g. behind CyberPanel/Cloudflare). Without this, IP-based rate
        // limits and event logs would record the proxy's IP.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_filter(array_map('trim', explode(',', (string) $proxies))));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
