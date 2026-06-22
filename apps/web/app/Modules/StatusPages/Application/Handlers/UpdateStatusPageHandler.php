<?php

namespace App\Modules\StatusPages\Application\Handlers;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\StatusPages\Application\Commands\UpdateStatusPage;
use App\Modules\StatusPages\Infrastructure\Persistence\Models\StatusPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateStatusPageHandler
{
    public function handle(UpdateStatusPage $command): StatusPage
    {
        return DB::transaction(function () use ($command): StatusPage {
            $data = $command->data;
            $monitorIds = array_column($data->monitors, 'monitor_id');
            $validCount = Monitor::query()
                ->where('organization_id', $data->organizationId)
                ->whereIn('id', $monitorIds)
                ->count();

            if ($validCount !== count(array_unique($monitorIds))) {
                throw ValidationException::withMessages([
                    'monitors' => 'Один или несколько мониторов недоступны.',
                ]);
            }

            $command->statusPage->update([
                'name' => $data->name,
                'slug' => $data->slug,
                'description' => $data->description,
                'is_published' => $data->isPublished,
                'show_incident_history' => $data->showIncidentHistory,
                'accent_color' => $data->accentColor,
            ]);

            $syncPayload = [];
            foreach ($data->monitors as $index => $monitor) {
                $syncPayload[$monitor['monitor_id']] = [
                    'display_name' => $monitor['display_name'],
                    'sort_order' => $index,
                ];
            }
            $command->statusPage->monitors()->sync($syncPayload);

            return $command->statusPage->refresh();
        });
    }
}
