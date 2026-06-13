<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Context;

final class TraceContext
{
    private ?string $traceId = null;
    private ?string $spanId = null;
    private bool $sampled = true;

    public function set(?string $traceId, ?string $spanId, bool $sampled = true): void
    {
        $this->traceId = $traceId;
        $this->spanId = $spanId;
        $this->sampled = $sampled;
    }

    public function clear(): void
    {
        $this->set(null, null);
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    public function spanId(): ?string
    {
        return $this->spanId;
    }

    public function sampled(): bool
    {
        return $this->sampled;
    }

    public function traceparent(): ?string
    {
        if ($this->traceId === null || $this->spanId === null) {
            return null;
        }

        return sprintf('00-%s-%s-%s', $this->traceId, $this->spanId, $this->sampled ? '01' : '00');
    }
}
