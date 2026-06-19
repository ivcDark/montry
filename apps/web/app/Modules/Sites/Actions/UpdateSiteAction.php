<?php

namespace App\Modules\Sites\Actions;

use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Commands\UpdateMonitorCommand;
use App\Modules\Monitoring\Application\Handlers\CreateMonitorHandler;
use App\Modules\Monitoring\Application\Handlers\UpdateMonitorHandler;
use App\Modules\Sites\DTO\CreateSiteData;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSiteAction
{
    public function __construct(
        private CreateMonitorHandler $createMonitor,
        private UpdateMonitorHandler $updateMonitor,
        private DeleteMonitorAction $deleteMonitor,
    ) {}

    public function handle(MonitoredResource $site, CreateSiteData $data, array $monitors): MonitoredResource
    {
        return DB::transaction(function () use ($site, $data, $monitors): MonitoredResource {
            $site->update([
                'name' => $data->name,
                'target' => $data->url,
                'scheme' => $data->scheme,
                'host' => $data->host,
                'port' => $data->port,
                'path' => $data->path,
                'notes' => $data->notes,
            ]);

            $existingByType = $site->monitors()->get()->groupBy('type');
            $submittedByType = collect($monitors)->groupBy('type');

            foreach ($submittedByType as $type => $payloads) {
                $existing = $existingByType->get($type, collect())->values();
                $payloads = $payloads->values();

                $existing->slice($payloads->count())->each(
                    fn ($monitor) => $this->deleteMonitor->handle($monitor),
                );

                $payloads
                    ->filter(fn (array $payload): bool => ! $payload['is_enabled'])
                    ->each(fn (array $payload, int $index) => $this->saveMonitor(
                        $site,
                        $existing->get($index),
                        $payload,
                    ));
            }

            foreach ($submittedByType as $type => $payloads) {
                $existing = $site->monitors()->where('type', $type)->orderBy('id')->get()->values();

                $payloads
                    ->values()
                    ->filter(fn (array $payload): bool => (bool) $payload['is_enabled'])
                    ->each(fn (array $payload, int $index) => $this->saveMonitor(
                        $site,
                        $existing->get($index),
                        $payload,
                    ));
            }

            return $site->refresh();
        });
    }

    private function saveMonitor(MonitoredResource $site, mixed $monitor, array $payload): void
    {
        if ($monitor !== null) {
            $this->updateMonitor->handle(new UpdateMonitorCommand(
                monitorId: $monitor->id,
                name: $payload['name'],
                enabled: (bool) $payload['is_enabled'],
                intervalSeconds: (int) $payload['interval_seconds'],
                timeoutMs: (int) $payload['timeout_ms'],
                settings: $payload['settings'],
                expected: $payload['expected'] ?? [],
            ));

            return;
        }

        $this->createMonitor->handle(new CreateMonitorCommand(
            organizationId: $site->organization_id,
            projectId: $site->project_id,
            monitoredResourceId: $site->id,
            type: $payload['type'],
            name: $payload['name'],
            enabled: (bool) $payload['is_enabled'],
            intervalSeconds: (int) $payload['interval_seconds'],
            timeoutMs: (int) $payload['timeout_ms'],
            settings: $payload['settings'],
            expected: $payload['expected'] ?? [],
        ));
    }
}
