<?php

use App\Http\Middleware\AdminBasicAuth;
use App\Http\Middleware\EnforceSessionState;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyOpinionPadiToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'journey.state' => EnforceSessionState::class,
            'op.token' => VerifyOpinionPadiToken::class,
            'admin.basic' => AdminBasicAuth::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Trust X-Forwarded-* headers only when deployed behind a known
        // proxy (comma-separated IPs/CIDRs, or '*'). Required on hosts like
        // Vercel for correct client IPs, HTTPS detection and signed URLs.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
