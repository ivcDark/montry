<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Http\Middleware;

use App\Modules\Observability\Infrastructure\Tracing\OpenTelemetryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class TracingMiddleware
{
    public function __construct(
        private OpenTelemetryService $tracer,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $span = $this->tracer->startSpan('http.request', [
            'http.request.method' => $request->method(),
            'url.path' => $request->path(),
            'http.route' => $request->route()?->getName(),
        ], OpenTelemetryService::SPAN_KIND_SERVER, $request->headers->get('traceparent'));

        $request->attributes->set('traceparent', $span->traceparent());

        try {
            /** @var Response $response */
            $response = $next($request);
            $response->headers->set('traceparent', $this->tracer->currentTraceparent() ?? $span->traceparent());
            $span->end($response->isSuccessful() ? 'STATUS_CODE_OK' : 'STATUS_CODE_ERROR');

            return $response;
        } catch (Throwable $exception) {
            $span->end('STATUS_CODE_ERROR');

            throw $exception;
        }
    }
}
