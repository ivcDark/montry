<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_settings_page(): void
    {
        [$user, $organization] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index', false)
                ->where('organization.id', $organization->id)
                ->where('settings.profile.name', $user->name)
                ->where('settings.telegram.notifications_enabled', false)
                ->where('settings.telegram.is_connected', false)
            );
    }

    public function test_user_can_update_profile_name(): void
    {
        [$user] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'name' => 'Ivan Petrov',
            ])
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Настройки профиля сохранены.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Ivan Petrov',
        ]);
    }

    public function test_user_can_enable_telegram_notifications_and_get_connect_token(): void
    {
        [$user] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->patch('/settings/telegram', [
                'telegram_notifications_enabled' => true,
            ])
            ->assertRedirect('/settings')
            ->assertSessionHas('success', 'Настройки Telegram сохранены.');

        $user->refresh();

        $this->assertTrue($user->telegram_notifications_enabled);
        $this->assertNotNull($user->telegram_connection_token);
        $this->assertNull($user->telegram_chat_id);
        $this->assertNull($user->telegram_connected_at);
    }

    public function test_settings_page_exposes_telegram_setup_url_when_bot_username_is_configured(): void
    {
        config()->set('services.telegram.bot_username', 'montry_bot');

        [$user] = $this->createOrganizationContext();
        $user->forceFill([
            'telegram_notifications_enabled' => true,
            'telegram_connection_token' => 'connect-token-123',
        ])->save();

        $this
            ->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.telegram.setup_url', 'https://t.me/montry_bot?start=connect-token-123')
                ->where('settings.telegram.bot_username', 'montry_bot')
            );
    }

    /**
     * @return array{User, Organization}
     */
    private function createOrganizationContext(): array
    {
        $user = User::factory()->create([
            'name' => 'Ivan',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Ivan Studio',
            'slug' => 'ivan-studio-'.str()->random(8),
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
