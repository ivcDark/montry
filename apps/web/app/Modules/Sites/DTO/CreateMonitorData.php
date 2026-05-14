<?php

namespace App\Modules\Sites\DTO;

use App\Modules\Sites\Enums\MonitorType;

final readonly class CreateMonitorData
{
    public function __construct(
        public string $name,
        public MonitorType $type,
        public bool $isEnabled,
        public int $intervalSeconds,
        public int $timeoutMs,
        public array $settings = [],
        public array $expected = [],
    ) {
    }
}
