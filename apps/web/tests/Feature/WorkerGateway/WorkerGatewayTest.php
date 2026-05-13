<?php

namespace Tests\Feature\WorkerGateway;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use App\Modules\WorkerGateway\Application\DTO\WorkerCheckPayload;
use App\Modules\WorkerGateway\Domain\Contracts\MonitoringWorkerClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkerGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_manual_check(): void
    {
        [$user, , , $monitor] = $this->createMonitorContext();
        $client = new class implements MonitoringWorkerClientInterface
        {
            public ?WorkerCheckPayload $payload = null;

            public function requestManualCheck(WorkerCheckPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $this->app->instance(MonitoringWorkerClientInterface::class, $client);

        $this
            ->actingAs($user)
            ->post("/monitors/{$monitor->id}/check-now")
            ->assertRedirect();

        $this->assertNotNull($client->payload);
        $this->assertSame($monitor->id, $client->payload->monitorId);
        $this->assertSame('http', $client->payload->checkType);
    }

    public function test_manual_check_respects_plan_limit(): void
    {
        [$user, $organization, , $monitor] = $this->createMonitorContext();
        $client = new class implements MonitoringWorkerClientInterface
        {
            public ?WorkerCheckPayload $payload = null;

            public function requestManualCheck(WorkerCheckPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $plan = Plan::query()->create([
            'code' => 'free',
            'name' => 'Free',
            'price_cents' => 0,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $plan->limits()->create([
            'key' => 'manual_checks_per_day',
            'value' => ['limit' => 0],
        ]);
        $plan->subscriptions()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $this->app->instance(MonitoringWorkerClientInterface::class, $client);

        $this
            ->actingAs($user)
            ->post("/monitors/{$monitor->id}/check-now")
            ->assertForbidden();

        $this->assertNull($client->payload);
    }

    public function test_internal_due_monitors_endpoint_returns_enabled_due_monitors(): void
    {
        [, , , $dueMonitor] = $this->createMonitorContext([
            'next_check_at' => now()->subMinute(),
        ]);

        $this->createMonitorContext([
            'enabled' => false,
            'next_check_at' => now()->subMinute(),
        ]);

        $this->createMonitorContext([
            'next_check_at' => now()->addMinute(),
        ]);

        $this
            ->getJson('/internal/monitors/due?limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.monitor_id', $dueMonitor->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_internal_check_result_is_saved_and_opens_incident_after_confirmed_failure(): void
    {
        [, $organization, , $monitor] = $this->createMonitorContext([
            'status' => 'failure',
            'consecutive_failures' => 1,
        ]);

        $this
            ->postJson('/internal/check-results', [
                'event_id' => 'event-1',
                'monitor_id' => $monitor->id,
                'check_type' => 'http',
                'status' => 'failure',
                'checked_at' => '2026-05-12T12:00:05+03:00',
                'duration_ms' => 120,
                'result' => [
                    'status_code' => 500,
                    'response_time_ms' => 120,
                ],
                'error' => null,
            ])
            ->assertCreated()
            ->assertJson([
                'status' => 'failure',
            ]);

        $this->assertDatabaseHas('check_results', [
            'monitor_id' => $monitor->id,
            'organization_id' => $organization->id,
            'check_type' => 'http',
            'status' => 'failure',
            'status_code' => 500,
        ]);

        $this->assertDatabaseHas('monitors', [
            'id' => $monitor->id,
            'status' => 'failure',
            'consecutive_failures' => 2,
        ]);

        $this->assertDatabaseHas('incidents', [
            'monitor_id' => $monitor->id,
            'status' => 'open',
        ]);
    }

    public function test_internal_check_result_is_idempotent_by_event_id(): void
    {
        [, , , $monitor] = $this->createMonitorContext();

        $payload = [
            'event_id' => 'event-duplicate',
            'monitor_id' => $monitor->id,
            'check_type' => 'http',
            'status' => 'success',
            'checked_at' => '2026-05-12T12:00:05+03:00',
            'duration_ms' => 120,
            'result' => [
                'status_code' => 200,
                'response_time_ms' => 120,
            ],
            'error' => null,
        ];

        $this->postJson('/internal/check-results', $payload)->assertCreated();
        $this->postJson('/internal/check-results', $payload)->assertOk();

        $this->assertDatabaseCount('check_results', 1);
    }

    public function test_internal_check_result_rejects_mismatched_check_type(): void
    {
        [, , , $monitor] = $this->createMonitorContext();

        $this
            ->postJson('/internal/check-results', [
                'event_id' => 'event-wrong-type',
                'monitor_id' => $monitor->id,
                'check_type' => 'ssl',
                'status' => 'success',
                'checked_at' => '2026-05-12T12:00:05+03:00',
                'duration_ms' => 120,
                'result' => [],
                'error' => null,
            ])
            ->assertUnprocessable();
    }

    private function createMonitorContext(array $monitorOverrides = []): array
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

        $monitor = Monitor::query()->create([
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
            ...$monitorOverrides,
        ]);

        return [$user, $organization, $resource, $monitor];
    }
}
