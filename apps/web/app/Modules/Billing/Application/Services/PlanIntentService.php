<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use Illuminate\Http\Request;

final class PlanIntentService
{
    private const SESSION_KEY = 'billing.intended_plan_code';

    public function captureFromRequest(Request $request): void
    {
        $planCode = $request->query('plan');

        if (! is_string($planCode) || $planCode === '') {
            return;
        }

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            $this->clear($request);

            return;
        }

        $request->session()->put(self::SESSION_KEY, $plan->code);
    }

    public function get(Request $request): ?string
    {
        $planCode = $request->session()->get(self::SESSION_KEY);

        return is_string($planCode) && $planCode !== '' ? $planCode : null;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
