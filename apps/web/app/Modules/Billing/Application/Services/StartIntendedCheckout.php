<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class StartIntendedCheckout
{
    public function __construct(
        private PlanIntentService $planIntent,
        private GetCurrentOrganization $getCurrentOrganization,
        private BusinessEventRecorder $events,
    ) {}

    public function redirect(Request $request, User $user): RedirectResponse
    {
        $planCode = $this->planIntent->get($request);

        if ($planCode === null) {
            return redirect()->intended(route('sites.index', absolute: false));
        }

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan === null || $plan->price_cents === 0) {
            $this->planIntent->clear($request);

            return to_route('sites.index');
        }

        $organization = $this->getCurrentOrganization->handle($user);

        $this->events->record(new RecordBusinessEventData(
            eventType: 'plan.selected',
            organizationId: $organization->id,
            userId: $user->id,
            planCode: $plan->code,
            subjectType: 'plan',
            subjectId: (string) $plan->id,
            status: 'intended',
            source: 'registration',
            payload: [
                'selected_plan_code' => $plan->code,
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
            ],
        ));

        $this->planIntent->clear($request);

        return to_route('billing.index', ['plan' => $plan->code]);
    }
}
