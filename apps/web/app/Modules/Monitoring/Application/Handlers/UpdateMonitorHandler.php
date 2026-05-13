<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Monitoring\Application\Commands\UpdateMonitorCommand;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class UpdateMonitorHandler
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
        private MonitorRepositoryInterface $monitors,
        private LimitChecker $limits,
    ) {}

    public function handle(UpdateMonitorCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);
        $definition = $this->checkTypeRegistry->get($monitor->type);
        $this->limits->assertCanUseInterval($monitor->organization_id, $command->intervalSeconds);

        $monitor->name = $command->name;
        $monitor->enabled = $command->enabled;
        $monitor->interval_seconds = $command->intervalSeconds;
        $monitor->timeout_ms = $command->timeoutMs;
        $monitor->settings = $definition->normalizeSettings(
            $definition->validateSettings($command->settings ?: ($monitor->settings ?? $definition->defaultSettings())),
        );
        $monitor->expected = $definition->validateExpected($command->expected ?: ($monitor->expected ?? $definition->defaultExpected()));

        return $this->monitors->save($monitor);
    }
}
