<?php

namespace Tests\Feature\Incidents;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Incidents\Application\Mail\WeeklyIncidentDigestMail;
use App\Modules\Incidents\Application\Services\SendWeeklyIncidentDigests;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestLog;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestPreference;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Organizations\Enums\OrganizationRole;
use App\Modules\Projects\Infrastructure\Persistence\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class WeeklyIncidentDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_weekly_digest_preference_and_delivery_log(): void
    {
        [$user, $organization] = $this->createUserAndOrganization();

        $preference = IncidentWeeklyDigestPreference::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'enabled' => true,
            'send_time' => '09:00',
            'timezone' => 'Europe/Moscow',
        ]);

        $log = IncidentWeeklyDigestLog::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'week_start_date' => '2026-05-18',
            'week_end_date' => '2026-05-24',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertTrue($preference->enabled);
        $this->assertSame('sent', $log->status);
    }

    public function test_user_can_update_weekly_digest_preference(): void
    {
        [$user, $organization] = $this->createUserAndOrganization();

        $this
            ->actingAs($user)
            ->put('/incidents/weekly-digest-preference', [
                'enabled' => false,
                'send_time' => '10:30',
            ])
            ->assertRedirect('/incidents');

        $this->assertDatabaseHas('incident_weekly_digest_preferences', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'enabled' => false,
            'send_time' => '10:30',
            'timezone' => 'Europe/Moscow',
        ]);
    }

    public function test_it_sends_weekly_digest_once_for_paid_enabled_user(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 09:00:00', 'Europe/Moscow'));

        [$user, $organization] = $this->createUserAndOrganization();
        $this->activatePlan($organization, 'pro');
        IncidentWeeklyDigestPreference::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'enabled' => true,
            'send_time' => '09:00',
            'timezone' => 'Europe/Moscow',
        ]);
        $this->createIncident($organization, $user, 'client.test', 'http', '2026-05-20 12:00:00', 900);

        $sent = app(SendWeeklyIncidentDigests::class)->handle(CarbonImmutable::now('Europe/Moscow'));
        $sentAgain = app(SendWeeklyIncidentDigests::class)->handle(CarbonImmutable::now('Europe/Moscow'));

        $this->assertSame(1, $sent);
        $this->assertSame(0, $sentAgain);
        Mail::assertQueued(WeeklyIncidentDigestMail::class, 1);
        Mail::assertQueued(WeeklyIncidentDigestMail::class, function (WeeklyIncidentDigestMail $mail) use ($user): bool {
            return $mail->hasTo($user->email) && $mail->incidentCount === 1;
        });

        CarbonImmutable::setTestNow();
    }

    public function test_it_sends_zero_incident_digest_for_paid_user(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 09:00:00', 'Europe/Moscow'));

        [$user, $organization] = $this->createUserAndOrganization();
        $this->activatePlan($organization, 'plus');

        $sent = app(SendWeeklyIncidentDigests::class)->handle(CarbonImmutable::now('Europe/Moscow'));

        $this->assertSame(1, $sent);
        Mail::assertQueued(WeeklyIncidentDigestMail::class, function (WeeklyIncidentDigestMail $mail) use ($user): bool {
            return $mail->hasTo($user->email) && $mail->incidentCount === 0;
        });

        CarbonImmutable::setTestNow();
    }

    public function test_it_does_not_send_digest_for_free_organization(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 09:00:00', 'Europe/Moscow'));

        [$user, $organization] = $this->createUserAndOrganization();
        $this->activatePlan($organization, 'free');

        $sent = app(SendWeeklyIncidentDigests::class)->handle(CarbonImmutable::now('Europe/Moscow'));

        $this->assertSame(0, $sent);
        Mail::assertNothingQueued();

        CarbonImmutable::setTestNow();
    }

    public function test_console_command_sends_weekly_digests(): void
    {
        Mail::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-25 09:00:00', 'Europe/Moscow'));

        [$user, $organization] = $this->createUserAndOrganization();
        $this->activatePlan($organization, 'pro');

        $this
            ->artisan('incidents:send-weekly-digests')
            ->expectsOutput('Sent 1 weekly incident digests.')
            ->assertSuccessful();

        Mail::assertQueued(WeeklyIncidentDigestMail::class, function (WeeklyIncidentDigestMail $mail) use ($user): bool {
            return $mail->hasTo($user->email);
        });

        CarbonImmutable::setTestNow();
    }

    /**
     * @return array{User, Organization}
     */
    private function createUserAndOrganization(): array
    {
        $user = User::factory()->create();

        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-digest-'.$user->id,
            'timezone' => '+3',
            'status' => 'active',
        ]);

        $organization->users()->attach($user->id, [
            'role' => OrganizationRole::Owner->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }

    private function activatePlan(Organization $organization, string $code): void
    {
        $plan = Plan::query()->create([
            'code' => $code,
            'name' => ucfirst($code),
            'description' => $code,
            'price_cents' => 99000,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    private function createIncident(
        Organization $organization,
        User $user,
        string $host,
        string $type,
        string $startedAt,
        int $durationSeconds,
    ): void {
        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $site = MonitoredResource::query()->create([
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

        $startedAt = CarbonImmutable::parse($startedAt, 'Europe/Moscow')->utc();

        Incident::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'monitored_resource_id' => $site->id,
            'monitor_id' => $monitor->id,
            'status' => 'resolved',
            'severity' => 'incident',
            'title' => 'HTTP downtime',
            'started_at' => $startedAt,
            'resolved_at' => $startedAt->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ]);
    }
}
