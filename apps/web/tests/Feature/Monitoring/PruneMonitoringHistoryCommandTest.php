<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\MonitorStateChange;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PruneMonitoringHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_monitoring_history_and_resolved_incidents_older_than_retention(): void
    {
        $monitor = $this->createMonitor();
        $oldCheckedAt = now()->subDays(91);
        $recentCheckedAt = now()->subDays(30);

        $oldResult = $this->createCheckResult($monitor, $oldCheckedAt);
        $recentResult = $this->createCheckResult($monitor, $recentCheckedAt);

        MonitorStateChange::query()->create([
            'monitor_id' => $monitor->id,
            'organization_id' => $monitor->organization_id,
            'check_result_id' => $oldResult->id,
            'from_status' => 'success',
            'to_status' => 'failure',
            'reason' => 'http_status',
            'changed_at' => $oldCheckedAt,
        ]);

        MonitorStateChange::query()->create([
            'monitor_id' => $monitor->id,
            'organization_id' => $monitor->organization_id,
            'check_result_id' => $recentResult->id,
            'from_status' => 'failure',
            'to_status' => 'success',
            'reason' => 'http_status',
            'changed_at' => $recentCheckedAt,
        ]);

        $oldResolvedIncident = $this->createIncident($monitor, 'resolved', $oldCheckedAt, $oldCheckedAt->addHour());
        $recentResolvedIncident = $this->createIncident($monitor, 'resolved', $recentCheckedAt, $recentCheckedAt->addHour());
        $oldOpenIncident = $this->createIncident($monitor, 'open', $oldCheckedAt, null);

        $this->artisan('monitoring:prune-history')
            ->expectsOutput('Deleted 1 resolved incidents, 1 monitor state changes and 1 check results older than 90 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('check_results', ['id' => $oldResult->id]);
        $this->assertDatabaseHas('check_results', ['id' => $recentResult->id]);
        $this->assertDatabaseMissing('monitor_state_changes', ['check_result_id' => $oldResult->id]);
        $this->assertDatabaseHas('monitor_state_changes', ['check_result_id' => $recentResult->id]);
        $this->assertDatabaseMissing('incidents', ['id' => $oldResolvedIncident->id]);
        $this->assertDatabaseHas('incidents', ['id' => $recentResolvedIncident->id]);
        $this->assertDatabaseHas('incidents', ['id' => $oldOpenIncident->id]);
    }

    public function test_dry_run_reports_counts_without_deleting_records(): void
    {
        $monitor = $this->createMonitor();
        $oldCheckedAt = now()->subDays(91);

        $oldResult = $this->createCheckResult($monitor, $oldCheckedAt);
        $oldIncident = $this->createIncident($monitor, 'resolved', $oldCheckedAt, $oldCheckedAt->addHour());

        MonitorStateChange::query()->create([
            'monitor_id' => $monitor->id,
            'organization_id' => $monitor->organization_id,
            'check_result_id' => $oldResult->id,
            'from_status' => 'success',
            'to_status' => 'failure',
            'reason' => 'http_status',
            'changed_at' => $oldCheckedAt,
        ]);

        $this->artisan('monitoring:prune-history --dry-run')
            ->expectsOutput('Would delete 1 resolved incidents, 1 monitor state changes and 1 check results older than 90 days.')
            ->assertSuccessful();

        $this->assertDatabaseHas('check_results', ['id' => $oldResult->id]);
        $this->assertDatabaseHas('incidents', ['id' => $oldIncident->id]);
        $this->assertDatabaseHas('monitor_state_changes', ['check_result_id' => $oldResult->id]);
    }

    private function createMonitor(): Monitor
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-'.$user->id,
            'timezone' => '+3',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default',
            'is_default' => true,
            'sort_order' => 0,
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

        return Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => 'http',
            'name' => 'HTTP check',
            'enabled' => true,
            'status' => 'unknown',
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
        ]);
    }

    private function createCheckResult(Monitor $monitor, $checkedAt): CheckResult
    {
        return CheckResult::query()->create([
            'monitor_id' => $monitor->id,
            'organization_id' => $monitor->organization_id,
            'check_type' => 'http',
            'status' => 'failure',
            'checked_at' => $checkedAt,
            'response_time_ms' => 100,
            'status_code' => 500,
            'raw_result' => ['status_code' => 500],
            'normalized_result' => ['status_code' => 500],
        ]);
    }

    private function createIncident(Monitor $monitor, string $status, $startedAt, $resolvedAt): Incident
    {
        return Incident::query()->create([
            'organization_id' => $monitor->organization_id,
            'project_id' => $monitor->project_id,
            'monitored_resource_id' => $monitor->monitored_resource_id,
            'monitor_id' => $monitor->id,
            'status' => $status,
            'severity' => 'incident',
            'title' => 'HTTP check failed',
            'summary' => 'HTTP status 500',
            'started_at' => $startedAt,
            'resolved_at' => $resolvedAt,
            'duration_seconds' => $resolvedAt === null ? null : 3600,
        ]);
    }
}
