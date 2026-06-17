<?php

namespace App\Modules\Reports\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Application\Services\MonitorTypeCatalog;
use App\Modules\Reports\Application\Queries\ReportDashboardQuery;
use App\Modules\Reports\Application\Services\ReportPeriodResolver;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReportController extends Controller
{
    public function index(
        Request $request,
        GetCurrentOrganization $getCurrentOrganization,
        ReportPeriodResolver $periodResolver,
        ReportDashboardQuery $reports,
        MonitorTypeCatalog $monitorTypes,
    ): Response {
        $organization = $getCurrentOrganization->handle($request->user());
        $filters = $periodResolver->normalize($organization->id, $request->query());

        return Inertia::render('Reports/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
            ],
            'plan' => $periodResolver->planPayload($organization->id),
            'filters' => [
                'period' => $filters->period,
                'type' => $filters->type,
                'project_id' => $filters->projectId,
                'date_from' => $filters->start->toDateString(),
                'date_to' => $filters->end->toDateString(),
            ],
            'retention' => [
                'days' => $filters->retentionDays,
                'requested_days' => $filters->requestedDays,
                'was_limited_by_plan' => $filters->wasLimitedByPlan,
            ],
            'report' => $reports->build($organization->id, $filters),
            'monitorTypes' => $monitorTypes->payload(),
        ]);
    }
}
