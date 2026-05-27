<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Tracing;

use App\Modules\Observability\Application\Context\TraceContext;

final class OpenTelemetrySpan
{
    private readonly int $startTimeUnixNano;
    private ?int $endTimeUnixNano = null;
    private string $statusCode = 'STATUS_CODE_UNSET';

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function __construct(
        private readonly OpenTelemetryService $tracer,
        private readonly TraceContext $traceContext,
        private readonly string $name,
        private readonly string $traceId,
        private readonly string $spanId,
        private readonly ?string $parentSpanId,
        private readonly int $kind,
        private readonly array $attributes = [],
    ) {
        $this->startTimeUnixNano = (int) floor(microtime(true) * 1_000_000_000);
    }

    public function traceparent(): string
    {
        return sprintf('00-%s-%s-01', $this->traceId, $this->spanId);
    }

    public function end(string $statusCode = 'STATUS_CODE_OK'): void
    {
        if ($this->endTimeUnixNano !== null) {
            return;
        }

        $this->statusCode = $statusCode;
        $this->endTimeUnixNano = (int) floor(microtime(true) * 1_000_000_000);
        $this->tracer->export($this);

        if ($this->traceContext->spanId() === $this->spanId) {
            $this->traceContext->set($this->traceId, $this->parentSpanId, true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toOtlp(): array
    {
        $span = [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'name' => $this->name,
            'kind' => $this->kind,
            'startTimeUnixNano' => (string) $this->startTimeUnixNano,
            'endTimeUnixNano' => (string) ($this->endTimeUnixNano ?? $this->startTimeUnixNano),
            'attributes' => $this->attributes($this->attributes),
            'status' => [
                'code' => $this->statusCode,
            ],
        ];

        if ($this->parentSpanId !== null) {
            $span['parentSpanId'] = $this->parentSpanId;
        }

        return $span;
    }

    /**
     * @param array<string, scalar|null> $attributes
     * @return array<int, array<string, mixed>>
     */
    private function attributes(array $attributes): array
    {
        $encoded = [];

        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }

            $encoded[] = [
                'key' => $key,
                'value' => $this->attributeValue($value),
            ];
        }

        return $encoded;
    }

    /**
     * @return array<string, scalar>
     */
    private function attributeValue(bool|int|float|string $value): array
    {
        return match (true) {
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            default => ['stringValue' => $value],
        };
    }
}
