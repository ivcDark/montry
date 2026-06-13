<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;

final readonly class AssignFreeSubscription
{
    public function __construct(
        private BusinessEventRecorder $events,
    ) {
    }

    public function handle(int $organizationId): void
    {
        $freePlan = Plan::query()
            ->where('code', 'free')
            ->where('is_active', true)
            ->first();

        if ($freePlan === null) {
            return;
        }

        $subscription = Subscription::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'status' => 'active',
            ],
            [
                'plan_id' => $freePlan->id,
                'starts_at' => now(),
            ],
        );

        if ($subscription->wasRecentlyCreated) {
            $this->events->record(new RecordBusinessEventData(
                eventType: 'subscription.activated',
                organizationId: $organizationId,
                planCode: $freePlan->code,
                subjectType: 'subscription',
                subjectId: (string) $subscription->id,
                status: 'active',
                source: 'billing',
                payload: [
                    'plan_id' => $freePlan->id,
                    'price_cents' => $freePlan->price_cents,
                    'currency' => $freePlan->currency,
                ],
            ));
        }
    }
}
