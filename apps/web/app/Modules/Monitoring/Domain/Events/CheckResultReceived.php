<?php

namespace App\Modules\Monitoring\Domain\Events;

final readonly class CheckResultReceived
{
    public function __construct(
        public int $checkResultId,
    ) {
    }
}
