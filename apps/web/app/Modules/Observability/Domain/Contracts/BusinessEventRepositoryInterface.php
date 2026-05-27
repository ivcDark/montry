<?php

namespace App\Modules\Observability\Domain\Contracts;

use App\Modules\Observability\Domain\BusinessEvent;

interface BusinessEventRepositoryInterface
{
    public function add(BusinessEvent $event): void;
}

