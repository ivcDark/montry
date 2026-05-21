<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class CheckoutService
{
    public function start(int $organizationId, string $planCode): Payment
    {
        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($organizationId, $plan): Payment {
            Organization::query()
                ->lockForUpdate()
                ->findOrFail($organizationId);

            $existingPayment = Payment::query()
                ->where('organization_id', $organizationId)
                ->where('status', 'pending')
                ->whereHas('subscription', function ($query) use ($plan): void {
                    $query
                        ->where('plan_id', $plan->id)
                        ->where('status', 'pending');
                })
                ->latest('id')
                ->first();

            if ($existingPayment !== null) {
                return $existingPayment;
            }

            $subscription = Subscription::query()->create([
                'organization_id' => $organizationId,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'starts_at' => now(),
            ]);

            return Payment::query()->create([
                'organization_id' => $organizationId,
                'subscription_id' => $subscription->id,
                'provider' => 'manual',
                'status' => 'pending',
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'payload' => [
                    'plan_code' => $plan->code,
                    'period' => 'month',
                ],
            ]);
        });
    }

    public function confirm(Payment $payment): Subscription
    {
        return DB::transaction(function () use ($payment): Subscription {
            $payment = Payment::query()
                ->lockForUpdate()
                ->with('subscription.plan')
                ->findOrFail($payment->id);

            if ($payment->subscription === null) {
                throw new ModelNotFoundException('Payment has no subscription.');
            }

            Organization::query()
                ->lockForUpdate()
                ->findOrFail($payment->organization_id);

            if ($payment->status === 'paid') {
                return $payment->subscription;
            }

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            $subscription = $payment->subscription;
            $periodStart = now();

            Subscription::query()
                ->where('organization_id', $payment->organization_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'replaced',
                    'ends_at' => $periodStart,
                ]);

            $subscription->forceFill([
                'status' => 'active',
                'starts_at' => $periodStart,
                'ends_at' => $periodStart->copy()->addMonth(),
            ])->save();

            return $subscription;
        });
    }
}
