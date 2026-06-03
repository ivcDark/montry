<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Application\Mail\SubscriptionPastDueReminderMail;
use App\Modules\Billing\Application\Mail\SubscriptionRenewalReminderMail;
use App\Modules\Billing\Infrastructure\Persistence\Models\BillingNotificationLog;
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_checkout_and_confirm_payment_for_plan(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0);
        $pro = $this->createPlan('pro', 99000);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/billing/checkout', [
                'plan_code' => 'pro',
            ]);

        $payment = Payment::query()->firstOrFail();
        $response->assertRedirect("/billing/payments/{$payment->id}");

        $pendingSubscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('plan_id', $pro->id)
            ->firstOrFail();

        $this->assertSame('pending', $payment->status);
        $this->assertSame(99000, $payment->amount_cents);
        $this->assertSame('pending', $pendingSubscription->status);

        $this
            ->actingAs($user)
            ->get("/billing/payments/{$payment->id}/fake-bank")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/FakeBankPayment', false)
                ->where('organization.id', $organization->id)
                ->where('payment.id', $payment->id)
                ->where('payment.amount_cents', 99000)
                ->where('payment.plan.code', 'pro')
            );

        $this
            ->actingAs($user)
            ->post("/billing/payments/{$payment->id}/confirm")
            ->assertRedirect('/dashboard');

        $payment->refresh();
        $pendingSubscription->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('active', $pendingSubscription->status);
        $this->assertNotNull($pendingSubscription->ends_at);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'replaced',
        ]);
    }

    public function test_checkout_reuses_existing_pending_payment_for_same_plan(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $plan = $this->createPlan('studio', 299000);
        $subscription = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now()->subMinute(),
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'provider' => 'manual',
            'status' => 'pending',
            'amount_cents' => 299000,
            'currency' => 'RUB',
            'payload' => ['plan_code' => 'studio', 'period' => 'month'],
        ]);

        $this
            ->actingAs($user)
            ->post('/billing/checkout', ['plan_code' => 'studio'])
            ->assertRedirect("/billing/payments/{$payment->id}");

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_confirming_paid_payment_is_idempotent(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $plan = $this->createPlan('studio', 299000);
        $subscription = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'provider' => 'manual',
            'status' => 'paid',
            'amount_cents' => 299000,
            'currency' => 'RUB',
            'payload' => ['plan_code' => 'studio', 'period' => 'month'],
            'paid_at' => now()->subDays(5),
        ]);

        $originalStartsAt = $subscription->starts_at->toDateTimeString();
        $originalEndsAt = $subscription->ends_at->toDateTimeString();
        $originalPaidAt = $payment->paid_at->toDateTimeString();

        $this
            ->actingAs($user)
            ->post("/billing/payments/{$payment->id}/confirm")
            ->assertRedirect('/dashboard');

        $subscription->refresh();
        $payment->refresh();

        $this->assertSame($originalStartsAt, $subscription->starts_at->toDateTimeString());
        $this->assertSame($originalEndsAt, $subscription->ends_at->toDateTimeString());
        $this->assertSame($originalPaidAt, $payment->paid_at->toDateTimeString());
    }

    public function test_user_cannot_open_payment_from_another_organization(): void
    {
        [$user] = $this->createOrganizationContext();
        [, $otherOrganization] = $this->createOrganizationContext();
        $plan = $this->createPlan('studio', 299000);
        $subscription = Subscription::query()->create([
            'organization_id' => $otherOrganization->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'starts_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $otherOrganization->id,
            'subscription_id' => $subscription->id,
            'provider' => 'fake_bank',
            'status' => 'pending',
            'amount_cents' => 299000,
            'currency' => 'RUB',
            'payload' => ['plan_code' => 'studio'],
        ]);

        $this->actingAs($user)->get("/billing/payments/{$payment->id}")->assertNotFound();
        $this->actingAs($user)->get("/billing/payments/{$payment->id}/fake-bank")->assertNotFound();
        $this->actingAs($user)->post("/billing/payments/{$payment->id}/confirm")->assertNotFound();
    }

    public function test_authenticated_checkout_for_current_free_plan_does_not_create_payment(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $this
            ->actingAs($user)
            ->post('/billing/checkout', ['plan_code' => 'free'])
            ->assertRedirect('/billing');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_authenticated_checkout_for_lower_plan_schedules_downgrade_without_payment(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0, 10);
        $pro = $this->createPlan('pro', 99000, 20);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);

        $this
            ->actingAs($user)
            ->post('/billing/checkout', ['plan_code' => 'free'])
            ->assertRedirect('/billing');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_authenticated_checkout_rejects_invalid_plan_code(): void
    {
        [$user] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->from('/billing')
            ->post('/billing/checkout', ['plan_code' => 'missing'])
            ->assertRedirect('/billing')
            ->assertSessionHasErrors('plan_code');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_user_can_schedule_downgrade_after_current_paid_period(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0, 10);
        $pro = $this->createPlan('pro', 99000, 20);
        $periodEnd = now()->addDays(12);

        $activeSubscription = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subDays(18),
            'ends_at' => $periodEnd,
        ]);

        $this
            ->actingAs($user)
            ->post('/billing/schedule-downgrade', ['plan_code' => 'free'])
            ->assertRedirect('/billing');

        $activeSubscription->refresh();
        $scheduledSubscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('plan_id', $free->id)
            ->where('status', 'scheduled')
            ->firstOrFail();

        $this->assertSame('active', $activeSubscription->status);
        $this->assertSame($periodEnd->toDateTimeString(), $scheduledSubscription->starts_at->toDateTimeString());
        $this->assertNull($scheduledSubscription->ends_at);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_scheduling_downgrade_replaces_previous_scheduled_downgrade(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0, 10);
        $pro = $this->createPlan('pro', 99000, 20);
        $plus = $this->createPlan('plus', 249000, 30);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plus->id,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);

        $this
            ->actingAs($user)
            ->post('/billing/schedule-downgrade', ['plan_code' => 'pro'])
            ->assertRedirect('/billing');

        $this
            ->actingAs($user)
            ->post('/billing/schedule-downgrade', ['plan_code' => 'free'])
            ->assertRedirect('/billing');

        $this->assertSame(1, Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'scheduled')
            ->count());
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'canceled',
        ]);
    }

    public function test_paid_upgrade_cancels_scheduled_downgrade(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0, 10);
        $pro = $this->createPlan('pro', 99000, 20);
        $plus = $this->createPlan('plus', 249000, 30);
        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);
        $scheduledDowngrade = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'scheduled',
            'starts_at' => now()->addDays(20),
        ]);

        $this
            ->actingAs($user)
            ->post('/billing/checkout', ['plan_code' => 'plus'])
            ->assertRedirect();

        $payment = Payment::query()->firstOrFail();

        $this
            ->actingAs($user)
            ->post("/billing/payments/{$payment->id}/confirm")
            ->assertRedirect('/dashboard');

        $scheduledDowngrade->refresh();

        $this->assertSame('canceled', $scheduledDowngrade->status);
    }

    public function test_activating_scheduled_downgrade_pauses_excess_monitors_by_created_at(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $project = $this->createProject($organization);
        $resource = $this->createResource($organization, $project, $user);
        $free = $this->createPlan('free', 0, 10, [
            'max_monitors' => ['limit' => 2],
            'allowed_monitor_types' => ['types' => ['http', 'ssl']],
        ]);
        $pro = $this->createPlan('pro', 99000, 20, [
            'max_monitors' => ['limit' => 10],
            'allowed_monitor_types' => ['types' => ['http', 'ssl', 'domain']],
        ]);
        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMinute(),
        ]);
        $scheduledFree = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'scheduled',
            'starts_at' => now()->subMinute(),
        ]);

        $oldHttp = $this->createMonitor($organization, $project, $resource, 'http', now()->subDays(4));
        $oldSsl = $this->createMonitor($organization, $project, $resource, 'ssl', now()->subDays(3));
        $newHttp = $this->createMonitor($organization, $project, $resource, 'http', now()->subDays(2));
        $domain = $this->createMonitor($organization, $project, $resource, 'domain', now()->subDay());

        $this->artisan('billing:activate-scheduled-subscriptions')
            ->assertSuccessful();

        $scheduledFree->refresh();
        $oldHttp->refresh();
        $oldSsl->refresh();
        $newHttp->refresh();
        $domain->refresh();

        $this->assertSame('active', $scheduledFree->status);
        $this->assertTrue($oldHttp->enabled);
        $this->assertTrue($oldSsl->enabled);
        $this->assertFalse($newHttp->enabled);
        $this->assertSame('paused', $newHttp->status);
        $this->assertFalse($domain->enabled);
        $this->assertSame('paused', $domain->status);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'replaced',
        ]);
    }

    public function test_activating_free_plan_keeps_only_latest_three_sites_with_http_and_ssl_monitors(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $project = $this->createProject($organization);
        $free = $this->createPlan('free', 0, 10, [
            'max_sites' => ['limit' => 3],
            'max_monitors' => ['limit' => 6],
            'allowed_monitor_types' => ['types' => ['http', 'ssl']],
        ]);
        $pro = $this->createPlan('pro', 99000, 20, [
            'max_sites' => ['limit' => 50],
            'max_monitors' => ['limit' => 150],
            'allowed_monitor_types' => ['types' => ['http', 'ssl', 'domain']],
        ]);
        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMinute(),
        ]);
        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'scheduled',
            'starts_at' => now()->subMinute(),
        ]);

        $resources = collect(range(1, 5))->map(function (int $number) use ($organization, $project, $user): MonitoredResource {
            $resource = $this->createResource($organization, $project, $user, "site-{$number}.example.com");
            $resource->forceFill([
                'created_at' => now()->subDays(6 - $number),
                'updated_at' => now()->subDays(6 - $number),
            ])->save();

            foreach (['http', 'ssl', 'domain'] as $type) {
                $this->createMonitor($organization, $project, $resource, $type, now()->subDays(6 - $number));
            }

            return $resource;
        });

        $this->artisan('billing:activate-scheduled-subscriptions')
            ->assertSuccessful();

        $oldResources = $resources->take(2);
        $latestResources = $resources->slice(2);

        foreach ($oldResources as $resource) {
            $resource->refresh();
            $this->assertSame('paused', $resource->status);
            $this->assertFalse($resource->monitors()->where('enabled', true)->exists());
        }

        foreach ($latestResources as $resource) {
            $resource->refresh();
            $enabledTypes = $resource->monitors()->where('enabled', true)->pluck('type')->sort()->values()->all();

            $this->assertSame(['http', 'ssl'], $enabledTypes);
            $this->assertFalse($resource->monitors()->where('type', 'domain')->firstOrFail()->enabled);
        }
    }

    public function test_activating_scheduled_paid_plan_starts_grace_period_automatically(): void
    {
        [, $organization] = $this->createOrganizationContext();
        $pro = $this->createPlan('pro', 99000, 20);
        $plus = $this->createPlan('plus', 249000, 30);

        $activePlus = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plus->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMinute(),
        ]);
        $scheduledPro = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'scheduled',
            'starts_at' => now()->subMinute(),
        ]);

        $this->artisan('billing:activate-scheduled-subscriptions')
            ->assertSuccessful();

        $activePlus->refresh();
        $scheduledPro->refresh();

        $this->assertSame('replaced', $activePlus->status);
        $this->assertSame('past_due', $scheduledPro->status);
        $this->assertSame($scheduledPro->starts_at->toDateTimeString(), $scheduledPro->ends_at->toDateTimeString());
    }

    public function test_renewal_reminder_command_sends_paid_plan_reminder_three_days_before_expiration(): void
    {
        Mail::fake();
        [, $organization] = $this->createOrganizationContext();
        $plan = $this->createPlan('pro', 99000);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDays(27),
            'ends_at' => now()->addDays(3),
        ]);

        $this->artisan('billing:send-renewal-reminders')
            ->assertSuccessful();

        Mail::assertSent(SubscriptionRenewalReminderMail::class, function (SubscriptionRenewalReminderMail $mail): bool {
            return $mail->daysUntilExpiration === 3
                && $mail->currentPlanName === 'Pro'
                && $mail->upcomingPlanName === null;
        });
    }

    public function test_renewal_reminder_command_mentions_scheduled_plan(): void
    {
        Mail::fake();
        [, $organization] = $this->createOrganizationContext();
        $pro = $this->createPlan('pro', 99000, 20);
        $plus = $this->createPlan('plus', 249000, 30);
        $periodEnd = now()->addDay();

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plus->id,
            'status' => 'active',
            'starts_at' => now()->subDays(29),
            'ends_at' => $periodEnd,
        ]);
        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'scheduled',
            'starts_at' => $periodEnd,
        ]);

        $this->artisan('billing:send-renewal-reminders')
            ->assertSuccessful();

        Mail::assertSent(SubscriptionRenewalReminderMail::class, function (SubscriptionRenewalReminderMail $mail): bool {
            return $mail->daysUntilExpiration === 1
                && $mail->currentPlanName === 'Plus'
                && $mail->upcomingPlanName === 'Pro';
        });
    }

    public function test_renewal_reminder_command_skips_free_plan_and_deduplicates_paid_reminders(): void
    {
        Mail::fake();
        [, $freeOrganization] = $this->createOrganizationContext();
        [, $paidOrganization] = $this->createOrganizationContext();
        $free = $this->createPlan('free', 0);
        $pro = $this->createPlan('pro', 99000);

        Subscription::query()->create([
            'organization_id' => $freeOrganization->id,
            'plan_id' => $free->id,
            'status' => 'active',
            'starts_at' => now()->subDays(27),
            'ends_at' => now()->addDays(3),
        ]);
        $paidSubscription = Subscription::query()->create([
            'organization_id' => $paidOrganization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subDays(27),
            'ends_at' => now()->addDays(3),
        ]);

        $this->artisan('billing:send-renewal-reminders')->assertSuccessful();
        $this->artisan('billing:send-renewal-reminders')->assertSuccessful();

        Mail::assertSent(SubscriptionRenewalReminderMail::class, 1);
        $this->assertSame(1, BillingNotificationLog::query()
            ->where('subscription_id', $paidSubscription->id)
            ->where('event_type', 'renewal_3_days')
            ->count());
    }

    public function test_process_past_due_subscriptions_sends_daily_warning_during_grace_period(): void
    {
        Mail::fake();
        [, $organization] = $this->createOrganizationContext();
        $plan = $this->createPlan('pro', 99000);
        $subscription = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->artisan('billing:process-past-due-subscriptions')
            ->assertSuccessful();

        $subscription->refresh();

        $this->assertSame('past_due', $subscription->status);
        Mail::assertSent(SubscriptionPastDueReminderMail::class, function (SubscriptionPastDueReminderMail $mail): bool {
            return $mail->daysPastDue === 1
                && $mail->planName === 'Pro';
        });
    }

    public function test_process_past_due_subscriptions_switches_to_free_after_grace_period_and_applies_limits(): void
    {
        Mail::fake();
        [$user, $organization] = $this->createOrganizationContext();
        $project = $this->createProject($organization);
        $resource = $this->createResource($organization, $project, $user);
        $free = $this->createPlan('free', 0, 10, [
            'max_monitors' => ['limit' => 1],
            'allowed_monitor_types' => ['types' => ['http']],
        ]);
        $pro = $this->createPlan('pro', 99000, 20);
        $pastDue = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'past_due',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDays(4),
        ]);

        $oldHttp = $this->createMonitor($organization, $project, $resource, 'http', now()->subDays(3));
        $newHttp = $this->createMonitor($organization, $project, $resource, 'http', now()->subDays(2));
        $domain = $this->createMonitor($organization, $project, $resource, 'domain', now()->subDay());

        $this->artisan('billing:process-past-due-subscriptions')
            ->assertSuccessful();

        $pastDue->refresh();
        $oldHttp->refresh();
        $newHttp->refresh();
        $domain->refresh();

        $this->assertSame('expired', $pastDue->status);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $free->id,
            'status' => 'active',
        ]);
        $this->assertTrue($oldHttp->enabled);
        $this->assertFalse($newHttp->enabled);
        $this->assertFalse($domain->enabled);
    }

    public function test_expiration_command_moves_active_subscription_to_past_due_then_free_after_grace_period(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $this->createPlan('free', 0);
        $plan = $this->createPlan('pro', 99000);

        $subscription = Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subHour(),
        ]);

        $this->artisan('billing:expire-subscriptions')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);

        $subscription->forceFill([
            'ends_at' => now()->subDays(4),
        ])->save();

        $this->artisan('billing:expire-subscriptions')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_guest_register_page_stores_valid_plan_intent(): void
    {
        $this->createPlan('studio', 299000);

        $this
            ->get('/register?plan=studio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('intendedPlanCode', 'studio')
            );

        $this->assertSame('studio', session('billing.intended_plan_code'));
    }

    public function test_guest_register_page_clears_invalid_plan_intent(): void
    {
        $this
            ->withSession(['billing.intended_plan_code' => 'studio'])
            ->get('/register?plan=missing')
            ->assertOk();

        $this->assertNull(session('billing.intended_plan_code'));
    }

    public function test_guest_register_page_clears_empty_plan_intent(): void
    {
        $this
            ->withSession(['billing.intended_plan_code' => 'studio'])
            ->get('/register?plan=')
            ->assertOk();

        $this->assertNull(session('billing.intended_plan_code'));
    }

    public function test_guest_register_page_clears_array_plan_intent(): void
    {
        $this
            ->withSession(['billing.intended_plan_code' => 'studio'])
            ->get('/register?plan[]=studio')
            ->assertOk();

        $this->assertNull(session('billing.intended_plan_code'));
    }

    public function test_guest_login_page_stores_valid_plan_intent(): void
    {
        $this->createPlan('solo', 99000);

        $this
            ->get('/login?plan=solo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('intendedPlanCode', 'solo')
            );

        $this->assertSame('solo', session('billing.intended_plan_code'));
    }

    private function createPlan(string $code, int $priceCents, int $sortOrder = 0, array $limits = []): Plan
    {
        $plan = Plan::query()->create([
            'code' => $code,
            'name' => str($code)->headline()->toString(),
            'price_cents' => $priceCents,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);

        foreach ($limits as $key => $value) {
            $plan->limits()->create([
                'key' => $key,
                'value' => $value,
            ]);
        }

        return $plan;
    }

    /**
     * @return array{User, Organization}
     */
    private function createOrganizationContext(): array
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-'.str()->random(8),
            'timezone' => '+3',
            'status' => 'active',
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }

    private function createProject(Organization $organization): Project
    {
        return Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default',
            'color' => '#ffffff',
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }

    private function createResource(
        Organization $organization,
        Project $project,
        User $user,
        string $host = 'example.com',
    ): MonitoredResource {
        return MonitoredResource::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_user_id' => $user->id,
            'type' => 'website',
            'name' => $host,
            'target' => "https://{$host}",
            'scheme' => 'https',
            'host' => $host,
            'path' => '/',
            'status' => 'unknown',
        ]);
    }

    private function createMonitor(
        Organization $organization,
        Project $project,
        MonitoredResource $resource,
        string $type,
        \DateTimeInterface $createdAt,
    ): Monitor {
        $monitor = Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => $type,
            'name' => str($type)->headline()->toString(),
            'enabled' => true,
            'status' => 'ok',
            'interval_seconds' => 300,
            'timeout_ms' => 10000,
            'settings' => ['type' => $type],
            'expected' => [],
        ]);

        $monitor->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $monitor;
    }
}
