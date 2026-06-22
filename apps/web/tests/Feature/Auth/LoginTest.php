<?php

namespace Tests\Feature\Auth;

use App\Modules\Billing\Infrastructure\Persistence\Models\Payment;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_login_session_cookie_lasts_seven_days_when_remember_is_not_selected(): void
    {
        Carbon::setTestNow('2026-05-18 12:00:00');
        User::factory()->create([
            'email' => 'ivan@gmail.com',
        ]);

        $response = $this->post('/login', [
            'email' => 'ivan@gmail.com',
            'password' => 'password',
            'remember' => false,
        ]);

        $response->assertRedirect('/sites');

        $sessionCookie = $this->getResponseCookie($response, config('session.cookie'));

        $this->assertSame(
            Carbon::now()->addDays(7)->getTimestamp(),
            $sessionCookie->getExpiresTime(),
        );
    }

    public function test_remember_me_cookie_lasts_thirty_days_when_selected(): void
    {
        Carbon::setTestNow('2026-05-18 12:00:00');
        User::factory()->create([
            'email' => 'ivan@gmail.com',
        ]);

        $response = $this->post('/login', [
            'email' => 'ivan@gmail.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect('/sites');

        $rememberCookie = $this->getResponseCookie($response, Auth::guard('web')->getRecallerName());

        $this->assertSame(
            Carbon::now()->addDays(30)->getTimestamp(),
            $rememberCookie->getExpiresTime(),
        );
    }

    public function test_login_with_paid_plan_intent_redirects_to_billing_configuration(): void
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

        $response->assertRedirect('/billing?plan=studio');
        $this->assertNull(session('billing.intended_plan_code'));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $studioPlan->id,
            'status' => 'pending',
        ]);
    }

    private function getResponseCookie(object $response, string $name): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        $this->fail("Cookie [{$name}] was not set.");
    }
}
