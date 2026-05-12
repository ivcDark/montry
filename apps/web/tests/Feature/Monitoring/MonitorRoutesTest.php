<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MonitorRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_sites_index(): void
    {
        [$user] = $this->createOrganizationContext();

        $this
            ->actingAs($user)
            ->get('/sites')
            ->assertOk();
    }

    public function test_authenticated_user_can_create_http_monitor(): void
    {
        [$user, $organization, $project] = $this->createOrganizationContext();

        $resource = MonitoredResource::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_user_id' => $user->id,
            'type' => 'website',
            'name' => 'Example',
            'target' => 'https://example.com',
            'scheme' => 'https',
            'host' => 'example.com',
            'path' => '/',
            'status' => 'unknown',
        ]);

        $this
            ->actingAs($user)
            ->post("/sites/{$resource->id}/monitors", [
                'type' => 'http',
                'name' => 'HTTP check',
                'is_enabled' => true,
                'interval_seconds' => 60,
                'timeout_ms' => 10000,
                'settings' => [
                    'method' => 'GET',
                    'url' => 'https://example.com',
                    'follow_redirects' => true,
                    'verify_ssl' => true,
                ],
                'expected' => [
                    'status_codes' => [200],
                    'max_response_time_ms' => 5000,
                ],
            ])
            ->assertRedirect("/sites/{$resource->id}");

        $this->assertDatabaseHas('monitors', [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'http',
            'name' => 'HTTP check',
            'enabled' => true,
        ]);
    }

    /**
     * @return array{User, Organization, Project}
     */
    private function createOrganizationContext(): array
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'timezone' => '+3',
            'status' => 'active',
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'default',
            'color' => '#ffffff',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        return [$user, $organization, $project];
    }
}
