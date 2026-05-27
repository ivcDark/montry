<?php

namespace App\Modules\Admin\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Identity\Infrastructure\Persistence\Models\Organization;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdminUserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->withCount('organizations')
            ->orderBy('email')
            ->limit(100)
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'is_blocked' => $user->is_blocked,
                'organizations_count' => $user->organizations_count,
                'created_at' => $user->created_at?->toISOString(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->load([
            'organizations' => fn ($query) => $query
                ->withCount(['projects'])
                ->orderBy('organizations.name'),
        ]);

        $organizationIds = $user->organizations->pluck('id');

        $siteCounts = MonitoredResource::query()
            ->whereIn('organization_id', $organizationIds)
            ->selectRaw('organization_id, count(*) as aggregate')
            ->groupBy('organization_id')
            ->pluck('aggregate', 'organization_id');

        $monitorCounts = Monitor::query()
            ->whereIn('organization_id', $organizationIds)
            ->selectRaw('organization_id, count(*) as aggregate')
            ->groupBy('organization_id')
            ->pluck('aggregate', 'organization_id');

        $subscriptions = Subscription::query()
            ->with('plan:id,code,name,price_cents,currency')
            ->whereIn('organization_id', $organizationIds)
            ->where('status', 'active')
            ->latest('starts_at')
            ->get()
            ->keyBy('organization_id');

        $sites = MonitoredResource::query()
            ->with([
                'organization:id,name',
                'project:id,name',
            ])
            ->withCount('monitors')
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('host')
            ->limit(100)
            ->get();

        $monitors = Monitor::query()
            ->with([
                'organization:id,name',
                'project:id,name',
                'monitoredResource:id,name,target,host',
            ])
            ->whereIn('organization_id', $organizationIds)
            ->orderByRaw('case when status in (?, ?) then 0 when status in (?, ?) then 1 else 2 end', [
                'failure',
                'down',
                'degraded',
                'warning',
            ])
            ->orderByDesc('last_check_at')
            ->limit(100)
            ->get();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_cents')
            ->get(['id', 'code', 'name', 'price_cents', 'currency']);

        return Inertia::render('Admin/Users/Show', [
            'adminUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'is_blocked' => $user->is_blocked,
                'can_block' => ! $request->user()?->is($user),
                'created_at' => $user->created_at?->toISOString(),
            ],
            'organizations' => $user->organizations
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'status' => $organization->status,
                    'role' => $organization->pivot->role,
                    'member_status' => $organization->pivot->status,
                    'projects_count' => $organization->projects_count,
                    'sites_count' => (int) ($siteCounts[$organization->id] ?? 0),
                    'monitors_count' => (int) ($monitorCounts[$organization->id] ?? 0),
                    'subscription' => $subscriptions->get($organization->id)
                        ? [
                            'id' => $subscriptions->get($organization->id)->id,
                            'status' => $subscriptions->get($organization->id)->status,
                            'plan_id' => $subscriptions->get($organization->id)->plan_id,
                            'plan' => [
                                'id' => $subscriptions->get($organization->id)->plan?->id,
                                'code' => $subscriptions->get($organization->id)->plan?->code,
                                'name' => $subscriptions->get($organization->id)->plan?->name,
                            ],
                        ]
                        : null,
                ])
                ->values(),
            'sites' => $sites
                ->map(fn (MonitoredResource $site): array => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'target' => $site->target,
                    'host' => $site->host,
                    'status' => $site->status,
                    'monitors_count' => $site->monitors_count,
                    'organization' => [
                        'id' => $site->organization?->id,
                        'name' => $site->organization?->name,
                    ],
                    'project' => $site->project
                        ? [
                            'id' => $site->project->id,
                            'name' => $site->project->name,
                        ]
                        : null,
                ])
                ->values(),
            'monitors' => $monitors
                ->map(fn (Monitor $monitor): array => [
                    'id' => $monitor->id,
                    'name' => $monitor->name,
                    'type' => $monitor->type,
                    'status' => $monitor->status,
                    'is_enabled' => $monitor->is_enabled,
                    'last_check_at' => $monitor->last_check_at?->toISOString(),
                    'next_check_at' => $monitor->next_check_at?->toISOString(),
                    'organization' => [
                        'id' => $monitor->organization?->id,
                        'name' => $monitor->organization?->name,
                    ],
                    'site' => [
                        'id' => $monitor->monitoredResource?->id,
                        'name' => $monitor->monitoredResource?->name,
                        'target' => $monitor->monitoredResource?->target,
                        'host' => $monitor->monitoredResource?->host,
                    ],
                    'project' => $monitor->project
                        ? [
                            'id' => $monitor->project->id,
                            'name' => $monitor->project->name,
                        ]
                        : null,
                ])
                ->values(),
            'plans' => $plans,
        ]);
    }

    public function toggleBlock(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        abort_if($request->user()?->is($user), 422, 'You cannot block your own admin account.');

        $wasBlocked = (bool) $user->is_blocked;

        $user->forceFill([
            'is_blocked' => ! $user->is_blocked,
        ])->save();

        $audit->record(
            category: 'admin',
            action: $user->is_blocked ? 'admin.user.blocked' : 'admin.user.unblocked',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            targetType: 'user',
            targetId: (string) $user->id,
            source: 'admin',
            metadata: [
                'was_blocked' => $wasBlocked,
                'is_blocked' => (bool) $user->is_blocked,
            ],
        );

        return back()->with('success', $user->is_blocked ? 'User blocked.' : 'User unblocked.');
    }

    public function updatePlan(Request $request, User $user, Organization $organization, AuditLogger $audit): RedirectResponse
    {
        abort_unless($user->organizations()->whereKey($organization->id)->exists(), 404);

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
        ]);

        $previousSubscription = Subscription::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->latest('starts_at')
            ->first(['id', 'plan_id']);

        $newSubscriptionId = null;

        DB::transaction(function () use ($organization, $validated, &$newSubscriptionId): void {
            Subscription::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'ended',
                    'ends_at' => now(),
                ]);

            $subscription = Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $validated['plan_id'],
                'status' => 'active',
                'starts_at' => now(),
            ]);

            $newSubscriptionId = $subscription->id;
        });

        $audit->record(
            category: 'admin',
            action: 'admin.organization.plan_changed',
            outcome: 'success',
            request: $request,
            actorUserId: $request->user()?->id,
            organizationId: $organization->id,
            targetType: 'organization',
            targetId: (string) $organization->id,
            source: 'admin',
            metadata: [
                'user_id' => $user->id,
                'previous_subscription_id' => $previousSubscription?->id,
                'previous_plan_id' => $previousSubscription?->plan_id,
                'new_subscription_id' => $newSubscriptionId,
                'new_plan_id' => (int) $validated['plan_id'],
            ],
        );

        return back()->with('success', 'Organization plan changed.');
    }
}
