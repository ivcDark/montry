<?php

namespace Tests\Feature\Sites;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Organizations\Enums\OrganizationStatus;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_limits_site_creation_to_three_sites(): void
    {
        [$user, $organization, $project] = $this->createOrganizationContext();
        $this->subscribe($organization, 'free', [
            'max_sites' => ['limit' => 3],
            'max_monitors' => ['limit' => 6],
            'minimum_check_interval_seconds' => ['seconds' => 900],
            'allowed_monitor_types' => ['types' => ['http', 'ssl']],
        ]);

        foreach (['one.example.com', 'two.example.com', 'three.example.com'] as $host) {
            MonitoredResource::query()->create([
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

        $this
            ->actingAs($user)
            ->post('/sites', [
                'name' => 'Fourth site',
                'url' => 'https://four.example.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('monitored_resources', [
            'organization_id' => $organization->id,
            'host' => 'four.example.com',
        ]);
    }

    public function test_free_plan_creates_only_http_and_ssl_default_monitors(): void
    {
        [$user, $organization] = $this->createOrganizationContext();
        $this->subscribe($organization, 'free', [
            'max_sites' => ['limit' => 3],
            'max_monitors' => ['limit' => 6],
            'minimum_check_interval_seconds' => ['seconds' => 900],
            'allowed_monitor_types' => ['types' => ['http', 'ssl']],
        ]);

        $this
            ->actingAs($user)
            ->post('/sites', [
                'name' => 'Healthcheck',
                'url' => 'https://example.com',
            ])
            ->assertRedirect('/sites');

        $site = MonitoredResource::query()->where('host', 'example.com')->firstOrFail();

        $this->assertSame(['http', 'ssl'], $site->monitors()->orderBy('type')->pluck('type')->all());
        $this->assertDatabaseMissing('monitors', [
            'monitored_resource_id' => $site->id,
            'type' => 'domain',
        ]);
    }

    public function test_site_creation_creates_configured_mvp_monitors(): void
    {
        $user = User::factory()->create();
        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'timezone' => '+3',
            'status' => OrganizationStatus::Active->value,
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'default',
            'color' => '#ffffff',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/sites', [
                'name' => 'Healthcheck',
                'url' => 'https://example.com/health?ready=1',
                'monitors' => [
                    [
                        'type' => 'http',
                        'name' => 'HTTP availability',
                        'is_enabled' => true,
                        'interval_seconds' => 300,
                        'timeout_ms' => 10000,
                        'settings' => [
                            'method' => 'GET',
                            'url' => 'https://example.com/health?ready=1',
                            'follow_redirects' => true,
                            'verify_ssl' => true,
                        ],
                        'expected' => [
                            'status_codes' => [200, 204],
                            'max_response_time_ms' => 3000,
                        ],
                    ],
                    [
                        'type' => 'ssl',
                        'name' => 'SSL certificate',
                        'is_enabled' => false,
                        'interval_seconds' => 86400,
                        'timeout_ms' => 10000,
                        'settings' => [
                            'domain' => 'example.com',
                            'port' => 443,
                            'warning_days' => [30, 14, 7, 3, 1],
                        ],
                        'expected' => [
                            'valid' => true,
                        ],
                    ],
                    [
                        'type' => 'domain',
                        'name' => 'Domain expiration',
                        'is_enabled' => true,
                        'interval_seconds' => 86400,
                        'timeout_ms' => 10000,
                        'settings' => [
                            'domain' => 'example.com',
                            'warning_days' => [45, 30, 14, 7],
                        ],
                        'expected' => [
                            'registered' => true,
                        ],
                    ],
                ],
            ]);

        $site = MonitoredResource::query()->where('host', 'example.com')->firstOrFail();

        $response->assertRedirect('/sites');

        $this->assertSame('/health?ready=1', $site->path);
        $this->assertCount(3, $site->monitors);

        $monitor = $site->monitors()->where('type', 'http')->firstOrFail();
        $sslMonitor = $site->monitors()->where('type', 'ssl')->firstOrFail();
        $domainMonitor = $site->monitors()->where('type', 'domain')->firstOrFail();

        $this->assertTrue($monitor->is_enabled);
        $this->assertSame([
            'method' => 'GET',
            'url' => 'https://example.com/health?ready=1',
            'follow_redirects' => true,
            'verify_ssl' => true,
        ], $monitor->settings);
        $this->assertSame([
            'status_codes' => [200, 204],
            'max_response_time_ms' => 3000,
        ], $monitor->expected);
        $this->assertFalse($sslMonitor->is_enabled);
        $this->assertSame('example.com', $sslMonitor->settings['domain']);
        $this->assertTrue($domainMonitor->is_enabled);
        $this->assertSame([45, 30, 14, 7], $domainMonitor->settings['warning_days']);
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
            'status' => OrganizationStatus::Active->value,
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

    private function subscribe(Organization $organization, string $planCode, array $limits): void
    {
        $plan = Plan::query()->create([
            'code' => $planCode,
            'name' => str($planCode)->headline()->toString(),
            'price_cents' => 0,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        foreach ($limits as $key => $value) {
            $plan->limits()->create([
                'key' => $key,
                'value' => $value,
            ]);
        }

        $plan->subscriptions()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
