<?php

namespace Tests\Feature\Auth;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
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

        $response->assertRedirect('/dashboard');

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

        $response->assertRedirect('/dashboard');

        $rememberCookie = $this->getResponseCookie($response, Auth::guard('web')->getRecallerName());

        $this->assertSame(
            Carbon::now()->addDays(30)->getTimestamp(),
            $rememberCookie->getExpiresTime(),
        );
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
