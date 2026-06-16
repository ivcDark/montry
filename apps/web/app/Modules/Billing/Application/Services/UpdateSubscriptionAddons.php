<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSubscriptionAddons
{
    public function __construct(
        private BillingAddonCatalog $addons,
        private ApplySubscriptionLimits $applySubscriptionLimits,
        private BusinessEventRecorder $events,
    ) {}

    /**
     * @param array<string, int> $quantities
     */
    public function handle(int $organizationId, array $quantities): Subscription
    {
        $quantities = $this->addons->normalizeQuantities($quantities);

        return DB::transaction(function () use ($organizationId, $quantities): Subscription {
            Organization::query()
                ->lockForUpdate()
                ->findOrFail($organizationId);

            $subscription = Subscription::query()
                ->lockForUpdate()
                ->with(['plan.limits', 'items'])
                ->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->latest('starts_at')
                ->first();

            if ($subscription === null || $subscription->plan === null) {
                throw new ModelNotFoundException('Active subscription was not found.');
            }

            $previousQuantities = $subscription->items
                ->mapWithKeys(fn ($item): array => [$item->code => (int) $item->quantity])
                ->all();

            foreach ($subscription->items as $item) {
                if (! array_key_exists($item->code, $quantities)) {
                    $item->delete();
                }
            }

            foreach ($quantities as $code => $quantity) {
                $subscription->items()->updateOrCreate(
                    ['code' => $code],
                    [
                        'quantity' => $quantity,
                        'unit_price_cents' => $this->addons->unitPriceCents($code),
                        'currency' => $subscription->plan->currency,
                        'meta' => $this->addons->all()[$code] ?? [],
                    ],
                );
            }

            $subscription->load(['plan.limits', 'items']);

            $this->applySubscriptionLimits->handle($organizationId, $subscription->plan);

            $this->events->record(new RecordBusinessEventData(
                eventType: 'subscription.addons_updated',
                organizationId: $organizationId,
                planCode: $subscription->plan->code,
                subjectType: 'subscription',
                subjectId: (string) $subscription->id,
                status: 'active',
                source: 'billing',
                payload: [
                    'previous_addon_quantities' => $previousQuantities,
                    'addon_quantities' => $quantities,
                ],
            ));

            return $subscription;
        });
    }
}
