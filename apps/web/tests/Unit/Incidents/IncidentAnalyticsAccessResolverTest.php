<?php

namespace Tests\Unit\Incidents;

use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Incidents\Application\Services\IncidentAnalyticsAccessResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IncidentAnalyticsAccessResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_has_no_paid_analytics_access(): void
    {
        $organization = $this->organizationWithPlan('free', 3);

        $access = app(IncidentAnalyticsAccessResolver::class)->resolve($organization->id);

        $this->assertFalse($access->enabled);
        $this->assertSame('free', $access->planCode);
        $this->assertSame(3, $access->retentionDays);
    }

    public function test_paid_plan_can_use_retention_limited_custom_range(): void
    {
        $organization = $this->organizationWithPlan('pro', 14);

        $filters = app(IncidentAnalyticsAccessResolver::class)->normalizeFilters(
            organizationId: $organization->id,
            input: [
                'date_from' => now()->subDays(13)->toDateString(),
                'date_to' => now()->toDateString(),
                'type' => 'http',
            ],
        );

        $this->assertSame('http', $filters->type);
        $this->assertTrue($filters->start->lessThanOrEqualTo($filters->end));
    }

    public function test_paid_plan_rejects_range_longer_than_retention(): void
    {
        $organization = $this->organizationWithPlan('pro', 14);

        $this->expectException(ValidationException::class);

        app(IncidentAnalyticsAccessResolver::class)->normalizeFilters(
            organizationId: $organization->id,
            input: [
                'date_from' => now()->subDays(20)->toDateString(),
                'date_to' => now()->toDateString(),
            ],
        );
    }

    private function organizationWithPlan(string $code, int $retentionDays): Organization
    {
        $organization = Organization::query()->create([
            'name' => 'Acme',
            'slug' => 'acme-'.$code,
            'timezone' => '+3',
            'status' => 'active',
        ]);

        $plan = Plan::query()->create([
            'code' => $code,
            'name' => ucfirst($code),
            'description' => $code,
            'price_cents' => $code === 'free' ? 0 : 99000,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $plan->limits()->create([
            'key' => 'history_retention_days',
            'value' => ['days' => $retentionDays],
        ]);

        Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        return $organization;
    }
}
