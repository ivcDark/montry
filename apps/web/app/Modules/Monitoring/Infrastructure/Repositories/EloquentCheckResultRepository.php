<?php

namespace App\Modules\Monitoring\Infrastructure\Repositories;

use App\Modules\Monitoring\Domain\Contracts\CheckResultRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;

final class EloquentCheckResultRepository implements CheckResultRepositoryInterface
{
    public function findByEventId(string $eventId): ?CheckResult
    {
        return CheckResult::query()->where('event_id', $eventId)->first();
    }

    public function create(array $attributes): CheckResult
    {
        return CheckResult::query()->create($attributes);
    }
}
