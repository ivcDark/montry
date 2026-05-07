<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_get_an_organization(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ivan Petrov',
            'email' => 'ivan@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/dashboard', parse_url($response->headers->get('Location'), PHP_URL_PATH));

        $user = User::query()->where('email', 'ivan@gmail.com')->firstOrFail();
        $organization = Organization::query()->where('name', 'Ivan Petrov')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => Organization::ROLE_OWNER,
        ]);
    }
}
