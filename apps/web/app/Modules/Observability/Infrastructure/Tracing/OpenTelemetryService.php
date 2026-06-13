<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Tracing;

use App\Modules\Observability\Application\Context\TraceContext;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class OpenTelemetryService
{
    public const SPAN_KIND_INTERNAL = 1;
    public const SPAN_KIND_SERVER = 2;
    public const SPAN_KIND_CLIENT = 3;
    public const SPAN_KIND_PRODUCER = 4;
    public const SPAN_KIND_CONSUMER = 5;

    public function __construct(
        private TraceContext $traceContext,
    ) {
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function startSpan(string $name, array $attributes = [], int $kind = self::SPAN_KIND_INTERNAL, ?string $traceparent = null): OpenTelemetrySpan
    {
        [$traceId, $parentSpanId, $sampled] = $this->resolveParent($traceparent);
        $spanId = $this->randomHex(8);

        $this->traceContext->set($traceId, $spanId, $sampled);

        return new OpenTelemetrySpan(
            tracer: $this,
            traceContext: $this->traceContext,
            name: $name,
            traceId: $traceId,
            spanId: $spanId,
            parentSpanId: $parentSpanId,
            kind: $kind,
            attributes: [
                'service.name' => config('observability.service_name', 'montry-web'),
                'deployment.environment' => config('observability.environment', 'local'),
                ...$attributes,
            ],
        );
    }

    public function currentTraceparent(): ?string
    {
        return $this->traceContext->traceparent();
    }

    public function export(OpenTelemetrySpan $span): void
    {
        if (! config('observability.tracing.enabled', true)) {
            return;
        }

        try {
            Http::timeout((float) config('observability.tracing.timeout_seconds', 2))
                ->acceptJson()
                ->asJson()
                ->post(config('observability.tracing.endpoint') . '/v1/traces', $this->payload($span));
        } catch (Throwable) {
            // Tracing must never break product flows.
        }
    }

    /**
     * @return array{0: string, 1: ?string, 2: bool}
     */
    private function resolveParent(?string $traceparent): array
    {
        $parsed = $this->parseTraceparent($traceparent ?? $this->traceContext->traceparent());

        if ($parsed !== null) {
            return $parsed;
        }

        return [$this->randomHex(16), null, true];
    }

    /**
     * @return array{0: string, 1: string, 2: bool}|null
     */
    private function parseTraceparent(?string $traceparent): ?array
    {
        if ($traceparent === null || $traceparent === '') {
            return null;
        }

        if (! preg_match('/^00-([a-f0-9]{32})-([a-f0-9]{16})-([a-f0-9]{2})$/', strtolower($traceparent), $matches)) {
            return null;
        }

        return [$matches[1], $matches[2], hexdec($matches[3]) === 1];
    }

    private function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(OpenTelemetrySpan $span): array
    {
        return [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => config('observability.service_name', 'montry-web')]],
                        ['key' => 'service.namespace', 'value' => ['stringValue' => 'montry']],
                        ['key' => 'deployment.environment', 'value' => ['stringValue' => config('observability.environment', 'local')]],
                    ],
                ],
                'scopeSpans' => [[
                    'scope' => [
                        'name' => 'montry.laravel',
                    ],
                    'spans' => [$span->toOtlp()],
                ]],
            ]],
        ];
    }
}
