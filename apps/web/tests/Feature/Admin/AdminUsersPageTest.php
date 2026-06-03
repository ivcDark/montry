<?php

namespace Tests\Feature\Admin;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminUsersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_users_page(): void
    {
        $this
            ->get('/admin/users')
            ->assertRedirect('/login');
    }

    public function test_regular_user_cannot_open_admin_users_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this
            ->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_open_users_page(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        User::factory()->create([
            'name' => 'Alice Owner',
            'email' => 'alice@example.com',
        ]);

        $this
            ->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index', false)
                ->has('users', 2)
                ->where('users.0.email', 'alice@example.com')
            );
    }

    public function test_admin_plan_change_to_free_applies_subscription_limits_immediately(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $user = User::factory()->create();
        $organization = $this->createOrganization($user);
        $project = $this->createProject($organization);

        $pro = $this->createPlan('pro', 99000, [
            'max_sites' => ['limit' => null],
            'max_monitors' => ['limit' => null],
            'allowed_monitor_types' => ['types' => ['*']],
        ]);
        $free = $this->createPlan('free', 0, [
            'max_sites' => ['limit' => 3],
            'max_monitors' => ['limit' => 10],
            'allowed_monitor_types' => ['types' => ['http', 'ssl']],
        ]);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
        ]);

        $resources = collect(range(1, 5))
            ->map(function (int $number) use ($organization, $project, $user): MonitoredResource {
                $resource = $this->createResource($organization, $project, $user, "site-{$number}.example.com");

                $resource->forceFill([
                    'created_at' => now()->subDays(6 - $number),
                    'updated_at' => now()->subDays(6 - $number),
                ])->save();

                foreach (['http', 'ssl', 'domain'] as $type) {
                    $this->createMonitor($organization, $project, $resource, $type);
                }

                return $resource;
            });

        $this
            ->actingAs($admin)
            ->patch(route('admin.users.organizations.plan', [$user, $organization]), [
                'plan_id' => $free->id,
            ])
            ->assertRedirect();

        $oldResources = $resources->take(2);
        $latestResources = $resources->slice(2);

        foreach ($oldResources as $resource) {
            $this->assertDatabaseHas('monitored_resources', [
                'id' => $resource->id,
                'status' => 'paused',
            ]);

            $this->assertSame(0, Monitor::query()
                ->where('monitored_resource_id', $resource->id)
                ->where('enabled', true)
                ->count());
        }

        foreach ($latestResources as $resource) {
            $this->assertDatabaseHas('monitored_resources', [
                'id' => $resource->id,
                'status' => 'unknown',
            ]);

            $this->assertSame(['http', 'ssl'], Monitor::query()
                ->where('monitored_resource_id', $resource->id)
                ->where('enabled', true)
                ->orderBy('type')
                ->pluck('type')
                ->all());
        }
    }

    private function createPlan(string $code, int $priceCents, array $limits = []): Plan
    {
        $plan = Plan::query()->create([
            'code' => $code,
            'name' => str($code)->headline()->toString(),
            'price_cents' => $priceCents,
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

        return $plan;
    }

    private function createOrganization(User $user): Organization
    {
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

        return $organization;
    }

    private function createProject(Organization $organization): Project
    {
        return Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default',
            'color' => '#ffffff',
            'is_default' => true,
            'sort_order' => 0,
        ]);
    }

    private function createResource(
        Organization $organization,
        Project $project,
        User $user,
        string $host,
    ): MonitoredResource {
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

    private function createMonitor(
        Organization $organization,
        Project $project,
        MonitoredResource $resource,
        string $type,
    ): Monitor {
        return Monitor::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $resource->id,
            'type' => $type,
            'name' => str($type)->headline()->toString(),
            'enabled' => true,
            'status' => 'ok',
            'interval_seconds' => 300,
            'timeout_ms' => 10000,
            'settings' => ['type' => $type],
            'expected' => [],
        ]);
    }
}
