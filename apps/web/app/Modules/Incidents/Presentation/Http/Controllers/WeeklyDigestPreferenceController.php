<?php

namespace App\Modules\Incidents\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Infrastructure\Persistence\Models\IncidentWeeklyDigestPreference;
use App\Modules\Incidents\Presentation\Http\Requests\UpdateWeeklyDigestPreferenceRequest;
use App\Modules\Sites\Actions\GetCurrentOrganization;
use Illuminate\Http\RedirectResponse;

final class WeeklyDigestPreferenceController extends Controller
{
    public function update(
        UpdateWeeklyDigestPreferenceRequest $request,
        GetCurrentOrganization $getCurrentOrganization,
    ): RedirectResponse {
        $organization = $getCurrentOrganization->handle($request->user());

        IncidentWeeklyDigestPreference::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'organization_id' => $organization->id,
            ],
            [
                'enabled' => $request->boolean('enabled'),
                'send_time' => $request->string('send_time')->toString(),
                'timezone' => 'Europe/Moscow',
            ],
        );

        return redirect()->route('incidents.index');
    }
}
