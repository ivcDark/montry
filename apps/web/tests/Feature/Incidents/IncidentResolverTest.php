<?php

namespace Tests\Feature\Incidents;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Domain\Events\IncidentResolved;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\ReceiveCheckResultCommand;
use App\Modules\Monitoring\Application\Handlers\ReceiveCheckResultHandler;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class IncidentResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_incident_after_confirmed_failures(): void
    {
        Event::fake([IncidentOpened::class]);
        $monitor = $this->createMonitor();

        $this->receiveHttpResult($monitor, 500, '2026-05-12T12:00:00+03:00');
        $this->assertDatabaseMissing('incidents', [
            'monitor_id' => $monitor->id,
        ]);

        $this->receiveHttpResult($monitor->refresh(), 500, '2026-05-12T12:01:00+03:00');

        $incident = Incident::query()->where('monitor_id', $monitor->id)->firstOrFail();

        $this->assertSame('open', $incident->status);
        $this->assertSame(2, $monitor->refresh()->consecutive_failures);
        Event::assertDispatched(IncidentOpened::class);
    }

    public function test_it_closes_incident_after_confirmed_success_and_calculates_duration(): void
    {
        Event::fake([IncidentResolved::class]);
        $monitor = $this->createMonitor();

        $firstFailure = $this->receiveHttpResult($monitor, 500, '2026-05-12T12:00:00+03:00');
        $this->receiveHttpResult($monitor->refresh(), 500, '2026-05-12T12:01:00+03:00');

        Incident::query()
            ->where('monitor_id', $monitor->id)
            ->update([
                'started_at' => $firstFailure->checked_at,
            ]);

        $this->receiveHttpResult($monitor->refresh(), 200, '2026-05-12T12:05:00+03:00');

        $incident = Incident::query()->where('monitor_id', $monitor->id)->firstOrFail();

        $this->assertSame('resolved', $incident->status);
        $this->assertSame(300, $incident->duration_seconds);
        Event::assertDispatched(IncidentResolved::class);
    }

    public function test_it_does_not_create_duplicate_incidents_for_repeated_failures(): void
    {
        $monitor = $this->createMonitor();

        $this->receiveHttpResult($monitor, 500, '2026-05-12T12:00:00+03:00');
        $this->receiveHttpResult($monitor->refresh(), 500, '2026-05-12T12:01:00+03:00');
        $this->receiveHttpResult($monitor->refresh(), 500, '2026-05-12T12:02:00+03:00');

        $this->assertSame(1, Incident::query()->where('monitor_id', $monitor->id)->count());
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
        ]);
    }

    private function receiveHttpResult(Monitor $monitor, int $statusCode, string $checkedAt)
    {
        return app(ReceiveCheckResultHandler::class)->handle(new ReceiveCheckResultCommand(
            eventId: null,
            monitorId: $monitor->id,
            checkType: 'http',
            workerResult: [
                'status_code' => $statusCode,
                'response_time_ms' => 100,
            ],
            checkedAt: new \DateTimeImmutable($checkedAt),
        ));
    }
}
