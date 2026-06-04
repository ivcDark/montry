<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class CheckoutService
{
    public function __construct(
        private BusinessEventRecorder $events,
    ) {
    }

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

            $payment = Payment::query()->create([
                'organization_id' => $organizationId,
                'subscription_id' => $subscription->id,
                'provider' => 'robokassa',
                'status' => 'pending',
                'amount_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'payload' => [
                    'plan_code' => $plan->code,
                    'period' => 'month',
                    'provider' => 'robokassa',
                    'mode' => config('services.robokassa.mode', 'test'),
                ],
            ]);

            $this->events->record(new RecordBusinessEventData(
                eventType: 'payment.started',
                organizationId: $organizationId,
                planCode: $plan->code,
                subjectType: 'payment',
                subjectId: (string) $payment->id,
                status: 'pending',
                source: 'billing',
                payload: [
                    'subscription_id' => $subscription->id,
                    'provider' => $payment->provider,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                ],
            ));

            return $payment;
        });
    }

    /**
     * @param array<string, mixed> $providerPayload
     */
    public function confirm(Payment $payment, array $providerPayload = [], ?string $providerPaymentId = null): Subscription
    {
        return DB::transaction(function () use ($payment, $providerPayload, $providerPaymentId): Subscription {
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
                $this->mergeProviderPayload($payment, $providerPayload, $providerPaymentId);

                return $payment->subscription;
            }

            $payment->forceFill([
                'status' => 'paid',
                'provider_payment_id' => $providerPaymentId ?: $payment->provider_payment_id,
                'payload' => $this->mergedPayload($payment, $providerPayload),
                'paid_at' => now(),
                'failed_at' => null,
                'failure_code' => null,
                'failure_reason' => null,
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

            Subscription::query()
                ->where('organization_id', $payment->organization_id)
                ->where('status', 'scheduled')
                ->update(['status' => 'canceled']);

            $subscription->forceFill([
                'status' => 'active',
                'starts_at' => $periodStart,
                'ends_at' => $periodStart->copy()->addMonth(),
            ])->save();

            $plan = $subscription->plan;

            $this->events->record(new RecordBusinessEventData(
                eventType: 'payment.succeeded',
                organizationId: $payment->organization_id,
                planCode: $plan?->code,
                subjectType: 'payment',
                subjectId: (string) $payment->id,
                status: 'paid',
                source: 'billing',
                payload: [
                    'subscription_id' => $subscription->id,
                    'provider' => $payment->provider,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                ],
            ));

            $this->events->record(new RecordBusinessEventData(
                eventType: 'subscription.activated',
                organizationId: $payment->organization_id,
                planCode: $plan?->code,
                subjectType: 'subscription',
                subjectId: (string) $subscription->id,
                status: 'active',
                source: 'billing',
                payload: [
                    'payment_id' => $payment->id,
                    'plan_id' => $plan?->id,
                    'starts_at' => $subscription->starts_at?->toISOString(),
                    'ends_at' => $subscription->ends_at?->toISOString(),
                ],
            ));

            $this->events->record(new RecordBusinessEventData(
                eventType: 'plan.changed',
                organizationId: $payment->organization_id,
                planCode: $plan?->code,
                subjectType: 'subscription',
                subjectId: (string) $subscription->id,
                status: 'active',
                source: 'billing',
                payload: [
                    'payment_id' => $payment->id,
                    'plan_id' => $plan?->id,
                ],
            ));

            return $subscription;
        });
    }

    /**
     * @param array<string, mixed> $providerPayload
     */
    public function markFailed(Payment $payment, string $failureCode, string $failureReason, array $providerPayload = []): Payment
    {
        return DB::transaction(function () use ($payment, $failureCode, $failureReason, $providerPayload): Payment {
            $payment = Payment::query()
                ->lockForUpdate()
                ->with('subscription.plan')
                ->findOrFail($payment->id);

            if ($payment->status === 'paid') {
                return $payment;
            }

            $payment->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_code' => mb_substr($failureCode, 0, 64),
                'failure_reason' => $failureReason,
                'payload' => $this->mergedPayload($payment, $providerPayload),
            ])->save();

            if ($payment->subscription !== null && $payment->subscription->status === 'pending') {
                $payment->subscription->forceFill(['status' => 'canceled'])->save();
            }

            $plan = $payment->subscription?->plan;

            $this->events->record(new RecordBusinessEventData(
                eventType: 'payment.failed',
                organizationId: $payment->organization_id,
                planCode: $plan?->code,
                subjectType: 'payment',
                subjectId: (string) $payment->id,
                status: 'failed',
                source: 'billing',
                payload: [
                    'subscription_id' => $payment->subscription_id,
                    'provider' => $payment->provider,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'failure_code' => $payment->failure_code,
                ],
            ));

            return $payment;
        });
    }

    /**
     * @param array<string, mixed> $providerPayload
     */
    private function mergeProviderPayload(Payment $payment, array $providerPayload, ?string $providerPaymentId): void
    {
        if ($providerPayload === [] && ($providerPaymentId === null || $providerPaymentId === $payment->provider_payment_id)) {
            return;
        }

        $payment->forceFill([
            'provider_payment_id' => $providerPaymentId ?: $payment->provider_payment_id,
            'payload' => $this->mergedPayload($payment, $providerPayload),
        ])->save();
    }

    /**
     * @param array<string, mixed> $providerPayload
     * @return array<string, mixed>
     */
    private function mergedPayload(Payment $payment, array $providerPayload): array
    {
        $payload = is_array($payment->payload) ? $payment->payload : [];

        return array_replace_recursive($payload, $providerPayload);
    }
}
