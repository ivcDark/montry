<?php

namespace Tests\Feature\Dashboard;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_dashboard_overview_for_current_organization(): void
    {
        [$user, $organization, $project] = $this->createOrganizationContext('Acme');
        [$otherUser, $otherOrganization, $otherProject] = $this->createOrganizationContext('Other');

        $resource = MonitoredResource::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_user_id' => $user->id,
            'type' => 'website',
            'name' => 'Client shop',
            'target' => 'https://client-shop.test',
            'scheme' => 'https',
            'host' => 'client-shop.test',
            'path' => '/',
            'status' => 'down',
        ]);

        $monitor = Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'http',
            'name' => 'HTTP check',
            'enabled' => true,
            'status' => 'failure',
            'interval_seconds' => 300,
            'timeout_ms' => 10000,
            'settings' => ['url' => 'https://client-shop.test'],
            'expected' => ['status_codes' => [200]],
            'last_check_at' => now()->subMinutes(2),
        ]);

        CheckResult::query()->create([
            'event_id' => 'evt-dashboard-http',
            'monitor_id' => $monitor->id,
            'organization_id' => $organization->id,
            'check_type' => 'http',
            'status' => 'failure',
            'checked_at' => now()->subMinutes(2),
            'status_code' => 502,
            'raw_result' => ['status_code' => 502],
            'normalized_result' => ['status_code' => 502],
        ]);

        $otherResource = MonitoredResource::query()->create([
            'organization_id' => $otherOrganization->id,
            'project_id' => $otherProject->id,
            'created_user_id' => $otherUser->id,
            'type' => 'website',
            'name' => 'Other shop',
            'target' => 'https://other-shop.test',
            'scheme' => 'https',
            'host' => 'other-shop.test',
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
            'settings' => ['url' => 'https://other-shop.test'],
            'expected' => ['status_codes' => [200]],
        ]);

        $this
            ->actingAs($user)
            ->get('/sites')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('organization.id', $organization->id)
                ->where('summary.total_resources', 1)
                ->where('summary.total_projects', 1)
                ->where('summary.down_monitors', 1)
                ->has('problems', 1)
                ->where('problems.0.site', 'client-shop.test')
                ->where('problems.0.status', 'down')
                ->has('latest_checks', 1)
                ->where('latest_checks.0.result', '502')
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
