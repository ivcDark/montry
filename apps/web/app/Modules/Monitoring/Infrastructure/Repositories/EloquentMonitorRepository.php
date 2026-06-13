<?php

namespace App\Modules\Monitoring\Infrastructure\Repositories;

use App\Modules\Monitoring\Domain\Contracts\MonitorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

final class EloquentMonitorRepository implements MonitorRepositoryInterface
{
    public function create(array $attributes): Monitor
    {
        return Monitor::query()->create($attributes);
    }

    public function findById(int $id): ?Monitor
    {
        return Monitor::query()->find($id);
    }

    public function getById(int $id): Monitor
    {
        return Monitor::query()->findOrFail($id);
    }

    public function save(Monitor $monitor): Monitor
    {
        $monitor->save();

        return $monitor->refresh();
    }
}
