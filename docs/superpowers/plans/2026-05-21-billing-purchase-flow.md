# Billing Purchase Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the tariff purchase process from public pricing cards through registration/login, purchase confirmation, fake payment, and dashboard redirect.

**Architecture:** Use a session-based billing intent and the existing Billing models. Every new organization keeps an active Free subscription; paid checkout creates a separate pending subscription/payment, then fake payment confirmation activates it and replaces Free.

**Tech Stack:** Laravel, Inertia, Vue 3, existing Docker/Makefile test flow.

---

## File Map

- Modify `apps/web/app/Modules/Auth/Http/Controllers/RegisterController.php`: capture `?plan=` and redirect after verification through billing intent resolver.
- Modify `apps/web/app/Modules/Auth/Http/Controllers/RegisterVerificationController.php`: after successful email verification, resolve intended plan instead of always redirecting to dashboard.
- Modify `apps/web/app/Modules/Auth/Http/Controllers/LoginController.php`: capture `?plan=` on login page and resolve intended plan after login.
- Create `apps/web/app/Modules/Billing/Application/Services/PlanIntentService.php`: store, read, clear, and validate session plan intent.
- Create `apps/web/app/Modules/Billing/Application/Services/StartIntendedCheckout.php`: consume plan intent after auth and return the correct redirect.
- Modify `apps/web/app/Modules/Billing/Application/Services/CheckoutService.php`: reuse pending unpaid payment when possible.
- Modify `apps/web/app/Modules/Billing/Presentation/Http/Controllers/BillingController.php`: add fake bank page route action and change confirm redirect to dashboard.
- Modify `apps/web/app/Modules/Billing/Presentation/Routes/web.php`: add `GET /billing/payments/{payment}/fake-bank`.
- Modify `apps/web/resources/js/Pages/Auth/Register.vue`: preserve `plan` in the "Войти" link.
- Modify `apps/web/resources/js/Pages/Auth/Login.vue`: preserve `plan` in the "Создать аккаунт" link.
- Modify `apps/web/resources/js/Pages/Billing/Payment.vue`: make it purchase confirmation with "Перейти к оплате".
- Create `apps/web/resources/js/Pages/Billing/FakeBankPayment.vue`: wait 1 second, post confirmation, redirect through backend.
- Modify `apps/web/tests/Feature/Auth/RegisterTest.php`: cover Free and paid registration flows.
- Modify `apps/web/tests/Feature/Auth/LoginTest.php`: cover selected paid plan after login.
- Modify `apps/web/tests/Feature/Billing/BillingFlowTest.php`: cover authenticated checkout, fake bank confirmation, invalid plans, and cross-organization access.

## Task 1: Plan Intent Storage

**Files:**
- Create: `apps/web/app/Modules/Billing/Application/Services/PlanIntentService.php`
- Modify: `apps/web/app/Modules/Auth/Http/Controllers/RegisterController.php`
- Modify: `apps/web/app/Modules/Auth/Http/Controllers/LoginController.php`
- Modify: `apps/web/resources/js/Pages/Auth/Register.vue`
- Modify: `apps/web/resources/js/Pages/Auth/Login.vue`
- Test: `apps/web/tests/Feature/Billing/BillingFlowTest.php`

- [ ] **Step 1: Write failing tests for capturing valid and invalid plan intent**

Append these tests to `BillingFlowTest`:

```php
public function test_guest_register_page_stores_valid_plan_intent(): void
{
    $this->createPlan('studio', 299000);

    $this->get('/register?plan=studio')->assertOk();

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

public function test_guest_login_page_stores_valid_plan_intent(): void
{
    $this->createPlan('solo', 99000);

    $this->get('/login?plan=solo')->assertOk();

    $this->assertSame('solo', session('billing.intended_plan_code'));
}
```

- [ ] **Step 2: Run failing tests**

Run:

```bash
make test -- --filter=BillingFlowTest
```

Expected: at least `test_guest_register_page_stores_valid_plan_intent` fails because the session key is not set.

- [ ] **Step 3: Create `PlanIntentService`**

Create `apps/web/app/Modules/Billing/Application/Services/PlanIntentService.php`:

```php
<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use Illuminate\Http\Request;

final class PlanIntentService
{
    private const SESSION_KEY = 'billing.intended_plan_code';

    public function captureFromRequest(Request $request): void
    {
        $planCode = $request->query('plan');

        if (! is_string($planCode) || $planCode === '') {
            return;
        }

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            $this->clear($request);

            return;
        }

        $request->session()->put(self::SESSION_KEY, $plan->code);
    }

    public function get(Request $request): ?string
    {
        $planCode = $request->session()->get(self::SESSION_KEY);

        return is_string($planCode) && $planCode !== '' ? $planCode : null;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
```

- [ ] **Step 4: Capture intent in auth page controllers**

Update `RegisterController::create`:

```php
use App\Modules\Billing\Application\Services\PlanIntentService;
use Illuminate\Http\Request;

public function create(Request $request, PlanIntentService $planIntent): Response
{
    $planIntent->captureFromRequest($request);

    return Inertia::render('Auth/Register', [
        'intendedPlanCode' => $planIntent->get($request),
    ]);
}
```

Update `LoginController::create`:

```php
use App\Modules\Billing\Application\Services\PlanIntentService;
use Illuminate\Http\Request;

public function create(Request $request, PlanIntentService $planIntent): Response
{
    $planIntent->captureFromRequest($request);

    return Inertia::render('Auth/Login', [
        'intendedPlanCode' => $planIntent->get($request),
    ]);
}
```

- [ ] **Step 5: Preserve plan in auth cross-links**

In `Register.vue`, add props and computed login href:

```ts
import { computed } from 'vue'

const props = defineProps<{
    intendedPlanCode?: string | null
}>()

const loginHref = computed(() => props.intendedPlanCode ? `/login?plan=${props.intendedPlanCode}` : '/login')
```

Change the "Войти" link:

```vue
<Link :href="loginHref" class="font-bold text-[#0F6BFF] hover:text-[#0757D8]">
    Войти
</Link>
```

In `Login.vue`, add props and computed register href:

```ts
import { computed } from 'vue'

const props = defineProps<{
    intendedPlanCode?: string | null
}>()

const registerHref = computed(() => props.intendedPlanCode ? `/register?plan=${props.intendedPlanCode}` : '/register')
```

Change the "Создать аккаунт" link:

```vue
<Link :href="registerHref" class="font-bold text-[#0F6BFF] hover:text-[#0757D8]">
    Создать аккаунт
</Link>
```

- [ ] **Step 6: Run intent tests**

Run:

```bash
make test -- --filter=BillingFlowTest
```

Expected: the three new intent tests pass.

- [ ] **Step 7: Commit**

```bash
git add apps/web/app/Modules/Billing/Application/Services/PlanIntentService.php apps/web/app/Modules/Auth/Http/Controllers/RegisterController.php apps/web/app/Modules/Auth/Http/Controllers/LoginController.php apps/web/resources/js/Pages/Auth/Register.vue apps/web/resources/js/Pages/Auth/Login.vue apps/web/tests/Feature/Billing/BillingFlowTest.php
git commit -m "Add billing plan intent storage"
```

## Task 2: Post-Auth Checkout Resolution

**Files:**
- Create: `apps/web/app/Modules/Billing/Application/Services/StartIntendedCheckout.php`
- Modify: `apps/web/app/Modules/Auth/Http/Controllers/RegisterVerificationController.php`
- Modify: `apps/web/app/Modules/Auth/Http/Controllers/LoginController.php`
- Modify: `apps/web/app/Modules/Billing/Application/Services/CheckoutService.php`
- Test: `apps/web/tests/Feature/Auth/RegisterTest.php`
- Test: `apps/web/tests/Feature/Auth/LoginTest.php`

- [ ] **Step 1: Write failing registration tests**

Add imports to `RegisterTest`:

```php
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
```

Append:

```php
public function test_valid_code_with_paid_plan_intent_creates_free_subscription_and_pending_payment(): void
{
    Mail::fake();
    Carbon::setTestNow('2026-05-18 12:00:00');

    $freePlan = Plan::query()->create([
        'code' => 'free',
        'name' => 'Free',
        'price_cents' => 0,
        'currency' => 'RUB',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $studioPlan = Plan::query()->create([
        'code' => 'studio',
        'name' => 'Studio',
        'price_cents' => 299000,
        'currency' => 'RUB',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $user = User::factory()->create([
        'name' => 'Ivan Petrov',
        'email' => 'ivan@gmail.com',
        'email_verified_at' => null,
    ]);
    DB::table('email_verification_codes')->insert([
        'user_id' => $user->id,
        'code_hash' => Hash::make('12345'),
        'expires_at' => Carbon::now()->addMinutes(10),
        'consumed_at' => null,
        'attempts' => 0,
        'last_sent_at' => Carbon::now(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $response = $this
        ->withSession([
            'pending_registration_user_id' => $user->id,
            'billing.intended_plan_code' => 'studio',
        ])
        ->post('/register/verify-code', ['code' => '12345']);

    $payment = Payment::query()->firstOrFail();
    $organization = Organization::query()->where('name', 'Ivan Petrov')->firstOrFail();

    $response->assertRedirect("/billing/payments/{$payment->id}");
    $this->assertNull(session('billing.intended_plan_code'));
    $this->assertDatabaseHas('subscriptions', [
        'organization_id' => $organization->id,
        'plan_id' => $freePlan->id,
        'status' => 'active',
    ]);
    $this->assertDatabaseHas('subscriptions', [
        'organization_id' => $organization->id,
        'plan_id' => $studioPlan->id,
        'status' => 'pending',
    ]);
    $this->assertSame(299000, $payment->amount_cents);
    $this->assertSame('pending', $payment->status);
}

public function test_valid_code_with_free_plan_intent_does_not_create_payment(): void
{
    Mail::fake();
    Carbon::setTestNow('2026-05-18 12:00:00');

    Plan::query()->create([
        'code' => 'free',
        'name' => 'Free',
        'price_cents' => 0,
        'currency' => 'RUB',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $user = User::factory()->create([
        'name' => 'Ivan Petrov',
        'email_verified_at' => null,
    ]);
    DB::table('email_verification_codes')->insert([
        'user_id' => $user->id,
        'code_hash' => Hash::make('12345'),
        'expires_at' => Carbon::now()->addMinutes(10),
        'consumed_at' => null,
        'attempts' => 0,
        'last_sent_at' => Carbon::now(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $this
        ->withSession([
            'pending_registration_user_id' => $user->id,
            'billing.intended_plan_code' => 'free',
        ])
        ->post('/register/verify-code', ['code' => '12345'])
        ->assertRedirect('/dashboard');

    $this->assertDatabaseCount('payments', 0);
    $this->assertNull(session('billing.intended_plan_code'));
}
```

- [ ] **Step 2: Write failing login test**

Add imports to `LoginTest`:

```php
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Organizations\Enums\OrganizationRole;
```

Append:

```php
public function test_login_with_paid_plan_intent_redirects_to_purchase_confirmation(): void
{
    $user = User::factory()->create([
        'email' => 'ivan@gmail.com',
    ]);
    $organization = Organization::query()->create([
        'name' => 'Ivan Studio',
        'slug' => 'ivan-studio',
        'timezone' => '+3',
        'status' => 'active',
    ]);
    $organization->users()->attach($user->id, [
        'role' => OrganizationRole::Owner->value,
        'status' => 'active',
        'invited_at' => now(),
        'joined_at' => now(),
    ]);
    $freePlan = Plan::query()->create([
        'code' => 'free',
        'name' => 'Free',
        'price_cents' => 0,
        'currency' => 'RUB',
        'is_active' => true,
        'sort_order' => 0,
    ]);
    $studioPlan = Plan::query()->create([
        'code' => 'studio',
        'name' => 'Studio',
        'price_cents' => 299000,
        'currency' => 'RUB',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    Subscription::query()->create([
        'organization_id' => $organization->id,
        'plan_id' => $freePlan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
    ]);

    $response = $this
        ->withSession(['billing.intended_plan_code' => 'studio'])
        ->post('/login', [
            'email' => 'ivan@gmail.com',
            'password' => 'password',
            'remember' => false,
        ]);

    $payment = Payment::query()->firstOrFail();

    $response->assertRedirect("/billing/payments/{$payment->id}");
    $this->assertNull(session('billing.intended_plan_code'));
    $this->assertDatabaseHas('subscriptions', [
        'organization_id' => $organization->id,
        'plan_id' => $studioPlan->id,
        'status' => 'pending',
    ]);
}
```

- [ ] **Step 3: Run failing auth tests**

Run:

```bash
make test -- --filter=RegisterTest
make test -- --filter=LoginTest
```

Expected: paid intent tests fail because auth controllers still redirect to `/dashboard`.

- [ ] **Step 4: Implement `StartIntendedCheckout`**

Create `apps/web/app/Modules/Billing/Application/Services/StartIntendedCheckout.php`:

```php
<?php

namespace App\Modules\Billing\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class StartIntendedCheckout
{
    public function __construct(
        private PlanIntentService $planIntent,
        private CheckoutService $checkout,
        private GetCurrentOrganization $getCurrentOrganization,
    ) {}

    public function redirect(Request $request, User $user): RedirectResponse
    {
        $planCode = $this->planIntent->get($request);

        if ($planCode === null) {
            return redirect()->intended(route('dashboard.index', absolute: false));
        }

        $this->planIntent->clear($request);

        $plan = Plan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->first();

        if ($plan === null || $plan->price_cents === 0) {
            return to_route('dashboard.index');
        }

        $organization = $this->getCurrentOrganization->handle($user);

        if ($this->hasActivePlan($organization->id, $plan->id)) {
            return to_route('billing.index');
        }

        $payment = $this->checkout->start($organization->id, $plan->code);

        return redirect()->route('billing.payments.show', $payment);
    }

    private function hasActivePlan(int $organizationId, int $planId): bool
    {
        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where('plan_id', $planId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }
}
```

- [ ] **Step 5: Use resolver after verification and login**

Update `RegisterVerificationController::store` signature and return:

```php
use App\Modules\Billing\Application\Services\StartIntendedCheckout;

public function store(
    VerifyRegistrationCodeRequest $request,
    VerifyRegistrationEmailCode $verifyRegistrationEmailCode,
    StartIntendedCheckout $startIntendedCheckout,
): RedirectResponse {
    $user = $this->pendingUser($request);

    if (! $user) {
        return redirect()->route('register');
    }

    $verifyRegistrationEmailCode->handle($user, $request->string('code')->toString());

    $request->session()->forget('pending_registration_user_id');
    $request->session()->regenerate();

    return $startIntendedCheckout->redirect($request, $user);
}
```

Update `LoginController::store`:

```php
use App\Modules\Billing\Application\Services\StartIntendedCheckout;

public function store(LoginRequest $request, LoginUser $loginUser, StartIntendedCheckout $startIntendedCheckout): RedirectResponse
{
    $loginUser->handle($request->toData());

    $request->session()->regenerate();

    return $startIntendedCheckout->redirect($request, $request->user());
}
```

- [ ] **Step 6: Make checkout reuse pending payment for the same plan**

In `CheckoutService::start`, after loading `$plan`, return an existing pending payment for the same organization and plan:

```php
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
```

Keep creating a new pending subscription/payment only when no pending payment exists.

- [ ] **Step 7: Run auth tests**

Run:

```bash
make test -- --filter=RegisterTest
make test -- --filter=LoginTest
```

Expected: all auth tests pass.

- [ ] **Step 8: Commit**

```bash
git add apps/web/app/Modules/Billing/Application/Services/StartIntendedCheckout.php apps/web/app/Modules/Auth/Http/Controllers/RegisterVerificationController.php apps/web/app/Modules/Auth/Http/Controllers/LoginController.php apps/web/app/Modules/Billing/Application/Services/CheckoutService.php apps/web/tests/Feature/Auth/RegisterTest.php apps/web/tests/Feature/Auth/LoginTest.php
git commit -m "Start checkout from selected plan intent"
```

## Task 3: Purchase Confirmation and Fake Bank Page

**Files:**
- Modify: `apps/web/app/Modules/Billing/Presentation/Http/Controllers/BillingController.php`
- Modify: `apps/web/app/Modules/Billing/Presentation/Routes/web.php`
- Modify: `apps/web/resources/js/Pages/Billing/Payment.vue`
- Create: `apps/web/resources/js/Pages/Billing/FakeBankPayment.vue`
- Test: `apps/web/tests/Feature/Billing/BillingFlowTest.php`

- [ ] **Step 1: Write failing billing flow tests**

Update `test_user_can_start_checkout_and_confirm_payment_for_plan` in `BillingFlowTest` so confirmation goes through fake bank and ends at dashboard:

```php
$this
    ->actingAs($user)
    ->get("/billing/payments/{$payment->id}/fake-bank")
    ->assertOk();

$this
    ->actingAs($user)
    ->post("/billing/payments/{$payment->id}/confirm")
    ->assertRedirect('/dashboard');
```

Append:

```php
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
```

- [ ] **Step 2: Run failing billing tests**

Run:

```bash
make test -- --filter=BillingFlowTest
```

Expected: fake bank `GET` route fails because it does not exist, and confirm redirects to `/billing`.

- [ ] **Step 3: Add fake bank route and controller action**

In `apps/web/app/Modules/Billing/Presentation/Routes/web.php` add before confirm:

```php
Route::get('/billing/payments/{payment}/fake-bank', [BillingController::class, 'fakeBank'])
    ->name('billing.payments.fake-bank');
```

Add to `BillingController`:

```php
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
```

Change confirm return:

```php
return to_route('dashboard.index')
    ->with('success', 'Тариф активирован.');
```

- [ ] **Step 4: Update purchase confirmation page**

In `Payment.vue`, change the primary button:

```vue
<Link
    :href="`/billing/payments/${payment.id}/fake-bank`"
    class="flex h-12 items-center justify-center rounded-xl bg-[#0F6BFF] px-6 text-sm font-extrabold text-white transition hover:bg-[#0757D8]"
>
    Перейти к оплате
</Link>
```

Keep the secondary `/billing` link.

- [ ] **Step 5: Create fake bank page**

Create `apps/web/resources/js/Pages/Billing/FakeBankPayment.vue`:

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { onMounted } from 'vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'

type Plan = {
    name: string
    description: string | null
}

const props = defineProps<{
    organization: { id: string | number; name: string }
    payment: {
        id: number
        status: string
        amount_cents: number
        currency: string
        plan: Plan | null
    }
}>()

const amount = new Intl.NumberFormat('ru-RU').format(props.payment.amount_cents / 100)

onMounted(() => {
    window.setTimeout(() => {
        router.post(`/billing/payments/${props.payment.id}/confirm`)
    }, 1000)
})
</script>

<template>
    <Head title="Оплата" />

    <DashboardLayout
        :organization="organization"
        active-item="billing"
        title="Оплата"
        subtitle="Фиктивная страница оплаты для MVP"
    >
        <section class="mx-auto max-w-3xl px-5 py-8 sm:px-8">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6">
                <p class="text-sm font-extrabold text-[#12B3A8]">Банк Montry</p>
                <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">{{ payment.plan?.name }}</h2>
                <p class="mt-2 text-[#667085]">Платеж обрабатывается. После подтверждения тариф включится автоматически.</p>
                <p class="mt-6 text-4xl font-extrabold text-[#111827]">{{ amount }} ₽</p>
                <div class="mt-8 h-2 overflow-hidden rounded-full bg-[#E5E7EB]">
                    <div class="h-full w-2/3 animate-pulse rounded-full bg-[#0F6BFF]" />
                </div>
            </div>
        </section>
    </DashboardLayout>
</template>
```

- [ ] **Step 6: Run billing tests**

Run:

```bash
make test -- --filter=BillingFlowTest
```

Expected: billing tests pass.

- [ ] **Step 7: Commit**

```bash
git add apps/web/app/Modules/Billing/Presentation/Http/Controllers/BillingController.php apps/web/app/Modules/Billing/Presentation/Routes/web.php apps/web/resources/js/Pages/Billing/Payment.vue apps/web/resources/js/Pages/Billing/FakeBankPayment.vue apps/web/tests/Feature/Billing/BillingFlowTest.php
git commit -m "Add fake bank payment flow"
```

## Task 4: Public and Authenticated Pricing Links

**Files:**
- Modify: `apps/web/resources/js/Pages/Welcome.vue`
- Modify: `apps/web/resources/js/Pages/Billing/Index.vue`
- Test: `apps/web/tests/Feature/Billing/BillingFlowTest.php`

- [ ] **Step 1: Add billing request test for Free/current plan**

Append to `BillingFlowTest`:

```php
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
```

- [ ] **Step 2: Run failing test**

Run:

```bash
make test -- --filter=BillingFlowTest
```

Expected: Free checkout currently creates a pending payment or redirects to payment. Invalid checkout should already fail through `StartCheckoutRequest`; keep that coverage in place.

- [ ] **Step 3: Update authenticated checkout to skip Free/current plan**

In `BillingController::checkout`, after `$organization` and before starting checkout, load plan and current subscription:

```php
$planCode = $request->string('plan_code')->toString();
$plan = Plan::query()
    ->where('code', $planCode)
    ->where('is_active', true)
    ->firstOrFail();

$currentSubscription = $this->currentSubscription($organization->id);

if ($plan->price_cents === 0 || $currentSubscription?->plan_id === $plan->id) {
    return to_route('billing.index');
}

$payment = $checkout->start($organization->id, $plan->code);
```

- [ ] **Step 4: Make public pricing links submit checkout for authenticated users**

In `Welcome.vue`, keep guest users on registration and submit checkout directly for authenticated users:

```ts
function planHref(planCode: string): string {
    return user ? '/billing/checkout' : `/register?plan=${planCode}`
}

function planMethod(): 'get' | 'post' {
    return user ? 'post' : 'get'
}

function planAs(): 'a' | 'button' {
    return user ? 'button' : 'a'
}

function planData(planCode: string): Record<string, string> {
    return user ? { plan_code: planCode } : {}
}
```

Update the pricing card `Link`:

```vue
<Link
    :href="planHref(plan.code)"
    :method="planMethod()"
    :as="planAs()"
    :data="planData(plan.code)"
    class="mt-8 inline-flex h-12 w-full items-center justify-center rounded-xl px-5 text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-[#0F6BFF]/30 focus:ring-offset-2"
    :class="plan.featured ? 'bg-[#0F6BFF] text-white hover:bg-[#0757D8]' : 'border border-[#E5E7EB] bg-white text-[#111827] hover:border-[#CBD5E1]'"
>
    {{ user ? 'Выбрать тариф' : plan.cta }}
</Link>
```

In `Billing/Index.vue`, keep authenticated checkout as `POST /billing/checkout` with `plan_code`.

- [ ] **Step 5: Run billing tests**

Run:

```bash
make test -- --filter=BillingFlowTest
```

Expected: all billing tests pass.

- [ ] **Step 6: Commit**

```bash
git add apps/web/app/Modules/Billing/Presentation/Http/Controllers/BillingController.php apps/web/resources/js/Pages/Welcome.vue apps/web/resources/js/Pages/Billing/Index.vue apps/web/tests/Feature/Billing/BillingFlowTest.php
git commit -m "Skip checkout for free and current plans"
```

## Task 5: Final Verification

**Files:**
- No planned source changes.

- [ ] **Step 1: Run focused PHP tests**

Run:

```bash
make test -- --filter=RegisterTest
make test -- --filter=LoginTest
make test -- --filter=BillingFlowTest
```

Expected: all three commands pass.

- [ ] **Step 2: Run full Laravel test suite**

Run:

```bash
make test
```

Expected: full Laravel test suite passes.

- [ ] **Step 3: Check git diff**

Run:

```bash
git status --short
git diff --stat
```

Expected: only files from this plan are modified beyond any pre-existing user changes.

- [ ] **Step 4: Manual browser smoke path**

Start the app if needed:

```bash
make up
```

Open the app and smoke these flows:

```text
1. Visit /, click a paid pricing card.
2. Register, verify code through the local mail tool, and confirm redirect to /billing/payments/{id}.
3. Click "Перейти к оплате", wait 1 second, confirm redirect to /dashboard.
4. Log out, click a paid pricing card, click "Войти", log in, confirm redirect to /billing/payments/{id}.
5. Click Free from public pricing, register, verify, confirm redirect to /dashboard with no payment.
```

- [ ] **Step 5: Final commit**

If Task 5 required any small fixes, commit them:

```bash
git add apps/web
git commit -m "Verify billing purchase flow"
```

If Task 5 made no changes, do not create an empty commit.
