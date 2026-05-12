<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final readonly class CreateMonitorHandler
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
        private MonitorRepositoryInterface $monitors,
    ) {
    }

    public function handle(CreateMonitorCommand $command): Monitor
    {
        $definition = $this->checkTypeRegistry->get($command->type);

        return $this->monitors->create([
            'organization_id' => $command->organizationId,
            'project_id' => $command->projectId,
            'monitored_resource_id' => $command->monitoredResourceId,
            'type' => $definition->type(),
            'name' => $command->name,
            'enabled' => $command->enabled,
            'status' => 'unknown',
            'interval_seconds' => $command->intervalSeconds,
            'timeout_ms' => $command->timeoutMs,
            'settings' => $definition->normalizeSettings(
                $definition->validateSettings($command->settings ?: $definition->defaultSettings()),
            ),
            'expected' => $definition->validateExpected($command->expected ?: $definition->defaultExpected()),
        ]);
    }
}
