<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\CheckoutService;
use App\Modules\Billing\Application\Services\PlanChangeClassifier;
use App\Modules\Billing\Application\Services\ScheduleDowngrade;
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Billing\Presentation\Http\Requests\ScheduleDowngradeRequest;
use App\Modules\Billing\Presentation\Http\Requests\StartCheckoutRequest;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\AuditLogger;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class BillingController extends Controller
{
    public function index(Request $request, GetCurrentOrganization $getCurrentOrganization): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        $currentSubscription = $this->currentSubscription($organization->id);
        $scheduledSubscription = $this->scheduledSubscription($organization->id);

        return Inertia::render('Billing/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'currentSubscription' => $currentSubscription ? [
                'id' => $currentSubscription->id,
                'status' => $currentSubscription->status,
                'starts_at' => $currentSubscription->starts_at?->toISOString(),
                'ends_at' => $currentSubscription->ends_at?->toISOString(),
                'plan' => $this->planPayload($currentSubscription->plan),
            ] : null,
            'scheduledSubscription' => $scheduledSubscription ? [
                'id' => $scheduledSubscription->id,
                'status' => $scheduledSubscription->status,
                'starts_at' => $scheduledSubscription->starts_at?->toISOString(),
                'plan' => $this->planPayload($scheduledSubscription->plan),
            ] : null,
            'plans' => Plan::query()
                ->with('limits')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan): array => $this->planPayload($plan))
                ->values(),
            'usage' => [
                'sites' => MonitoredResource::query()
                    ->where('organization_id', $organization->id)
                    ->where('type', 'website')
                    ->count(),
                'monitors' => Monitor::query()
                    ->where('organization_id', $organization->id)
                    ->count(),
                'active_monitors' => Monitor::query()
                    ->where('organization_id', $organization->id)
                    ->where('enabled', true)
                    ->count(),
            ],
        ]);
    }

    public function checkout(
        StartCheckoutRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        CheckoutService $checkout,
        ScheduleDowngrade $scheduleDowngrade,
        PlanChangeClassifier $planChangeClassifier,
        BusinessEventRecorder $events,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());
        $planCode = $request->string('plan_code')->toString();
        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        $currentSubscription = $this->currentSubscription($organization->id);
        $changeType = $planChangeClassifier->classify($currentSubscription?->plan, $plan);

        if ($changeType === 'same') {
            return to_route('billing.index');
        }

        $events->record(new RecordBusinessEventData(
            eventType: 'plan.selected',
            organizationId: $organization->id,
            userId: $request->user()?->id,
            planCode: $plan->code,
            subjectType: 'plan',
            subjectId: (string) $plan->id,
            status: $changeType,
            source: 'web',
            payload: [
                'current_plan_code' => $currentSubscription?->plan?->code,
                'selected_plan_code' => $plan->code,
                'change_type' => $changeType,
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
            ],
        ));

        if ($changeType === 'downgrade') {
            $scheduleDowngrade->handle($organization->id, $plan->code);

            return to_route('billing.index')
                ->with('success', 'Смена тарифа запланирована.');
        }

        if ($plan->price_cents === 0) {
            return to_route('billing.index');
        }

        $payment = $checkout->start($organization->id, $plan->code);

        return redirect()->route('billing.payments.show', $payment);
    }

    public function scheduleDowngrade(
        ScheduleDowngradeRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        ScheduleDowngrade $scheduleDowngrade,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());

        $scheduleDowngrade->handle($organization->id, $request->string('plan_code')->toString());

        return to_route('billing.index')
            ->with('success', 'Смена тарифа запланирована.');
    }

    public function payment(
        Request $request,
        Payment $payment,
        GetCurrentOrganization $getCurrentOrganization,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($payment->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        $payment->load('subscription.plan');

        return Inertia::render('Billing/Payment', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'plan' => $payment->subscription?->plan
                    ? $this->planPayload($payment->subscription->plan)
                    : null,
            ],
        ]);
    }

    public function fakeBank(
        Request $request,
        Payment $payment,
        GetCurrentOrganization $getCurrentOrganization,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($payment->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        $payment->load('subscription.plan');

        return Inertia::render('Billing/FakeBankPayment', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'plan' => $payment->subscription?->plan
                    ? $this->planPayload($payment->subscription->plan)
                    : null,
            ],
        ]);
    }

    public function confirm(
        Request $request,
        Payment $payment,
        GetCurrentOrganization $getCurrentOrganization,
        CheckoutService $checkout,
        AuditLogger $audit,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($payment->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        $this->verifyOptionalPaymentSignature($request, $payment, $audit);

        $checkout->confirm($payment);

        return to_route('dashboard.index')
            ->with('success', 'Тариф активирован.');
    }

    private function currentSubscription(int $organizationId): ?Subscription
    {
        return Subscription::query()
            ->with('plan.limits')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    private function scheduledSubscription(int $organizationId): ?Subscription
    {
        return Subscription::query()
            ->with('plan.limits')
            ->where('organization_id', $organizationId)
            ->where('status', 'scheduled')
            ->where('starts_at', '>', now())
            ->latest('starts_at')
            ->first();
    }

    private function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'sort_order' => $plan->sort_order,
            'limits' => $plan->limits
                ->mapWithKeys(fn ($limit): array => [$limit->key => $limit->value])
                ->all(),
        ];
    }

    private function verifyOptionalPaymentSignature(Request $request, Payment $payment, AuditLogger $audit): void
    {
        $secret = (string) config('services.fake_bank.webhook_secret', '');
        $signatureValue = $request->headers->get('X-Montry-Payment-Signature') ?? $request->input('signature', '');
        $signature = is_scalar($signatureValue) ? (string) $signatureValue : '';

        if ($secret === '' || $signature === '') {
            return;
        }

        $expected = hash_hmac(
            'sha256',
            implode('|', [$payment->id, $payment->amount_cents, $payment->currency]),
            $secret,
        );

        if (hash_equals($expected, $signature)) {
            return;
        }

        $audit->record(
            category: 'security',
            action: 'payment.signature_failed',
            outcome: 'failed',
            request: $request,
            actorUserId: $request->user()?->id,
            organizationId: $payment->organization_id,
            targetType: 'payment',
            targetId: (string) $payment->id,
            source: 'billing',
            metadata: [
                'provider' => $payment->provider,
                'signature_hash' => $audit->hashValue($signature),
            ],
        );

        abort(403, 'Invalid payment signature.');
    }
}
