# Incident Analytics and Weekly Digest Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build paid incident analytics inside the existing Incidents page and add automatic weekly incident digest emails for Pro/Plus users.

**Architecture:** Keep the feature in the `Incidents` module. `IncidentController` remains thin and delegates analytics and digest work to focused application/query classes. Heavy analytics aggregates are cached in Redis for 5 minutes with organization-level version invalidation; operational incident lists remain uncached.

**Tech Stack:** Laravel, Inertia, Vue 3, PostgreSQL, Redis cache, Laravel Mail, Laravel Scheduler, Chart.js/vue-chartjs.

---

## File Map

Create:

- `apps/web/app/Modules/Incidents/Application/DTO/IncidentAnalyticsFilters.php` — normalized analytics filters.
- `apps/web/app/Modules/Incidents/Application/DTO/IncidentAnalyticsAccess.php` — plan access metadata for the frontend.
- `apps/web/app/Modules/Incidents/Application/Queries/IncidentAnalyticsQuery.php` — query service for all aggregate analytics.
- `apps/web/app/Modules/Incidents/Application/Services/IncidentAnalyticsCache.php` — Redis-backed aggregate cache and versioning.
- `apps/web/app/Modules/Incidents/Application/Services/IncidentAnalyticsAccessResolver.php` — plan access and retention logic.
- `apps/web/app/Modules/Incidents/Application/Listeners/InvalidateIncidentAnalyticsCache.php` — increments analytics cache version on incident changes.
- `apps/web/app/Modules/Incidents/Application/Services/SendWeeklyIncidentDigests.php` — selects due recipients and queues digest mail.
- `apps/web/app/Modules/Incidents/Application/Mail/WeeklyIncidentDigestMail.php` — weekly digest mailable.
- `apps/web/app/Modules/Incidents/Infrastructure/Persistence/Models/IncidentWeeklyDigestPreference.php` — user preference model.
- `apps/web/app/Modules/Incidents/Infrastructure/Persistence/Models/IncidentWeeklyDigestLog.php` — delivery idempotency log model.
- `apps/web/app/Modules/Incidents/Presentation/Http/Requests/UpdateWeeklyDigestPreferenceRequest.php` — validates digest settings.
- `apps/web/app/Modules/Incidents/Presentation/Http/Controllers/WeeklyDigestPreferenceController.php` — updates current user's preference.
- `apps/web/resources/views/emails/incidents/weekly-digest.blade.php` — digest email template.
- `apps/web/database/migrations/2026_05_28_000100_create_incident_weekly_digest_preferences_table.php`.
- `apps/web/database/migrations/2026_05_28_000110_create_incident_weekly_digest_logs_table.php`.
- `apps/web/database/migrations/2026_05_28_000120_add_incident_analytics_indexes.php`.
- `apps/web/tests/Unit/Incidents/IncidentAnalyticsAccessResolverTest.php`.
- `apps/web/tests/Unit/Incidents/IncidentAnalyticsQueryTest.php`.
- `apps/web/tests/Feature/Incidents/WeeklyIncidentDigestTest.php`.

Modify:

- `apps/web/app/Modules/Incidents/Presentation/Http/Controllers/IncidentController.php` — delegate analytics, add access metadata, retain existing lists.
- `apps/web/app/Modules/Incidents/Infrastructure/Providers/IncidentsModuleServiceProvider.php` — register cache invalidation listeners.
- `apps/web/app/Modules/Incidents/Presentation/Routes/web.php` — add digest settings route.
- `apps/web/resources/js/Pages/Incidents/Index.vue` — paid analytics UI, Free locked block, digest settings control.
- `apps/web/resources/js/Layouts/DashboardLayout.vue` — remove the "Отчеты" sidebar item.
- `apps/web/routes/console.php` — add `incidents:send-weekly-digests` command and schedule every 5 minutes.
- `apps/web/package.json` and `apps/web/package-lock.json` — add `chart.js` and `vue-chartjs`.
- `docs/product/tariffs.md` — note Pro/Plus incident analytics and weekly digest.

---

### Task 1: Add Frontend Chart Dependencies

**Files:**

- Modify: `apps/web/package.json`
- Modify: `apps/web/package-lock.json`

- [ ] **Step 1: Install Chart.js packages**

Run:

```bash
docker compose run --rm vite npm install chart.js vue-chartjs
```

Expected:

Expected: npm updates `apps/web/package.json` and `apps/web/package-lock.json`.

- [ ] **Step 2: Verify package entries**

Check `apps/web/package.json` includes:

```json
"dependencies": {
  "@inertiajs/vite": "^3.0.3",
  "@inertiajs/vue3": "^3.0.3",
  "axios": "^1.16.0",
  "chart.js": "^4",
  "vue": "^3.5.34",
  "vue-chartjs": "^5"
}
```

- [ ] **Step 3: Commit dependency update**

Run:

```bash
git add apps/web/package.json apps/web/package-lock.json
git commit -m "Add chart dependencies for incident analytics"
```

Expected: commit succeeds.

---

### Task 2: Add Analytics Access and Filter Tests

**Files:**

- Create: `apps/web/tests/Unit/Incidents/IncidentAnalyticsAccessResolverTest.php`
- Create: `apps/web/app/Modules/Incidents/Application/DTO/IncidentAnalyticsAccess.php`
- Create: `apps/web/app/Modules/Incidents/Application/DTO/IncidentAnalyticsFilters.php`
- Create: `apps/web/app/Modules/Incidents/Application/Services/IncidentAnalyticsAccessResolver.php`

- [ ] **Step 1: Write failing access resolver tests**

Create `apps/web/tests/Unit/Incidents/IncidentAnalyticsAccessResolverTest.php`:

```php
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
```

- [ ] **Step 2: Run test and verify it fails**

Run:

```bash
make test cmd="--filter=IncidentAnalyticsAccessResolverTest"
```

If `make test` does not pass `cmd`, run:

```bash
docker compose exec web php artisan test --filter=IncidentAnalyticsAccessResolverTest
```

Expected: FAIL because classes do not exist.

- [ ] **Step 3: Implement DTOs and resolver**

Create `IncidentAnalyticsAccess.php`:

```php
<?php

namespace App\Modules\Incidents\Application\DTO;

final readonly class IncidentAnalyticsAccess
{
    public function __construct(
        public bool $enabled,
        public string $planCode,
        public int $retentionDays,
    ) {
    }
}
```

Create `IncidentAnalyticsFilters.php`:

```php
<?php

namespace App\Modules\Incidents\Application\DTO;

use Carbon\CarbonImmutable;

final readonly class IncidentAnalyticsFilters
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $type,
        public ?int $projectId,
        public string $search,
    ) {
    }

    public function previousStart(): CarbonImmutable
    {
        return $this->start->subSeconds($this->periodSeconds());
    }

    public function previousEnd(): CarbonImmutable
    {
        return $this->start->subSecond();
    }

    public function periodSeconds(): int
    {
        return max(1, $this->start->diffInSeconds($this->end) + 1);
    }
}
```

Create `IncidentAnalyticsAccessResolver.php`:

```php
<?php

namespace App\Modules\Incidents\Application\Services;

use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Incidents\Application\DTO\IncidentAnalyticsAccess;
use App\Modules\Incidents\Application\DTO\IncidentAnalyticsFilters;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class IncidentAnalyticsAccessResolver
{
    public function resolve(int $organizationId): IncidentAnalyticsAccess
    {
        $subscription = $this->currentSubscription($organizationId);
        $planCode = (string) ($subscription?->plan?->code ?? 'free');
        $retentionDays = $this->retentionDays($subscription);

        return new IncidentAnalyticsAccess(
            enabled: in_array($planCode, ['pro', 'plus'], true),
            planCode: $planCode,
            retentionDays: $retentionDays,
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public function normalizeFilters(int $organizationId, array $input): IncidentAnalyticsFilters
    {
        $access = $this->resolve($organizationId);
        $type = (string) ($input['type'] ?? 'all');

        if (! in_array($type, ['all', 'http', 'ssl', 'domain'], true)) {
            throw ValidationException::withMessages(['type' => 'Unknown incident type filter.']);
        }

        $projectId = isset($input['project_id']) && $input['project_id'] !== ''
            ? max(1, (int) $input['project_id'])
            : null;

        if (! empty($input['date_from']) || ! empty($input['date_to'])) {
            $start = CarbonImmutable::parse((string) $input['date_from'])->startOfDay();
            $end = CarbonImmutable::parse((string) ($input['date_to'] ?? $input['date_from']))->endOfDay();
        } else {
            $period = (string) ($input['period'] ?? 'max');
            $days = match ($period) {
                '1', '24' => 1,
                '7' => 7,
                default => $access->retentionDays,
            };
            $days = min($days, $access->retentionDays);
            $end = CarbonImmutable::now()->endOfDay();
            $start = $end->subDays($days - 1)->startOfDay();
        }

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['date_to' => 'End date must be after start date.']);
        }

        if ($start->diffInDays($end) + 1 > $access->retentionDays) {
            throw ValidationException::withMessages(['date_from' => 'Date range exceeds current plan retention.']);
        }

        return new IncidentAnalyticsFilters(
            start: $start,
            end: $end,
            type: $type,
            projectId: $projectId,
            search: trim((string) ($input['search'] ?? '')),
        );
    }

    private function currentSubscription(int $organizationId): ?Subscription
    {
        return Subscription::query()
            ->with('plan.limits')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('starts_at')
            ->first();
    }

    private function retentionDays(?Subscription $subscription): int
    {
        $limit = $subscription?->plan?->limits->firstWhere('key', 'history_retention_days');
        $value = $limit?->value;

        if (is_array($value)) {
            return max(1, (int) ($value['days'] ?? 3));
        }

        return 3;
    }
}
```

- [ ] **Step 4: Run access tests**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentAnalyticsAccessResolverTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add apps/web/tests/Unit/Incidents/IncidentAnalyticsAccessResolverTest.php apps/web/app/Modules/Incidents/Application/DTO/IncidentAnalyticsAccess.php apps/web/app/Modules/Incidents/Application/DTO/IncidentAnalyticsFilters.php apps/web/app/Modules/Incidents/Application/Services/IncidentAnalyticsAccessResolver.php
git commit -m "Add incident analytics access resolver"
```

---

### Task 3: Add Analytics Query and Aggregate Tests

**Files:**

- Create: `apps/web/tests/Unit/Incidents/IncidentAnalyticsQueryTest.php`
- Create: `apps/web/app/Modules/Incidents/Application/Queries/IncidentAnalyticsQuery.php`

- [ ] **Step 1: Write aggregate query tests**

Create `IncidentAnalyticsQueryTest.php` with tests that create two projects, three sites, HTTP/SSL/domain incidents, and assert:

```php
$dashboard = app(IncidentAnalyticsQuery::class)->build($organization->id, $filters);

$this->assertSame(3, $dashboard['kpi']['total_incidents']);
$this->assertSame(3600, $dashboard['kpi']['downtime_seconds']);
$this->assertSame(1200, $dashboard['kpi']['mttr_seconds']);
$this->assertCount(2, $dashboard['projects']);
$this->assertSame('Client A', $dashboard['projects'][0]['name']);
$this->assertSame(['http' => 1, 'ssl' => 1, 'domain' => 1], $dashboard['type_distribution']);
```

Use the existing setup style from `IncidentIndexPageTest`.

- [ ] **Step 2: Run test and verify it fails**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentAnalyticsQueryTest
```

Expected: FAIL because `IncidentAnalyticsQuery` does not exist.

- [ ] **Step 3: Implement `IncidentAnalyticsQuery`**

Create query with public method:

```php
/**
 * @return array<string, mixed>
 */
public function build(int $organizationId, IncidentAnalyticsFilters $filters): array
```

Return this payload shape:

```php
[
    'kpi' => [
        'total_incidents' => 0,
        'active_incidents' => 0,
        'downtime_seconds' => 0,
        'mttr_seconds' => 0,
    ],
    'comparison' => [
        'total_incidents_delta' => 0,
        'downtime_seconds_delta' => 0,
        'mttr_seconds_delta' => 0,
    ],
    'series' => [
        'incident_counts' => [['date' => '2026-05-25', 'value' => 0]],
        'downtime_seconds' => [['date' => '2026-05-25', 'value' => 0]],
    ],
    'type_distribution' => [
        'http' => 0,
        'ssl' => 0,
        'domain' => 0,
    ],
    'projects' => [],
    'selected_project_id' => null,
    'selected_project' => null,
    'sites' => [],
    'top_sites' => [],
]
```

Use `DB::table('incidents')` joined to `monitors`, `projects`, and `monitored_resources`. Exclude warnings from analytics with `severity != 'warning'`.

- [ ] **Step 4: Run aggregate tests**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentAnalyticsQueryTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add apps/web/tests/Unit/Incidents/IncidentAnalyticsQueryTest.php apps/web/app/Modules/Incidents/Application/Queries/IncidentAnalyticsQuery.php
git commit -m "Add incident analytics aggregate query"
```

---

### Task 4: Add Redis Cache Wrapper and Invalidation

**Files:**

- Create: `apps/web/app/Modules/Incidents/Application/Services/IncidentAnalyticsCache.php`
- Create: `apps/web/app/Modules/Incidents/Application/Listeners/InvalidateIncidentAnalyticsCache.php`
- Modify: `apps/web/app/Modules/Incidents/Infrastructure/Providers/IncidentsModuleServiceProvider.php`
- Test: `apps/web/tests/Unit/Incidents/IncidentAnalyticsQueryTest.php`

- [ ] **Step 1: Add tests for cached analytics**

Extend `IncidentAnalyticsQueryTest`:

```php
public function test_it_reuses_cached_analytics_until_version_changes(): void
{
    Cache::store('array')->flush();
    config(['cache.default' => 'array']);

    [$organization, $filters] = $this->createAnalyticsFixture();
    $cache = app(IncidentAnalyticsCache::class);

    $first = $cache->remember($organization->id, 'pro', $filters, null, fn () => ['value' => 1]);
    $second = $cache->remember($organization->id, 'pro', $filters, null, fn () => ['value' => 2]);

    $this->assertSame(['value' => 1], $first);
    $this->assertSame(['value' => 1], $second);

    $cache->incrementVersion($organization->id);

    $third = $cache->remember($organization->id, 'pro', $filters, null, fn () => ['value' => 3]);

    $this->assertSame(['value' => 3], $third);
}
```

Add this helper to the same test class:

```php
private function createAnalyticsFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Acme',
        'slug' => 'acme-cache',
        'timezone' => '+3',
        'status' => 'active',
    ]);

    $filters = new IncidentAnalyticsFilters(
        start: \Carbon\CarbonImmutable::now()->subDays(6)->startOfDay(),
        end: \Carbon\CarbonImmutable::now()->endOfDay(),
        type: 'all',
        projectId: null,
        search: '',
    );

    return [$organization, $filters];
}
```

- [ ] **Step 2: Run test and verify it fails**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentAnalyticsQueryTest
```

Expected: FAIL because cache class does not exist.

- [ ] **Step 3: Implement cache service**

Create:

```php
final class IncidentAnalyticsCache
{
    public function remember(int $organizationId, string $planCode, IncidentAnalyticsFilters $filters, ?int $projectId, Closure $callback): array
    {
        try {
            return Cache::store(config('cache.default'))->remember(
                $this->key($organizationId, $planCode, $filters, $projectId),
                now()->addMinutes(5),
                $callback,
            );
        } catch (Throwable) {
            return $callback();
        }
    }

    public function incrementVersion(int $organizationId): void
    {
        try {
            Cache::store(config('cache.default'))->increment($this->versionKey($organizationId));
        } catch (Throwable) {
        }
    }
}
```

Include private `version()` and `key()` methods that hash dates/type/project/version.

- [ ] **Step 4: Add invalidation listener**

Create listener:

```php
final readonly class InvalidateIncidentAnalyticsCache
{
    public function __construct(private IncidentAnalyticsCache $cache) {}

    public function handle(IncidentOpened|IncidentResolved $event): void
    {
        $incident = Incident::query()->find($event->incidentId);

        if ($incident !== null) {
            $this->cache->incrementVersion($incident->organization_id);
        }
    }
}
```

- [ ] **Step 5: Register listeners**

In `IncidentsModuleServiceProvider::boot()` add:

```php
Event::listen(IncidentOpened::class, InvalidateIncidentAnalyticsCache::class);
Event::listen(IncidentResolved::class, InvalidateIncidentAnalyticsCache::class);
```

- [ ] **Step 6: Run tests**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentAnalytics
```

Expected: PASS.

- [ ] **Step 7: Commit**

Run:

```bash
git add apps/web/app/Modules/Incidents/Application/Services/IncidentAnalyticsCache.php apps/web/app/Modules/Incidents/Application/Listeners/InvalidateIncidentAnalyticsCache.php apps/web/app/Modules/Incidents/Infrastructure/Providers/IncidentsModuleServiceProvider.php apps/web/tests/Unit/Incidents/IncidentAnalyticsQueryTest.php
git commit -m "Cache incident analytics aggregates"
```

---

### Task 5: Wire Analytics into IncidentController

**Files:**

- Modify: `apps/web/app/Modules/Incidents/Presentation/Http/Controllers/IncidentController.php`
- Modify: `apps/web/tests/Feature/Incidents/IncidentIndexPageTest.php`

- [ ] **Step 1: Extend feature tests**

Add assertions:

```php
->has('analyticsAccess')
->where('analyticsAccess.enabled', false)
->has('analytics')
```

Add a paid-plan test that creates active Pro subscription and asserts:

```php
->where('analyticsAccess.enabled', true)
->where('analytics.kpi.total_incidents', 2)
->has('analytics.projects', 1)
```

- [ ] **Step 2: Run test and verify failure**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentIndexPageTest
```

Expected: FAIL because payload fields do not exist.

- [ ] **Step 3: Update controller**

Inject:

```php
IncidentAnalyticsAccessResolver $accessResolver,
IncidentAnalyticsQuery $analyticsQuery,
IncidentAnalyticsCache $analyticsCache,
```

Build:

```php
$access = $accessResolver->resolve($organization->id);
$filters = $accessResolver->normalizeFilters($organization->id, $request->query());
$analytics = null;

if ($access->enabled) {
    $analytics = $analyticsCache->remember(
        $organization->id,
        $access->planCode,
        $filters,
        $filters->projectId,
        fn () => $analyticsQuery->build($organization->id, $filters),
    );
}
```

Return:

```php
'analyticsAccess' => [
    'enabled' => $access->enabled,
    'plan_code' => $access->planCode,
    'retention_days' => $access->retentionDays,
],
'analytics' => $analytics,
```

Keep existing `summary`, `activeIncidents`, `resolvedIncidents`, and `warnings`.

- [ ] **Step 4: Run controller tests**

Run:

```bash
docker compose exec web php artisan test --filter=IncidentIndexPageTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add apps/web/app/Modules/Incidents/Presentation/Http/Controllers/IncidentController.php apps/web/tests/Feature/Incidents/IncidentIndexPageTest.php
git commit -m "Expose incident analytics on incidents page"
```

---

### Task 6: Build Incidents Analytics Frontend

**Files:**

- Modify: `apps/web/resources/js/Pages/Incidents/Index.vue`
- Modify: `apps/web/resources/js/Layouts/DashboardLayout.vue`

- [ ] **Step 1: Remove Reports navigation item**

Delete:

```ts
{ key: 'reports', label: 'Отчеты', icon: '▤' },
```

- [ ] **Step 2: Add analytics prop types**

Add types for:

```ts
type AnalyticsAccess = {
    enabled: boolean
    plan_code: string
    retention_days: number
}

type IncidentAnalytics = {
    kpi: { total_incidents: number; active_incidents: number; downtime_seconds: number; mttr_seconds: number }
    comparison: Record<string, number>
    series: { incident_counts: Array<{ date: string; value: number }>; downtime_seconds: Array<{ date: string; value: number }> }
    type_distribution: Record<'http' | 'ssl' | 'domain', number>
    projects: Array<{ id: number; name: string; incident_count: number; downtime_seconds: number; mttr_seconds: number; affected_sites: number }>
    selected_project_id: number | null
    selected_project: { id: number; name: string } | null
    sites: Array<{ id: number; name: string; incident_count: number; downtime_seconds: number; mttr_seconds: number; last_incident_at: string | null }>
    top_sites: Array<{ id: number; name: string; incident_count: number; downtime_seconds: number }>
}
```

- [ ] **Step 3: Register Chart.js components**

At top:

```ts
import { Bar, Doughnut } from 'vue-chartjs'
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
    ArcElement,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Tooltip, Legend)
```

- [ ] **Step 4: Add locked analytics block**

Render when `!analyticsAccess.enabled`:

```vue
<section class="rounded-2xl border border-[#D8E2F0] bg-white p-6">
  <p class="text-sm font-bold text-[#0F6BFF]">Аналитика инцидентов</p>
  <h2 class="mt-2 text-2xl font-extrabold text-[#111827]">Доступна на Pro и Plus</h2>
  <p class="mt-2 max-w-2xl text-sm leading-6 text-[#667085]">
    Смотрите динамику инцидентов, downtime, проблемные проекты и сайты за период.
  </p>
  <Link href="/billing" class="mt-5 inline-flex h-11 items-center rounded-xl bg-[#0F6BFF] px-5 text-sm font-extrabold text-white">
    Перейти на тариф
  </Link>
</section>
```

- [ ] **Step 5: Add paid analytics section**

Render when `analyticsAccess.enabled && analytics`:

- KPI cards;
- project list as left column on desktop;
- project select on mobile;
- three charts;
- site summary table;
- incident journal remains below.

Use existing colors and compact dashboard style; do not nest cards inside cards.

- [ ] **Step 6: Run frontend build**

Run:

```bash
docker compose run --rm vite npm run build
```

Expected: Vite build succeeds.

- [ ] **Step 7: Commit**

Run:

```bash
git add apps/web/resources/js/Pages/Incidents/Index.vue apps/web/resources/js/Layouts/DashboardLayout.vue
git commit -m "Add paid incident analytics UI"
```

---

### Task 7: Add Weekly Digest Tables and Models

**Files:**

- Create migrations and models listed in File Map.
- Test: `apps/web/tests/Feature/Incidents/WeeklyIncidentDigestTest.php`

- [ ] **Step 1: Write failing model/migration test**

Create a test that creates a user, organization, preference, and log:

```php
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
```

- [ ] **Step 2: Run test and verify failure**

Run:

```bash
docker compose exec web php artisan test --filter=WeeklyIncidentDigestTest
```

Expected: FAIL because tables/models do not exist.

- [ ] **Step 3: Create migrations**

Preferences table:

```php
Schema::create('incident_weekly_digest_preferences', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->boolean('enabled')->default(true);
    $table->time('send_time')->default('09:00');
    $table->string('timezone', 64)->default('Europe/Moscow');
    $table->timestamps();
    $table->unique(['user_id', 'organization_id']);
});
```

Logs table:

```php
Schema::create('incident_weekly_digest_logs', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->date('week_start_date');
    $table->date('week_end_date');
    $table->string('status', 32)->default('pending');
    $table->timestamp('sent_at')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'organization_id', 'week_start_date']);
});
```

- [ ] **Step 4: Create models**

Use fillable casts:

```php
protected $fillable = ['user_id', 'organization_id', 'enabled', 'send_time', 'timezone'];
protected function casts(): array { return ['enabled' => 'boolean']; }
```

and:

```php
protected $fillable = ['user_id', 'organization_id', 'week_start_date', 'week_end_date', 'status', 'sent_at', 'error_message'];
protected function casts(): array { return ['week_start_date' => 'date', 'week_end_date' => 'date', 'sent_at' => 'datetime']; }
```

- [ ] **Step 5: Run migrations and test**

Run:

```bash
docker compose exec web php artisan test --filter=WeeklyIncidentDigestTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add apps/web/database/migrations apps/web/app/Modules/Incidents/Infrastructure/Persistence/Models apps/web/tests/Feature/Incidents/WeeklyIncidentDigestTest.php
git commit -m "Add weekly incident digest persistence"
```

---

### Task 8: Add Weekly Digest Settings UI and Route

**Files:**

- Create: `UpdateWeeklyDigestPreferenceRequest.php`
- Create: `WeeklyDigestPreferenceController.php`
- Modify: `apps/web/app/Modules/Incidents/Presentation/Routes/web.php`
- Modify: `apps/web/app/Modules/Incidents/Presentation/Http/Controllers/IncidentController.php`
- Modify: `apps/web/resources/js/Pages/Incidents/Index.vue`
- Test: `WeeklyIncidentDigestTest.php`

- [ ] **Step 1: Add feature test for preference update**

Test:

```php
$this->actingAs($user)
    ->put('/incidents/weekly-digest-preference', [
        'enabled' => false,
        'send_time' => '10:30',
    ])
    ->assertRedirect('/incidents');

$this->assertDatabaseHas('incident_weekly_digest_preferences', [
    'user_id' => $user->id,
    'organization_id' => $organization->id,
    'enabled' => false,
    'send_time' => '10:30:00',
    'timezone' => 'Europe/Moscow',
]);
```

- [ ] **Step 2: Implement request/controller/route**

Route:

```php
Route::put('/incidents/weekly-digest-preference', [WeeklyDigestPreferenceController::class, 'update'])
    ->name('incidents.weekly-digest-preference.update');
```

Request rules:

```php
'enabled' => ['required', 'boolean'],
'send_time' => ['required', 'date_format:H:i'],
```

Controller:

```php
IncidentWeeklyDigestPreference::query()->updateOrCreate(
    ['user_id' => $request->user()->id, 'organization_id' => $organization->id],
    ['enabled' => $request->boolean('enabled'), 'send_time' => $request->input('send_time'), 'timezone' => 'Europe/Moscow'],
);
```

- [ ] **Step 3: Add preference payload to incidents page**

Return:

```php
'weeklyDigestPreference' => [
    'enabled' => $preference?->enabled ?? true,
    'send_time' => substr((string) ($preference?->send_time ?? '09:00'), 0, 5),
    'timezone' => 'Europe/Moscow',
],
```

- [ ] **Step 4: Add UI control**

On paid analytics section, add checkbox/toggle and time input posting via `router.put('/incidents/weekly-digest-preference', ...)`.

- [ ] **Step 5: Run tests and build**

Run:

```bash
docker compose exec web php artisan test --filter=WeeklyIncidentDigestTest
docker compose run --rm vite npm run build
```

Expected: PASS and build succeeds.

- [ ] **Step 6: Commit**

Run:

```bash
git add apps/web/app/Modules/Incidents/Presentation apps/web/resources/js/Pages/Incidents/Index.vue apps/web/tests/Feature/Incidents/WeeklyIncidentDigestTest.php
git commit -m "Add weekly digest user settings"
```

---

### Task 9: Add Weekly Digest Mail and Sender Service

**Files:**

- Create: `WeeklyIncidentDigestMail.php`
- Create: `SendWeeklyIncidentDigests.php`
- Create: `emails/incidents/weekly-digest.blade.php`
- Modify: `WeeklyIncidentDigestTest.php`

- [ ] **Step 1: Add mail tests**

Use `Mail::fake()` and `Carbon::setTestNow('2026-05-25 09:00:00 Europe/Moscow')`.

Assert:

```php
Mail::assertQueued(WeeklyIncidentDigestMail::class, function (WeeklyIncidentDigestMail $mail) use ($user) {
    return $mail->hasTo($user->email) && $mail->incidentCount === 1;
});
```

Add duplicate-run assertion:

```php
$service->handle(now('Europe/Moscow'));
$service->handle(now('Europe/Moscow'));
Mail::assertQueued(WeeklyIncidentDigestMail::class, 1);
```

- [ ] **Step 2: Implement mailable**

Constructor:

```php
public function __construct(
    public readonly string $organizationName,
    public readonly CarbonImmutable $weekStart,
    public readonly CarbonImmutable $weekEnd,
    public readonly int $incidentCount,
    public readonly array $incidents,
) {}
```

Build:

```php
return $this
    ->subject("Montry: отчет по инцидентам за неделю")
    ->view('emails.incidents.weekly-digest');
```

- [ ] **Step 3: Implement sender service**

`handle(?CarbonImmutable $now = null): int`:

- use Moscow time;
- return 0 if not Monday;
- select active non-blocked users with active Pro/Plus subscriptions;
- ensure preference enabled, default true if missing;
- ensure `send_time <= now H:i`;
- create log with unique key before queueing;
- query incidents for previous Monday-Sunday;
- `Mail::to($user->email)->queue(new WeeklyIncidentDigestMail($organizationName, $weekStart, $weekEnd, $incidentCount, $incidentRows))`;
- mark log sent.

- [ ] **Step 4: Create email template**

Include:

```blade
<h1>Отчет по инцидентам за неделю</h1>
<p>{{ $organizationName }}</p>
<p>Период: {{ $weekStart->format('d.m.Y') }} - {{ $weekEnd->format('d.m.Y') }}</p>
<p>Всего инцидентов: <strong>{{ $incidentCount }}</strong></p>

@if ($incidentCount === 0)
    <p>За прошлую неделю инцидентов не было.</p>
@else
    <table>
        <thead><tr><th>Сайт</th><th>Тип</th><th>Начался</th><th>Длительность</th></tr></thead>
        <tbody>
        @foreach ($incidents as $incident)
            <tr>
                <td>{{ $incident['site'] }}</td>
                <td>{{ $incident['type'] }}</td>
                <td>{{ $incident['started_at'] }}</td>
                <td>{{ $incident['duration'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<p><a href="{{ url('/incidents') }}">Открыть инциденты</a></p>
```

- [ ] **Step 5: Run tests**

Run:

```bash
docker compose exec web php artisan test --filter=WeeklyIncidentDigestTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add apps/web/app/Modules/Incidents/Application apps/web/resources/views/emails/incidents apps/web/tests/Feature/Incidents/WeeklyIncidentDigestTest.php
git commit -m "Send weekly incident digest emails"
```

---

### Task 10: Add Console Command, Schedule, and Indexes

**Files:**

- Modify: `apps/web/routes/console.php`
- Create: `apps/web/database/migrations/2026_05_28_000120_add_incident_analytics_indexes.php`
- Test: `WeeklyIncidentDigestTest.php`

- [ ] **Step 1: Add command test**

Test:

```php
$this->artisan('incidents:send-weekly-digests')
    ->expectsOutputToContain('Sent')
    ->assertSuccessful();
```

- [ ] **Step 2: Add console command**

In `routes/console.php`:

```php
use App\Modules\Incidents\Application\Services\SendWeeklyIncidentDigests;

Artisan::command('incidents:send-weekly-digests', function (SendWeeklyIncidentDigests $digests): int {
    $sent = $digests->handle();
    $this->info("Sent {$sent} weekly incident digests.");
    return self::SUCCESS;
})->purpose('Send weekly incident digest emails to paid users.');

Schedule::command('incidents:send-weekly-digests')->everyFiveMinutes();
```

- [ ] **Step 3: Add indexes migration**

Add:

```php
Schema::table('incidents', function (Blueprint $table): void {
    $table->index(['organization_id', 'started_at'], 'incidents_org_started_idx');
    $table->index(['organization_id', 'project_id', 'started_at'], 'incidents_org_project_started_idx');
    $table->index(['organization_id', 'monitored_resource_id', 'started_at'], 'incidents_org_resource_started_idx');
});
```

Down drops these indexes.

- [ ] **Step 4: Run tests**

Run:

```bash
docker compose exec web php artisan test --filter=WeeklyIncidentDigestTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add apps/web/routes/console.php apps/web/database/migrations apps/web/tests/Feature/Incidents/WeeklyIncidentDigestTest.php
git commit -m "Schedule weekly incident digests"
```

---

### Task 11: Update Tariff Documentation

**Files:**

- Modify: `docs/product/tariffs.md`

- [ ] **Step 1: Update paid tariff descriptions**

Add to Pro:

```markdown
- аналитика инцидентов за 14 дней;
- еженедельный email-отчет по инцидентам.
```

Add to Plus:

```markdown
- аналитика инцидентов за 60 дней;
- еженедельный email-отчет по инцидентам.
```

Add to Free:

```markdown
- аналитика инцидентов и weekly reports недоступны.
```

- [ ] **Step 2: Commit docs**

Run:

```bash
git add docs/product/tariffs.md
git commit -m "Document incident analytics tariff access"
```

---

### Task 12: Final Verification

**Files:**

- All changed files.

- [ ] **Step 1: Run Laravel tests**

Run:

```bash
docker compose exec web php artisan test
```

Expected: all tests pass.

- [ ] **Step 2: Run frontend build**

Run:

```bash
docker compose run --rm vite npm run build
```

Expected: build succeeds.

- [ ] **Step 3: Run migrations locally**

Run:

```bash
make migrate
```

Expected: migrations run successfully.

- [ ] **Step 4: Manual smoke test**

Run:

```bash
make up
```

Open `/incidents` and verify:

- Free organization sees locked analytics block and incident list.
- Pro/Plus organization sees analytics controls and charts.
- Digest setting can be updated.
- `php artisan incidents:send-weekly-digests` sends one digest per eligible user/week.

- [ ] **Step 5: Final status**

Run:

```bash
git status --short
```

Expected: only intentional untracked local notes remain, currently `Заметки.txt`.
