<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;

final class AssignFreeSubscription
{
    public function handle(int $organizationId): void
    {
        $freePlan = Plan::query()
            ->where('code', 'free')
            ->where('is_active', true)
            ->first();

        if ($freePlan === null) {
            return;
        }

        Subscription::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'status' => 'active',
            ],
            [
                'plan_id' => $freePlan->id,
                'starts_at' => now(),
            ],
        );
    }
}
