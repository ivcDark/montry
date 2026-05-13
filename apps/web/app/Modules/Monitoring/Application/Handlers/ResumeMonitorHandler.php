<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Monitoring\Application\Commands\ResumeMonitorCommand;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class ResumeMonitorHandler
{
    public function __construct(
        private MonitorRepositoryInterface $monitors,
        private LimitChecker $limits,
    ) {}

    public function handle(ResumeMonitorCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);
        $this->limits->assertCanUseInterval($monitor->organization_id, (int) $monitor->interval_seconds);
        $monitor->enabled = true;

        return $this->monitors->save($monitor);
    }
}
