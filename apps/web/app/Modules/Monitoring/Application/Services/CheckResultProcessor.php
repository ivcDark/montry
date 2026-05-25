<?php

namespace App\Modules\Monitoring\Application\Services;

use App\Modules\Monitoring\Domain\Contracts\CheckResultRepositoryInterface;
use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Domain\Events\DomainExpiring;
use App\Modules\Monitoring\Domain\Events\SslExpiring;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

final readonly class CheckResultProcessor
{
    public function __construct(
        private CheckTypeRegistry $checkTypeRegistry,
        private MonitorStatusResolver $monitorStatusResolver,
        private MonitorRepositoryInterface $monitors,
        private CheckResultRepositoryInterface $checkResults,
    ) {}

    public function process(
        Monitor $monitor,
        array $workerResult,
        ?DateTimeInterface $checkedAt = null,
        ?string $eventId = null,
    ): CheckResult {
        $definition = $this->checkTypeRegistry->get($monitor->type);
        $normalizedResult = $definition->normalizeWorkerResult($workerResult);
        $status = $this->monitorStatusResolver->resolve($monitor, $normalizedResult);
        $checkedAt ??= Carbon::now();

        $checkResult = $this->checkResults->create([
            'event_id' => $eventId,
            'monitor_id' => $monitor->id,
            'organization_id' => $monitor->organization_id,
            'check_type' => $monitor->type,
            'status' => $status,
            'checked_at' => $checkedAt,
            'response_time_ms' => $normalizedResult['response_time_ms'] ?? $workerResult['response_time_ms'] ?? $workerResult['duration_ms'] ?? null,
            'status_code' => $normalizedResult['status_code'] ?? $workerResult['status_code'] ?? null,
            'error_code' => $normalizedResult['error_code'] ?? $workerResult['error_code'] ?? null,
            'error_message' => $normalizedResult['error_message'] ?? $workerResult['error_message'] ?? null,
            'raw_result' => $workerResult,
            'normalized_result' => $normalizedResult,
        ]);

        $this->updateMonitorState($monitor, $status, $checkedAt, $eventId);
        $this->emitExpirationWarningIfNeeded($monitor, $normalizedResult);

        return $checkResult;
    }

    private function emitExpirationWarningIfNeeded(Monitor $monitor, array $normalizedResult): void
    {
        if (! in_array($monitor->type, ['ssl', 'domain'], true)) {
            return;
        }

        $daysUntilExpiration = $normalizedResult['days_until_expiration'] ?? null;

        if ($daysUntilExpiration === null || $daysUntilExpiration <= 0) {
            return;
        }

        $warningDays = $monitor->settings['warning_days'] ?? [];

        if (! in_array((int) $daysUntilExpiration, array_map('intval', $warningDays), true)) {
            return;
        }

        $domain = (string) ($monitor->settings['domain'] ?? $monitor->monitoredResource?->host ?? '');
        $expiresAt = isset($normalizedResult['expires_at'])
            ? new DateTimeImmutable((string) $normalizedResult['expires_at'])
            : null;

        if ($monitor->type === 'ssl') {
            event(new SslExpiring(
                monitorId: $monitor->id,
                organizationId: $monitor->organization_id,
                domain: $domain,
                daysUntilExpiration: (int) $daysUntilExpiration,
                expiresAt: $expiresAt,
            ));

            return;
        }

        event(new DomainExpiring(
            monitorId: $monitor->id,
            organizationId: $monitor->organization_id,
            domain: $domain,
            daysUntilExpiration: (int) $daysUntilExpiration,
            expiresAt: $expiresAt,
        ));
    }

    private function updateMonitorState(
        Monitor $monitor,
        string $status,
        DateTimeInterface $checkedAt,
        ?string $eventId,
    ): void {
        $monitor->status = $status;
        $monitor->last_check_at = $checkedAt;
        $monitor->next_check_at = Carbon::instance($checkedAt)->copy()->addSeconds((int) $monitor->interval_seconds);

        if ($eventId !== null && $eventId === $monitor->last_check_event_id) {
            $monitor->check_in_progress_until = null;
        }

        if ($status === 'success') {
            $monitor->last_success_at = $checkedAt;
            $monitor->consecutive_successes = (int) $monitor->consecutive_successes + 1;
            $monitor->consecutive_failures = 0;
        } else {
            $monitor->last_failure_at = $checkedAt;
            $monitor->consecutive_failures = (int) $monitor->consecutive_failures + 1;
            $monitor->consecutive_successes = 0;
        }

        $this->monitors->save($monitor);
    }
}
