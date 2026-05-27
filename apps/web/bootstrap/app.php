<?php

use App\Http\Middleware\EnsureUserIsNotBlocked;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Observability\Infrastructure\Http\Middleware\CorrelationIdMiddleware;
use App\Modules\Observability\Infrastructure\Http\Middleware\AuditSensitiveRequestMiddleware;
use App\Modules\Observability\Infrastructure\Http\Middleware\MetricsMiddleware;
use App\Modules\Observability\Infrastructure\Http\Middleware\SentryContextMiddleware;
use App\Modules\Observability\Infrastructure\Http\Middleware\TracingMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CorrelationIdMiddleware::class);
        $middleware->append(SentryContextMiddleware::class);
        $middleware->append(TracingMiddleware::class);
        $middleware->append(MetricsMiddleware::class);
        $middleware->append(AuditSensitiveRequestMiddleware::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureUserIsNotBlocked::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        SentryIntegration::handles($exceptions);
    })->create();
