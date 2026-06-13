<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Logging;

use App\Modules\Observability\Application\Context\CorrelationContext;
use Monolog\LogRecord;

final readonly class ContextProcessor
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_KEYS = [
        'api_key',
        'authorization',
        'cookie',
        'password',
        'secret',
        'token',
        'verification_code',
    ];

    public function __construct(
        private CorrelationContext $correlationContext,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redact($record->context);
        $extra = $this->redact($record->extra);

        $extra['service'] ??= config('observability.service_name', 'montry-web');
        $extra['component'] ??= 'laravel';
        $extra['environment'] ??= config('app.env', 'local');
        $extra['correlation_id'] ??= $context['correlation_id'] ?? $this->correlationContext->id();

        return $record->with(context: $context, extra: $extra);
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function redact(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey($key)) {
                $value[$key] = self::REDACTED;

                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->redact($item);
            }
        }

        return $value;
    }

    private function isSensitiveKey(mixed $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($normalized, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
