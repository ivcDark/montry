<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Monitoring\Application\Commands\PauseMonitorCommand;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class PauseMonitorHandler
{
    public function __construct(
        private MonitorRepositoryInterface $monitors,
    ) {
    }

    public function handle(PauseMonitorCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);
        $monitor->enabled = false;

        return $this->monitors->save($monitor);
    }
}
