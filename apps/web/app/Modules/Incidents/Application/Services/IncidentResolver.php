<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Application\Services\MonitorFailureThresholds;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Support\Carbon;

final readonly class IncidentResolver
{
    public function __construct(
        private BusinessEventRecorder $events,
        private MonitorFailureThresholds $failureThresholds,
        private int $recoveryThreshold = 1,
    ) {}

    public function resolve(CheckResult $checkResult): ?Incident
    {
        $checkResult->loadMissing('monitor');
        $monitor = $checkResult->monitor;

        if ($monitor === null) {
            return null;
        }

        if ($checkResult->status === 'success') {
            if ((int) $monitor->consecutive_successes < $this->recoveryThreshold) {
                return null;
            }

            return $this->resolveOpenIncident($checkResult);
        }

        if ((int) $monitor->consecutive_failures < $this->failureThresholds->forMonitor($monitor)) {
            return null;
        }

        return $this->openIncidentIfMissing($checkResult);
    }

    private function openIncidentIfMissing(CheckResult $checkResult): ?Incident
    {
        $monitor = $checkResult->monitor;

        $existingIncident = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('status', 'open')
            ->where('severity', '!=', 'warning')
            ->first();

        if ($existingIncident !== null) {
            return $existingIncident;
        }

        $incident = Incident::query()->create([
            'organization_id' => $monitor->organization_id,
            'project_id' => $monitor->project_id,
            'monitored_resource_id' => $monitor->monitored_resource_id,
            'monitor_id' => $monitor->id,
            'status' => 'open',
            'severity' => 'incident',
            'title' => $monitor->name.' is failing',
            'summary' => $checkResult->error_message,
            'started_at' => $checkResult->checked_at,
            'opened_by_check_result_id' => $checkResult->id,
        ]);

        event(new IncidentOpened($incident->id));

        $this->events->record(new RecordBusinessEventData(
            eventType: 'incident.opened',
            organizationId: $incident->organization_id,
            subjectType: 'incident',
            subjectId: (string) $incident->id,
            status: 'open',
            source: 'incidents',
            payload: [
                'monitor_id' => $monitor->id,
                'monitored_resource_id' => $monitor->monitored_resource_id,
                'check_result_id' => $checkResult->id,
                'check_type' => $monitor->type,
                'severity' => $incident->severity,
                'started_at' => $incident->started_at?->toISOString(),
            ],
        ));

        return $incident;
    }

    private function resolveOpenIncident(CheckResult $checkResult): ?Incident
    {
        $monitor = $checkResult->monitor;

        $incident = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('status', 'open')
            ->where('severity', '!=', 'warning')
            ->first();

        if ($incident === null) {
            return null;
        }

        $resolvedAt = $checkResult->checked_at ?? Carbon::now();
        $startedAt = $incident->started_at ?? $resolvedAt;

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => $resolvedAt,
            'duration_seconds' => max(0, $startedAt->diffInSeconds($resolvedAt)),
            'resolved_by_check_result_id' => $checkResult->id,
        ]);

        event(new IncidentResolved($incident->id));

        $this->events->record(new RecordBusinessEventData(
            eventType: 'incident.resolved',
            organizationId: $incident->organization_id,
            subjectType: 'incident',
            subjectId: (string) $incident->id,
            status: 'resolved',
            source: 'incidents',
            payload: [
                'monitor_id' => $monitor->id,
                'monitored_resource_id' => $monitor->monitored_resource_id,
                'check_result_id' => $checkResult->id,
                'duration_seconds' => $incident->duration_seconds,
                'resolved_at' => $incident->resolved_at?->toISOString(),
            ],
        ));

        return $incident->refresh();
    }
}
