<?php

namespace Tests\Feature\Incidents;

use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestLog;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestPreference;
use App\Modules\Organizations\Enums\OrganizationRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
