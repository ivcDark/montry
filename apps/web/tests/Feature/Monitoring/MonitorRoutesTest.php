<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
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

    public function test_authenticated_user_can_open_monitors_index_for_current_organization(): void
    {
        [$user, $organization, $project] = $this->createOrganizationContext();
        [$otherUser, $otherOrganization, $otherProject] = $this->createOrganizationContext();

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
            'status' => 'up',
        ]);

        $monitor = Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'http',
            'name' => 'HTTP check',
            'enabled' => true,
            'status' => 'success',
            'interval_seconds' => 300,
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
            'last_check_at' => now(),
            'next_check_at' => now()->addMinutes(5),
        ]);

        CheckResult::query()->create([
            'event_id' => 'evt-http-check',
            'monitor_id' => $monitor->id,
            'organization_id' => $organization->id,
            'check_type' => 'http',
            'status' => 'success',
            'checked_at' => now(),
            'response_time_ms' => 215,
            'status_code' => 200,
            'raw_result' => ['status_code' => 200],
            'normalized_result' => ['status_code' => 200, 'response_time_ms' => 215],
        ]);

        $otherResource = MonitoredResource::query()->create([
            'organization_id' => $otherOrganization->id,
            'project_id' => $otherProject->id,
            'created_user_id' => $otherUser->id,
            'type' => 'website',
            'name' => 'Other',
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
            ->get('/monitors')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Monitors/Index', false)
                ->where('organization.id', $organization->id)
                ->has('monitors', 1)
                ->where('monitors.0.name', 'HTTP check')
                ->where('monitors.0.resource.host', 'example.com')
                ->where('monitors.0.latest_result.status_code', 200)
                ->where('monitors.0.latest_result.response_time_ms', 215)
            );
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

    public function test_monitor_creation_respects_plan_monitor_limit(): void
    {
        [$user, $organization, $project] = $this->createOrganizationContext();
        $plan = Plan::query()->create([
            'code' => 'free',
            'name' => 'Free',
            'price_cents' => 0,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $plan->limits()->create([
            'key' => 'max_monitors',
            'value' => ['limit' => 0],
        ]);

        $plan->subscriptions()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

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
            ->assertForbidden();

        $this->assertDatabaseMissing('monitors', [
            'organization_id' => $organization->id,
            'monitored_resource_id' => $resource->id,
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
            'slug' => 'acme-'.str()->random(8),
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
