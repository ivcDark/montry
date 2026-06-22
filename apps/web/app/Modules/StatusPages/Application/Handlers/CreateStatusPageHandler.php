<?php

namespace App\Modules\StatusPages\Application\Handlers;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\StatusPages\Application\Commands\CreateStatusPage;
use App\Modules\StatusPages\Infrastructure\Persistence\Models\StatusPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateStatusPageHandler
{
    public function handle(CreateStatusPage $command): StatusPage
    {
        return DB::transaction(function () use ($command): StatusPage {
            $data = $command->data;
            $this->assertMonitorsBelongToOrganization($data->organizationId, $data->monitors);

            $statusPage = StatusPage::query()->create([
                'organization_id' => $data->organizationId,
                'created_user_id' => $data->createdUserId,
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'is_published' => $data->isPublished,
                'show_incident_history' => $data->showIncidentHistory,
                'accent_color' => $data->accentColor,
            ]);

            $statusPage->monitors()->sync($this->monitorSyncPayload($data->monitors));

            return $statusPage;
        });
    }

    /**
     * @param  list<array{monitor_id: int, display_name: string|null}>  $monitors
     */
    private function assertMonitorsBelongToOrganization(int $organizationId, array $monitors): void
    {
        $monitorIds = array_column($monitors, 'monitor_id');
        $validCount = Monitor::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $monitorIds)
            ->count();

        if ($validCount !== count(array_unique($monitorIds))) {
            throw ValidationException::withMessages([
                'monitors' => 'Один или несколько мониторов недоступны.',
            ]);
        }
    }

    /**
     * @param  list<array{monitor_id: int, display_name: string|null}>  $monitors
     * @return array<int, array{display_name: string|null, sort_order: int}>
     */
    private function monitorSyncPayload(array $monitors): array
    {
        $payload = [];

        foreach ($monitors as $index => $monitor) {
            $payload[$monitor['monitor_id']] = [
                'display_name' => $monitor['display_name'],
                'sort_order' => $index,
            ];
        }

        return $payload;
    }
}
