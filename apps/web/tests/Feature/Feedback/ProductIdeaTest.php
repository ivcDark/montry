<?php

namespace Tests\Feature\Feedback;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductIdeaTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_save_product_idea(): void
    {
        [$user, $organization] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->from('/sites')
            ->post('/product-ideas', [
                'title' => 'Групповая проверка сайтов',
                'description' => 'Хочу выбрать несколько сайтов и запустить проверку одним действием.',
                'type' => 'feature',
            ])
            ->assertRedirect('/sites')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('product_ideas', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'title' => 'Групповая проверка сайтов',
            'description' => 'Хочу выбрать несколько сайтов и запустить проверку одним действием.',
            'type' => 'feature',
            'status' => 'new',
        ]);
    }

    public function test_guest_cannot_save_product_idea(): void
    {
        $this
            ->from('/login')
            ->post('/product-ideas', [
                'title' => 'Темная тема',
                'description' => 'Хочу темную тему в кабинете.',
                'type' => 'improvement',
            ])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('product_ideas', 0);
    }

    public function test_product_idea_requires_valid_fields(): void
    {
        [$user] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->from('/sites')
            ->post('/product-ideas', [
                'title' => '',
                'description' => '',
                'type' => 'urgent',
            ])
            ->assertRedirect('/sites')
            ->assertSessionHasErrors(['title', 'description', 'type']);

        $this->assertDatabaseCount('product_ideas', 0);
    }

    /**
     * @return array{User, Organization}
     */
    private function createOrganizationContext(): array
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-'.$user->id,
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
