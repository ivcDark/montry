<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\CompleteRegistration;
use App\Modules\Auth\Actions\RegisterUser;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Billing\Application\Services\PlanIntentService;
use App\Modules\Billing\Application\Services\StartIntendedCheckout;
use App\Modules\Observability\Application\DTO\RecordBusinessEventData;
use App\Modules\Observability\Application\Services\BusinessEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function create(
        Request $request,
        PlanIntentService $planIntent,
        BusinessEventRecorder $events,
    ): Response
    {
        $planIntent->captureFromRequest($request);
        $intendedPlanCode = $planIntent->get($request);

        $events->record(new RecordBusinessEventData(
            eventType: 'registration.form_opened',
            payload: [
                'intended_plan_code' => $intendedPlanCode,
                'referrer' => $request->headers->get('referer'),
            ],
            planCode: $intendedPlanCode,
            source: 'web',
        ));

        return Inertia::render('Auth/Register', [
            'intendedPlanCode' => $intendedPlanCode,
            'googleAuthEnabled' => (bool) config('services.google.enabled', false),
        ]);
    }

    public function store(
        RegisterRequest $request,
        RegisterUser $registerUser,
        CompleteRegistration $completeRegistration,
        StartIntendedCheckout $startIntendedCheckout,
        BusinessEventRecorder $events,
    ): RedirectResponse {
        $events->record(new RecordBusinessEventData(
            eventType: 'registration.email_submitted',
            payload: [
                'email_domain' => str($request->string('email')->toString())->after('@')->lower()->toString(),
            ],
            source: 'web',
        ));

        $user = $registerUser->handle($request->toData());

        $completeRegistration->handle($user);
        $request->session()->forget('pending_registration_user_id');
        $request->session()->regenerate();

        return $startIntendedCheckout->redirect($request, $user);
    }
}
