<?php

namespace App\Modules\Monitoring\Application\Services;

use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\MonitorStateChange;
use Carbon\CarbonImmutable;

final class PruneMonitoringHistory
{
    public function handle(int $retentionDays = 90, bool $dryRun = false): array
    {
        $cutoff = CarbonImmutable::now()->subDays($retentionDays);

        $counts = [
            'incidents' => $this->resolvedIncidentsQuery($cutoff)->count(),
            'monitor_state_changes' => $this->stateChangesQuery($cutoff)->count(),
            'check_results' => $this->checkResultsQuery($cutoff)->count(),
        ];

        if ($dryRun) {
            return $counts;
        }

        $this->resolvedIncidentsQuery($cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($incidents): void {
                foreach ($incidents as $incident) {
                    $incident->delete();
                }
            });

        $this->deleteByChunks($this->stateChangesQuery($cutoff), MonitorStateChange::class);
        $this->deleteByChunks($this->checkResultsQuery($cutoff), CheckResult::class);

        return $counts;
    }

    private function resolvedIncidentsQuery(CarbonImmutable $cutoff)
    {
        return Incident::query()
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<', $cutoff);
    }

    private function stateChangesQuery(CarbonImmutable $cutoff)
    {
        return MonitorStateChange::query()
            ->where('changed_at', '<', $cutoff);
    }

    private function checkResultsQuery(CarbonImmutable $cutoff)
    {
        return CheckResult::query()
            ->where('checked_at', '<', $cutoff);
    }

    private function deleteByChunks($query, string $modelClass): void
    {
        $query
            ->select('id')
            ->orderBy('id')
            ->chunkById(1000, function ($models) use ($modelClass): void {
                $modelClass::query()
                    ->whereKey($models->pluck('id')->all())
                    ->delete();
            });
    }
}
