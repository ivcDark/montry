<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Incidents\Application\DTO\IncidentAnalyticsAccess;
use App\Modules\Incidents\Application\DTO\IncidentAnalyticsFilters;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class IncidentAnalyticsAccessResolver
{
    public function resolve(int $organizationId): IncidentAnalyticsAccess
    {
        $subscription = $this->currentSubscription($organizationId);
        $planCode = (string) ($subscription?->plan?->code ?? 'free');
        $retentionDays = $this->retentionDays($subscription);

        return new IncidentAnalyticsAccess(
            enabled: in_array($planCode, ['pro', 'plus'], true),
            planCode: $planCode,
            retentionDays: $retentionDays,
        );
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function normalizeFilters(int $organizationId, array $input): IncidentAnalyticsFilters
    {
        $access = $this->resolve($organizationId);
        $type = (string) ($input['type'] ?? 'all');

        if ($type !== 'all' && ! in_array($type, app(MonitorTypeCatalog::class)->allCodes(), true)) {
            throw ValidationException::withMessages(['type' => 'Unknown incident type filter.']);
        }

        $projectId = isset($input['project_id']) && $input['project_id'] !== ''
            ? max(1, (int) $input['project_id'])
            : null;

        if (! empty($input['date_from']) || ! empty($input['date_to'])) {
            $start = CarbonImmutable::parse((string) ($input['date_from'] ?? $input['date_to']))->startOfDay();
            $end = CarbonImmutable::parse((string) ($input['date_to'] ?? $input['date_from']))->endOfDay();
        } else {
            $period = (string) ($input['period'] ?? 'max');
            $days = match ($period) {
                '1', '24' => 1,
                '7' => 7,
                default => $access->retentionDays,
            };
            $days = min($days, $access->retentionDays);
            $end = CarbonImmutable::now()->endOfDay();
            $start = $end->subDays($days - 1)->startOfDay();
        }

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['date_to' => 'End date must be after start date.']);
        }

        if ($start->startOfDay()->diffInDays($end->startOfDay()) + 1 > $access->retentionDays) {
            throw ValidationException::withMessages(['date_from' => 'Date range exceeds current plan retention.']);
        }

        return new IncidentAnalyticsFilters(
            start: $start,
            end: $end,
            type: $type,
            projectId: $projectId,
            search: trim((string) ($input['search'] ?? '')),
        );
    }

    private function currentSubscription(int $organizationId): ?Subscription
    {
        return Subscription::query()
            ->with('plan.limits')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    private function retentionDays(?Subscription $subscription): int
    {
        $limit = $subscription?->plan?->limits->firstWhere('key', 'history_retention_days');
        $value = $limit?->value;

        if (is_array($value)) {
            return max(1, (int) ($value['days'] ?? 3));
        }

        return 3;
    }
}
