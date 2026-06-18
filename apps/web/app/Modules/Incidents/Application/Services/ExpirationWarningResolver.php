<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Support\Carbon;

final readonly class ExpirationWarningResolver
{
    public function __construct(
        private BusinessEventRecorder $events,
    ) {}

    public function resolve(CheckResult $checkResult): ?Incident
    {
        $checkResult->loadMissing('monitor');
        $monitor = $checkResult->monitor;

        if ($monitor === null || ! in_array($monitor->type, ['ssl', 'domain'], true)) {
            return null;
        }

        $openWarning = Incident::query()
            ->where('monitor_id', $monitor->id)
            ->where('status', 'open')
            ->where('severity', 'warning')
            ->first();

        $daysLeft = $checkResult->normalized_result['days_until_expiration'] ?? null;

        if ($daysLeft === null) {
            return null;
        }

        $daysLeft = (int) $daysLeft;
        $warningDays = array_values(array_filter(array_map(
            'intval',
            is_array($monitor->settings['warning_days'] ?? null) ? $monitor->settings['warning_days'] : [],
        ), static fn (int $day): bool => $day > 0));
        $warningThreshold = $warningDays === [] ? null : max($warningDays);

        if ($daysLeft <= 0 || $warningThreshold === null || $daysLeft > $warningThreshold) {
            return $this->resolveWarning($openWarning, $checkResult);
        }

        if ($checkResult->status !== 'success') {
            return null;
        }

        $expiresAt = $checkResult->normalized_result['expires_at'] ?? null;
        $target = (string) ($monitor->settings['domain'] ?? $monitor->monitoredResource?->host ?? '');
        $label = $monitor->type === 'ssl' ? 'SSL-сертификат' : 'Срок регистрации домена';
        $summary = "{$label} для {$target} истекает через {$daysLeft} дн."
            .($expiresAt ? " Дата окончания: {$expiresAt}." : '');

        if ($openWarning !== null) {
            $openWarning->update([
                'title' => $this->title($monitor->type),
                'summary' => $summary,
            ]);

            return $openWarning->refresh();
        }

        $warning = Incident::query()->create([
            'organization_id' => $monitor->organization_id,
            'project_id' => $monitor->project_id,
            'monitored_resource_id' => $monitor->monitored_resource_id,
            'monitor_id' => $monitor->id,
            'status' => 'open',
            'severity' => 'warning',
            'title' => $this->title($monitor->type),
            'summary' => $summary,
            'started_at' => $checkResult->checked_at,
            'opened_by_check_result_id' => $checkResult->id,
        ]);

        event(new IncidentOpened($warning->id));

        $this->recordEvent('incident.warning_opened', $warning, $checkResult);

        return $warning;
    }

    private function title(string $type): string
    {
        return $type === 'ssl'
            ? 'Истекает SSL-сертификат'
            : 'Истекает срок регистрации домена';
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
                'check_type' => $checkResult->check_type,
                'severity' => 'warning',
                'summary' => $warning->summary,
            ],
        ));
    }
}
