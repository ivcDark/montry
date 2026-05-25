<?php

namespace App\Modules\Monitoring\Application\Handlers;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Monitoring\Application\Commands\RequestManualCheckCommand;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\WorkerGateway\Application\DTO\WorkerCheckPayload;
use App\Modules\WorkerGateway\Domain\Contracts\MonitoringWorkerClientInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

final readonly class RequestManualCheckHandler
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
        private MonitorRepositoryInterface $monitors,
        private MonitoringWorkerClientInterface $workerClient,
        private LimitChecker $limits,
    ) {}

    public function handle(RequestManualCheckCommand $command): Monitor
    {
        $monitor = $this->monitors->getById($command->monitorId);

        if (! $monitor->enabled) {
            throw new DomainException('Cannot request a manual check for a paused monitor.');
        }

        $this->limits->assertCanRequestManualCheck($monitor->organization_id);
        $this->checkTypeRegistry->get($monitor->type);
        $monitor->loadMissing('monitoredResource');

        $eventId = (string) Str::uuid();
        $requestedAt = Carbon::now();

        $monitor->last_check_event_id = $eventId;
        $monitor->check_in_progress_until = $requestedAt
            ->copy()
            ->addMilliseconds((int) $monitor->timeout_ms)
            ->addSeconds(60);
        $this->monitors->save($monitor);

        try {
            $this->workerClient->requestManualCheck(new WorkerCheckPayload(
                eventId: $eventId,
                eventType: 'manual_check_requested',
                monitorId: $monitor->id,
                checkType: $monitor->type,
                target: $monitor->monitoredResource?->target ?? $monitor->settings['url'] ?? $monitor->settings['domain'] ?? '',
                settings: $monitor->settings ?? [],
                expected: $monitor->expected ?? [],
                requestedAt: $requestedAt,
            ));
        } catch (Throwable $exception) {
            $monitor->check_in_progress_until = null;
            $this->monitors->save($monitor);

            throw $exception;
        }

        return $monitor;
    }
}
