<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ScheduleDowngrade
{
    public function __construct(
        private PlanChangeClassifier $classifier,
        private BusinessEventRecorder $events,
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

            $subscription = Subscription::query()->create([
                'organization_id' => $organizationId,
                'plan_id' => $selectedPlan->id,
                'status' => 'scheduled',
                'starts_at' => $currentSubscription->ends_at,
            ]);

            $this->events->record(new RecordBusinessEventData(
                eventType: 'plan.changed',
                organizationId: $organizationId,
                planCode: $selectedPlan->code,
                subjectType: 'subscription',
                subjectId: (string) $subscription->id,
                status: 'scheduled',
                source: 'billing',
                payload: [
                    'change_type' => 'downgrade',
                    'from_plan_code' => $currentSubscription->plan?->code,
                    'to_plan_code' => $selectedPlan->code,
                    'starts_at' => $subscription->starts_at?->toISOString(),
                ],
            ));

            return $subscription;
        });
    }
}
