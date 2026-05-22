<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ScheduleDowngrade
{
    public function __construct(
        private PlanChangeClassifier $classifier,
    ) {}

    public function handle(int $organizationId, string $planCode): Subscription
    {
        $selectedPlan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($organizationId, $selectedPlan): Subscription {
            Organization::query()
                ->lockForUpdate()
                ->findOrFail($organizationId);

            $currentSubscription = Subscription::query()
                ->with('plan')
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->latest('starts_at')
                ->lockForUpdate()
                ->first();

            if ($currentSubscription === null || $currentSubscription->plan === null) {
                throw ValidationException::withMessages([
                    'plan_code' => 'Текущий тариф не найден.',
                ]);
            }

            if ($this->classifier->classify($currentSubscription->plan, $selectedPlan) !== 'downgrade') {
                throw ValidationException::withMessages([
                    'plan_code' => 'Выбранный тариф не является понижением.',
                ]);
            }

            if ($currentSubscription->ends_at === null) {
                throw ValidationException::withMessages([
                    'plan_code' => 'Понижение доступно только для тарифа с датой окончания.',
                ]);
            }

            Subscription::query()
                ->where('organization_id', $organizationId)
                ->where('status', 'scheduled')
                ->update(['status' => 'canceled']);

            return Subscription::query()->create([
                'organization_id' => $organizationId,
                'plan_id' => $selectedPlan->id,
                'status' => 'scheduled',
                'starts_at' => $currentSubscription->ends_at,
            ]);
        });
    }
}
