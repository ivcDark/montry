<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
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
            'settings' => ['url' => 'https://example.com'],
            'expected' => ['status_codes' => [200]],
            'last_check_at' => now(),
            'next_check_at' => now()->addMinutes(5),
        ]);

        CheckResult::query()->create([
            'event_id' => 'evt-sites-index-http-check',
            'monitor_id' => $monitor->id,
            'organization_id' => $organization->id,
            'check_type' => 'http',
            'status' => 'success',
            'checked_at' => now(),
            'response_time_ms' => 180,
            'status_code' => 200,
            'raw_result' => ['status_code' => 200],
            'normalized_result' => ['status_code' => 200, 'response_time_ms' => 180],
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
            ->get('/sites')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Index', false)
                ->where('organization.id', $organization->id)
                ->has('sites', 1)
                ->where('sites.0.id', $resource->id)
                ->where('sites.0.name', 'Example')
                ->where('sites.0.project.name', 'default')
                ->where('sites.0.monitors_count', 1)
                ->where('sites.0.status', 'ok')
                ->where('sites.0.problem_label', 'Нет')
                ->where('sites.0.monitors.0.name', 'HTTP check')
                ->where('sites.0.monitors.0.latest_result.status_code', 200)
                ->where('sites.0.monitors.0.latest_result.response_time_ms', 180)
            );
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

    public function test_authenticated_user_can_open_site_show_with_health_payload(): void
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
            'settings' => ['url' => 'https://example.com'],
            'expected' => ['status_codes' => [200]],
            'last_check_at' => now(),
            'next_check_at' => now()->addMinutes(5),
            'last_success_at' => now(),
        ]);

        CheckResult::query()->create([
            'event_id' => 'evt-site-show-http-old',
            'monitor_id' => $monitor->id,
            'organization_id' => $organization->id,
            'check_type' => 'http',
            'status' => 'failure',
            'checked_at' => now()->subMinutes(10),
            'response_time_ms' => null,
            'status_code' => 500,
            'error_message' => 'Server error',
            'raw_result' => ['status_code' => 500],
            'normalized_result' => ['status_code' => 500],
        ]);

        $latestResult = CheckResult::query()->create([
            'event_id' => 'evt-site-show-http-latest',
            'monitor_id' => $monitor->id,
            'organization_id' => $organization->id,
            'check_type' => 'http',
            'status' => 'success',
            'checked_at' => now(),
            'response_time_ms' => 184,
            'status_code' => 200,
            'raw_result' => ['status_code' => 200],
            'normalized_result' => ['status_code' => 200, 'response_time_ms' => 184],
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'monitor_id' => $monitor->id,
            'status' => 'resolved',
            'severity' => 'incident',
            'title' => 'HTTP check is failing',
            'summary' => 'Server error',
            'started_at' => now()->subMinutes(10),
            'resolved_at' => now(),
            'duration_seconds' => 600,
            'opened_by_check_result_id' => $latestResult->id,
        ]);

        $this
            ->actingAs($user)
            ->get("/sites/{$resource->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sites/Show', false)
                ->where('site.id', $resource->id)
                ->where('site.status', 'ok')
                ->where('site.monitors.0.latest_result.status_code', 200)
                ->where('site.monitors.0.latest_result.response_time_ms', 184)
                ->has('site.recent_checks', 2)
                ->where('site.recent_checks.0.status_code', 200)
                ->has('site.incidents', 1)
                ->where('site.incidents.0.title', 'HTTP check is failing')
            );
    }

    public function test_authenticated_user_can_delete_site_with_monitors(): void
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
            'settings' => ['url' => 'https://example.com'],
            'expected' => ['status_codes' => [200]],
        ]);

        $this
            ->actingAs($user)
            ->delete("/sites/{$resource->id}")
            ->assertRedirect('/sites');

        $this->assertSoftDeleted('monitored_resources', [
            'id' => $resource->id,
        ]);
        $this->assertSoftDeleted('monitors', [
            'id' => $monitor->id,
        ]);
    }

    public function test_authenticated_user_can_open_create_monitor_page_for_site(): void
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
            ->get("/sites/{$resource->id}/monitors/create")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Monitors/Create', false)
                ->where('organization.id', $organization->id)
                ->where('site.id', $resource->id)
                ->where('site.host', 'example.com')
                ->has('monitorTypes', 3)
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
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('monitors', [
            'organization_id' => $organization->id,
            'monitored_resource_id' => $resource->id,
        ]);
    }

    public function test_monitor_creation_respects_allowed_monitor_types(): void
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
            'key' => 'allowed_monitor_types',
            'value' => ['types' => ['http', 'ssl']],
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
                'type' => 'domain',
                'name' => 'Domain check',
                'is_enabled' => true,
                'interval_seconds' => 86400,
                'timeout_ms' => 10000,
                'settings' => [
                    'domain' => 'example.com',
                    'warning_days' => [30, 14, 7, 3, 1],
                ],
                'expected' => [
                    'registered' => true,
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('monitors', [
            'organization_id' => $organization->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'domain',
        ]);
    }

    public function test_monitor_interval_must_be_at_least_five_minutes_and_whole_minutes(): void
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

        $basePayload = [
            'type' => 'http',
            'name' => 'HTTP check',
            'is_enabled' => true,
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
        ];

        $this
            ->actingAs($user)
            ->post("/sites/{$resource->id}/monitors", $basePayload + [
                'interval_seconds' => 299,
            ])
            ->assertSessionHasErrors('interval_seconds');

        $this
            ->actingAs($user)
            ->post("/sites/{$resource->id}/monitors", $basePayload + [
                'interval_seconds' => 318,
            ])
            ->assertSessionHasErrors('interval_seconds');
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
