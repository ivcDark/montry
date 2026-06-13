<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;

final readonly class CreateMonitorHandler
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
        private MonitorRepositoryInterface $monitors,
        private LimitChecker $limits,
        private BusinessEventRecorder $events,
    ) {}

    public function handle(CreateMonitorCommand $command): Monitor
    {
        $definition = $this->checkTypeRegistry->get($command->type);
        $this->limits->assertCanCreateMonitor($command->organizationId);
        $this->limits->assertCanUseMonitorType($command->organizationId, $definition->type());
        $this->limits->assertCanCreatePaidCheck($command->organizationId, $definition->type());
        $this->limits->assertCanUseInterval($command->organizationId, $command->intervalSeconds);

        $monitor = $this->monitors->create([
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

        $this->events->record(new RecordBusinessEventData(
            eventType: 'monitor.created',
            organizationId: $monitor->organization_id,
            subjectType: 'monitor',
            subjectId: (string) $monitor->id,
            status: $monitor->enabled ? 'enabled' : 'disabled',
            source: 'web',
            payload: [
                'project_id' => $monitor->project_id,
                'monitored_resource_id' => $monitor->monitored_resource_id,
                'type' => $monitor->type,
                'interval_seconds' => $monitor->interval_seconds,
                'timeout_ms' => $monitor->timeout_ms,
                'enabled' => $monitor->enabled,
            ],
        ));

        if ($monitor->enabled) {
            $this->events->record(new RecordBusinessEventData(
                eventType: 'monitor.enabled',
                organizationId: $monitor->organization_id,
                subjectType: 'monitor',
                subjectId: (string) $monitor->id,
                status: 'enabled',
                source: 'web',
                payload: [
                    'type' => $monitor->type,
                    'reason' => 'created_enabled',
                ],
            ));
        }

        return $monitor;
    }
}
