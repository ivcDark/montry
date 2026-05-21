<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\StartRegistrationVerification;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Billing\Application\Services\PlanIntentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RegisterController extends Controller
{
    public function create(Request $request, PlanIntentService $planIntent): Response
    {
        $planIntent->captureFromRequest($request);

        return Inertia::render('Auth/Register', [
            'intendedPlanCode' => $planIntent->get($request),
        ]);
    }

    public function store(
        RegisterRequest $request,
        StartRegistrationVerification $startRegistrationVerification,
    ): RedirectResponse {
        $user = $startRegistrationVerification->handle($request->toData());

        $request->session()->put('pending_registration_user_id', $user->id);

        return redirect()->route('register.verify-code');
    }
}
