<?php

namespace App\Modules\Observability\Infrastructure\Http\Middleware;

use App\Modules\Observability\Application\Context\CorrelationContext;
use Closure;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

final readonly class SentryContextMiddleware
{
    public function __construct(private CorrelationContext $correlationContext)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Integration::configureScope(function (Scope $scope) use ($request): void {
            $scope->setTag('service', 'laravel');
            $scope->setTag('correlation_id', $this->correlationContext->id());
            $scope->setTag('route', (string) ($request->route()?->getName() ?? $request->path()));

            $user = $request->user();

            if ($user !== null) {
                $scope->setUser([
                    'id' => (string) $user->id,
                ]);
            }

            $scope->setContext('montry', array_filter([
                'correlation_id' => $this->correlationContext->id(),
                'route' => $request->route()?->getName(),
                'method' => $request->getMethod(),
            ]));
        });

        return $next($request);
    }
}

