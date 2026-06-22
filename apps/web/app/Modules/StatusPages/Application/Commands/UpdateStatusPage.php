<?php

namespace App\Modules\StatusPages\Application\Commands;

use App\Modules\StatusPages\Application\DTO\SaveStatusPageData;
use App\Modules\StatusPages\Infrastructure\Persistence\Models\StatusPage;

final readonly class UpdateStatusPage
{
    public function __construct(
        public StatusPage $statusPage,
        public SaveStatusPageData $data,
    ) {}
}
