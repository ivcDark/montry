<?php

namespace App\Modules\Admin\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Observability\Application\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdminPlanController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()
            ->with('limits')
            ->withCount([
                'subscriptions',
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get()
            ->map(fn (Plan $plan): array => $this->planPayload($plan))
            ->values();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validatedPlanData($request);

        $plan = DB::transaction(function () use ($validated): Plan {
            $plan = Plan::query()->create($this->planAttributes($validated));
            $this->syncLimits($plan, $validated);

            return $plan;
        });

        $audit->record(
            category: 'admin',
            action: 'admin.plan.created',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'plan',
            targetId: (string) $plan->id,
            source: 'admin',
            metadata: [
                'plan_code' => $plan->code,
                'price_cents' => $plan->price_cents,
            ],
        );

        return back()->with('success', "Тариф «{$plan->name}» создан.");
    }

    public function update(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validatedPlanData($request, $plan);
        $previous = $plan->only(['code', 'name', 'price_cents', 'currency', 'is_active', 'sort_order']);

        DB::transaction(function () use ($plan, $validated): void {
            $plan->update($this->planAttributes($validated));
            $this->syncLimits($plan, $validated);
        });

        $plan->refresh();

        $audit->record(
            category: 'admin',
            action: 'admin.plan.updated',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'plan',
            targetId: (string) $plan->id,
            source: 'admin',
            metadata: [
                'previous' => $previous,
                'current' => $plan->only(['code', 'name', 'price_cents', 'currency', 'is_active', 'sort_order']),
            ],
        );

        return back()->with('success', "Тариф «{$plan->name}» обновлён.");
    }

    public function destroy(Request $request, Plan $plan, AuditLogger $audit): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', "Нельзя удалить тариф «{$plan->name}»: он используется в подписках.");
        }

        $deletedPlan = $plan->only(['id', 'code', 'name', 'price_cents', 'currency']);
        $plan->delete();

        $audit->record(
            category: 'admin',
            action: 'admin.plan.deleted',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'plan',
            targetId: (string) $deletedPlan['id'],
            source: 'admin',
            metadata: $deletedPlan,
        );

        return back()->with('success', "Тариф «{$deletedPlan['name']}» удалён.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPlanData(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('plans', 'code')->ignore($plan?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_rubles' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_sites' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_monitors' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'allowed_monitor_types' => ['nullable', 'string', 'max:255'],
            'history_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'minimum_check_interval_seconds' => ['nullable', 'integer', 'min:30', 'max:86400'],
            'notification_channels' => ['nullable', 'string', 'max:255'],
            'can_create_projects' => ['required', 'boolean'],
            'manual_checks_per_day' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function planAttributes(array $validated): array
    {
        return [
            'code' => strtolower((string) $validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_cents' => (int) round(((float) $validated['price_rubles']) * 100),
            'currency' => strtoupper((string) $validated['currency']),
            'is_active' => (bool) $validated['is_active'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function syncLimits(Plan $plan, array $validated): void
    {
        $limits = [
            'max_sites' => ['limit' => $this->nullableInteger($validated['max_sites'] ?? null)],
            'max_monitors' => ['limit' => $this->nullableInteger($validated['max_monitors'] ?? null)],
            'allowed_monitor_types' => ['types' => $this->csvList((string) ($validated['allowed_monitor_types'] ?? ''), app(MonitorTypeCatalog::class)->allCodes())],
            'history_retention_days' => ['days' => $this->nullableInteger($validated['history_retention_days'] ?? null) ?? 0],
            'minimum_check_interval_seconds' => ['seconds' => $this->nullableInteger($validated['minimum_check_interval_seconds'] ?? null) ?? 300],
            'notification_channels' => ['channels' => $this->csvList((string) ($validated['notification_channels'] ?? ''), ['email'])],
            'can_create_projects' => ['enabled' => (bool) $validated['can_create_projects']],
            'manual_checks_per_day' => ['limit' => $this->nullableInteger($validated['manual_checks_per_day'] ?? null)],
        ];

        foreach ($limits as $key => $value) {
            $plan->limits()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(Plan $plan): array
    {
        $limits = $plan->limits
            ->mapWithKeys(fn ($limit): array => [$limit->key => $limit->value])
            ->all();

        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'price_rubles' => $plan->price_cents / 100,
            'currency' => $plan->currency,
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
            'subscriptions_count' => $plan->subscriptions_count,
            'active_subscriptions_count' => $plan->active_subscriptions_count,
            'limits' => $limits,
            'form' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_rubles' => $plan->price_cents / 100,
                'currency' => $plan->currency,
                'is_active' => $plan->is_active,
                'sort_order' => $plan->sort_order,
                'max_sites' => $limits['max_sites']['limit'] ?? null,
                'max_monitors' => $limits['max_monitors']['limit'] ?? null,
                'allowed_monitor_types' => implode(', ', $limits['allowed_monitor_types']['types'] ?? []),
                'history_retention_days' => $limits['history_retention_days']['days'] ?? null,
                'minimum_check_interval_seconds' => $limits['minimum_check_interval_seconds']['seconds'] ?? null,
                'notification_channels' => implode(', ', $limits['notification_channels']['channels'] ?? []),
                'can_create_projects' => (bool) ($limits['can_create_projects']['enabled'] ?? false),
                'manual_checks_per_day' => $limits['manual_checks_per_day']['limit'] ?? null,
            ],
        ];
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param list<string> $fallback
     * @return list<string>
     */
    private function csvList(string $value, array $fallback): array
    {
        $items = collect(explode(',', $value))
            ->map(fn (string $item): string => trim(strtolower($item)))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();

        return $items === [] ? $fallback : $items;
    }
}
