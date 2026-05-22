<?php

use App\Modules\Billing\Application\Services\ApplySubscriptionLimits;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:expire-subscriptions', function (): int {
    $now = now();

    $pastDue = Subscription::query()
        ->where('status', 'active')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', $now)
        ->update(['status' => 'past_due']);

    $expired = Subscription::query()
        ->where('status', 'past_due')
        ->whereNotNull('ends_at')
        ->where('ends_at', '<=', $now->copy()->subDays(3))
        ->update(['status' => 'expired']);

    $this->info("Moved {$pastDue} subscriptions to past_due and {$expired} subscriptions to expired.");

    return self::SUCCESS;
})->purpose('Expire active billing subscriptions after the paid period and grace period.');

Artisan::command('billing:activate-scheduled-subscriptions', function (ApplySubscriptionLimits $applySubscriptionLimits): int {
    $subscriptionIds = Subscription::query()
        ->where('status', 'scheduled')
        ->where('starts_at', '<=', now())
        ->orderBy('starts_at')
        ->pluck('id');

    $activated = 0;

    foreach ($subscriptionIds as $subscriptionId) {
        DB::transaction(function () use ($subscriptionId, $applySubscriptionLimits, &$activated): void {
            $subscription = Subscription::query()
                ->with('plan.limits')
                ->lockForUpdate()
                ->find($subscriptionId);

            if ($subscription === null || $subscription->status !== 'scheduled' || $subscription->starts_at->isFuture()) {
                return;
            }

            Subscription::query()
                ->where('organization_id', $subscription->organization_id)
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'replaced',
                    'ends_at' => $subscription->starts_at,
                ]);

            $subscription->forceFill([
                'status' => 'active',
            ])->save();

            $applySubscriptionLimits->handle($subscription->organization_id, $subscription->plan);

            $activated++;
        });
    }

    $this->info("Activated {$activated} scheduled subscriptions.");

    return self::SUCCESS;
})->purpose('Activate scheduled billing downgrades and apply new plan limits.');
