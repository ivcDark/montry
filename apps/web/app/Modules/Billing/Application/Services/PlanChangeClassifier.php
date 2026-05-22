<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;

final class PlanChangeClassifier
{
    public function classify(?Plan $currentPlan, Plan $selectedPlan): string
    {
        if ($currentPlan?->id === $selectedPlan->id) {
            return 'same';
        }

        if ($currentPlan === null) {
            return 'upgrade';
        }

        if ($selectedPlan->sort_order !== $currentPlan->sort_order) {
            return $selectedPlan->sort_order > $currentPlan->sort_order
                ? 'upgrade'
                : 'downgrade';
        }

        return $selectedPlan->price_cents > $currentPlan->price_cents
            ? 'upgrade'
            : 'downgrade';
    }
}
