<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Mail\SubscriptionRenewalReminderMail;
use App\Modules\Billing\Infrastructure\Persistence\Models\BillingNotificationLog;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

final class SendSubscriptionRenewalReminders
{
    public function handle(): int
    {
        $sent = 0;

        $subscriptions = Subscription::query()
            ->with(['organization.users', 'plan'])
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereHas('plan', function ($query): void {
                $query
                    ->where('code', '!=', 'free')
                    ->where('price_cents', '>', 0);
            })
            ->where(function ($query): void {
                $query
                    ->whereDate('ends_at', now()->copy()->addDays(3)->toDateString())
                    ->orWhereDate('ends_at', now()->copy()->addDay()->toDateString());
            })
            ->orderBy('ends_at')
            ->get();

        foreach ($subscriptions as $subscription) {
            $daysUntilExpiration = (int) now()
                ->copy()
                ->startOfDay()
                ->diffInDays($subscription->ends_at->copy()->startOfDay(), false);

            if (! in_array($daysUntilExpiration, [1, 3], true)) {
                continue;
            }

            $eventType = "renewal_{$daysUntilExpiration}_days";

            if ($this->alreadySent($subscription, $eventType)) {
                continue;
            }

            $scheduledSubscription = Subscription::query()
                ->with('plan')
                ->where('organization_id', $subscription->organization_id)
                ->where('status', 'scheduled')
                ->where('starts_at', '>=', $subscription->ends_at)
                ->orderBy('starts_at')
                ->first();

            foreach ($this->recipients($subscription) as $recipient) {
                Mail::to($recipient->email)->send(new SubscriptionRenewalReminderMail(
                    organizationName: $subscription->organization->name,
                    currentPlanName: $subscription->plan->name,
                    upcomingPlanName: $scheduledSubscription?->plan?->name,
                    daysUntilExpiration: $daysUntilExpiration,
                    expirationDate: $subscription->ends_at->timezone(config('app.timezone'))->format('d.m.Y'),
                ));
            }

            $this->markSent($subscription, $eventType);
            $sent++;
        }

        return $sent;
    }

    private function alreadySent(Subscription $subscription, string $eventType): bool
    {
        return BillingNotificationLog::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_type', $eventType)
            ->whereDate('event_date', now()->toDateString())
            ->exists();
    }

    private function markSent(Subscription $subscription, string $eventType): void
    {
        BillingNotificationLog::query()->firstOrCreate(
            [
                'subscription_id' => $subscription->id,
                'event_type' => $eventType,
                'event_date' => now()->toDateString(),
            ],
            [
                'organization_id' => $subscription->organization_id,
                'sent_at' => now(),
            ],
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Subscription $subscription): Collection
    {
        $users = $subscription->organization->users
            ->filter(fn (User $user): bool => $user->pivot->status === 'active' && $user->pivot->role === 'owner')
            ->values();

        if ($users->isNotEmpty()) {
            return $users;
        }

        return $subscription->organization->users
            ->filter(fn (User $user): bool => $user->pivot->status === 'active')
            ->values();
    }
}
