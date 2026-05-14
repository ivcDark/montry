<?php

namespace Tests\Feature\Projects;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProjectIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_projects_index_for_current_organization(): void
    {
        [$user, $organization, $project] = $this->createOrganizationContext('Acme');
        [$otherUser, $otherOrganization, $otherProject] = $this->createOrganizationContext('Other');

        $resource = MonitoredResource::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_user_id' => $user->id,
            'type' => 'website',
            'name' => 'Storefront',
            'target' => 'https://store.example.com',
            'scheme' => 'https',
            'host' => 'store.example.com',
            'path' => '/',
            'status' => 'down',
        ]);

        Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'http',
            'name' => 'HTTP check',
            'enabled' => true,
            'status' => 'failure',
            'interval_seconds' => 300,
            'timeout_ms' => 10000,
            'settings' => ['url' => 'https://store.example.com'],
            'expected' => ['status_codes' => [200]],
            'last_failure_at' => now()->subMinutes(18),
        ]);

        Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'ssl',
            'name' => 'SSL check',
            'enabled' => true,
            'status' => 'success',
            'interval_seconds' => 86400,
            'timeout_ms' => 10000,
            'settings' => ['domain' => 'store.example.com'],
            'expected' => [],
        ]);

        $otherResource = MonitoredResource::query()->create([
            'organization_id' => $otherOrganization->id,
            'project_id' => $otherProject->id,
            'created_user_id' => $otherUser->id,
            'type' => 'website',
            'name' => 'Other Storefront',
            'target' => 'https://other.example.com',
            'scheme' => 'https',
            'host' => 'other.example.com',
            'path' => '/',
            'status' => 'up',
        ]);

        Monitor::query()->create([
            'organization_id' => $otherOrganization->id,
            'project_id' => $otherProject->id,
            'monitored_resource_id' => $otherResource->id,
            'type' => 'http',
            'name' => 'Other HTTP check',
            'enabled' => true,
            'status' => 'success',
            'interval_seconds' => 300,
            'timeout_ms' => 10000,
            'settings' => ['url' => 'https://other.example.com'],
            'expected' => ['status_codes' => [200]],
        ]);

        $this
            ->actingAs($user)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index', false)
                ->where('organization.id', $organization->id)
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->where('projects.0.name', 'Acme project')
                ->where('projects.0.resources_count', 1)
                ->where('projects.0.monitors_count', 2)
                ->where('projects.0.status', 'down')
                ->where('projects.0.problem_label', '1 монитор упал')
            );
    }

    /**
     * @return array{User, Organization, Project}
     */
    private function createOrganizationContext(string $name): array
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(8),
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
            'name' => "{$name} project",
            'color' => '#0F6BFF',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        return [$user, $organization, $project];
    }
}
