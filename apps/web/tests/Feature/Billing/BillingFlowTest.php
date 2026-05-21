<?php

namespace Tests\Feature\Billing;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this
            ->actingAs($user)
            ->post('/billing/checkout', [
                'plan_code' => 'pro',
            ])
            ->assertRedirect('/billing/payments/1');

        $payment = Payment::query()->firstOrFail();
        $pendingSubscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('plan_id', $pro->id)
            ->firstOrFail();

        $this->assertSame('pending', $payment->status);
        $this->assertSame(99000, $payment->amount_cents);
        $this->assertSame('pending', $pendingSubscription->status);

        $this
            ->actingAs($user)
            ->post("/billing/payments/{$payment->id}/confirm")
            ->assertRedirect('/billing');

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
            ->assertRedirect('/billing');

        $subscription->refresh();
        $payment->refresh();

        $this->assertSame($originalStartsAt, $subscription->starts_at->toDateTimeString());
        $this->assertSame($originalEndsAt, $subscription->ends_at->toDateTimeString());
        $this->assertSame($originalPaidAt, $payment->paid_at->toDateTimeString());
    }

    public function test_expiration_command_moves_active_subscription_to_past_due_then_expired(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
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

    private function createPlan(string $code, int $priceCents): Plan
    {
        return Plan::query()->create([
            'code' => $code,
            'name' => str($code)->headline()->toString(),
            'price_cents' => $priceCents,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 0,
        ]);
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
}
