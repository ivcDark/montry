<?php

namespace App\Modules\Sites\DTO;

final readonly class UpdateMonitorData
{
    public function __construct(
        public string $name,
        public bool $isEnabled,
        public int $intervalSeconds,
        public int $timeoutMs,
        public array $settings = [],
    ) {
    }
}
