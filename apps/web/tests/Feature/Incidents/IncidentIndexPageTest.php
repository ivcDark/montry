<?php

namespace Tests\Feature\Incidents;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class IncidentIndexPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_incidents_index_for_current_organization(): void
    {
        [$user, $organization, $project, $resource, $monitor] = $this->createMonitoringContext('Acme', 'client-shop.test');
        [, $otherOrganization, $otherProject, $otherResource, $otherMonitor] = $this->createMonitoringContext('Other', 'other-shop.test');

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'monitor_id' => $monitor->id,
            'status' => 'open',
            'severity' => 'incident',
            'title' => 'HTTP check is failing',
            'summary' => 'Server returned 502.',
            'started_at' => now()->subMinutes(12),
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'monitor_id' => $monitor->id,
            'status' => 'resolved',
            'severity' => 'incident',
            'title' => 'Previous downtime',
            'summary' => 'Recovered after retry.',
            'started_at' => now()->subHours(3),
            'resolved_at' => now()->subHours(2),
            'duration_seconds' => 3600,
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'monitor_id' => $monitor->id,
            'status' => 'open',
            'severity' => 'warning',
            'title' => 'SSL expires soon',
            'summary' => 'Certificate expires in 7 days.',
            'started_at' => now()->subHour(),
        ]);

        Incident::query()->create([
            'organization_id' => $otherOrganization->id,
            'project_id' => $otherProject->id,
            'monitored_resource_id' => $otherResource->id,
            'monitor_id' => $otherMonitor->id,
            'status' => 'open',
            'severity' => 'incident',
            'title' => 'Other organization incident',
            'started_at' => now()->subMinute(),
        ]);

        $this
            ->actingAs($user)
            ->get('/incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Incidents/Index', false)
                ->where('organization.id', $organization->id)
                ->where('summary.open_incidents', 1)
                ->where('summary.resolved_last_24_hours', 1)
                ->where('summary.warnings', 1)
                ->where('summary.downtime_30_days_seconds', 3600)
                ->where('analyticsAccess.enabled', false)
                ->where('analyticsAccess.plan_code', 'free')
                ->where('analytics', null)
                ->has('activeIncidents', 1)
                ->where('activeIncidents.0.site', 'client-shop.test')
                ->where('activeIncidents.0.project', 'Acme project')
                ->where('activeIncidents.0.type', 'http')
                ->where('activeIncidents.0.title', 'HTTP check is failing')
                ->has('resolvedIncidents', 1)
                ->where('resolvedIncidents.0.title', 'Previous downtime')
                ->where('resolvedIncidents.0.duration_seconds', 3600)
                ->has('warnings', 1)
                ->where('warnings.0.title', 'SSL expires soon')
            );
    }

    public function test_paid_user_receives_incident_analytics_payload(): void
    {
        [$user, $organization, $project, $resource, $monitor] = $this->createMonitoringContext('Acme Paid', 'paid-shop.test');
        $this->activatePlan($organization, 'pro', 14);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'monitor_id' => $monitor->id,
            'status' => 'resolved',
            'severity' => 'incident',
            'title' => 'HTTP downtime',
            'started_at' => now()->subDays(2),
            'resolved_at' => now()->subDays(2)->addMinutes(20),
            'duration_seconds' => 1200,
        ]);

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'monitor_id' => $monitor->id,
            'status' => 'resolved',
            'severity' => 'incident',
            'title' => 'HTTP downtime again',
            'started_at' => now()->subDay(),
            'resolved_at' => now()->subDay()->addMinutes(10),
            'duration_seconds' => 600,
        ]);

        $this
            ->actingAs($user)
            ->get('/incidents')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Incidents/Index', false)
                ->where('analyticsAccess.enabled', true)
                ->where('analyticsAccess.plan_code', 'pro')
                ->where('analyticsAccess.retention_days', 14)
                ->where('analytics.kpi.total_incidents', 2)
                ->where('analytics.kpi.downtime_seconds', 1800)
                ->has('analytics.projects', 1)
                ->where('analytics.projects.0.name', 'Acme Paid project')
                ->where('analytics.projects.0.incident_count', 2)
            );
    }

    /**
     * @return array{User, Organization, Project, MonitoredResource, Monitor}
     */
    private function createMonitoringContext(string $name, string $host): array
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

        $resource = MonitoredResource::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'created_user_id' => $user->id,
            'type' => 'website',
            'name' => $host,
            'target' => "https://{$host}",
            'scheme' => 'https',
            'host' => $host,
            'path' => '/',
            'status' => 'unknown',
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
            'settings' => ['url' => "https://{$host}"],
            'expected' => ['status_codes' => [200]],
        ]);

        return [$user, $organization, $project, $resource, $monitor];
    }

    private function activatePlan(Organization $organization, string $code, int $retentionDays): void
    {
        $plan = Plan::query()->create([
            'code' => $code,
            'name' => ucfirst($code),
            'description' => $code,
            'price_cents' => $code === 'free' ? 0 : 99000,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $plan->limits()->create([
            'key' => 'history_retention_days',
            'value' => ['days' => $retentionDays],
        ]);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
