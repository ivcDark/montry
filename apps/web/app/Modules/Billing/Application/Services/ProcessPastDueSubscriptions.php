<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Application\Mail\SubscriptionPastDueReminderMail;
use App\Modules\Billing\Infrastructure\Persistence\Models\BillingNotificationLog;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class ProcessPastDueSubscriptions
{
    public function __construct(
        private readonly ApplySubscriptionLimits $applySubscriptionLimits,
    ) {}

    /**
     * @return array{past_due:int, warned:int, switched_to_free:int}
     */
    public function handle(): array
    {
        $movedToPastDue = $this->moveExpiredActiveSubscriptionsToPastDue();
        $warned = 0;
        $switchedToFree = 0;

        $subscriptionIds = Subscription::query()
            ->where('status', 'past_due')
            ->whereNotNull('ends_at')
            ->orderBy('ends_at')
            ->pluck('id');

        foreach ($subscriptionIds as $subscriptionId) {
            $subscription = Subscription::query()
                ->with(['organization.users', 'plan'])
                ->find($subscriptionId);

            if ($subscription === null || $subscription->plan?->code === 'free') {
                continue;
            }

            $daysPastDue = (int) $subscription->ends_at
                ->copy()
                ->startOfDay()
                ->diffInDays(now()->copy()->startOfDay());

            if ($daysPastDue > 3) {
                if ($this->switchToFree($subscription->id)) {
                    $switchedToFree++;
                }

                continue;
            }

            if ($daysPastDue >= 1 && $this->sendPastDueReminder($subscription, $daysPastDue)) {
                $warned++;
            }
        }

        return [
            'past_due' => $movedToPastDue,
            'warned' => $warned,
            'switched_to_free' => $switchedToFree,
        ];
    }

    private function moveExpiredActiveSubscriptionsToPastDue(): int
    {
        return Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->whereHas('plan', function ($query): void {
                $query
                    ->where('code', '!=', 'free')
                    ->where('price_cents', '>', 0);
            })
            ->update(['status' => 'past_due']);
    }

    private function sendPastDueReminder(Subscription $subscription, int $daysPastDue): bool
    {
        $eventType = "past_due_day_{$daysPastDue}";

        if ($this->alreadySent($subscription, $eventType)) {
            return false;
        }

        foreach ($this->recipients($subscription) as $recipient) {
            Mail::to($recipient->email)->send(new SubscriptionPastDueReminderMail(
                organizationName: $subscription->organization->name,
                planName: $subscription->plan->name,
                daysPastDue: $daysPastDue,
                freeSwitchDate: $subscription->ends_at->copy()->addDays(3)->timezone(config('app.timezone'))->format('d.m.Y'),
            ));
        }

        $this->markSent($subscription, $eventType);

        return true;
    }

    private function switchToFree(int $subscriptionId): bool
    {
        return DB::transaction(function () use ($subscriptionId): bool {
            $subscription = Subscription::query()
                ->with('plan')
                ->lockForUpdate()
                ->find($subscriptionId);

            if ($subscription === null || $subscription->status !== 'past_due' || $subscription->ends_at === null) {
                return false;
            }

            $daysPastDue = (int) $subscription->ends_at
                ->copy()
                ->startOfDay()
                ->diffInDays(now()->copy()->startOfDay());

            if ($daysPastDue <= 3) {
                return false;
            }

            $freePlan = Plan::query()
                ->with('limits')
                ->where('code', 'free')
                ->where('is_active', true)
                ->first();

            if ($freePlan === null) {
                return false;
            }

            Organization::query()
                ->lockForUpdate()
                ->findOrFail($subscription->organization_id);

            $subscription->forceFill([
                'status' => 'expired',
            ])->save();

            Subscription::query()
                ->where('organization_id', $subscription->organization_id)
                ->where('status', 'scheduled')
                ->update(['status' => 'canceled']);

            Subscription::query()->create([
                'organization_id' => $subscription->organization_id,
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'starts_at' => now(),
            ]);

            $this->applySubscriptionLimits->handle($subscription->organization_id, $freePlan);

            return true;
        });
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
