<?php

namespace Tests\Feature\Sites;

use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Organizations\Enums\OrganizationStatus;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_http_monitor_uses_the_created_site_path(): void
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
            ]);

        $response->assertRedirect('/sites');

        $site = MonitoredResource::query()->where('host', 'example.com')->firstOrFail();
        $monitor = $site->monitors()->firstOrFail();

        $this->assertSame('/health?ready=1', $site->path);
        $this->assertSame('/health?ready=1', $monitor->settings['path']);
    }
}
