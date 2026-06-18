<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Support\Carbon;

final readonly class DnsRecordChangeWarningResolver
{
    public function __construct(
        private BusinessEventRecorder $events,
    ) {}

    public function resolve(CheckResult $checkResult): ?Incident
    {
        $checkResult->loadMissing('monitor');
        $monitor = $checkResult->monitor;

        if ($monitor === null || $monitor->type !== 'dns' || $checkResult->status !== 'success') {
            return null;
        }

        $openWarning = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('status', 'open')
            ->where('severity', 'warning')
            ->first();

        if (! (bool) ($monitor->settings['warn_on_change'] ?? false)) {
            return $this->resolveWarning($openWarning, $checkResult);
        }

        $previousResult = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('status', 'success')
            ->where('id', '<', $checkResult->id)
            ->latest('checked_at')
            ->latest('id')
            ->first();

        if ($previousResult === null) {
            return null;
        }

        $previousRecords = $this->canonicalRecords($previousResult->normalized_result['records'] ?? []);
        $currentRecords = $this->canonicalRecords($checkResult->normalized_result['records'] ?? []);
        $added = array_values(array_diff($currentRecords, $previousRecords));
        $removed = array_values(array_diff($previousRecords, $currentRecords));

        if ($added === [] && $removed === []) {
            return $this->resolveWarning($openWarning, $checkResult);
        }

        $summary = $this->changeSummary($added, $removed);

        if ($openWarning !== null) {
            $openWarning->update(['summary' => $summary]);

            return $openWarning->refresh();
        }

        $warning = Incident::query()->create([
            'organization_id' => $monitor->organization_id,
            'project_id' => $monitor->project_id,
            'monitored_resource_id' => $monitor->monitored_resource_id,
            'monitor_id' => $monitor->id,
            'status' => 'open',
            'severity' => 'warning',
            'title' => 'Изменились DNS-записи',
            'summary' => $summary,
            'started_at' => $checkResult->checked_at,
            'opened_by_check_result_id' => $checkResult->id,
        ]);

        event(new IncidentOpened($warning->id));

        $this->recordEvent('incident.warning_opened', $warning, $checkResult);

        return $warning;
    }

    /**
     * @param  array<int, mixed>  $records
     * @return list<string>
     */
    private function canonicalRecords(array $records): array
    {
        $canonical = array_map(function (mixed $record): string {
            if (! is_array($record)) {
                return trim((string) $record);
            }

            $type = strtoupper(trim((string) ($record['type'] ?? '')));
            $value = trim((string) ($record['value'] ?? ''));
            $priority = isset($record['priority']) ? ' priority='.(int) $record['priority'] : '';

            return trim("{$type} {$value}{$priority}");
        }, $records);

        $canonical = array_values(array_unique(array_filter($canonical)));
        sort($canonical, SORT_STRING);

        return $canonical;
    }

    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    private function changeSummary(array $added, array $removed): string
    {
        $parts = [];

        if ($added !== []) {
            $parts[] = 'Добавлены: '.implode('; ', $added).'.';
        }

        if ($removed !== []) {
            $parts[] = 'Удалены: '.implode('; ', $removed).'.';
        }

        return implode(' ', $parts);
    }

    private function resolveWarning(?Incident $warning, CheckResult $checkResult): ?Incident
    {
        if ($warning === null) {
            return null;
        }

        $resolvedAt = $checkResult->checked_at ?? Carbon::now();
        $startedAt = $warning->started_at ?? $resolvedAt;

        $warning->update([
            'status' => 'resolved',
            'resolved_at' => $resolvedAt,
            'duration_seconds' => max(0, $startedAt->diffInSeconds($resolvedAt)),
            'resolved_by_check_result_id' => $checkResult->id,
        ]);

        event(new IncidentResolved($warning->id));

        $this->recordEvent('incident.warning_resolved', $warning, $checkResult);

        return $warning->refresh();
    }

    private function recordEvent(string $eventType, Incident $warning, CheckResult $checkResult): void
    {
        $this->events->record(new RecordBusinessEventData(
            eventType: $eventType,
            organizationId: $warning->organization_id,
            subjectType: 'incident',
            subjectId: (string) $warning->id,
            status: $warning->status,
            source: 'incidents',
            payload: [
                'monitor_id' => $warning->monitor_id,
                'check_result_id' => $checkResult->id,
                'check_type' => 'dns',
                'severity' => 'warning',
                'summary' => $warning->summary,
            ],
        ));
    }
}
