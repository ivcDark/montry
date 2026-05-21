<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class StartIntendedCheckout
{
    public function __construct(
        private PlanIntentService $planIntent,
        private CheckoutService $checkout,
        private GetCurrentOrganization $getCurrentOrganization,
    ) {}

    public function redirect(Request $request, User $user): RedirectResponse
    {
        $planCode = $this->planIntent->get($request);

        if ($planCode === null) {
            return redirect()->intended(route('dashboard.index', absolute: false));
        }

        $this->planIntent->clear($request);

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan === null || $plan->price_cents === 0) {
            return to_route('dashboard.index');
        }

        $organization = $this->getCurrentOrganization->handle($user);

        if ($this->hasActivePlan($organization->id, $plan->id)) {
            return to_route('billing.index');
        }

        $payment = $this->checkout->start($organization->id, $plan->code);

        return redirect()->route('billing.payments.show', $payment);
    }

    private function hasActivePlan(int $organizationId, int $planId): bool
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('plan_id', $planId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }
}
