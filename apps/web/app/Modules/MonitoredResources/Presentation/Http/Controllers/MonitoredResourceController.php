<?php

namespace App\Modules\MonitoredResources\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\Services\BillingAddonCatalog;
use App\Modules\Billing\Application\Services\LimitChecker;
use App\Modules\Billing\Infrastructure\Persistence\Models\Plan;
use App\Modules\Billing\Infrastructure\Persistence\Models\Subscription;
use App\Modules\Incidents\Infrastructure\Persistence\Models\Incident;
use App\Modules\MonitoredResources\Application\Handlers\ListMonitoredResourcesHandler;
use App\Modules\MonitoredResources\Application\Queries\ListMonitoredResourcesQuery;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\MonitoredResources\Presentation\Http\Requests\StoreMonitoredResourceRequest;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\CheckResult;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use App\Modules\Sites\Actions\CreateDefaultFolderForOrganization;
use App\Modules\Sites\Actions\CreateSiteAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class MonitoredResourceController extends Controller
{
    public function index(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
        ListMonitoredResourcesHandler $listMonitoredResources,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());

        return Inertia::render('Sites/Index', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'sites' => $listMonitoredResources->handle(new ListMonitoredResourcesQuery($organization->id)),
        ]);
    }

    public function create(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
        CheckTypeRegistry $checkTypes,
        BillingAddonCatalog $addonCatalog,
        LimitChecker $limits,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());

        $currentSubscription = $this->currentSubscription((int) $organization->id);
        $currentPlan = $currentSubscription?->plan ?? Plan::query()
            ->with('limits')
            ->where('code', 'free')
            ->first();

        return Inertia::render('Sites/Create', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'monitorTypes' => collect($checkTypes->all())
                ->map(fn ($definition) => [
                    'value' => $definition->type(),
                    'label' => $definition->label(),
                ])
                ->values()
                ->all(),
            'currentPlan' => $currentPlan ? $this->planPayload($currentPlan) : null,
            'addonCatalog' => $addonCatalog->payload(),
            'entitlements' => $limits->usageSummary((int) $organization->id),
            'usage' => [
                'sites' => MonitoredResource::query()
                    ->where('organization_id', $organization->id)
                    ->where('type', 'website')
                    ->count(),
                'monitors' => Monitor::query()
                    ->where('organization_id', $organization->id)
                    ->count(),
                'active_monitors' => Monitor::query()
                    ->where('organization_id', $organization->id)
                    ->where('enabled', true)
                    ->count(),
                'site_limit' => $limits->effectiveSiteLimit((int) $organization->id),
                'monitor_limit' => null,
                'minimum_check_interval_seconds' => $limits->minimumCheckIntervalSeconds((int) $organization->id),
                'allowed_monitor_types' => $limits->allowedMonitorTypes((int) $organization->id),
            ],
        ]);
    }

    public function store(
        StoreMonitoredResourceRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
        CreateDefaultFolderForOrganization $createDefaultProject,
        CreateSiteAction $createMonitoredResource,
        LimitChecker $limits,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());
        $project = $createDefaultProject->handle($organization);

        $siteData = $request->toData(
            organizationId: $organization->id,
            project: $project,
        );

        try {
            $createMonitoredResource->handle(
                $siteData,
                $request->monitorPayloads([
                    'url' => $siteData->url,
                    'host' => $siteData->host,
                    'port' => $siteData->port,
                ], null, $limits->minimumCheckIntervalSeconds($organization->id)),
            );
        } catch (AuthorizationException $exception) {
            if (! $this->isCreateLimitException($exception)) {
                throw $exception;
            }

            return redirect()
                ->route('sites.create')
                ->with('error', $this->limitErrorMessage($exception));
        }

        return redirect()
            ->route('sites.index')
            ->with('success', 'Site added.');
    }

    public function show(
        Request $request,
        MonitoredResource $site,
        GetCurrentOrganization $getCurrentOrganization,
        LimitChecker $limits,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());
        $site->load([
            'project:id,name',
            'monitors.latestCheckResult' => fn ($query) => $query->select([
                'check_results.id',
                'check_results.monitor_id',
                'check_results.status',
                'check_results.checked_at',
                'check_results.response_time_ms',
                'check_results.status_code',
                'check_results.error_code',
                'check_results.error_message',
                'check_results.normalized_result',
            ]),
        ]);

        if ($site->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('Sites/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'scheme' => $site->scheme,
                'host' => $site->host,
                'port' => $site->port,
                'path' => $site->path,
                'status' => $this->resourceStatus($site),
                'raw_status' => $site->status,
                'problem_label' => $this->problemLabel($site),
                'created_at' => $site->created_at?->toISOString(),
                'updated_at' => $site->updated_at?->toISOString(),
                'project' => $site->project
                    ? [
                        'id' => $site->project->id,
                        'name' => $site->project->name,
                    ]
                    : null,
                'monitors' => $site->monitors
                    ->sortBy(fn (Monitor $monitor): string => sprintf(
                        '%d-%d-%s',
                        (! $monitor->is_enabled || $monitor->status === 'paused') ? 1 : 0,
                        $this->monitorTypeOrder($monitor->type),
                        $monitor->name,
                    ))
                    ->values()
                    ->map(fn (Monitor $monitor) => [
                        'id' => $monitor->id,
                        'name' => $monitor->name,
                        'type' => $monitor->type,
                        'status' => $monitor->status,
                        'is_enabled' => $monitor->is_enabled,
                        'is_available' => $limits->isMonitorTypeAvailable((int) $organization->id, $monitor->type),
                        'interval_seconds' => $monitor->interval_seconds,
                        'timeout_ms' => $monitor->timeout_ms,
                        'settings' => $monitor->settings,
                        'expected' => $monitor->expected,
                        'last_check_at' => $monitor->last_check_at?->toISOString(),
                        'next_check_at' => $monitor->next_check_at?->toISOString(),
                        'check_in_progress_until' => $monitor->check_in_progress_until?->toISOString(),
                        'is_checking' => $monitor->check_in_progress_until?->isFuture() ?? false,
                        'last_success_at' => $monitor->last_success_at?->toISOString(),
                        'last_failure_at' => $monitor->last_failure_at?->toISOString(),
                        'latest_result' => $monitor->latestCheckResult
                            ? [
                                'status' => $monitor->latestCheckResult->status,
                                'checked_at' => $monitor->latestCheckResult->checked_at?->toISOString(),
                                'response_time_ms' => $monitor->latestCheckResult->response_time_ms,
                                'status_code' => $monitor->latestCheckResult->status_code,
                                'error_code' => $monitor->latestCheckResult->error_code,
                                'error_message' => $monitor->latestCheckResult->error_message,
                                'normalized_result' => $monitor->latestCheckResult->normalized_result ?? [],
                            ]
                            : null,
                    ]),
                'recent_checks' => $this->recentChecks($site),
                'incidents' => $this->incidents($site),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        MonitoredResource $site,
        GetCurrentOrganization $getCurrentOrganization,
        BusinessEventRecorder $events,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());

        if ($site->organization_id !== $organization->id) {
            throw new NotFoundHttpException;
        }

        DB::transaction(function () use ($site, $request, $events): void {
            $events->record(new RecordBusinessEventData(
                eventType: 'site.deleted',
                organizationId: $site->organization_id,
                userId: $request->user()?->id,
                subjectType: 'monitored_resource',
                subjectId: (string) $site->id,
                status: 'deleted',
                source: 'web',
                payload: [
                    'project_id' => $site->project_id,
                    'type' => $site->type,
                    'host' => $site->host,
                    'monitors_count' => $site->monitors()->count(),
                ],
            ));

            $site->monitors()->delete();
            $site->delete();
        });

        return to_route('sites.index')
            ->with('success', 'Site deleted.');
    }

    private function resourceStatus(MonitoredResource $resource): string
    {
        $monitors = $resource->monitors;
        $enabledMonitors = $monitors->filter(fn (Monitor $monitor): bool => $monitor->is_enabled);

        if ($enabledMonitors->contains(fn (Monitor $monitor): bool => $this->isDown($monitor))) {
            return 'down';
        }

        if ($enabledMonitors->contains(fn (Monitor $monitor): bool => $this->isWarning($monitor))) {
            return 'warning';
        }

        if ($monitors->isNotEmpty() && $enabledMonitors->isEmpty()) {
            return 'paused';
        }

        if ($enabledMonitors->isNotEmpty()) {
            return 'ok';
        }

        return 'empty';
    }


    private function currentSubscription(int $organizationId): ?Subscription
    {
        return Subscription::query()
            ->with('plan.limits')
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();
    }

    private function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'sort_order' => $plan->sort_order,
            'limits' => $plan->limits
                ->mapWithKeys(fn ($limit): array => [$limit->key => $limit->value])
                ->all(),
        ];
    }

    private function planLimit(?Plan $plan, string $key): ?int
    {
        $value = $plan?->limits->firstWhere('key', $key)?->value;

        if (! is_array($value) || ! array_key_exists('limit', $value)) {
            return null;
        }

        $limit = $value['limit'];

        return is_numeric($limit) ? (int) $limit : null;
    }

    private function limitErrorMessage(AuthorizationException $exception): string
    {
        return match ($exception->getMessage()) {
            'Site limit reached for the current plan.' => 'Лимит по сайтам исчерпан. Повысьте тариф для добавления сайта.',
            'Monitor limit reached for the current plan.' => 'Лимит по мониторингам больше не используется. Проверьте выбранные платные проверки.',
            'Paid check is not purchased for the current subscription.' => 'Эта проверка платная. Подключите её в тарифе или уберите из формы.',
            'Paid check limit reached for the current subscription.' => 'Лимит по этой платной проверке исчерпан. Докупите ещё одну проверку или отключите лишнюю.',
            'Check interval is below the current plan limit.' => 'Интервал проверки меньше, чем разрешено на текущем тарифе.',
            'Monitor type is not available for the current plan.' => 'Этот тип проверки недоступен на текущем тарифе.',
            default => $exception->getMessage(),
        };
    }

    private function isCreateLimitException(AuthorizationException $exception): bool
    {
        return in_array($exception->getMessage(), [
            'Site limit reached for the current plan.',
            'Monitor limit reached for the current plan.',
            'Paid check is not purchased for the current subscription.',
            'Paid check limit reached for the current subscription.',
            'Check interval is below the current plan limit.',
            'Monitor type is not available for the current plan.',
        ], true);
    }

    private function problemLabel(MonitoredResource $resource): string
    {
        $enabledMonitors = $resource->monitors->filter(fn (Monitor $monitor): bool => $monitor->is_enabled);
        $downCount = $enabledMonitors->filter(fn (Monitor $monitor): bool => $this->isDown($monitor))->count();
        $warningCount = $enabledMonitors->filter(fn (Monitor $monitor): bool => $this->isWarning($monitor))->count();

        if ($downCount > 0) {
            return $this->plural($downCount, 'монитор упал', 'монитора упали', 'мониторов упали');
        }

        if ($warningCount > 0) {
            return $this->plural($warningCount, 'warning', 'warning', 'warning');
        }

        if ($resource->monitors->isNotEmpty() && $enabledMonitors->isEmpty()) {
            return 'Мониторинг на паузе';
        }

        if ($resource->monitors->isEmpty()) {
            return 'Нет мониторингов';
        }

        return 'Нет';
    }

    private function isDown(Monitor $monitor): bool
    {
        return in_array($monitor->status, ['failure', 'down'], true);
    }

    private function isWarning(Monitor $monitor): bool
    {
        return in_array($monitor->status, ['degraded', 'warning'], true)
            || $monitor->latestCheckResult?->status === 'warning';
    }

    private function recentChecks(MonitoredResource $site): array
    {
        return CheckResult::query()
            ->where('organization_id', $site->organization_id)
            ->whereIn('monitor_id', $site->monitors->pluck('id'))
            ->latest('checked_at')
            ->limit(10)
            ->get()
            ->map(fn (CheckResult $result): array => [
                'id' => $result->id,
                'monitor_id' => $result->monitor_id,
                'check_type' => $result->check_type,
                'status' => $result->status,
                'checked_at' => $result->checked_at?->toISOString(),
                'response_time_ms' => $result->response_time_ms,
                'status_code' => $result->status_code,
                'error_code' => $result->error_code,
                'error_message' => $result->error_message,
                'normalized_result' => $result->normalized_result ?? [],
            ])
            ->all();
    }

    private function incidents(MonitoredResource $site): array
    {
        return Incident::query()
            ->where('organization_id', $site->organization_id)
            ->where('monitored_resource_id', $site->id)
            ->latest('started_at')
            ->limit(10)
            ->get()
            ->map(fn (Incident $incident): array => [
                'id' => $incident->id,
                'monitor_id' => $incident->monitor_id,
                'status' => $incident->status,
                'severity' => $incident->severity,
                'title' => $incident->title,
                'summary' => $incident->summary,
                'started_at' => $incident->started_at?->toISOString(),
                'resolved_at' => $incident->resolved_at?->toISOString(),
                'duration_seconds' => $incident->duration_seconds,
            ])
            ->all();
    }

    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        $word = $many;

        if ($mod10 === 1 && $mod100 !== 11) {
            $word = $one;
        } elseif ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            $word = $few;
        }

        return "{$count} {$word}";
    }

    private function monitorTypeOrder(string $type): int
    {
        return match ($type) {
            'http' => 0,
            'ssl' => 1,
            'domain' => 2,
            'dns' => 3,
            'robots_txt' => 4,
            'sitemap_xml' => 5,
            'api_endpoint' => 6,
            'tcp_port' => 7,
            default => 99,
        };
    }
}
