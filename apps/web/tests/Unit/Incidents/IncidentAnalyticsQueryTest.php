<?php

namespace Tests\Unit\Incidents;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Application\DTO\IncidentAnalyticsFilters;
use App\Modules\Incidents\Application\Queries\IncidentAnalyticsQuery;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IncidentAnalyticsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_project_site_and_type_analytics(): void
    {
        [$organization, $projectA, $projectB, $siteA, $siteB, $siteC] = $this->createFixture();

        $httpMonitor = $this->createMonitor($organization, $projectA, $siteA, 'http');
        $sslMonitor = $this->createMonitor($organization, $projectA, $siteB, 'ssl');
        $domainMonitor = $this->createMonitor($organization, $projectB, $siteC, 'domain');

        $this->createIncident($organization, $projectA, $siteA, $httpMonitor, 3600, '2026-05-25 10:00:00');
        $this->createIncident($organization, $projectA, $siteB, $sslMonitor, 1200, '2026-05-26 10:00:00');
        $this->createIncident($organization, $projectB, $siteC, $domainMonitor, 1800, '2026-05-27 10:00:00');

        $dashboard = app(IncidentAnalyticsQuery::class)->build($organization->id, new IncidentAnalyticsFilters(
            start: CarbonImmutable::parse('2026-05-25 00:00:00'),
            end: CarbonImmutable::parse('2026-05-31 23:59:59'),
            type: 'all',
            projectId: $projectA->id,
            search: '',
        ));

        $this->assertSame(3, $dashboard['kpi']['total_incidents']);
        $this->assertSame(6600, $dashboard['kpi']['downtime_seconds']);
        $this->assertSame(2200, $dashboard['kpi']['mttr_seconds']);
        $this->assertSame(['http' => 1, 'ssl' => 1, 'domain' => 1], $dashboard['type_distribution']);
        $this->assertCount(2, $dashboard['projects']);
        $this->assertSame('Client A', $dashboard['projects'][0]['name']);
        $this->assertSame(2, $dashboard['projects'][0]['incident_count']);
        $this->assertSame($projectA->id, $dashboard['selected_project_id']);
        $this->assertCount(2, $dashboard['sites']);
        $this->assertSame('alpha.test', $dashboard['sites'][0]['name']);
        $this->assertSame(3600, $dashboard['sites'][0]['downtime_seconds']);
    }

    /**
     * @return array{Organization, Project, Project, MonitoredResource, MonitoredResource, MonitoredResource}
     */
    private function createFixture(): array
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-analytics',
            'timezone' => '+3',
            'status' => 'active',
        ]);

        $projectA = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Client A',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $projectB = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Client B',
            'is_default' => false,
            'sort_order' => 1,
        ]);

        return [
            $organization,
            $projectA,
            $projectB,
            $this->createSite($organization, $projectA, $user, 'alpha.test'),
            $this->createSite($organization, $projectA, $user, 'beta.test'),
            $this->createSite($organization, $projectB, $user, 'gamma.test'),
        ];
    }

    private function createSite(Organization $organization, Project $project, User $user, string $host): MonitoredResource
    {
        return MonitoredResource::query()->create([
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
    }

    private function createMonitor(Organization $organization, Project $project, MonitoredResource $site, string $type): Monitor
    {
        return Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $site->id,
            'type' => $type,
            'name' => strtoupper($type).' check',
            'enabled' => true,
            'status' => 'failure',
            'interval_seconds' => 300,
            'timeout_ms' => 10000,
            'settings' => ['url' => $site->target],
            'expected' => ['status_codes' => [200]],
        ]);
    }

    private function createIncident(
        Organization $organization,
        Project $project,
        MonitoredResource $site,
        Monitor $monitor,
        int $durationSeconds,
        string $startedAt,
    ): Incident {
        $startedAt = CarbonImmutable::parse($startedAt);

        return Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $site->id,
            'monitor_id' => $monitor->id,
            'status' => 'resolved',
            'severity' => 'incident',
            'title' => 'Incident',
            'started_at' => $startedAt,
            'resolved_at' => $startedAt->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ]);
    }
}
