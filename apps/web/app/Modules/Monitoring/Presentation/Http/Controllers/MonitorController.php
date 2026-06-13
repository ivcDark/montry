<?php

namespace App\Modules\Monitoring\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MonitoredResources\Infrastructure\Persistence\Models\MonitoredResource;
use App\Modules\Monitoring\Application\Commands\CreateMonitorCommand;
use App\Modules\Monitoring\Application\Commands\PauseMonitorCommand;
use App\Modules\Monitoring\Application\Commands\ResumeMonitorCommand;
use App\Modules\Monitoring\Application\Commands\UpdateMonitorCommand;
use App\Modules\Monitoring\Application\Handlers\CreateMonitorHandler;
use App\Modules\Monitoring\Application\Handlers\PauseMonitorHandler;
use App\Modules\Monitoring\Application\Handlers\ResumeMonitorHandler;
use App\Modules\Monitoring\Application\Handlers\UpdateMonitorHandler;
use App\Modules\Monitoring\Application\Services\CheckTypeRegistry;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\Monitor;
use App\Modules\Monitoring\Presentation\Http\Requests\SaveMonitorRequest;
use App\Modules\Sites\Actions\DeleteMonitorAction;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MonitorController extends Controller
{
    public function __construct(
        private readonly GetCurrentOrganization $getCurrentOrganization,
    ) {}

    public function create(Request $request, MonitoredResource $site, CheckTypeRegistry $checkTypes): Response
    {
        $organization = $this->getCurrentOrganization->handle($request->user());
        abort_unless($site->organization_id === $organization->id, 404);

        return Inertia::render('Monitors/Create', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'site' => $this->sitePayload($site),
            'monitorTypes' => $this->monitorTypes($checkTypes),
        ]);
    }

    public function store(
        SaveMonitorRequest $request,
        MonitoredResource $site,
        CreateMonitorHandler $createMonitor,
    ): RedirectResponse {
        $organization = $this->getCurrentOrganization->handle($request->user());
        abort_unless($site->organization_id === $organization->id, 404);

        $validated = $request->validated();

        try {
            $createMonitor->handle(new CreateMonitorCommand(
                organizationId: $organization->id,
                projectId: $site->project_id,
                monitoredResourceId: $site->id,
                type: $validated['type'],
                name: $validated['name'],
                enabled: $validated['is_enabled'],
                intervalSeconds: $validated['interval_seconds'],
                timeoutMs: $validated['timeout_ms'],
                settings: $validated['settings'],
                expected: $validated['expected'] ?? [],
            ));
        } catch (AuthorizationException $exception) {
            if (! $this->isBillingLimitException($exception)) {
                throw $exception;
            }

            return redirect()
                ->route('sites.show', $site)
                ->with('error', $this->limitErrorMessage($exception));
        }

        $message = ($validated['feedback_action'] ?? null) === 'toggle'
            ? ($validated['is_enabled'] ? 'Тип мониторинга включён.' : 'Тип мониторинга отключён.')
            : 'Настройки типа мониторинга сохранены.';

        return redirect()
            ->route('sites.show', $site)
            ->with('success', $message);
    }

    public function edit(
        Request $request,
        MonitoredResource $site,
        Monitor $siteMonitor,
        CheckTypeRegistry $checkTypes,
    ): Response {
        $organization = $this->getCurrentOrganization->handle($request->user());
        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->monitored_resource_id === $site->id, 404);

        return Inertia::render('Sites/Monitors/Edit', [
            'organization' => ['id' => $organization->id, 'name' => $organization->name],
            'site' => $this->sitePayload($site),
            'monitor' => [
                'id' => $siteMonitor->id,
                'type' => $siteMonitor->type,
                'name' => $siteMonitor->name,
                'is_enabled' => $siteMonitor->is_enabled,
                'interval_seconds' => $siteMonitor->interval_seconds,
                'timeout_ms' => $siteMonitor->timeout_ms,
                'settings' => $siteMonitor->settings ?? [],
                'expected' => $siteMonitor->expected ?? [],
            ],
            'monitorTypes' => $this->monitorTypes($checkTypes),
        ]);
    }

    public function update(
        SaveMonitorRequest $request,
        MonitoredResource $site,
        Monitor $siteMonitor,
        UpdateMonitorHandler $updateMonitor,
    ): RedirectResponse {
        $organization = $this->getCurrentOrganization->handle($request->user());
        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->monitored_resource_id === $site->id, 404);
        abort_unless($request->validated('type') === $siteMonitor->type, 422);

        $validated = $request->validated();

        try {
            $updateMonitor->handle(new UpdateMonitorCommand(
                monitorId: $siteMonitor->id,
                name: $validated['name'],
                enabled: $validated['is_enabled'],
                intervalSeconds: $validated['interval_seconds'],
                timeoutMs: $validated['timeout_ms'],
                settings: $validated['settings'],
                expected: $validated['expected'] ?? [],
            ));
        } catch (AuthorizationException $exception) {
            if (! $this->isBillingLimitException($exception)) {
                throw $exception;
            }

            return redirect()
                ->route('sites.show', $site)
                ->with('error', $this->limitErrorMessage($exception));
        }

        return to_route('sites.show', $site)
            ->with('success', 'Настройки типа мониторинга сохранены.');
    }

    public function toggle(
        Request $request,
        MonitoredResource $site,
        Monitor $siteMonitor,
        PauseMonitorHandler $pauseMonitor,
        ResumeMonitorHandler $resumeMonitor,
    ): RedirectResponse {
        $organization = $this->getCurrentOrganization->handle($request->user());
        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->monitored_resource_id === $site->id, 404);

        $wasEnabled = $siteMonitor->is_enabled;

        try {
            if ($wasEnabled) {
                $pauseMonitor->handle(new PauseMonitorCommand($siteMonitor->id));
            } else {
                $resumeMonitor->handle(new ResumeMonitorCommand($siteMonitor->id));
            }
        } catch (AuthorizationException $exception) {
            if (! $this->isBillingLimitException($exception)) {
                throw $exception;
            }

            return redirect()
                ->route('sites.show', $site)
                ->with('error', $this->limitErrorMessage($exception));
        }

        return to_route('sites.show', $site)
            ->with('success', $wasEnabled
                ? 'Тип мониторинга отключён.'
                : 'Тип мониторинга включён.');
    }

    public function destroy(
        Request $request,
        MonitoredResource $site,
        Monitor $siteMonitor,
        DeleteMonitorAction $deleteMonitor,
    ): RedirectResponse {
        $organization = $this->getCurrentOrganization->handle($request->user());
        abort_unless($site->organization_id === $organization->id, 404);
        abort_unless($siteMonitor->monitored_resource_id === $site->id, 404);

        $deleteMonitor->handle($siteMonitor);

        return to_route('sites.show', $site);
    }

    private function sitePayload(MonitoredResource $site): array
    {
        return [
            'id' => $site->id,
            'name' => $site->name,
            'url' => $site->url,
            'scheme' => $site->scheme,
            'host' => $site->host,
            'port' => $site->port,
            'path' => $site->path,
        ];
    }

    private function limitErrorMessage(AuthorizationException $exception): string
    {
        return match ($exception->getMessage()) {
            'Monitor limit reached for the current plan.' => 'Лимит по мониторингам больше не используется. Проверьте выбранные платные проверки.',
            'Paid check is not purchased for the current subscription.' => 'Эта проверка платная. Подключите её в тарифе или уберите из формы.',
            'Paid check limit reached for the current subscription.' => 'Лимит по этой платной проверке исчерпан. Докупите ещё одну проверку или отключите лишнюю.',
            'Check interval is below the current plan limit.' => 'Интервал проверки меньше, чем разрешено на текущем тарифе.',
            'Monitor type is not available for the current plan.' => 'Этот тип проверки недоступен на текущем тарифе.',
            default => $exception->getMessage(),
        };
    }

    private function isBillingLimitException(AuthorizationException $exception): bool
    {
        return in_array($exception->getMessage(), [
            'Monitor limit reached for the current plan.',
            'Paid check is not purchased for the current subscription.',
            'Paid check limit reached for the current subscription.',
            'Check interval is below the current plan limit.',
            'Monitor type is not available for the current plan.',
        ], true);
    }

    private function monitorTypes(CheckTypeRegistry $checkTypes): array
    {
        return collect($checkTypes->all())
            ->map(fn ($definition) => [
                'value' => $definition->type(),
                'label' => $definition->label(),
            ])
            ->values()
            ->all();
    }
}
