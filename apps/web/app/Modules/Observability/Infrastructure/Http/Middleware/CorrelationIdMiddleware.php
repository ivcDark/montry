<?php

namespace App\Modules\Observability\Infrastructure\Http\Middleware;

use App\Modules\Observability\Application\Context\CorrelationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final readonly class CorrelationIdMiddleware
{
    public function __construct(
        private CorrelationContext $correlationContext,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->resolveCorrelationId($request);

        $this->correlationContext->set($correlationId);
        $request->attributes->set('correlation_id', $correlationId);

        Log::withContext([
            'correlation_id' => $correlationId,
        ]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }

    private function resolveCorrelationId(Request $request): string
    {
        $header = trim((string) $request->headers->get('X-Correlation-ID', ''));

        if ($header !== '' && strlen($header) <= 128) {
            return $header;
        }

        return (string) Str::uuid();
    }
}

