<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Mail\RegistrationCompletedMail;
use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_registration_creates_unverified_user_and_sends_verification_code(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 12:00:00');

        $response = $this->post('/register', [
            'name' => 'Ivan Petrov',
            'email' => 'ivan@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/register/verify-code');
        $this->assertGuest();

        $user = User::query()->where('email', 'ivan@gmail.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertSame($user->id, session('pending_registration_user_id'));
        $this->assertDatabaseMissing('organizations', [
            'name' => 'Ivan Petrov',
        ]);
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
            'attempts' => 0,
            'consumed_at' => null,
        ]);

        $code = DB::table('email_verification_codes')
            ->where('user_id', $user->id)
            ->first();

        $this->assertSame(Carbon::now()->addMinutes(10)->toDateTimeString(), $code->expires_at);
        $this->assertSame(Carbon::now()->toDateTimeString(), $code->last_sent_at);
        $this->assertSame(0, preg_match('/^\d{5}$/', $code->code_hash));
        Mail::assertSentCount(1);
    }

    public function test_valid_code_verifies_user_creates_account_and_logs_user_in(): void
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
            ->withSession(['pending_registration_user_id' => $user->id])
            ->post('/register/verify-code', [
                'code' => '12345',
            ]);

        $response->assertRedirect('/dashboard');

        $user->refresh();
        $organization = Organization::query()->where('name', 'Ivan Petrov')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('pending_registration_user_id'));
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Owner->value,
        ]);
        $this->assertTrue(Project::query()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->exists());
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
            'consumed_at' => Carbon::now()->toDateTimeString(),
        ]);
        Mail::assertSent(RegistrationCompletedMail::class, function (RegistrationCompletedMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });
    }

    public function test_expired_code_does_not_verify_user(): void
    {
        Carbon::setTestNow('2026-05-18 12:00:00');
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('12345'),
            'expires_at' => Carbon::now()->subSecond(),
            'consumed_at' => null,
            'attempts' => 0,
            'last_sent_at' => Carbon::now()->subMinutes(10),
            'created_at' => Carbon::now()->subMinutes(10),
            'updated_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this
            ->withSession(['pending_registration_user_id' => $user->id])
            ->from('/register/verify-code')
            ->post('/register/verify-code', [
                'code' => '12345',
            ]);

        $response->assertRedirect('/register/verify-code');
        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_wrong_code_increments_attempts(): void
    {
        Carbon::setTestNow('2026-05-18 12:00:00');
        $user = User::factory()->create([
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
            ->withSession(['pending_registration_user_id' => $user->id])
            ->from('/register/verify-code')
            ->post('/register/verify-code', [
                'code' => '54321',
            ]);

        $response->assertRedirect('/register/verify-code');
        $response->assertSessionHasErrors('code');
        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
            'attempts' => 1,
            'consumed_at' => null,
        ]);
    }

    public function test_resend_code_is_blocked_until_cooldown_passes(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 12:00:00');
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('12345'),
            'expires_at' => Carbon::now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'last_sent_at' => Carbon::now()->subSeconds(119),
            'created_at' => Carbon::now()->subSeconds(119),
            'updated_at' => Carbon::now()->subSeconds(119),
        ]);

        $response = $this
            ->withSession(['pending_registration_user_id' => $user->id])
            ->from('/register/verify-code')
            ->post('/register/verify-code/resend');

        $response->assertRedirect('/register/verify-code');
        $response->assertSessionHasErrors('code');
        Mail::assertNothingSent();
        $this->assertSame(1, DB::table('email_verification_codes')->where('user_id', $user->id)->count());
    }

    public function test_resend_code_after_cooldown_invalidates_old_code_and_sends_new_one(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-05-18 12:00:00');
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        DB::table('email_verification_codes')->insert([
            'user_id' => $user->id,
            'code_hash' => Hash::make('12345'),
            'expires_at' => Carbon::now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'last_sent_at' => Carbon::now()->subSeconds(120),
            'created_at' => Carbon::now()->subSeconds(120),
            'updated_at' => Carbon::now()->subSeconds(120),
        ]);

        $response = $this
            ->withSession(['pending_registration_user_id' => $user->id])
            ->post('/register/verify-code/resend');

        $response->assertRedirect('/register/verify-code');
        $response->assertSessionHas('success');
        Mail::assertSentCount(1);
        $this->assertSame(2, DB::table('email_verification_codes')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('email_verification_codes')->where('user_id', $user->id)->whereNotNull('consumed_at')->count());
        $this->assertSame(1, DB::table('email_verification_codes')->where('user_id', $user->id)->whereNull('consumed_at')->count());
    }

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
}
