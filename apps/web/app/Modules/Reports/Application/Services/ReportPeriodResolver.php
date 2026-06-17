<?php

namespace App\Modules\Reports\Application\Services;

use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Reports\Application\DTO\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class ReportPeriodResolver
{
    public function __construct(
        private LimitChecker $limits,
        private MonitorTypeCatalog $monitorTypes,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function normalize(int $organizationId, array $input): ReportFilters
    {
        $retentionDays = max(1, (int) ($this->limits->historyRetentionDays($organizationId) ?? 3));
        $type = (string) ($input['type'] ?? 'all');

        if ($type !== 'all' && ! in_array($type, $this->monitorTypes->allCodes(), true)) {
            throw ValidationException::withMessages(['type' => 'Unknown report type filter.']);
        }

        $projectId = isset($input['project_id']) && $input['project_id'] !== ''
            ? max(1, (int) $input['project_id'])
            : null;

        $period = (string) ($input['period'] ?? 'max');
        $requestedDays = match ($period) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            default => $retentionDays,
        };

        if (! empty($input['date_from']) || ! empty($input['date_to'])) {
            $start = CarbonImmutable::parse((string) ($input['date_from'] ?? $input['date_to']))->startOfDay();
            $end = CarbonImmutable::parse((string) ($input['date_to'] ?? $input['date_from']))->endOfDay();
            $requestedDays = max(1, (int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1);
            $period = 'custom';
        } else {
            $end = CarbonImmutable::now()->endOfDay();
            $start = $end->subDays($requestedDays - 1)->startOfDay();
        }

        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        $earliestAllowed = CarbonImmutable::now()->subDays($retentionDays - 1)->startOfDay();
        $wasLimitedByPlan = $start->lessThan($earliestAllowed);

        if ($wasLimitedByPlan) {
            $start = $earliestAllowed;
        }

        return new ReportFilters(
            start: $start,
            end: $end,
            period: $period,
            type: $type,
            projectId: $projectId,
            retentionDays: $retentionDays,
            requestedDays: $requestedDays,
            wasLimitedByPlan: $wasLimitedByPlan,
        );
    }

    /**
     * @return array{code:string, name:string}
     */
    public function planPayload(int $organizationId): array
    {
        $subscription = Subscription::query()
            ->with('plan')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        return [
            'code' => (string) ($subscription?->plan?->code ?? 'free'),
            'name' => (string) ($subscription?->plan?->name ?? 'Free'),
        ];
    }
}
