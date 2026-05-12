<?php

namespace App\Modules\Monitoring\Domain\Contracts;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;

interface CheckResultRepositoryInterface
{
    public function create(array $attributes): CheckResult;
}
