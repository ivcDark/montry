<?php

namespace App\Modules\Monitoring\Domain\Events;

final readonly class DomainExpiring
{
    public function __construct(
        public int $monitorId,
        public int $organizationId,
        public string $domain,
        public int $daysUntilExpiration,
        public ?\DateTimeInterface $expiresAt = null,
    ) {
    }
}
