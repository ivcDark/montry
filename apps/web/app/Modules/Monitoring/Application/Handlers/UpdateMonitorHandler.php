<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Monitoring\Application\Commands\UpdateMonitorCommand;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;

final readonly class UpdateMonitorHandler
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
        private MonitorRepositoryInterface $monitors,
        private LimitChecker $limits,
        private BusinessEventRecorder $events,
    ) {}

    public function handle(UpdateMonitorCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);
        $wasEnabled = (bool) $monitor->enabled;
        $definition = $this->checkTypeRegistry->get($monitor->type);

        if (! $wasEnabled && $command->enabled) {
            $this->limits->assertCanEnableMonitor($monitor->organization_id);
            $this->limits->assertCanUseMonitorType($monitor->organization_id, $monitor->type);
        }

        $this->limits->assertCanUseInterval($monitor->organization_id, $command->intervalSeconds);

        $monitor->name = $command->name;
        $monitor->enabled = $command->enabled;
        $monitor->interval_seconds = $command->intervalSeconds;
        $monitor->timeout_ms = $command->timeoutMs;
        $monitor->settings = $definition->normalizeSettings(
            $definition->validateSettings($command->settings ?: ($monitor->settings ?? $definition->defaultSettings())),
        );
        $monitor->expected = $definition->validateExpected($command->expected ?: ($monitor->expected ?? $definition->defaultExpected()));

        $monitor = $this->monitors->save($monitor);

        if ($wasEnabled !== (bool) $monitor->enabled) {
            $eventType = $monitor->enabled ? 'monitor.enabled' : 'monitor.disabled';

            $this->events->record(new RecordBusinessEventData(
                eventType: $eventType,
                organizationId: $monitor->organization_id,
                subjectType: 'monitor',
                subjectId: (string) $monitor->id,
                status: $monitor->enabled ? 'enabled' : 'disabled',
                source: 'web',
                payload: [
                    'type' => $monitor->type,
                    'reason' => 'settings_update',
                ],
            ));
        }

        return $monitor;
    }
}
