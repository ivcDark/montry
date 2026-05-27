<?php

namespace App\Modules\Observability\Application\Services;

use App\Modules\Observability\Application\Context\CorrelationContext;
use App\Modules\Observability\Infrastructure\Persistence\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final readonly class AuditLogger
{
    public function __construct(private CorrelationContext $correlationContext)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $category,
        string $action,
        string $outcome,
        ?Request $request = null,
        ?int $actorUserId = null,
        ?int $organizationId = null,
        ?string $targetType = null,
        ?string $targetId = null,
        string $source = 'web',
        array $metadata = [],
    ): void {
        $category = $this->normalizeLabel($category, 'category');
        $action = $this->normalizeAction($action);
        $outcome = $this->normalizeLabel($outcome, 'outcome');
        $source = $this->normalizeLabel($source, 'source');

        try {
            AuditLog::query()->create([
                'event_id' => (string) Str::uuid(),
                'occurred_at' => now(),
                'category' => $category,
                'action' => $action,
                'outcome' => $outcome,
                'source' => $source,
                'actor_user_id' => $actorUserId,
                'organization_id' => $organizationId,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'route_name' => $request?->route()?->getName(),
                'request_method' => $request?->getMethod(),
                'request_path' => $request?->path(),
                'ip_hash' => $this->hashNullable($request?->ip()),
                'user_agent_hash' => $this->hashNullable($request?->userAgent()),
                'correlation_id' => $this->correlationContext->id(),
                'metadata' => $this->sanitizeMetadata($metadata),
            ]);
        } catch (Throwable $exception) {
            Log::warning('audit log write failed', [
                'category' => $category,
                'action' => $action,
                'outcome' => $outcome,
                'source' => $source,
                'exception' => $exception::class,
            ]);
        }
    }

    public function hashValue(?string $value): ?string
    {
        return $this->hashNullable($value);
    }

    private function normalizeLabel(string $value, string $field): string
    {
        $value = trim($value);

        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException("Invalid audit {$field}: {$value}");
        }

        return $value;
    }

    private function normalizeAction(string $value): string
    {
        $value = trim($value);

        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException("Invalid audit action: {$value}");
        }

        return $value;
    }

    private function hashNullable(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function sanitizeMetadata(array $metadata): array
    {
        return $this->sanitizeArray(Arr::except($metadata, [
            'password',
            'token',
            'authorization',
            'signature',
            'secret',
        ]));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values): array
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
                $sanitized[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
