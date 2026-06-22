<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\BillingAddonCatalog;
use App\Modules\Billing\Application\Services\CheckoutService;
use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Billing\Application\Services\PlanChangeClassifier;
use App\Modules\Billing\Application\Services\RobokassaService;
use App\Modules\Billing\Application\Services\ScheduleDowngrade;
use App\Modules\Billing\Application\Services\UpdateSubscriptionAddons;
use App\Modules\Billing\Application\Services\YooKassaService;
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
    public function index(Request $request, GetCurrentOrganization $getCurrentOrganization, BillingAddonCatalog $addonCatalog, LimitChecker $limits): Response
    {
        $organization = $getCurrentOrganization->handle($request->user());

        $currentSubscription = $this->currentSubscription($organization->id);
        $scheduledSubscription = $this->scheduledSubscription($organization->id);
        $requestedPlanCode = $request->query('plan');
        $selectedPlanCode = is_string($requestedPlanCode) && $requestedPlanCode !== ''
            ? Plan::query()
                ->where('code', $requestedPlanCode)
                ->where('is_active', true)
                ->where('price_cents', '>', 0)
                ->value('code')
            : null;
        $restoredAddonQuantities = $addonCatalog->normalizeQuantities(
            $request->session()->get('billing.restored_addons', []),
        );

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
            'selectedPlanCode' => $selectedPlanCode,
            'restoredAddonQuantities' => $restoredAddonQuantities,
            'checkoutNotice' => $request->session()->get('billing.checkout_notice'),
            'plans' => Plan::query()
                ->with('limits')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan): array => $this->planPayload($plan))
                ->values(),
            'addonCatalog' => $addonCatalog->payload(),
            'currentAddons' => $currentSubscription?->items
                ? $currentSubscription->items->mapWithKeys(fn ($item): array => [$item->code => [
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'currency' => $item->currency,
                ]])->all()
                : [],
            'entitlements' => $limits->usageSummary((int) $organization->id),
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
                'site_limit' => $limits->effectiveSiteLimit((int) $organization->id),
            ],
        ]);
    }

    public function checkout(
        StartCheckoutRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        CheckoutService $checkout,
        BillingAddonCatalog $addonCatalog,
        ScheduleDowngrade $scheduleDowngrade,
        PlanChangeClassifier $planChangeClassifier,
        UpdateSubscriptionAddons $updateSubscriptionAddons,
        BusinessEventRecorder $events,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());
        $planCode = $request->string('plan_code')->toString();
        $addonQuantities = $addonCatalog->normalizeQuantities($request->validated('addons', []));
        $isAddonManagement = $request->boolean('manage_addons');
        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        $currentSubscription = $this->currentSubscription($organization->id);
        $changeType = $planChangeClassifier->classify($currentSubscription?->plan, $plan);

        if ($changeType === 'same' && ! $isAddonManagement && $addonQuantities === []) {
            return to_route('billing.index');
        }

        if ($changeType === 'same' && $currentSubscription !== null) {
            $currentAddonQuantities = $this->addonQuantities($currentSubscription);

            if ($this->sameAddonQuantities($addonQuantities, $currentAddonQuantities)) {
                return to_route('billing.index');
            }

            if (! $this->hasAddonIncrease($addonQuantities, $currentAddonQuantities)) {
                $updateSubscriptionAddons->handle($organization->id, $addonQuantities);

                return to_route('billing.index')
                    ->with('success', $addonQuantities === []
                        ? 'Дополнительные лимиты отключены.'
                        : 'Дополнительные лимиты обновлены.');
            }
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
                'addons_amount_cents' => $addonCatalog->totalCents($addonQuantities),
                'addon_quantities' => $addonQuantities,
                'currency' => $plan->currency,
            ],
        ));

        if ($changeType === 'downgrade') {
            if ($addonQuantities !== []) {
                return to_route('billing.index')
                    ->with('error', 'Дополнительные проверки нельзя подключить вместе с понижением тарифа. Сначала запланируйте понижение, затем подключите допы после смены тарифа.');
            }

            $scheduleDowngrade->handle($organization->id, $plan->code);

            return to_route('billing.index')
                ->with('success', 'Смена тарифа запланирована.');
        }

        $totalCents = $plan->price_cents + $addonCatalog->totalCents($addonQuantities);

        if ($totalCents === 0) {
            return to_route('billing.index');
        }

        $payment = $checkout->start($organization->id, $plan->code, $addonQuantities);

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
        RobokassaService $robokassa,
        YooKassaService $yookassa,
        BillingAddonCatalog $addonCatalog,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($payment->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        $payment->load(['subscription.plan', 'subscription.items']);

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
                'provider' => $payment->provider,
                'failed_at' => $payment->failed_at?->toISOString(),
                'failure_code' => $payment->failure_code,
                'failure_reason' => $payment->failure_reason,
                'plan' => $payment->subscription?->plan
                    ? $this->planPayload($payment->subscription->plan)
                    : null,
                'items' => $payment->subscription?->items
                    ? $payment->subscription->items->map(function ($item) use ($addonCatalog): array {
                        $catalogItem = $addonCatalog->all()[$item->code] ?? null;

                        return [
                            'code' => $item->code,
                            'name' => $catalogItem['name'] ?? $item->code,
                            'quantity' => $item->quantity,
                            'unit_price_cents' => $item->unit_price_cents,
                            'amount_cents' => $item->quantity * $item->unit_price_cents,
                        ];
                    })->values()
                    : [],
                'robokassa' => $robokassa->paymentForm($payment, $request->user()?->email),
                'yookassa' => $yookassa->paymentPayload($payment),
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

        $payment->load(['subscription.plan', 'subscription.items']);

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

        return to_route('sites.index')
            ->with('success', 'Тариф активирован.');
    }

    private function currentSubscription(int $organizationId): ?Subscription
    {
        return Subscription::query()
            ->with(['plan.limits', 'items'])
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
            ->with(['plan.limits', 'items'])
            ->where('organization_id', $organizationId)
            ->where('status', 'scheduled')
            ->where('starts_at', '>', now())
            ->latest('starts_at')
            ->first();
    }

    /**
     * @return array<string, int>
     */
    private function addonQuantities(Subscription $subscription): array
    {
        return $subscription->items
            ->mapWithKeys(fn ($item): array => [$item->code => (int) $item->quantity])
            ->all();
    }

    /**
     * @param array<string, int> $requested
     * @param array<string, int> $current
     */
    private function hasAddonIncrease(array $requested, array $current): bool
    {
        foreach ($requested as $code => $quantity) {
            if ($quantity > ($current[$code] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, int> $left
     * @param array<string, int> $right
     */
    private function sameAddonQuantities(array $left, array $right): bool
    {
        ksort($left);
        ksort($right);

        return $left === $right;
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
