<?php

namespace App\Modules\Observability\Infrastructure\Http\Middleware;

use App\Modules\Observability\Application\Services\MetricsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class MetricsMiddleware
{
    public function __construct(private MetricsRecorder $metrics)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = null;

        try {
            $response = $next($request);

            return $response;
        } finally {
            if (! $request->is('internal/metrics')) {
                $durationSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
                $route = $request->route();
                $routeName = $route?->getName();
                $routeLabel = $routeName ?: ($route?->uri() ?: 'unmatched');
                $statusCode = $response?->getStatusCode() ?? 500;

                $labels = [
                    'method' => $request->getMethod(),
                    'route' => $routeLabel,
                    'status_class' => ((int) floor($statusCode / 100)) . 'xx',
                ];

                $this->metrics->observeHttpRequest($labels, $durationSeconds);

                if ($request->is('internal/*')) {
                    $this->metrics->observeInternalApiRequest($labels, $durationSeconds);
                }
            }
        }
    }
}
