<?php

use App\Modules\Billing\Application\Services\ApplySubscriptionLimits;
use App\Modules\Billing\Application\Services\ProcessPastDueSubscriptions;
use App\Modules\Billing\Application\Services\SendSubscriptionRenewalReminders;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('billing:send-renewal-reminders', function (SendSubscriptionRenewalReminders $reminders): int {
    $sent = $reminders->handle();

    $this->info("Sent {$sent} billing renewal reminders.");

    return self::SUCCESS;
})->purpose('Send paid billing renewal reminders before tariff expiration.');

Artisan::command('billing:process-past-due-subscriptions', function (ProcessPastDueSubscriptions $processor): int {
    $result = $processor->handle();

    $this->info(
        "Moved {$result['past_due']} subscriptions to past_due, sent {$result['warned']} warnings and switched {$result['switched_to_free']} subscriptions to free."
    );

    return self::SUCCESS;
})->purpose('Process paid subscription grace periods and switch unpaid accounts to free.');

Artisan::command('billing:expire-subscriptions', function (ProcessPastDueSubscriptions $processor): int {
    $result = $processor->handle();

    $this->info(
        "Moved {$result['past_due']} subscriptions to past_due, sent {$result['warned']} warnings and switched {$result['switched_to_free']} subscriptions to free."
    );

    return self::SUCCESS;
})->purpose('Compatibility alias for billing:process-past-due-subscriptions.');

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

            if ($subscription->plan->code === 'free' || $subscription->plan->price_cents === 0) {
                $subscription->forceFill([
                    'status' => 'active',
                ])->save();

                $applySubscriptionLimits->handle($subscription->organization_id, $subscription->plan);

                $activated++;

                return;
            }

            $subscription->forceFill([
                'status' => 'past_due',
                'ends_at' => $subscription->starts_at,
            ])->save();

            $activated++;
        });
    }

    $this->info("Activated {$activated} scheduled subscriptions.");

    return self::SUCCESS;
})->purpose('Activate scheduled billing downgrades and apply new plan limits.');

Schedule::command('billing:send-renewal-reminders')->dailyAt('09:00');
Schedule::command('billing:activate-scheduled-subscriptions')->dailyAt('09:10');
Schedule::command('billing:process-past-due-subscriptions')->dailyAt('09:20');
