<?php

namespace Tests\Feature\Admin;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminUsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_users_page(): void
    {
        $this
            ->get('/admin/users')
            ->assertRedirect('/login');
    }

    public function test_regular_user_cannot_open_admin_users_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this
            ->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_open_users_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        User::factory()->create([
            'name' => 'Alice Owner',
            'email' => 'alice@example.com',
        ]);

        $this
            ->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index', false)
                ->has('users', 2)
                ->where('users.0.email', 'alice@example.com')
            );
    }
}
