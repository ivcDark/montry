<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Services\MetricsRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class MetricsController
{
    public function __construct(private MetricsRecorder $metrics)
    {
    }

    public function __invoke(Request $request): Response
    {
        if (! (bool) config('observability.metrics.enabled', true)) {
            throw new NotFoundHttpException();
        }

        abort_unless($this->isAllowed($request), 403);

        return response($this->metrics->render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function isAllowed(Request $request): bool
    {
        $token = config('observability.metrics.token');

        if (is_string($token) && $token !== '') {
            $candidate = $request->bearerToken() ?: $request->headers->get('X-Montry-Metrics-Token');

            return is_string($candidate) && hash_equals($token, $candidate);
        }

        $allowedIps = config('observability.metrics.allowed_ips', []);

        return is_array($allowedIps) && IpUtils::checkIp($request->ip() ?: '', $allowedIps);
    }
}
