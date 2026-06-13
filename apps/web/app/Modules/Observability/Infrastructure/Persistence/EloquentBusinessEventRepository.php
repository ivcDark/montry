<?php

namespace App\Modules\Observability\Infrastructure\Persistence;

use App\Modules\Observability\Domain\BusinessEvent;
use App\Modules\Observability\Domain\Contracts\BusinessEventRepositoryInterface;
use App\Modules\Observability\Infrastructure\Persistence\Models\BusinessEvent as BusinessEventModel;

final class EloquentBusinessEventRepository implements BusinessEventRepositoryInterface
{
    public function add(BusinessEvent $event): void
    {
        BusinessEventModel::query()->create([
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'occurred_at' => $event->occurredAt,
            'organization_id' => $event->organizationId,
            'user_id' => $event->userId,
            'plan_code' => $event->planCode,
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'status' => $event->status,
            'source' => $event->source,
            'correlation_id' => $event->correlationId,
            'payload' => $event->payload,
        ]);
    }
}

