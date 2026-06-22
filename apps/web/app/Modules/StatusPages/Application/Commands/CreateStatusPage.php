<?php

namespace App\Modules\StatusPages\Application\Commands;

use App\Modules\StatusPages\Application\DTO\SaveStatusPageData;

final readonly class CreateStatusPage
{
    public function __construct(public SaveStatusPageData $data) {}
}
