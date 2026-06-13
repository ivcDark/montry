<?php

namespace App\Modules\Observability\Application\Context;

final class CorrelationContext
{
    private ?string $correlationId = null;

    public function set(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function id(): ?string
    {
        return $this->correlationId;
    }
}

