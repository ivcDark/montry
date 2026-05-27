<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\Observability\Application\Context\CorrelationContext;
use App\Modules\Observability\Infrastructure\Persistence\Models\DeadLetter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class DeadLetterRecorder
{
    public function __construct(private CorrelationContext $correlationContext)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function record(
        string $source,
        string $type,
        ?Throwable $exception = null,
        ?string $errorMessage = null,
        bool $recoverable = false,
        ?string $idempotencyKey = null,
        ?int $organizationId = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        array $payload = [],
        array $context = [],
        int $attempts = 0,
        ?int $maxAttempts = null,
    ): void {
        $values = [
            'event_id' => (string) Str::uuid(),
            'source' => $this->normalizeLabel($source, 'source'),
            'type' => $this->normalizeLabel($type, 'type'),
            'status' => DeadLetter::STATUS_OPEN,
            'recoverable' => $recoverable,
            'idempotency_key' => $idempotencyKey,
            'organization_id' => $organizationId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'error_class' => $exception ? $exception::class : null,
            'error_message' => mb_strimwidth($errorMessage ?? $exception?->getMessage() ?? '', 0, 4000),
            'payload' => $this->sanitize($payload),
            'context' => $this->sanitize($context),
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'failed_at' => now(),
            'correlation_id' => $this->correlationContext->id(),
        ];

        try {
            if ($idempotencyKey === null || $idempotencyKey === '') {
                DeadLetter::query()->create($values);

                return;
            }

            DeadLetter::query()->updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                Arr::except($values, ['event_id', 'idempotency_key']) + [
                    'event_id' => DeadLetter::query()->where('idempotency_key', $idempotencyKey)->value('event_id') ?: $values['event_id'],
                ],
            );
        } catch (Throwable $writeException) {
            Log::warning('dead letter write failed', [
                'source' => $source,
                'type' => $type,
                'exception' => $writeException::class,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $lowerKey = strtolower($key);

            if (str_contains($lowerKey, 'password')
                || str_contains($lowerKey, 'token')
                || str_contains($lowerKey, 'secret')
                || str_contains($lowerKey, 'signature')
                || str_contains($lowerKey, 'authorization')) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function normalizeLabel(string $value, string $field): string
    {
        $value = trim($value);

        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new \InvalidArgumentException("Invalid dead-letter {$field}: {$value}");
        }

        return $value;
    }
}

