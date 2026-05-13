<?php

namespace Tests\Feature\Notifications;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Domain\Events\IncidentOpened;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Domain\Events\DomainExpiring;
use App\Modules\Monitoring\Domain\Events\SslExpiring;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Notifications\Infrastructure\Persistence\Models\NotificationChannel;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class NotificationsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_email_and_logs_incident_opened_notification(): void
    {
        Mail::fake();
        [$organization, $monitor] = $this->createMonitorContext();
        $incident = $this->createIncident($organization, $monitor);

        NotificationChannel::query()->create([
            'organization_id' => $organization->id,
            'type' => 'email',
            'name' => 'Primary email',
            'enabled' => true,
            'settings' => ['email' => 'ops@example.com'],
        ]);

        Event::dispatch(new IncidentOpened($incident->id));

        $this->assertDatabaseHas('notification_logs', [
            'organization_id' => $organization->id,
            'incident_id' => $incident->id,
            'event_type' => 'incident.opened',
            'status' => 'sent',
        ]);
    }

    public function test_it_does_not_send_duplicate_incident_opened_notifications(): void
    {
        Mail::fake();
        [$organization, $monitor] = $this->createMonitorContext();
        $incident = $this->createIncident($organization, $monitor);

        NotificationChannel::query()->create([
            'organization_id' => $organization->id,
            'type' => 'email',
            'name' => 'Primary email',
            'enabled' => true,
            'settings' => ['email' => 'ops@example.com'],
        ]);

        Event::dispatch(new IncidentOpened($incident->id));
        Event::dispatch(new IncidentOpened($incident->id));

        $this->assertDatabaseCount('notification_logs', 1);
    }

    public function test_it_sends_telegram_ssl_expiring_notification(): void
    {
        config()->set('services.telegram.bot_token', 'test-token');
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        [$organization, $monitor] = $this->createMonitorContext();

        NotificationChannel::query()->create([
            'organization_id' => $organization->id,
            'type' => 'telegram',
            'name' => 'Telegram',
            'enabled' => true,
            'settings' => ['chat_id' => '123456'],
        ]);

        Event::dispatch(new SslExpiring(
            monitorId: $monitor->id,
            organizationId: $organization->id,
            domain: 'example.com',
            daysUntilExpiration: 14,
            expiresAt: new \DateTimeImmutable('2026-05-26T12:00:00+03:00'),
        ));

        $this->assertDatabaseHas('notification_logs', [
            'organization_id' => $organization->id,
            'event_type' => 'ssl.expiring',
            'status' => 'sent',
        ]);

        Http::assertSentCount(1);
    }

    public function test_it_sends_domain_expiring_notification(): void
    {
        Mail::fake();
        [$organization, $monitor] = $this->createMonitorContext();

        NotificationChannel::query()->create([
            'organization_id' => $organization->id,
            'type' => 'email',
            'name' => 'Primary email',
            'enabled' => true,
            'settings' => ['email' => 'ops@example.com'],
        ]);

        Event::dispatch(new DomainExpiring(
            monitorId: $monitor->id,
            organizationId: $organization->id,
            domain: 'example.com',
            daysUntilExpiration: 7,
            expiresAt: new \DateTimeImmutable('2026-05-19T12:00:00+03:00'),
        ));

        $this->assertDatabaseHas('notification_logs', [
            'organization_id' => $organization->id,
            'event_type' => 'domain.expiring',
            'status' => 'sent',
        ]);
    }

    public function test_ssl_expiring_check_result_emits_notification_event(): void
    {
        Mail::fake();
        [$organization, $monitor] = $this->createMonitorContext([
            'type' => 'ssl',
            'settings' => [
                'domain' => 'example.com',
                'port' => 443,
                'warning_days' => [30, 14, 7, 3, 1],
            ],
            'expected' => [
                'valid' => true,
            ],
        ]);

        NotificationChannel::query()->create([
            'organization_id' => $organization->id,
            'type' => 'email',
            'name' => 'Primary email',
            'enabled' => true,
            'settings' => ['email' => 'ops@example.com'],
        ]);

        $this->postJson('/internal/check-results', [
            'event_id' => 'ssl-expiring-event',
            'monitor_id' => $monitor->id,
            'check_type' => 'ssl',
            'status' => 'success',
            'checked_at' => now()->toAtomString(),
            'duration_ms' => 80,
            'result' => [
                'valid' => true,
                'expires_at' => '2026-05-26T12:00:00+03:00',
                'days_until_expiration' => 14,
            ],
            'error' => null,
        ])->assertCreated();

        $this->assertDatabaseHas('notification_logs', [
            'organization_id' => $organization->id,
            'event_type' => 'ssl.expiring',
            'status' => 'sent',
        ]);
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

        return [$organization, $monitor];
    }

    private function createIncident(Organization $organization, Monitor $monitor): Incident
    {
        return Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $monitor->project_id,
            'monitored_resource_id' => $monitor->monitored_resource_id,
            'monitor_id' => $monitor->id,
            'status' => 'open',
            'severity' => 'critical',
            'title' => 'HTTP check failed',
            'summary' => 'Monitor returned failure.',
            'started_at' => now(),
        ]);
    }
}
