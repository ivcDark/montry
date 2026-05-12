<?php

namespace App\Modules\Monitoring\Domain\Contracts;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;

interface MonitorRepositoryInterface
{
    public function create(array $attributes): Monitor;

    public function findById(int $id): ?Monitor;

    public function getById(int $id): Monitor;

    public function save(Monitor $monitor): Monitor;
}
