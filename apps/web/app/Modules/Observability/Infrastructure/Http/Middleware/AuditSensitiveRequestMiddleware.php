<?php

namespace App\Modules\Observability\Infrastructure\Http\Middleware;

use App\Modules\Observability\Application\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AuditSensitiveRequestMiddleware
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 429 && $this->isSensitive($request)) {
            $this->audit->record(
                category: 'security',
                action: 'rate_limit.hit',
                outcome: 'blocked',
                request: $request,
                actorUserId: $request->user()?->id,
                source: $request->is('internal/*') ? 'internal_api' : 'web',
                metadata: [
                    'status_code' => 429,
                ],
            );
        }

        return $response;
    }

    private function isSensitive(Request $request): bool
    {
        return $request->is('login')
            || $request->is('register*')
            || $request->is('admin/*')
            || $request->is('billing/payments/*')
            || $request->is('billing/robokassa/*')
            || $request->is('internal/*');
    }
}

