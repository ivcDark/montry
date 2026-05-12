<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use Illuminate\Support\Carbon;

final readonly class IncidentResolver
{
    public function __construct(
        private int $failureThreshold = 2,
        private int $recoveryThreshold = 1,
    ) {
    }

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

        if ((int) $monitor->consecutive_failures < $this->failureThreshold) {
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
            'title' => $monitor->name . ' is failing',
            'summary' => $checkResult->error_message,
            'started_at' => $checkResult->checked_at,
            'opened_by_check_result_id' => $checkResult->id,
        ]);

        event(new IncidentOpened($incident->id));

        return $incident;
    }

    private function resolveOpenIncident(CheckResult $checkResult): ?Incident
    {
        $monitor = $checkResult->monitor;

        $incident = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('status', 'open')
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

        return $incident->refresh();
    }
}
